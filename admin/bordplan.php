<?php
/**
 * Admin - Seating Plan / Bordplan
 */

// Handle AJAX requests BEFORE including header (which outputs HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/auth.php';

    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $db = getDB();
    $eventId = getCurrentEventId();

    // Ensure tables exist
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS seating_tables (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                table_type ENUM('round', 'rectangle', 'square', 'ushape') DEFAULT 'round',
                capacity INT DEFAULT 8,
                position_x INT DEFAULT 0,
                position_y INT DEFAULT 0,
                sort_order INT DEFAULT 0,
                is_high_table BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
            )
        ");
        // Add is_high_table column if it doesn't exist
        try {
            $db->exec("ALTER TABLE seating_tables ADD COLUMN is_high_table BOOLEAN DEFAULT FALSE AFTER sort_order");
        } catch (Exception $e) {
            error_log('Failed to add is_high_table column to seating_tables: ' . $e->getMessage());
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS seating_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                table_id INT NOT NULL,
                guest_name VARCHAR(255) NOT NULL,
                seat_number INT DEFAULT NULL,
                guest_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                FOREIGN KEY (table_id) REFERENCES seating_tables(id) ON DELETE CASCADE,
                FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL
            )
        ");
    } catch (Exception $e) {
        error_log('Failed to create seating tables (seating_tables/seating_assignments): ' . $e->getMessage());
    }

    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'add_table') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['table_type'] ?? 'round';
        $capacity = max(2, min(20, (int)($_POST['capacity'] ?? 8)));

        if ($name) {
            $stmt = $db->prepare("SELECT MAX(sort_order) FROM seating_tables WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $maxOrder = $stmt->fetchColumn() ?? 0;

            $stmt = $db->prepare("
                INSERT INTO seating_tables (event_id, name, table_type, capacity, sort_order)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $name, $type, $capacity, $maxOrder + 1]);

            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Navn påkrævet']);
        exit;
    }

    if ($action === 'delete_table') {
        $tableId = (int)($_POST['table_id'] ?? 0);
        if ($tableId) {
            $stmt = $db->prepare("DELETE FROM seating_assignments WHERE table_id = ? AND event_id = ?");
            $stmt->execute([$tableId, $eventId]);

            $stmt = $db->prepare("DELETE FROM seating_tables WHERE id = ? AND event_id = ?");
            $stmt->execute([$tableId, $eventId]);

            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'assign_guest') {
        $tableId = (int)($_POST['table_id'] ?? 0);
        $seatNumber = (int)($_POST['seat_number'] ?? 0);
        $guestName = trim($_POST['guest_name'] ?? '');
        $guestId = !empty($_POST['guest_id']) ? (int)$_POST['guest_id'] : null;

        if ($tableId && $guestName && $seatNumber > 0) {
            // Check if seat is already taken
            $stmt = $db->prepare("SELECT id FROM seating_assignments WHERE table_id = ? AND seat_number = ?");
            $stmt->execute([$tableId, $seatNumber]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Pladsen er optaget']);
                exit;
            }

            $stmt = $db->prepare("
                INSERT INTO seating_assignments (event_id, table_id, guest_name, seat_number, guest_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eventId, $tableId, $guestName, $seatNumber, $guestId]);

            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Manglende data']);
        exit;
    }

    if ($action === 'remove_guest') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        if ($assignmentId) {
            $stmt = $db->prepare("DELETE FROM seating_assignments WHERE id = ? AND event_id = ?");
            $stmt->execute([$assignmentId, $eventId]);

            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'move_guest') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $newTableId = (int)($_POST['new_table_id'] ?? 0);
        $newSeatNumber = (int)($_POST['seat_number'] ?? 0);

        if ($assignmentId && $newTableId && $newSeatNumber > 0) {
            // Check if seat is already taken (by someone else)
            $stmt = $db->prepare("SELECT id FROM seating_assignments WHERE table_id = ? AND seat_number = ? AND id != ?");
            $stmt->execute([$newTableId, $newSeatNumber, $assignmentId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Pladsen er optaget']);
                exit;
            }

            $stmt = $db->prepare("UPDATE seating_assignments SET table_id = ?, seat_number = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$newTableId, $newSeatNumber, $assignmentId, $eventId]);

            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'swap_seats') {
        $assignmentId1 = (int)($_POST['assignment_id_1'] ?? 0);
        $assignmentId2 = (int)($_POST['assignment_id_2'] ?? 0);

        if ($assignmentId1 && $assignmentId2) {
            // Get both assignments
            $stmt = $db->prepare("SELECT id, table_id, seat_number FROM seating_assignments WHERE id IN (?, ?) AND event_id = ?");
            $stmt->execute([$assignmentId1, $assignmentId2, $eventId]);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($assignments) === 2) {
                $a1 = $assignments[0];
                $a2 = $assignments[1];

                // Swap seats
                $stmt = $db->prepare("UPDATE seating_assignments SET table_id = ?, seat_number = ? WHERE id = ?");
                $stmt->execute([$a2['table_id'], $a2['seat_number'], $a1['id']]);
                $stmt->execute([$a1['table_id'], $a1['seat_number'], $a2['id']]);

                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['success' => false]);
        exit;
    }

    if ($action === 'update_table') {
        $tableId = (int)($_POST['table_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $capacity = max(2, min(20, (int)($_POST['capacity'] ?? 8)));
        $isHighTable = isset($_POST['is_high_table']) ? (int)$_POST['is_high_table'] : null;

        if ($tableId && $name) {
            $stmt = $db->prepare("UPDATE seating_tables SET name = ?, capacity = ? WHERE id = ? AND event_id = ?");
            $stmt->execute([$name, $capacity, $tableId, $eventId]);

            // Update high table status if provided
            if ($isHighTable !== null) {
                // First clear any existing high table
                if ($isHighTable) {
                    $db->prepare("UPDATE seating_tables SET is_high_table = 0 WHERE event_id = ?")->execute([$eventId]);
                }
                $db->prepare("UPDATE seating_tables SET is_high_table = ? WHERE id = ? AND event_id = ?")->execute([$isHighTable, $tableId, $eventId]);
            }

            echo json_encode(['success' => true]);
            exit;
        }
    }

    // Set high table
    if ($action === 'set_high_table') {
        $tableId = (int)($_POST['table_id'] ?? 0);
        // Clear all high tables first
        $db->prepare("UPDATE seating_tables SET is_high_table = 0 WHERE event_id = ?")->execute([$eventId]);
        if ($tableId) {
            $db->prepare("UPDATE seating_tables SET is_high_table = 1 WHERE id = ? AND event_id = ?")->execute([$tableId, $eventId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Clear all seating assignments
    if ($action === 'clear_all_seats') {
        $stmt = $db->prepare("DELETE FROM seating_assignments WHERE event_id = ?");
        $stmt->execute([$eventId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Delete all tables
    if ($action === 'delete_all_tables') {
        $db->prepare("DELETE FROM seating_assignments WHERE event_id = ?")->execute([$eventId]);
        $db->prepare("DELETE FROM seating_tables WHERE event_id = ?")->execute([$eventId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Create tables automatically
    if ($action === 'create_tables_auto') {
        $tableType = $_POST['table_type'] ?? 'round';
        $capacity = max(4, min(16, (int)($_POST['capacity'] ?? 8)));
        $includeHighTable = isset($_POST['include_high_table']) && $_POST['include_high_table'] === '1';
        $highTableCapacity = max(4, min(20, (int)($_POST['high_table_capacity'] ?? 10)));

        // Count confirmed guests
        $stmt = $db->prepare("
            SELECT SUM(COALESCE(adults_count, 1) + COALESCE(children_count, 0)) as total
            FROM guests
            WHERE event_id = ? AND rsvp_status = 'yes'
        ");
        $stmt->execute([$eventId]);
        $totalGuests = (int)$stmt->fetchColumn();

        if ($totalGuests === 0) {
            echo json_encode(['success' => false, 'error' => 'Ingen bekræftede gæster at placere']);
            exit;
        }

        // Delete existing tables and assignments
        $db->prepare("DELETE FROM seating_assignments WHERE event_id = ?")->execute([$eventId]);
        $db->prepare("DELETE FROM seating_tables WHERE event_id = ?")->execute([$eventId]);

        $tablesCreated = 0;
        $remainingGuests = $totalGuests;

        // Create high table first if requested
        if ($includeHighTable) {
            $stmt = $db->prepare("
                INSERT INTO seating_tables (event_id, name, table_type, capacity, sort_order, is_high_table)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$eventId, 'Højbord', $tableType, $highTableCapacity, 1]);
            $tablesCreated++;
            $remainingGuests -= $highTableCapacity;
        }

        // Calculate number of regular tables needed
        $tablesNeeded = max(0, ceil($remainingGuests / $capacity));

        // Create regular tables
        for ($i = 1; $i <= $tablesNeeded; $i++) {
            $tableName = 'Bord ' . ($includeHighTable ? $i : $i);
            $stmt = $db->prepare("
                INSERT INTO seating_tables (event_id, name, table_type, capacity, sort_order, is_high_table)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$eventId, $tableName, $tableType, $capacity, $tablesCreated + 1]);
            $tablesCreated++;
        }

        echo json_encode([
            'success' => true,
            'tables_created' => $tablesCreated,
            'total_guests' => $totalGuests,
            'total_capacity' => $includeHighTable
                ? $highTableCapacity + ($tablesNeeded * $capacity)
                : $tablesNeeded * $capacity
        ]);
        exit;
    }

    // Auto-seat algorithm
    if ($action === 'auto_seat') {
        $options = json_decode($_POST['options'] ?? '{}', true);
        $highTableId = (int)($options['high_table_id'] ?? 0);
        $vipGuests = $options['vip_guests'] ?? []; // Array of guest names for high table
        $keepCouples = $options['keep_couples'] ?? true;
        $alternateGender = $options['alternate_gender'] ?? false;
        $groupByRelation = $options['group_by_relation'] ?? true;

        // Danish name to gender mapping
        $maleNames = ['adam','adrian','albert','alexander','alf','alfred','allan','anders','andreas','anton','arne','asger','axel','benjamin','bent','birger','bjarne','bjørn','bo','boris','brian','carl','carsten','casper','christian','christoffer','claus','daniel','david','dennis','egon','eigil','einar','ejner','elias','emil','erik','erling','ernst','esben','finn','flemming','frank','frederik','frode','georg','gert','gorm','gunnar','gustav','hans','harald','harry','heinrich','helge','henning','henrik','herman','holger','hugo','ib','ivan','jack','jacob','jakob','jan','janne','jasper','jean','jens','jesper','jimmy','joachim','johan','johannes','john','johnny','jonas','jonathan','jørgen','jørn','karl','kasper','kenneth','kent','kim','kjeld','klaus','knud','kristian','kristoffer','kurt','lars','lasse','lauge','laurits','leif','lennart','leo','lucas','ludvig','mads','magnus','malthe','marcus','mario','mark','markus','martin','mathias','matti','max','michael','mikael','mikkel','mogens','morten','nicklas','nicolai','niels','nikolaj','nils','noah','ole','oliver','oskar','ove','palle','patrick','paul','per','peter','poul','preben','ralf','rasmus','rene','richard','robert','rolf','ronni','rune','samuel','sebastian','sigurd','simon','steen','stefan','steffen','sten','stig','sune','svend','søren','thomas','thorbjørn','thorkild','tim','tobias','tom','tommy','torben','troels','uffe','ulrik','vagn','valdemar','victor','viggo','viktor','villads','william','willy'];

        $femaleNames = ['agnes','alberte','alexandra','alice','alma','amanda','amalie','andrea','ane','anemone','anette','angelica','anika','anita','anja','anna','anne','annemarie','annette','asta','astrid','augusta','beate','beatrice','bente','berit','bettina','birgit','birgitte','birte','birthe','bodil','bolette','britt','britta','camilla','carina','carla','caroline','cathrine','cecilie','charlotte','christina','christine','clara','connie','dagmar','dagny','diana','ditte','dora','doris','dorte','dorthe','ea','edith','elena','elin','elina','elinor','elisabeth','ella','ellen','elna','elsa','else','elsebeth','elvira','emma','emilie','erik','erna','esther','eva','fie','filippa','frederikke','frida','gerda','gitte','grethe','gudrun','gunda','gurli','hanne','harriet','heidi','helen','helena','helene','helle','henriette','herdis','ida','ina','inga','inge','ingeborg','ingelise','inger','ingrid','irene','iris','irma','isabella','jane','janni','jasmin','jeanette','jenny','jensine','jessica','jette','johanne','josefine','julie','jytte','karen','karin','karina','karla','karlene','karoline','kate','kathrine','katja','katrine','kirsteen','kirsten','kirstine','klara','laura','laila','lea','lena','lene','leonora','line','lisbet','lisbeth','lise','liselotte','lissi','liv','lone','lotte','louise','lucia','luna','lydia','maja','majbritt','malene','maren','margit','margrethe','maria','marianne','marie','marlene','martha','mathilde','mette','mia','michelle','mille','minna','nadia','nanna','natalie','natasja','nicoline','nicole','nille','nina','nora','olivia','patricia','pernille','petra','pia','rikke','rita','rosa','rose','ruth','sabine','sally','sandra','sanne','sara','sarah','sidsel','signe','sigrid','silke','simone','sine','sofia','sofie','solveig','sonja','sophia','stina','stine','susanne','tanja','thea','tina','tove','trine','ulla','ursula','vera','vibeke','victoria','viola','vivian'];

        // Function to guess gender from first name
        $guessGender = function($fullName) use ($maleNames, $femaleNames) {
            // Extract first name (handle "Hans Peter" -> "Hans")
            $parts = preg_split('/[\s\-]+/', trim($fullName));
            $firstName = strtolower($parts[0] ?? '');

            // Remove Danish special chars for matching
            $firstName = str_replace(['æ','ø','å'], ['ae','oe','aa'], $firstName);

            if (in_array($firstName, $maleNames)) return 'male';
            if (in_array($firstName, $femaleNames)) return 'female';

            // Try without last letter (handles "Marias" -> "Maria")
            $shortened = substr($firstName, 0, -1);
            if (strlen($shortened) > 2) {
                if (in_array($shortened, $maleNames)) return 'male';
                if (in_array($shortened, $femaleNames)) return 'female';
            }

            return null; // Unknown
        };

        // Get all tables
        $stmt = $db->prepare("SELECT * FROM seating_tables WHERE event_id = ? ORDER BY is_high_table DESC, sort_order");
        $stmt->execute([$eventId]);
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tables)) {
            echo json_encode(['success' => false, 'error' => 'Ingen borde oprettet']);
            exit;
        }

        // Clear existing assignments
        $db->prepare("DELETE FROM seating_assignments WHERE event_id = ?")->execute([$eventId]);

        // Get all confirmed guests with their individual names
        $stmt = $db->prepare("
            SELECT g.id, g.name, g.guest_names, g.adults_count, g.children_count,
                   g.relation_type, g.is_vip, g.couple_group
            FROM guests g
            WHERE g.event_id = ? AND g.rsvp_status = 'yes'
            ORDER BY g.is_vip DESC, g.relation_type, g.name
        ");
        $stmt->execute([$eventId]);
        $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build list of individual people
        $allPeople = [];
        foreach ($guests as $guest) {
            $names = [];
            if (!empty($guest['guest_names'])) {
                $decoded = json_decode($guest['guest_names'], true);
                if ($decoded) {
                    foreach ($decoded as $item) {
                        $personName = is_array($item) ? ($item['name'] ?? '') : $item;
                        $personGender = is_array($item) ? ($item['gender'] ?? null) : null;

                        // Guess gender if not set
                        if (!$personGender) {
                            $personGender = $guessGender($personName);
                        }

                        $names[] = [
                            'name' => $personName,
                            'gender' => $personGender,
                            'guest_id' => $guest['id'],
                            'relation' => $guest['relation_type'] ?? 'other',
                            'is_vip' => $guest['is_vip'] || in_array($personName, $vipGuests),
                            'couple_group' => $guest['couple_group'],
                            'invitation_id' => $guest['id'] // For keeping couples together
                        ];
                    }
                }
            }

            // Fallback if no guest_names
            if (empty($names)) {
                $fallbackGender = $guessGender($guest['name']);
                $names[] = [
                    'name' => $guest['name'],
                    'gender' => $fallbackGender,
                    'guest_id' => $guest['id'],
                    'relation' => $guest['relation_type'] ?? 'other',
                    'is_vip' => $guest['is_vip'] || in_array($guest['name'], $vipGuests),
                    'couple_group' => $guest['couple_group'],
                    'invitation_id' => $guest['id']
                ];
            }

            $allPeople = array_merge($allPeople, $names);
        }

        // Separate VIP and non-VIP
        $vipPeople = array_filter($allPeople, fn($p) => $p['is_vip']);
        $regularPeople = array_filter($allPeople, fn($p) => !$p['is_vip']);

        // Group regular people by relation if enabled
        if ($groupByRelation) {
            $grouped = [];
            foreach ($regularPeople as $person) {
                $rel = $person['relation'] ?? 'other';
                $grouped[$rel][] = $person;
            }
            // Flatten back, keeping groups together
            $regularPeople = [];
            foreach ($grouped as $group) {
                $regularPeople = array_merge($regularPeople, $group);
            }
        }

        // Prepare seating
        $assignments = [];
        $tableSeats = []; // Track available seats per table

        foreach ($tables as $table) {
            $tableSeats[$table['id']] = [
                'capacity' => $table['capacity'],
                'filled' => 0,
                'is_high' => $table['is_high_table'] || $table['id'] == $highTableId
            ];
        }

        // Track seats per table with gender info for alternating
        $tableAssignments = []; // table_id => [assignments with gender]
        foreach ($tables as $table) {
            $tableAssignments[$table['id']] = [];
        }

        // Function to find best seat considering gender alternation
        $findBestSeat = function($person, $preferHighTable = false) use (&$tableSeats, &$tableAssignments, $tables, $alternateGender) {
            $candidates = [];

            foreach ($tables as $table) {
                $tid = $table['id'];
                $isHigh = $tableSeats[$tid]['is_high'];

                // Skip based on high table preference
                if ($preferHighTable && !$isHigh) continue;
                if (!$preferHighTable && $isHigh) continue;

                if ($tableSeats[$tid]['filled'] < $tableSeats[$tid]['capacity']) {
                    $score = 0;
                    $seatNum = $tableSeats[$tid]['filled'] + 1;

                    // If alternating gender, prefer tables where we'd create alternation
                    if ($alternateGender && $person['gender']) {
                        $lastAssignment = end($tableAssignments[$tid]);
                        if ($lastAssignment && $lastAssignment['gender']) {
                            // Give bonus if genders are different (good alternation)
                            if ($lastAssignment['gender'] !== $person['gender']) {
                                $score += 10;
                            }
                        }
                    }

                    $candidates[] = [
                        'table_id' => $tid,
                        'seat_number' => $seatNum,
                        'score' => $score
                    ];
                }
            }

            // If no candidates in preferred tables, try all tables
            if (empty($candidates)) {
                foreach ($tables as $table) {
                    $tid = $table['id'];
                    if ($tableSeats[$tid]['filled'] < $tableSeats[$tid]['capacity']) {
                        $candidates[] = [
                            'table_id' => $tid,
                            'seat_number' => $tableSeats[$tid]['filled'] + 1,
                            'score' => 0
                        ];
                    }
                }
            }

            if (empty($candidates)) return null;

            // Sort by score descending
            usort($candidates, fn($a, $b) => $b['score'] - $a['score']);
            $best = $candidates[0];

            // Update tracking
            $tableSeats[$best['table_id']]['filled']++;
            $tableAssignments[$best['table_id']][] = [
                'gender' => $person['gender'],
                'name' => $person['name']
            ];

            return $best;
        };

        // If keeping couples together, group people by invitation
        if ($keepCouples) {
            // Group VIPs by invitation
            $vipByInvitation = [];
            foreach ($vipPeople as $person) {
                $invId = $person['invitation_id'] ?? $person['guest_id'];
                $vipByInvitation[$invId][] = $person;
            }

            // Seat VIP groups together
            foreach ($vipByInvitation as $group) {
                // Sort group by gender for better alternation
                if ($alternateGender) {
                    usort($group, fn($a, $b) => ($a['gender'] ?? 'z') <=> ($b['gender'] ?? 'z'));
                }
                foreach ($group as $person) {
                    $seat = $findBestSeat($person, true);
                    if ($seat) {
                        $assignments[] = [
                            'table_id' => $seat['table_id'],
                            'seat_number' => $seat['seat_number'],
                            'guest_name' => $person['name'],
                            'guest_id' => $person['guest_id']
                        ];
                    }
                }
            }

            // Group regular people by invitation
            $regularByInvitation = [];
            foreach ($regularPeople as $person) {
                $invId = $person['invitation_id'] ?? $person['guest_id'];
                $regularByInvitation[$invId][] = $person;
            }

            // Seat regular groups together
            foreach ($regularByInvitation as $group) {
                if ($alternateGender) {
                    usort($group, fn($a, $b) => ($a['gender'] ?? 'z') <=> ($b['gender'] ?? 'z'));
                }
                foreach ($group as $person) {
                    $seat = $findBestSeat($person, false);
                    if ($seat) {
                        $assignments[] = [
                            'table_id' => $seat['table_id'],
                            'seat_number' => $seat['seat_number'],
                            'guest_name' => $person['name'],
                            'guest_id' => $person['guest_id']
                        ];
                    }
                }
            }
        } else {
            // Not keeping couples - can optimize gender alternation better
            if ($alternateGender) {
                // Separate by gender
                $males = array_filter($vipPeople, fn($p) => $p['gender'] === 'male');
                $females = array_filter($vipPeople, fn($p) => $p['gender'] === 'female');
                $unknown = array_filter($vipPeople, fn($p) => !$p['gender']);

                // Interleave genders for VIPs
                $vipPeople = [];
                while (!empty($males) || !empty($females)) {
                    if (!empty($males)) $vipPeople[] = array_shift($males);
                    if (!empty($females)) $vipPeople[] = array_shift($females);
                }
                $vipPeople = array_merge($vipPeople, $unknown);

                // Same for regular
                $males = array_filter($regularPeople, fn($p) => $p['gender'] === 'male');
                $females = array_filter($regularPeople, fn($p) => $p['gender'] === 'female');
                $unknown = array_filter($regularPeople, fn($p) => !$p['gender']);

                $regularPeople = [];
                while (!empty($males) || !empty($females)) {
                    if (!empty($males)) $regularPeople[] = array_shift($males);
                    if (!empty($females)) $regularPeople[] = array_shift($females);
                }
                $regularPeople = array_merge($regularPeople, $unknown);
            }

            // Seat VIP guests at high table
            foreach ($vipPeople as $person) {
                $seat = $findBestSeat($person, true);
                if ($seat) {
                    $assignments[] = [
                        'table_id' => $seat['table_id'],
                        'seat_number' => $seat['seat_number'],
                        'guest_name' => $person['name'],
                        'guest_id' => $person['guest_id']
                    ];
                }
            }

            // Seat regular guests
            foreach ($regularPeople as $person) {
                $seat = $findBestSeat($person, false);
                if ($seat) {
                    $assignments[] = [
                        'table_id' => $seat['table_id'],
                        'seat_number' => $seat['seat_number'],
                        'guest_name' => $person['name'],
                        'guest_id' => $person['guest_id']
                    ];
                }
            }
        }

        // Insert all assignments
        $stmt = $db->prepare("
            INSERT INTO seating_assignments (event_id, table_id, seat_number, guest_name, guest_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($assignments as $a) {
            $stmt->execute([$eventId, $a['table_id'], $a['seat_number'], $a['guest_name'], $a['guest_id']]);
        }

        echo json_encode([
            'success' => true,
            'seated' => count($assignments),
            'total' => count($allPeople)
        ]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

require_once __DIR__ . '/../includes/admin-header.php';

// Create tables if they don't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS seating_tables (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            table_type ENUM('round', 'rectangle', 'square', 'ushape') DEFAULT 'round',
            capacity INT DEFAULT 8,
            position_x INT DEFAULT 0,
            position_y INT DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS seating_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            table_id INT NOT NULL,
            guest_name VARCHAR(255) NOT NULL,
            seat_number INT DEFAULT NULL,
            guest_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (table_id) REFERENCES seating_tables(id) ON DELETE CASCADE,
            FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL
        )
    ");
} catch (Exception $e) {
    // Tables might already exist
}

// Get all tables
$stmt = $db->prepare("SELECT * FROM seating_tables WHERE event_id = ? ORDER BY sort_order");
$stmt->execute([$eventId]);
$tables = $stmt->fetchAll();

// Count confirmed guests (total people including +1s)
$stmt = $db->prepare("SELECT SUM(COALESCE(adults_count, 1) + COALESCE(children_count, 0)) as total FROM guests WHERE event_id = ? AND rsvp_status = 'yes'");
$stmt->execute([$eventId]);
$confirmedGuestCount = (int)$stmt->fetchColumn();

// Get all assignments
$stmt = $db->prepare("SELECT * FROM seating_assignments WHERE event_id = ?");
$stmt->execute([$eventId]);
$assignments = $stmt->fetchAll();

// Group assignments by table
$assignmentsByTable = [];
foreach ($assignments as $a) {
    $assignmentsByTable[$a['table_id']][] = $a;
}

// Get all confirmed guests with their names
$stmt = $db->prepare("
    SELECT g.id, g.name, g.guest_names, g.adults_count
    FROM guests g
    WHERE g.event_id = ? AND g.rsvp_status = 'yes'
    ORDER BY g.name
");
$stmt->execute([$eventId]);
$confirmedGuests = $stmt->fetchAll();

// Build list of all guest names (individual people)
$allGuestNames = [];
foreach ($confirmedGuests as $guest) {
    if (!empty($guest['guest_names'])) {
        $names = json_decode($guest['guest_names'], true);
        if ($names) {
            foreach ($names as $name) {
                $allGuestNames[] = [
                    'name' => $name,
                    'guest_id' => $guest['id'],
                    'invitation' => $guest['name']
                ];
            }
        }
    } else {
        // Fallback to invitation name
        $allGuestNames[] = [
            'name' => $guest['name'],
            'guest_id' => $guest['id'],
            'invitation' => $guest['name']
        ];
    }
}

// Find which guests are already assigned
$assignedNames = array_column($assignments, 'guest_name');
$unassignedGuests = array_filter($allGuestNames, function($g) use ($assignedNames) {
    return !in_array($g['name'], $assignedNames);
});

// Stats
$totalSeats = array_sum(array_column($tables, 'capacity'));
$totalAssigned = count($assignments);
$totalGuests = count($allGuestNames);

require_once __DIR__ . '/../includes/admin-sidebar.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-header__title">Bordplan</h1>
        <p class="page-header__subtitle">
            <?= $totalAssigned ?> af <?= $totalGuests ?> gæster placeret
            &middot; <?= count($tables) ?> borde med <?= $totalSeats ?> pladser
        </p>
    </div>
    <div class="page-header__actions">
        <?php if ($totalAssigned > 0): ?>
            <button onclick="clearAllSeats()" class="btn btn--ghost" title="Fjern alle placeringer">Ryd alle</button>
        <?php endif; ?>
        <button onclick="wizardReset(); openModal('auto-seat-modal')" class="btn btn--secondary">Automatisk placering</button>
        <button onclick="window.print()" class="btn btn--secondary">Print</button>
        <button onclick="openModal('add-table-modal')" class="btn btn--primary">+ Nyt bord</button>
    </div>
</div>

<?php if ($totalGuests === 0): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">👥</div>
            <h3 class="empty-state__title">Ingen bekræftede gæster endnu</h3>
            <p class="empty-state__text">Vent til gæsterne har svaret på invitationen</p>
        </div>
    </div>
<?php else: ?>

<div class="seating-layout">
    <!-- Unassigned Guests Panel -->
    <div class="seating-sidebar">
        <div class="card">
            <h3 class="card__title mb-sm">
                Ikke placeret (<?= count($unassignedGuests) ?>)
            </h3>
            <div class="guest-pool" id="guest-pool"
                 ondragover="allowDropToPool(event)"
                 ondragleave="leavePool(event)"
                 ondrop="dropToPool(event)">
                <?php if (empty($unassignedGuests)): ?>
                    <p class="small text-muted text-center pool-empty-msg" style="padding: 1rem;">
                        Alle gæster er placeret!
                    </p>
                <?php else: ?>
                    <?php foreach ($unassignedGuests as $guest): ?>
                        <div class="guest-chip"
                             draggable="true"
                             data-guest-name="<?= escape($guest['name']) ?>"
                             data-guest-id="<?= $guest['guest_id'] ?>">
                            <span class="guest-chip__name"><?= escape($guest['name']) ?></span>
                            <span class="guest-chip__info"><?= escape($guest['invitation']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="pool-drop-hint">Slip her for at fjerne fra bord</div>
            </div>
        </div>

        <!-- Quick add guest manually -->
        <div class="card mt-md">
            <h4 class="small mb-sm" style="font-weight: 600;">Tilføj gæst manuelt</h4>
            <form onsubmit="addManualGuest(event)">
                <input type="text" id="manual-guest-name" class="form-input mb-xs" placeholder="Navn">
                <button type="submit" class="btn btn--secondary btn--block">Tilføj</button>
            </form>
        </div>
    </div>

    <!-- Tables Area -->
    <div class="seating-main">
        <?php if (empty($tables)): ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-state__icon">🪑</div>
                    <h3 class="empty-state__title">Ingen borde endnu</h3>
                    <p class="empty-state__text">Tilføj borde for at begynde bordplanen</p>
                    <button onclick="openModal('add-table-modal')" class="btn btn--primary mt-md">
                        + Tilføj første bord
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="tables-grid">
                <?php foreach ($tables as $table):
                    $tableAssignments = $assignmentsByTable[$table['id']] ?? [];
                    // Index assignments by seat number
                    $seatAssignments = [];
                    foreach ($tableAssignments as $a) {
                        $seatAssignments[$a['seat_number']] = $a;
                    }
                    $seatsTaken = count($tableAssignments);
                    $seatsLeft = $table['capacity'] - $seatsTaken;
                    $capacity = $table['capacity'];
                    $tableType = $table['table_type'];
                ?>
                    <div class="table-card table-card--<?= $tableType ?>"
                         data-table-id="<?= $table['id'] ?>"
                         data-capacity="<?= $capacity ?>">

                        <div class="table-card__header">
                            <h3 class="table-card__name"><?= escape($table['name']) ?></h3>
                            <span class="table-card__count <?= $seatsLeft === 0 ? 'table-card__count--full' : '' ?>">
                                <?= $seatsTaken ?>/<?= $capacity ?>
                            </span>
                        </div>

                        <!-- Visual Table with Numbered Seats -->
                        <div class="table-visual table-visual--<?= $tableType ?>" data-seats="<?= $capacity ?>">
                            <div class="table-surface"></div>
                            <div class="seats-container">
                                <?php for ($seat = 1; $seat <= $capacity; $seat++):
                                    $assignment = $seatAssignments[$seat] ?? null;
                                    $isEmpty = !$assignment;
                                ?>
                                    <div class="seat seat--<?= $seat ?> <?= $isEmpty ? 'seat--empty' : 'seat--occupied' ?>"
                                         data-seat-number="<?= $seat ?>"
                                         data-table-id="<?= $table['id'] ?>"
                                         <?php if ($assignment): ?>
                                         data-assignment-id="<?= $assignment['id'] ?>"
                                         data-guest-name="<?= escape($assignment['guest_name']) ?>"
                                         draggable="true"
                                         <?php endif; ?>
                                         ondragover="allowDrop(event)"
                                         ondrop="dropOnSeat(event, <?= $table['id'] ?>, <?= $seat ?>)">
                                        <span class="seat__number"><?= $seat ?></span>
                                        <?php if ($assignment): ?>
                                            <span class="seat__name" title="<?= escape($assignment['guest_name']) ?>"><?= escape($assignment['guest_name']) ?></span>
                                            <button class="seat__remove" onclick="removeGuest(<?= $assignment['id'] ?>)" title="Fjern">&times;</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Guest List (for reference) -->
                        <div class="table-card__list">
                            <div class="guest-list-header">Gæster ved dette bord:</div>
                            <?php if (empty($tableAssignments)): ?>
                                <div class="guest-list-empty">Ingen gæster placeret</div>
                            <?php else: ?>
                                <?php
                                // Sort by seat number
                                usort($tableAssignments, fn($a, $b) => ($a['seat_number'] ?? 0) - ($b['seat_number'] ?? 0));
                                foreach ($tableAssignments as $assignment): ?>
                                    <div class="guest-list-item">
                                        <span class="guest-list-seat"><?= $assignment['seat_number'] ?: '?' ?></span>
                                        <span class="guest-list-name"><?= escape($assignment['guest_name']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="table-card__actions">
                            <button onclick="editTable(<?= htmlspecialchars(json_encode($table)) ?>)" class="btn btn--ghost btn--sm">Rediger</button>
                            <button onclick="deleteTable(<?= $table['id'] ?>)" class="btn btn--ghost btn--sm">Slet</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<!-- Add Table Modal -->
<div id="add-table-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Tilføj bord</h2>
            <button class="modal__close" onclick="closeModal('add-table-modal')">&times;</button>
        </div>
        <form onsubmit="addTable(event)">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Bordnavn *</label>
                    <input type="text" id="new-table-name" class="form-input" required placeholder="F.eks. Bord 1, Hovedbord, Børnebord...">
                </div>

                <div class="form-group">
                    <label class="form-label">Bordtype</label>
                    <div class="table-type-selector">
                        <label class="table-type-option">
                            <input type="radio" name="table_type" value="round" checked>
                            <div class="table-type-preview table-type-preview--round"></div>
                            <span>Rundt</span>
                        </label>
                        <label class="table-type-option">
                            <input type="radio" name="table_type" value="rectangle">
                            <div class="table-type-preview table-type-preview--rectangle"></div>
                            <span>Langt</span>
                        </label>
                        <label class="table-type-option">
                            <input type="radio" name="table_type" value="square">
                            <div class="table-type-preview table-type-preview--square"></div>
                            <span>Kvadrat</span>
                        </label>
                        <label class="table-type-option">
                            <input type="radio" name="table_type" value="ushape">
                            <div class="table-type-preview table-type-preview--ushape"></div>
                            <span>U-form</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Antal pladser</label>
                    <input type="number" id="new-table-capacity" class="form-input" value="8" min="2" max="20" style="width: 100px;">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('add-table-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Tilføj bord</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Table Modal -->
<div id="edit-table-modal" class="modal-overlay">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title">Rediger bord</h2>
            <button class="modal__close" onclick="closeModal('edit-table-modal')">&times;</button>
        </div>
        <form onsubmit="updateTable(event)">
            <input type="hidden" id="edit-table-id">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Bordnavn *</label>
                    <input type="text" id="edit-table-name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Antal pladser</label>
                    <input type="number" id="edit-table-capacity" class="form-input" min="2" max="20" style="width: 100px;">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('edit-table-modal')">Annuller</button>
                <button type="submit" class="btn btn--primary">Gem ændringer</button>
            </div>
        </form>
    </div>
</div>

<!-- Auto-Seat Wizard Modal -->
<div id="auto-seat-modal" class="modal-overlay">
    <div class="modal" style="max-width: 600px;">
        <div class="modal__header">
            <h2 class="modal__title">Automatisk bordplacering</h2>
            <button class="modal__close" onclick="closeModal('auto-seat-modal')">&times;</button>
        </div>
        <div class="modal__body">
            <!-- Step 0: Create Tables -->
            <div class="wizard-step" id="wizard-step-0">
                <h3 class="wizard-step__title">1. Opret borde</h3>
                <p class="text-muted mb-md">
                    Du har <strong><?= $confirmedGuestCount ?></strong> bekræftede gæster. Vælg bordtype og kapacitet.
                </p>

                <?php if (empty($tables)): ?>
                <div class="form-group">
                    <label class="form-label">Bordtype</label>
                    <div class="table-type-grid">
                        <label class="table-type-option">
                            <input type="radio" name="auto_table_type" value="round" checked>
                            <span class="table-type-icon">⭕</span>
                            <span>Rundt</span>
                        </label>
                        <label class="table-type-option">
                            <input type="radio" name="auto_table_type" value="rectangle">
                            <span class="table-type-icon">▭</span>
                            <span>Rektangel</span>
                        </label>
                        <label class="table-type-option">
                            <input type="radio" name="auto_table_type" value="square">
                            <span class="table-type-icon">⬜</span>
                            <span>Kvadrat</span>
                        </label>
                        <label class="table-type-option">
                            <input type="radio" name="auto_table_type" value="ushape">
                            <span class="table-type-icon">⊔</span>
                            <span>U-form</span>
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Pladser per bord</label>
                        <select id="auto-table-capacity" class="form-input">
                            <option value="6">6 pladser</option>
                            <option value="8" selected>8 pladser</option>
                            <option value="10">10 pladser</option>
                            <option value="12">12 pladser</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Antal borde</label>
                        <div class="calculated-tables">
                            <span id="calculated-tables-count"><?= $confirmedGuestCount > 0 ? ceil($confirmedGuestCount / 8) : 0 ?></span> borde
                        </div>
                    </div>
                </div>

                <div class="option-card">
                    <label class="option-toggle">
                        <input type="checkbox" id="auto-include-high-table" checked>
                        <span class="option-toggle__switch"></span>
                        <div>
                            <strong>Inkluder højbord</strong>
                            <span class="text-muted small">Opret et separat højbord til konfirmand og familie</span>
                        </div>
                    </label>
                </div>

                <div id="high-table-capacity-row" class="form-group mt-sm">
                    <label class="form-label">Pladser ved højbord</label>
                    <select id="auto-high-table-capacity" class="form-input">
                        <option value="6">6 pladser</option>
                        <option value="8">8 pladser</option>
                        <option value="10" selected>10 pladser</option>
                        <option value="12">12 pladser</option>
                    </select>
                </div>

                <button type="button" class="btn btn--primary mt-md" onclick="createTablesAuto()" id="create-tables-btn">
                    Opret borde automatisk
                </button>
                <?php else: ?>
                <div class="alert alert--success">
                    <strong>✓ Du har allerede <?= count($tables) ?> borde oprettet</strong>
                    <p class="small mt-xs">Gå videre til næste trin for at vælge højbord og VIP-gæster.</p>
                </div>
                <p class="text-muted small">Vil du starte forfra? <a href="#" onclick="deleteAllTablesAndRestart(); return false;">Slet alle borde</a></p>
                <?php endif; ?>
            </div>

            <!-- Step 1: High Table -->
            <div class="wizard-step" id="wizard-step-1" style="display: none;">
                <h3 class="wizard-step__title">2. Vælg højbord</h3>
                <p class="text-muted mb-md">
                    Vælg det bord hvor VIP-gæster (f.eks. konfirmand og forældre) skal sidde.
                </p>
                <div class="form-group" id="high-table-select-container">
                    <!-- Will be populated dynamically after table creation -->
                    <?php if (!empty($tables)): ?>
                    <select id="auto-high-table" class="form-input">
                        <option value="">Intet højbord</option>
                        <?php foreach ($tables as $table): ?>
                            <option value="<?= $table['id'] ?>" <?= ($table['is_high_table'] ?? false) ? 'selected' : '' ?>>
                                <?= escape($table['name']) ?> (<?= $table['capacity'] ?> pladser)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Step 2: VIP Guests -->
            <div class="wizard-step" id="wizard-step-2" style="display: none;">
                <h3 class="wizard-step__title">3. Vælg VIP-gæster til højbordet</h3>
                <p class="text-muted mb-md">
                    Vælg hvilke gæster der skal sidde ved højbordet.
                </p>
                <div class="vip-guest-list" id="vip-guest-list">
                    <?php foreach ($allGuestNames as $guest): ?>
                        <label class="vip-guest-item">
                            <input type="checkbox" name="vip_guests[]" value="<?= escape($guest['name']) ?>">
                            <span><?= escape($guest['name']) ?></span>
                            <span class="text-muted small">(<?= escape($guest['invitation']) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Step 3: Options -->
            <div class="wizard-step" id="wizard-step-3" style="display: none;">
                <h3 class="wizard-step__title">4. Placeringsregler</h3>
                <p class="text-muted mb-md">
                    Vælg hvordan gæsterne skal placeres.
                </p>

                <div class="option-card">
                    <label class="option-toggle">
                        <input type="checkbox" id="opt-group-relation" checked>
                        <span class="option-toggle__switch"></span>
                        <div>
                            <strong>Grupér efter relation</strong>
                            <span class="text-muted small">Hold familiegrupper sammen ved samme bord</span>
                        </div>
                    </label>
                </div>

                <div class="option-card">
                    <label class="option-toggle">
                        <input type="checkbox" id="opt-keep-couples" checked>
                        <span class="option-toggle__switch"></span>
                        <div>
                            <strong>Hold par sammen</strong>
                            <span class="text-muted small">Par/familier fra samme invitation sidder ved siden af hinanden</span>
                        </div>
                    </label>
                </div>

                <div class="option-card">
                    <label class="option-toggle">
                        <input type="checkbox" id="opt-alternate-gender">
                        <span class="option-toggle__switch"></span>
                        <div>
                            <strong>Mand/dame skiftevis</strong>
                            <span class="text-muted small">Placér mænd og kvinder skiftevis (gætter køn fra navne)</span>
                        </div>
                    </label>
                </div>

                <div class="alert alert--warning mt-md">
                    <strong>Bemærk:</strong> Automatisk placering vil fjerne alle eksisterende placeringer.
                </div>
            </div>
        </div>
        <div class="modal__footer">
            <div class="wizard-nav">
                <button type="button" id="wizard-prev" class="btn btn--secondary" onclick="wizardPrev()" style="display: none;">
                    Tilbage
                </button>
                <div style="flex: 1;"></div>
                <button type="button" id="wizard-next" class="btn btn--primary" onclick="wizardNext()">
                    Næste
                </button>
                <button type="button" id="wizard-finish" class="btn btn--primary" onclick="runAutoSeat()" style="display: none;">
                    Placér gæster automatisk
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Wizard styles */
.wizard-step__title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
}

.wizard-nav {
    display: flex;
    gap: 0.5rem;
    width: 100%;
}

/* Table type selection grid */
.table-type-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.table-type-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0.75rem 0.5rem;
    border: 2px solid var(--color-border-soft);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.table-type-option:hover {
    border-color: var(--color-primary-light);
    background: var(--color-bg-subtle);
}

.table-type-option:has(input:checked) {
    border-color: var(--color-primary);
    background: var(--color-primary-light);
}

.table-type-option input {
    display: none;
}

.table-type-icon {
    font-size: 1.5rem;
}

.table-type-option span:last-child {
    font-size: 0.75rem;
    font-weight: 500;
}

.calculated-tables {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 42px;
    background: var(--color-bg-subtle);
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 1.1rem;
}

.calculated-tables span {
    color: var(--color-primary);
    font-size: 1.5rem;
    margin-right: 0.25rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.vip-guest-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--color-border-soft);
    border-radius: var(--radius-md);
    padding: 0.5rem;
}

.vip-guest-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background 0.2s;
}

.vip-guest-item:hover {
    background: var(--color-bg-subtle);
}

.vip-guest-item input {
    width: 18px;
    height: 18px;
}

.option-card {
    background: var(--color-bg-subtle);
    border-radius: var(--radius-md);
    padding: 1rem;
    margin-bottom: 0.75rem;
}

.option-card--disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.option-toggle {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
}

.option-toggle input {
    display: none;
}

.option-toggle__switch {
    width: 44px;
    height: 24px;
    background: var(--color-border);
    border-radius: 12px;
    position: relative;
    flex-shrink: 0;
    transition: background 0.2s;
}

.option-toggle__switch::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transition: transform 0.2s;
}

.option-toggle input:checked + .option-toggle__switch {
    background: var(--color-primary);
}

.option-toggle input:checked + .option-toggle__switch::after {
    transform: translateX(20px);
}

.option-toggle div {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.option-toggle div strong {
    font-weight: 500;
}

.alert--warning {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    color: #92400e;
    padding: 0.75rem 1rem;
    border-radius: var(--radius-md);
    font-size: 0.9rem;
}

/* Seating Layout */
.seating-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: var(--space-lg);
    align-items: start;
}

@media (max-width: 900px) {
    .seating-layout {
        grid-template-columns: 1fr;
    }
}

.seating-sidebar {
    position: sticky;
    top: 100px;
}

.guest-pool {
    max-height: 400px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-height: 100px;
    padding: 0.5rem;
    border: 2px dashed transparent;
    border-radius: var(--radius-md);
    transition: all 0.2s;
    position: relative;
}

.guest-pool.drag-over {
    border-color: var(--color-primary);
    background: var(--color-primary-pale);
}

.guest-pool.drag-over .pool-empty-msg {
    display: none;
}

.pool-drop-hint {
    display: none;
    position: absolute;
    inset: 0;
    background: var(--color-primary-pale);
    border-radius: var(--radius-md);
    color: var(--color-primary-deep);
    font-weight: 500;
    justify-content: center;
    align-items: center;
    text-align: center;
    pointer-events: none;
}

.guest-pool.drag-over .pool-drop-hint {
    display: flex;
}

.guest-chip {
    display: flex;
    flex-direction: column;
    padding: 0.5rem 0.75rem;
    background: var(--color-bg-subtle);
    border-radius: var(--radius-sm);
    cursor: grab;
    transition: all 0.2s;
    border: 2px solid transparent;
}

.guest-chip:hover {
    background: var(--color-primary-pale);
    border-color: var(--color-primary-soft);
}

.guest-chip:active {
    cursor: grabbing;
}

.guest-chip__name {
    font-weight: 500;
    font-size: 0.9rem;
}

.guest-chip__info {
    font-size: 0.7rem;
    color: var(--color-text-muted);
}

/* Tables Grid */
.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: var(--space-lg);
}

.table-card {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    padding: var(--space-md);
    border: 2px solid var(--color-border-soft);
    transition: all 0.2s;
}

.table-card__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-sm);
}

.table-card__name {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.25rem;
    font-weight: 600;
}

.table-card__count {
    background: var(--color-bg-subtle);
    padding: 0.25rem 0.5rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.table-card__count--full {
    background: var(--color-success);
    color: white;
}

/* Visual Table with Seats */
.table-visual {
    position: relative;
    min-height: 220px;
    margin: var(--space-md) 0;
    display: flex;
    justify-content: center;
    align-items: center;
}

.table-surface {
    position: absolute;
    background: var(--color-primary-soft);
    border: 3px solid var(--color-primary);
    z-index: 1;
}

/* Round table */
.table-visual--round .table-surface {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
}

/* Rectangle table */
.table-visual--rectangle .table-surface {
    width: 200px;
    height: 60px;
    border-radius: 8px;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
}

/* Square table */
.table-visual--square .table-surface {
    width: 90px;
    height: 90px;
    border-radius: 8px;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
}

/* U-shape table */
.table-visual--ushape .table-surface {
    width: 180px;
    height: 100px;
    border-radius: 8px;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
}
.table-visual--ushape .table-surface::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, 0);
    width: 100px;
    height: 60px;
    background: var(--color-surface);
    border-radius: 0 0 8px 8px;
}

/* Seats Container */
.seats-container {
    position: absolute;
    inset: 0;
    z-index: 2;
}

/* Individual Seat */
.seat {
    position: absolute;
    width: 70px;
    height: 50px;
    background: var(--color-surface);
    border: 2px dashed var(--color-border-soft);
    border-radius: var(--radius-sm);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    transition: all 0.2s;
    cursor: pointer;
    overflow: hidden;
    padding: 4px;
}

.seat--empty {
    background: var(--color-bg-subtle);
}

.seat--empty:hover, .seat.drag-over {
    background: var(--color-primary-pale);
    border-color: var(--color-primary);
    border-style: solid;
}

.seat--occupied {
    background: var(--color-primary-pale);
    border-color: var(--color-primary);
    border-style: solid;
    cursor: grab;
}

.seat--occupied:active {
    cursor: grabbing;
}

.seat__number {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--color-text-muted);
    background: var(--color-surface);
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.seat--occupied .seat__number {
    position: absolute;
    top: 2px;
    left: 2px;
}

.seat__name {
    font-size: 0.7rem;
    font-weight: 500;
    text-align: center;
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    max-width: 100%;
    word-break: break-word;
}

.seat__remove {
    position: absolute;
    top: 2px;
    right: 2px;
    background: rgba(255,255,255,0.8);
    border: none;
    color: var(--color-text-muted);
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 2px 4px;
    border-radius: 4px;
    opacity: 0.6;
    transition: all 0.2s;
}

.seat--occupied:hover .seat__remove,
.seat__remove:focus {
    opacity: 1;
    background: rgba(255,255,255,1);
}

.seat__remove:hover {
    color: var(--color-error);
    background: #fee2e2;
}

/* Guest List below table */
.table-card__list {
    background: var(--color-bg-subtle);
    border-radius: var(--radius-sm);
    padding: var(--space-sm);
    margin-bottom: var(--space-sm);
    font-size: 0.8rem;
}

.guest-list-header {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--color-text-muted);
}

.guest-list-empty {
    color: var(--color-text-muted);
    font-style: italic;
}

.guest-list-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0;
}

.guest-list-seat {
    background: var(--color-primary);
    color: white;
    font-weight: 600;
    font-size: 0.7rem;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.guest-list-name {
    flex: 1;
}

.table-card__actions {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    padding-top: var(--space-sm);
    border-top: 1px solid var(--color-border-soft);
}

/* Table Type Selector */
.table-type-selector {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
}

.table-type-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border: 2px solid var(--color-border-soft);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.2s;
}

.table-type-option:has(input:checked) {
    border-color: var(--color-primary);
    background: var(--color-primary-pale);
}

.table-type-option input {
    display: none;
}

.table-type-option span {
    font-size: 0.75rem;
}

.table-type-preview {
    width: 40px;
    height: 30px;
    background: var(--color-primary-soft);
    border: 2px solid var(--color-primary);
}

.table-type-preview--round {
    border-radius: 50%;
    width: 35px;
    height: 35px;
}

.table-type-preview--rectangle {
    border-radius: 4px;
}

.table-type-preview--square {
    width: 30px;
    height: 30px;
    border-radius: 4px;
}

.table-type-preview--ushape {
    border-radius: 4px;
    position: relative;
}

.table-type-preview--ushape::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 15px;
    background: var(--color-surface);
}

/* Print Styles */
@media print {
    .seating-sidebar,
    .page-header__actions,
    .table-card__actions,
    .sidebar,
    .guest-nav,
    .seat__remove {
        display: none !important;
    }

    .seating-layout {
        grid-template-columns: 1fr;
    }

    .tables-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .table-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    body {
        background: white;
    }

    .main-content {
        margin: 0;
        padding: 1rem;
    }
}
</style>

<script>
// Drag and Drop
let draggedElement = null;
let draggedData = null;

// Position seats around tables on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.table-visual').forEach(tableVisual => {
        positionSeats(tableVisual);
    });

    // Setup drag events for guest chips
    document.querySelectorAll('.guest-chip').forEach(setupDragSource);

    // Setup drag events for occupied seats
    document.querySelectorAll('.seat--occupied').forEach(setupDragSource);
});

function setupDragSource(el) {
    el.addEventListener('dragstart', function(e) {
        draggedElement = this;
        draggedData = {
            guestName: this.dataset.guestName,
            guestId: this.dataset.guestId || null,
            assignmentId: this.dataset.assignmentId || null
        };
        this.style.opacity = '0.5';
        e.dataTransfer.effectAllowed = 'move';
    });

    el.addEventListener('dragend', function(e) {
        this.style.opacity = '1';
        document.querySelectorAll('.seat').forEach(seat => {
            seat.classList.remove('drag-over');
        });
        draggedElement = null;
        draggedData = null;
    });
}

function positionSeats(tableVisual) {
    const seats = tableVisual.querySelectorAll('.seat');
    const numSeats = seats.length;
    const tableType = tableVisual.classList.contains('table-visual--round') ? 'round' :
                      tableVisual.classList.contains('table-visual--rectangle') ? 'rectangle' :
                      tableVisual.classList.contains('table-visual--square') ? 'square' : 'ushape';

    const containerWidth = tableVisual.offsetWidth || 340;
    const containerHeight = tableVisual.offsetHeight || 220;
    const centerX = containerWidth / 2;
    const centerY = containerHeight / 2;
    const seatWidth = 70;
    const seatHeight = 50;

    seats.forEach((seat, index) => {
        let x, y;

        if (tableType === 'round') {
            // Circular arrangement
            const angle = (index / numSeats) * 2 * Math.PI - Math.PI / 2;
            const radius = Math.min(centerX, centerY) - seatHeight / 2 - 10;
            x = centerX + radius * Math.cos(angle) - seatWidth / 2;
            y = centerY + radius * Math.sin(angle) - seatHeight / 2;
        } else if (tableType === 'rectangle') {
            // Long table - seats on top and bottom
            const tableWidth = 200;
            const halfSeats = Math.ceil(numSeats / 2);
            const spacing = tableWidth / (halfSeats + 1);
            const isTopRow = index < halfSeats;
            const posInRow = isTopRow ? index : index - halfSeats;

            x = centerX - tableWidth / 2 + spacing * (posInRow + 1) - seatWidth / 2;
            y = isTopRow ? centerY - 30 - seatHeight - 10 : centerY + 30 + 10;
        } else if (tableType === 'square') {
            // Square table - seats on all 4 sides
            const seatsPerSide = Math.ceil(numSeats / 4);
            const side = Math.floor(index / seatsPerSide);
            const posInSide = index % seatsPerSide;
            const tableSize = 90;
            const offset = tableSize / 2 + 15;

            if (side === 0) { // Top
                x = centerX - seatWidth / 2;
                y = centerY - offset - seatHeight;
            } else if (side === 1) { // Right
                x = centerX + offset;
                y = centerY - seatHeight / 2;
            } else if (side === 2) { // Bottom
                x = centerX - seatWidth / 2;
                y = centerY + offset;
            } else { // Left
                x = centerX - offset - seatWidth;
                y = centerY - seatHeight / 2;
            }
        } else { // U-shape
            // U-shape: seats on outside of U
            const tableWidth = 180;
            const tableHeight = 100;
            const seatsTop = Math.ceil(numSeats * 0.4);
            const seatsSide = Math.floor((numSeats - seatsTop) / 2);
            const seatsOtherSide = numSeats - seatsTop - seatsSide;

            if (index < seatsTop) {
                // Top row
                const spacing = tableWidth / (seatsTop + 1);
                x = centerX - tableWidth / 2 + spacing * (index + 1) - seatWidth / 2;
                y = centerY - tableHeight / 2 - seatHeight - 10;
            } else if (index < seatsTop + seatsSide) {
                // Left side
                const posInSide = index - seatsTop;
                x = centerX - tableWidth / 2 - seatWidth - 10;
                y = centerY - tableHeight / 4 + posInSide * (seatHeight + 5);
            } else {
                // Right side
                const posInSide = index - seatsTop - seatsSide;
                x = centerX + tableWidth / 2 + 10;
                y = centerY - tableHeight / 4 + posInSide * (seatHeight + 5);
            }
        }

        seat.style.left = x + 'px';
        seat.style.top = y + 'px';
    });
}

function allowDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.add('drag-over');
}

function allowDropToPool(e) {
    e.preventDefault();
    // Only show drop zone if dragging from a seat (has assignmentId)
    if (draggedData && draggedData.assignmentId) {
        document.getElementById('guest-pool').classList.add('drag-over');
    }
}

function leavePool(e) {
    // Only remove if actually leaving the pool (not entering a child)
    if (!e.currentTarget.contains(e.relatedTarget)) {
        document.getElementById('guest-pool').classList.remove('drag-over');
    }
}

function dropToPool(e) {
    e.preventDefault();
    const pool = document.getElementById('guest-pool');
    pool.classList.remove('drag-over');

    if (!draggedData || !draggedData.assignmentId) return;

    // Remove guest from seat (return to pool)
    removeGuestSilent(draggedData.assignmentId);
}

// Handle drag leave on seats
document.querySelectorAll('.seat').forEach(seat => {
    seat.addEventListener('dragleave', function(e) {
        this.classList.remove('drag-over');
    });
});

function dropOnSeat(e, tableId, seatNumber) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.classList.remove('drag-over');

    if (!draggedData) return;

    const targetSeat = e.currentTarget;
    const isTargetOccupied = targetSeat.classList.contains('seat--occupied');

    if (draggedData.assignmentId) {
        // Moving from another seat
        if (isTargetOccupied) {
            // Swap seats
            swapSeats(draggedData.assignmentId, targetSeat.dataset.assignmentId);
        } else {
            // Move to empty seat
            moveGuest(draggedData.assignmentId, tableId, seatNumber);
        }
    } else {
        // New guest from pool
        if (isTargetOccupied) {
            alert('Pladsen er optaget. Træk til en tom plads eller byt med en anden gæst.');
            return;
        }
        assignGuest(tableId, seatNumber, draggedData.guestName, draggedData.guestId);
    }
}

function assignGuest(tableId, seatNumber, guestName, guestId) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=assign_guest&table_id=${tableId}&seat_number=${seatNumber}&guest_name=${encodeURIComponent(guestName)}&guest_id=${guestId || ''}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Fejl ved placering');
        }
    });
}

function moveGuest(assignmentId, newTableId, seatNumber) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=move_guest&assignment_id=${assignmentId}&new_table_id=${newTableId}&seat_number=${seatNumber}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Fejl ved flytning');
        }
    });
}

function swapSeats(assignmentId1, assignmentId2) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=swap_seats&assignment_id_1=${assignmentId1}&assignment_id_2=${assignmentId2}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Fejl ved bytte');
        }
    });
}

function removeGuest(assignmentId) {
    if (confirm('Fjern gæst fra bordet?')) {
        removeGuestSilent(assignmentId);
    }
}

function removeGuestSilent(assignmentId) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=remove_guest&assignment_id=${assignmentId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function addTable(e) {
    e.preventDefault();
    const name = document.getElementById('new-table-name').value;
    const type = document.querySelector('input[name="table_type"]:checked').value;
    const capacity = document.getElementById('new-table-capacity').value;

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add_table&name=${encodeURIComponent(name)}&table_type=${type}&capacity=${capacity}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Fejl ved oprettelse');
        }
    });
}

function deleteTable(tableId) {
    if (confirm('Slet dette bord? Alle placeringer fjernes.')) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete_table&table_id=${tableId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}

function editTable(table) {
    document.getElementById('edit-table-id').value = table.id;
    document.getElementById('edit-table-name').value = table.name;
    document.getElementById('edit-table-capacity').value = table.capacity;
    openModal('edit-table-modal');
}

function updateTable(e) {
    e.preventDefault();
    const tableId = document.getElementById('edit-table-id').value;
    const name = document.getElementById('edit-table-name').value;
    const capacity = document.getElementById('edit-table-capacity').value;

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=update_table&table_id=${tableId}&name=${encodeURIComponent(name)}&capacity=${capacity}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function addManualGuest(e) {
    e.preventDefault();
    const name = document.getElementById('manual-guest-name').value.trim();
    if (!name) return;

    // Add to guest pool visually
    const pool = document.getElementById('guest-pool');
    const chip = document.createElement('div');
    chip.className = 'guest-chip';
    chip.draggable = true;
    chip.dataset.guestName = name;
    chip.dataset.guestId = '';
    chip.innerHTML = `<span class="guest-chip__name">${name}</span><span class="guest-chip__info">Manuel tilføjelse</span>`;

    setupDragSource(chip);

    pool.insertBefore(chip, pool.firstChild);
    document.getElementById('manual-guest-name').value = '';
}

// Sidebar toggle
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('sidebar--open');
    document.getElementById('sidebar-overlay').classList.toggle('sidebar-overlay--active');
}

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('modal-overlay--active');
    // Reset wizard when opening
    if (modalId === 'auto-seat-modal') {
        wizardReset();
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('modal-overlay--active');
}

// ===== Auto-Seat Wizard =====
let wizardCurrentStep = 0;
const wizardTotalSteps = 4; // Steps 0, 1, 2, 3
const confirmedGuestCount = <?= $confirmedGuestCount ?? 0 ?>;
let tablesCreated = <?= count($tables ?? []) ?>;

function wizardReset() {
    wizardCurrentStep = 0;
    updateWizardUI();
}

function wizardNext() {
    // Skip step 0 if tables already exist
    if (wizardCurrentStep === 0 && tablesCreated > 0) {
        wizardCurrentStep = 1;
    }
    if (wizardCurrentStep < wizardTotalSteps - 1) {
        wizardCurrentStep++;
        updateWizardUI();
    }
}

function wizardPrev() {
    if (wizardCurrentStep > 0) {
        wizardCurrentStep--;
        // Skip step 0 if tables already exist
        if (wizardCurrentStep === 0 && tablesCreated > 0) {
            wizardCurrentStep = 0; // Stay on step 0 but show "tables exist" message
        }
        updateWizardUI();
    }
}

function updateWizardUI() {
    // Hide all steps
    for (let i = 0; i < wizardTotalSteps; i++) {
        const step = document.getElementById(`wizard-step-${i}`);
        if (step) step.style.display = i === wizardCurrentStep ? 'block' : 'none';
    }

    // Update buttons
    document.getElementById('wizard-prev').style.display = wizardCurrentStep > 0 ? 'inline-flex' : 'none';
    document.getElementById('wizard-next').style.display = wizardCurrentStep < wizardTotalSteps - 1 ? 'inline-flex' : 'none';
    document.getElementById('wizard-finish').style.display = wizardCurrentStep === wizardTotalSteps - 1 ? 'inline-flex' : 'none';
}

function updateTableCount() {
    const capacity = parseInt(document.getElementById('auto-table-capacity')?.value || 8);
    const includeHighTable = document.getElementById('auto-include-high-table')?.checked;
    const highTableCapacity = parseInt(document.getElementById('auto-high-table-capacity')?.value || 10);

    let remaining = confirmedGuestCount;
    if (includeHighTable) {
        remaining -= highTableCapacity;
    }
    const tablesNeeded = Math.max(0, Math.ceil(remaining / capacity)) + (includeHighTable ? 1 : 0);

    const countEl = document.getElementById('calculated-tables-count');
    if (countEl) countEl.textContent = tablesNeeded;
}

function createTablesAuto() {
    const btn = document.getElementById('create-tables-btn');
    btn.disabled = true;
    btn.textContent = 'Opretter borde...';

    const tableType = document.querySelector('input[name="auto_table_type"]:checked')?.value || 'round';
    const capacity = document.getElementById('auto-table-capacity')?.value || 8;
    const includeHighTable = document.getElementById('auto-include-high-table')?.checked ? '1' : '0';
    const highTableCapacity = document.getElementById('auto-high-table-capacity')?.value || 10;

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=create_tables_auto&table_type=${tableType}&capacity=${capacity}&include_high_table=${includeHighTable}&high_table_capacity=${highTableCapacity}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            tablesCreated = data.tables_created;
            alert(`${data.tables_created} borde oprettet med plads til ${data.total_capacity} gæster!`);
            location.reload();
        } else {
            alert(data.error || 'Fejl ved oprettelse af borde');
            btn.disabled = false;
            btn.textContent = 'Opret borde automatisk';
        }
    })
    .catch(err => {
        alert('Netværksfejl - prøv igen');
        btn.disabled = false;
        btn.textContent = 'Opret borde automatisk';
    });
}

function deleteAllTablesAndRestart() {
    if (!confirm('Er du sikker? Dette vil slette alle borde og placeringer.')) return;

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=clear_all_seats'
    })
    .then(r => r.json())
    .then(data => {
        // Now delete tables
        return fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_all_tables'
        });
    })
    .then(r => r.json())
    .then(data => {
        location.reload();
    });
}

// Add event listeners for capacity changes
document.addEventListener('DOMContentLoaded', function() {
    const capacitySelect = document.getElementById('auto-table-capacity');
    const highTableCheckbox = document.getElementById('auto-include-high-table');
    const highTableCapacitySelect = document.getElementById('auto-high-table-capacity');
    const highTableCapacityRow = document.getElementById('high-table-capacity-row');

    if (capacitySelect) capacitySelect.addEventListener('change', updateTableCount);
    if (highTableCapacitySelect) highTableCapacitySelect.addEventListener('change', updateTableCount);

    if (highTableCheckbox) {
        highTableCheckbox.addEventListener('change', function() {
            if (highTableCapacityRow) {
                highTableCapacityRow.style.display = this.checked ? 'block' : 'none';
            }
            updateTableCount();
        });
    }
});

function runAutoSeat() {
    const btn = document.getElementById('wizard-finish');
    btn.disabled = true;
    btn.textContent = 'Placerer gæster...';

    // Gather options
    const highTableId = document.getElementById('auto-high-table')?.value || '';
    const vipCheckboxes = document.querySelectorAll('#vip-guest-list input:checked');
    const vipGuests = Array.from(vipCheckboxes).map(cb => cb.value);
    const keepCouples = document.getElementById('opt-keep-couples')?.checked ?? true;
    const alternateGender = document.getElementById('opt-alternate-gender')?.checked ?? false;
    const groupByRelation = document.getElementById('opt-group-relation')?.checked ?? true;

    const options = {
        high_table_id: highTableId,
        vip_guests: vipGuests,
        keep_couples: keepCouples,
        alternate_gender: alternateGender,
        group_by_relation: groupByRelation
    };

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=auto_seat&options=${encodeURIComponent(JSON.stringify(options))}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(`Færdig! ${data.seated} af ${data.total} gæster er blevet placeret.`);
            location.reload();
        } else {
            alert(data.error || 'Fejl ved automatisk placering');
            btn.disabled = false;
            btn.textContent = 'Placér gæster automatisk';
        }
    })
    .catch(err => {
        alert('Netværksfejl - prøv igen');
        btn.disabled = false;
        btn.textContent = 'Placér gæster automatisk';
    });
}

function clearAllSeats() {
    if (!confirm('Er du sikker på at du vil fjerne ALLE placeringer?')) return;

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=clear_all_seats'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}
</script>

</main>
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

</body>
</html>
