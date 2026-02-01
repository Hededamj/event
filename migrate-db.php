<?php
/**
 * Web-based Database Migration Runner
 *
 * IMPORTANT: Delete this file after running migrations!
 */

// Simple auth protection - change this token!
$authToken = 'partypart-migrate-2026';

if (($_GET['token'] ?? '') !== $authToken) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>Access Denied</title></head><body style="font-family:sans-serif;padding:40px;text-align:center;"><h1>Access Denied</h1><p>Invalid or missing token.</p></body></html>');
}

require_once __DIR__ . '/config/database.php';
$db = getDB();

$migrationsDir = __DIR__ . '/database/migrations';
$action = $_POST['action'] ?? $_GET['action'] ?? 'status';
$messages = [];
$errors = [];

// Ensure migrations table exists
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS _migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    $errors[] = "Error creating migrations table: " . $e->getMessage();
}

// Get executed migrations
$executed = [];
try {
    $stmt = $db->query("SELECT migration FROM _migrations ORDER BY migration");
    $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Table might not exist yet
}

// Get migration files
$files = glob($migrationsDir . '/*.sql');
sort($files);

$pending = [];
$completed = [];

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $executed)) {
        $completed[] = $name;
    } else {
        $pending[] = ['name' => $name, 'path' => $file];
    }
}

// Mark migration as done without running
if ($action === 'mark_done' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $toMark = $_POST['migration'] ?? '';
    if ($toMark) {
        try {
            $stmt = $db->prepare("INSERT IGNORE INTO _migrations (migration) VALUES (?)");
            $stmt->execute([$toMark]);
            $messages[] = "✓ {$toMark} marked as completed (skipped)";
            $completed[] = $toMark;
        } catch (Exception $e) {
            $errors[] = "Failed to mark: " . $e->getMessage();
        }
    }
}

// Run migrations
if ($action === 'run' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $toRun = $_POST['migrations'] ?? [];
    $skipErrors = isset($_POST['skip_errors']);

    foreach ($pending as $migration) {
        if (!in_array($migration['name'], $toRun)) {
            continue;
        }

        $sql = file_get_contents($migration['path']);

        if (empty(trim($sql))) {
            $messages[] = "Skipped {$migration['name']} (empty file)";
            continue;
        }

        // Split statements
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*$/m', $sql)),
            function($s) { return !empty($s); }
        );

        $migrationSuccess = true;
        $migrationErrors = [];

        foreach ($statements as $statement) {
            if (empty(trim($statement))) continue;

            try {
                $db->exec($statement);
            } catch (Exception $e) {
                $errMsg = $e->getMessage();
                // Ignore "already exists" errors
                if (strpos($errMsg, 'already exists') !== false ||
                    strpos($errMsg, 'Duplicate') !== false) {
                    // Skip this statement, continue with next
                    continue;
                }
                $migrationErrors[] = $errMsg;
                if (!$skipErrors) {
                    $migrationSuccess = false;
                    break;
                }
            }
        }

        if ($migrationSuccess || $skipErrors) {
            // Record migration as done
            try {
                $stmt = $db->prepare("INSERT IGNORE INTO _migrations (migration) VALUES (?)");
                $stmt->execute([$migration['name']]);
                $completed[] = $migration['name'];

                if (empty($migrationErrors)) {
                    $messages[] = "✓ {$migration['name']} executed successfully";
                } else {
                    $messages[] = "⚠ {$migration['name']} completed with warnings (some statements skipped)";
                }
            } catch (Exception $e) {
                $errors[] = "✗ {$migration['name']} failed to record: " . $e->getMessage();
            }
        } else {
            $errors[] = "✗ {$migration['name']} failed: " . implode("; ", $migrationErrors);
        }
    }

    // Refresh pending list
    $pending = array_filter($pending, function($m) use ($completed) {
        return !in_array($m['name'], $completed);
    });
    $pending = array_values($pending);
}

?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migrations - Partypart</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f3f4f6;
            padding: 40px 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #111827;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #6b7280;
            margin-bottom: 32px;
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
        }
        .card h2 {
            font-size: 18px;
            color: #374151;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .migration-list {
            list-style: none;
        }
        .migration-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .migration-item:hover {
            background: #f9fafb;
        }
        .migration-item input {
            margin-right: 12px;
        }
        .migration-item label {
            flex: 1;
            cursor: pointer;
            font-family: monospace;
            font-size: 14px;
        }
        .migration-item.completed {
            background: #f0fdf4;
        }
        .migration-item.completed label {
            color: #15803d;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a67d8;
        }
        .btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .messages {
            margin-bottom: 24px;
        }
        .message {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .message.success {
            background: #dcfce7;
            color: #15803d;
        }
        .message.error {
            background: #fee2e2;
            color: #dc2626;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #111827;
        }
        .stat-label {
            font-size: 14px;
            color: #6b7280;
        }
        .stat.pending .stat-value { color: #d97706; }
        .stat.completed .stat-value { color: #15803d; }
        .select-actions {
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
        }
        .select-actions button {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 13px;
        }
        .select-actions button:hover {
            text-decoration: underline;
        }
        .empty {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
        }
        .btn-skip {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            color: #6b7280;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin-left: auto;
        }
        .btn-skip:hover {
            background: #e5e7eb;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Migrations</h1>
        <p class="subtitle">Partypart Event Platform</p>

        <div class="warning">
            <strong>Vigtigt:</strong> Slet denne fil (<code>database/migrate-web.php</code>) efter du har kørt migrations!
        </div>

        <?php if (!empty($messages) || !empty($errors)): ?>
        <div class="messages">
            <?php foreach ($messages as $msg): ?>
                <div class="message success"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $err): ?>
                <div class="message error"><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat">
                <div class="stat-value"><?= count($files) ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat completed">
                <div class="stat-value"><?= count($completed) ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat pending">
                <div class="stat-value"><?= count($pending) ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <?php if (!empty($pending)): ?>
        <div class="card">
            <h2>Pending Migrations</h2>
            <form method="POST" action="?token=<?= htmlspecialchars($authToken) ?>&action=run">
                <div class="select-actions">
                    <button type="button" onclick="selectAll()">Select All</button>
                    <button type="button" onclick="selectNone()">Select None</button>
                </div>
                <ul class="migration-list">
                    <?php foreach ($pending as $migration): ?>
                    <li class="migration-item">
                        <input type="checkbox"
                               name="migrations[]"
                               value="<?= htmlspecialchars($migration['name']) ?>"
                               id="m_<?= md5($migration['name']) ?>"
                               checked>
                        <label for="m_<?= md5($migration['name']) ?>"><?= htmlspecialchars($migration['name']) ?></label>
                        <button type="button" onclick="skipMigration('<?= htmlspecialchars($migration['name']) ?>')" class="btn-skip">Skip</button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <button type="submit" class="btn btn-primary">
                    Run Selected Migrations
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>Completed Migrations</h2>
            <?php if (empty($completed)): ?>
                <div class="empty">No migrations have been executed yet.</div>
            <?php else: ?>
                <ul class="migration-list">
                    <?php foreach ($completed as $name): ?>
                    <li class="migration-item completed">
                        <label>✓ <?= htmlspecialchars($name) ?></label>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function selectAll() {
            document.querySelectorAll('input[name="migrations[]"]').forEach(cb => cb.checked = true);
        }
        function skipMigration(name) {
            if (confirm('Mark "' + name + '" as completed without running it?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '?token=<?= htmlspecialchars($authToken) ?>&action=mark_done';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'migration';
                input.value = name;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        function selectNone() {
            document.querySelectorAll('input[name="migrations[]"]').forEach(cb => cb.checked = false);
        }
    </script>
</body>
</html>
