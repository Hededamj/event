<?php
/**
 * Event Creation Wizard
 * Multi-step wizard for creating new events
 */
$pageTitle = 'Opret arrangement';
require_once __DIR__ . '/../../includes/app-header.php';

// Check if user can create more events
if (!canCreateEvent($accountId)) {
    setFlash('error', 'Du har nået grænsen for antal arrangementer på din plan. Opgrader for at oprette flere.');
    redirect('/app/account/subscription.php');
}

// Get event types for step 1
$eventTypes = getAllEventTypes();

// Handle form submission (final step)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_event') {
    if (!verifyAccountCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Ugyldig anmodning. Prøv igen.');
        redirect('/app/events/create.php');
    }

    // Collect all wizard data
    $eventTypeId = (int)($_POST['event_type_id'] ?? 0);
    $mainPersonName = trim($_POST['main_person_name'] ?? '');
    $secondaryPersonName = trim($_POST['secondary_person_name'] ?? '');
    $eventName = trim($_POST['event_name'] ?? '');
    $eventDate = $_POST['event_date'] ?? null;
    $eventTime = $_POST['event_time'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $theme = $_POST['theme'] ?? 'elegant';
    $welcomeText = trim($_POST['welcome_text'] ?? '');

    // Validation
    if (empty($mainPersonName)) {
        $error = 'Hovedpersonens navn er påkrævet.';
    } elseif (empty($eventDate)) {
        $error = 'Dato er påkrævet.';
    } else {
        try {
            $db->beginTransaction();

            // Generate unique slug
            $baseSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($mainPersonName));
            $baseSlug = trim($baseSlug, '-');
            if (empty($baseSlug)) {
                $baseSlug = 'event';
            }

            // Check for slug uniqueness
            $slug = $baseSlug;
            $counter = 1;
            while (true) {
                $stmt = $db->prepare("SELECT id FROM events WHERE slug = ?");
                $stmt->execute([$slug]);
                if (!$stmt->fetch()) {
                    break;
                }
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Build event name if not provided
            if (empty($eventName)) {
                $eventType = getEventTypeBySlug($eventTypes[array_search($eventTypeId, array_column($eventTypes, 'id'))]['slug'] ?? '');
                $typeName = $eventType['name'] ?? 'Arrangement';
                $eventName = $mainPersonName . ($secondaryPersonName ? ' & ' . $secondaryPersonName : '') . 's ' . strtolower($typeName);
            }

            // Insert event
            $stmt = $db->prepare("
                INSERT INTO events (
                    account_id, event_type_id, slug, status, name,
                    main_person_name, secondary_person_name,
                    event_date, event_time, location, address,
                    theme, welcome_text, is_legacy
                ) VALUES (?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE)
            ");
            $stmt->execute([
                $accountId,
                $eventTypeId ?: null,
                $slug,
                $eventName,
                $mainPersonName,
                $secondaryPersonName ?: null,
                $eventDate,
                $eventTime ?: null,
                $location ?: null,
                $address ?: null,
                $theme,
                $welcomeText ?: null
            ]);
            $eventId = $db->lastInsertId();

            // Create event owner relationship
            $stmt = $db->prepare("
                INSERT INTO event_owners (account_id, event_id, role, accepted_at)
                VALUES (?, ?, 'owner', NOW())
            ");
            $stmt->execute([$accountId, $eventId]);

            $db->commit();

            setFlash('success', 'Dit arrangement er oprettet! Nu kan du tilføje gæster og tilpasse indholdet.');
            redirect('/app/events/manage.php?id=' . $eventId);

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Event creation failed: " . $e->getMessage());
            $error = 'Der opstod en fejl. Prøv igen.';
        }
    }
}

$error = $error ?? '';
?>

<div class="wizard-container">
    <div class="wizard-header">
        <h1>Opret dit arrangement</h1>
        <p>Følg trinene for at oprette og tilpasse dit arrangement</p>
    </div>

    <!-- Progress Steps -->
    <div class="wizard-progress">
        <div class="progress-step active" data-step="1">
            <div class="step-number">1</div>
            <span class="step-label">Type</span>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="2">
            <div class="step-number">2</div>
            <span class="step-label">Person</span>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="3">
            <div class="step-number">3</div>
            <span class="step-label">Detaljer</span>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="4">
            <div class="step-number">4</div>
            <span class="step-label">Tema</span>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="5">
            <div class="step-number">5</div>
            <span class="step-label">Færdig</span>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flash-message error">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" id="wizardForm">
        <?= accountCsrfField() ?>
        <input type="hidden" name="action" value="create_event">

        <!-- Step 1: Event Type -->
        <div class="wizard-step active" data-step="1">
            <h2>Hvilken type arrangement?</h2>
            <p class="step-description">Vælg den type der bedst beskriver dit arrangement</p>

            <div class="event-types-grid">
                <?php foreach ($eventTypes as $type): ?>
                <label class="event-type-card">
                    <input type="radio" name="event_type_id" value="<?= $type['id'] ?>" required>
                    <div class="event-type-content">
                        <div class="event-type-icon">
                            <?php
                            $icons = [
                                'cross' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v18m-9-9h18"></path></svg>',
                                'rings' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>',
                                'cake' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0A1.75 1.75 0 013 15.546V19a2 2 0 002 2h14a2 2 0 002-2v-3.454zM3 10v4.545M21 10v4.545M8 3v2m4-2v2m4-2v2"></path></svg>',
                                'baby' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                                'star' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>',
                                'briefcase' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>',
                                'calendar' => '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>'
                            ];
                            echo $icons[$type['icon']] ?? $icons['calendar'];
                            ?>
                        </div>
                        <h3><?= htmlspecialchars($type['name']) ?></h3>
                        <p><?= htmlspecialchars($type['description']) ?></p>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="wizard-actions">
                <a href="/app/dashboard.php" class="btn btn-secondary">Annuller</a>
                <button type="button" class="btn btn-primary next-step">
                    Næste
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Step 2: Person Details -->
        <div class="wizard-step" data-step="2">
            <h2>Hvem er arrangementet for?</h2>
            <p class="step-description">Indtast hovedpersonens navn</p>

            <div class="form-section">
                <div class="form-group">
                    <label class="form-label" for="main_person_name">Hovedpersonens navn *</label>
                    <input
                        type="text"
                        id="main_person_name"
                        name="main_person_name"
                        class="form-input"
                        placeholder="F.eks. Sofie Nielsen"
                        required
                    >
                </div>

                <div class="form-group secondary-person-group" style="display: none;">
                    <label class="form-label" for="secondary_person_name">Partners navn</label>
                    <input
                        type="text"
                        id="secondary_person_name"
                        name="secondary_person_name"
                        class="form-input"
                        placeholder="F.eks. Peter Hansen"
                    >
                    <p class="form-hint">Ved bryllupper og lignende med to hovedpersoner</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="event_name">Arrangementsnavn (valgfrit)</label>
                    <input
                        type="text"
                        id="event_name"
                        name="event_name"
                        class="form-input"
                        placeholder="Genereres automatisk hvis tomt"
                    >
                    <p class="form-hint">F.eks. "Sofies konfirmation" eller "Anne & Peters bryllup"</p>
                </div>
            </div>

            <div class="wizard-actions">
                <button type="button" class="btn btn-secondary prev-step">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Tilbage
                </button>
                <button type="button" class="btn btn-primary next-step">
                    Næste
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Step 3: Event Details -->
        <div class="wizard-step" data-step="3">
            <h2>Hvornår og hvor?</h2>
            <p class="step-description">Angiv dato, tid og sted for arrangementet</p>

            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="event_date">Dato *</label>
                        <input
                            type="date"
                            id="event_date"
                            name="event_date"
                            class="form-input"
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="event_time">Tidspunkt</label>
                        <input
                            type="time"
                            id="event_time"
                            name="event_time"
                            class="form-input"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="location">Sted</label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        class="form-input"
                        placeholder="F.eks. Skovshoved Kirke"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">Adresse</label>
                    <input
                        type="text"
                        id="address"
                        name="address"
                        class="form-input"
                        placeholder="F.eks. Skovshoved Strandvej 15, 2920 Charlottenlund"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="welcome_text">Velkomsttekst (valgfrit)</label>
                    <textarea
                        id="welcome_text"
                        name="welcome_text"
                        class="form-input"
                        rows="3"
                        placeholder="En personlig besked til dine gæster..."
                    ></textarea>
                </div>
            </div>

            <div class="wizard-actions">
                <button type="button" class="btn btn-secondary prev-step">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Tilbage
                </button>
                <button type="button" class="btn btn-primary next-step">
                    Næste
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Step 4: Theme -->
        <div class="wizard-step" data-step="4">
            <h2>Vælg et tema</h2>
            <p class="step-description">Vælg farvetemaet for din invitation og gæstesider</p>

            <div class="themes-grid">
                <label class="theme-card">
                    <input type="radio" name="theme" value="elegant" checked>
                    <div class="theme-preview elegant">
                        <div class="theme-colors">
                            <span style="background: #667eea"></span>
                            <span style="background: #764ba2"></span>
                            <span style="background: #f8fafc"></span>
                        </div>
                        <h4>Elegant</h4>
                        <p>Klassisk og tidløst</p>
                    </div>
                </label>

                <label class="theme-card">
                    <input type="radio" name="theme" value="romantic">
                    <div class="theme-preview romantic">
                        <div class="theme-colors">
                            <span style="background: #D4A5A5"></span>
                            <span style="background: #FFF9F7"></span>
                            <span style="background: #2A2222"></span>
                        </div>
                        <h4>Romantisk</h4>
                        <p>Blød og indbydende</p>
                    </div>
                </label>

                <label class="theme-card">
                    <input type="radio" name="theme" value="modern">
                    <div class="theme-preview modern">
                        <div class="theme-colors">
                            <span style="background: #0ea5e9"></span>
                            <span style="background: #f0f9ff"></span>
                            <span style="background: #0c4a6e"></span>
                        </div>
                        <h4>Moderne</h4>
                        <p>Frisk og nutidig</p>
                    </div>
                </label>

                <label class="theme-card">
                    <input type="radio" name="theme" value="nature">
                    <div class="theme-preview nature">
                        <div class="theme-colors">
                            <span style="background: #22c55e"></span>
                            <span style="background: #f0fdf4"></span>
                            <span style="background: #166534"></span>
                        </div>
                        <h4>Natur</h4>
                        <p>Frisk og naturlig</p>
                    </div>
                </label>

                <label class="theme-card">
                    <input type="radio" name="theme" value="golden">
                    <div class="theme-preview golden">
                        <div class="theme-colors">
                            <span style="background: #f59e0b"></span>
                            <span style="background: #fffbeb"></span>
                            <span style="background: #78350f"></span>
                        </div>
                        <h4>Guld</h4>
                        <p>Luksuriøst og festligt</p>
                    </div>
                </label>

                <label class="theme-card">
                    <input type="radio" name="theme" value="minimal">
                    <div class="theme-preview minimal">
                        <div class="theme-colors">
                            <span style="background: #1f2937"></span>
                            <span style="background: #ffffff"></span>
                            <span style="background: #6b7280"></span>
                        </div>
                        <h4>Minimalistisk</h4>
                        <p>Rent og simpelt</p>
                    </div>
                </label>
            </div>

            <div class="wizard-actions">
                <button type="button" class="btn btn-secondary prev-step">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Tilbage
                </button>
                <button type="button" class="btn btn-primary next-step">
                    Næste
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Step 5: Summary & Create -->
        <div class="wizard-step" data-step="5">
            <h2>Opsummering</h2>
            <p class="step-description">Gennemgå dine valg og opret arrangementet</p>

            <div class="summary-card">
                <div class="summary-section">
                    <h4>Arrangementtype</h4>
                    <p id="summary-type">-</p>
                </div>
                <div class="summary-section">
                    <h4>Hovedperson</h4>
                    <p id="summary-person">-</p>
                </div>
                <div class="summary-section">
                    <h4>Dato & Sted</h4>
                    <p id="summary-datetime">-</p>
                    <p id="summary-location">-</p>
                </div>
                <div class="summary-section">
                    <h4>Tema</h4>
                    <p id="summary-theme">-</p>
                </div>
            </div>

            <div class="info-box">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <strong>Hvad sker der nu?</strong>
                    <p>Efter oprettelse kan du tilføje gæster, opsætte ønskeliste, menu, program og meget mere.</p>
                </div>
            </div>

            <div class="wizard-actions">
                <button type="button" class="btn btn-secondary prev-step">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Tilbage
                </button>
                <button type="submit" class="btn btn-primary btn-create">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Opret arrangement
                </button>
            </div>
        </div>
    </form>
</div>

<style>
    .wizard-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .wizard-header {
        text-align: center;
        margin-bottom: 40px;
    }

    .wizard-header h1 {
        font-size: 32px;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 8px;
    }

    .wizard-header p {
        color: var(--gray-500);
        font-size: 16px;
    }

    .wizard-progress {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 48px;
        padding: 0 20px;
    }

    .progress-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .step-number {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--gray-200);
        color: var(--gray-500);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
    }

    .progress-step.active .step-number,
    .progress-step.completed .step-number {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
    }

    .progress-step.completed .step-number::after {
        content: '✓';
    }

    .step-label {
        font-size: 12px;
        color: var(--gray-500);
        font-weight: 500;
    }

    .progress-step.active .step-label {
        color: var(--primary);
    }

    .progress-line {
        width: 60px;
        height: 2px;
        background: var(--gray-200);
        margin: 0 8px;
        margin-bottom: 24px;
    }

    .wizard-step {
        display: none;
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid var(--gray-200);
    }

    .wizard-step.active {
        display: block;
    }

    .wizard-step h2 {
        font-size: 24px;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 8px;
    }

    .step-description {
        color: var(--gray-500);
        margin-bottom: 32px;
    }

    .event-types-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }

    .event-type-card {
        cursor: pointer;
    }

    .event-type-card input {
        display: none;
    }

    .event-type-content {
        padding: 24px;
        border: 2px solid var(--gray-200);
        border-radius: 12px;
        text-align: center;
        transition: all 0.2s;
    }

    .event-type-card:hover .event-type-content {
        border-color: var(--gray-300);
    }

    .event-type-card input:checked + .event-type-content {
        border-color: var(--primary);
        background: rgba(102, 126, 234, 0.05);
    }

    .event-type-icon {
        width: 48px;
        height: 48px;
        margin: 0 auto 12px;
        color: var(--gray-400);
    }

    .event-type-card input:checked + .event-type-content .event-type-icon {
        color: var(--primary);
    }

    .event-type-icon svg {
        width: 100%;
        height: 100%;
    }

    .event-type-content h3 {
        font-size: 16px;
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 4px;
    }

    .event-type-content p {
        font-size: 13px;
        color: var(--gray-500);
    }

    .form-section {
        margin-bottom: 32px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 6px;
    }

    .form-input {
        width: 100%;
        padding: 12px 16px;
        font-size: 15px;
        border: 2px solid var(--gray-200);
        border-radius: 10px;
        transition: all 0.2s;
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-hint {
        font-size: 12px;
        color: var(--gray-500);
        margin-top: 4px;
    }

    .themes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }

    .theme-card {
        cursor: pointer;
    }

    .theme-card input {
        display: none;
    }

    .theme-preview {
        padding: 20px;
        border: 2px solid var(--gray-200);
        border-radius: 12px;
        text-align: center;
        transition: all 0.2s;
    }

    .theme-card:hover .theme-preview {
        border-color: var(--gray-300);
    }

    .theme-card input:checked + .theme-preview {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .theme-colors {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-bottom: 12px;
    }

    .theme-colors span {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .theme-preview h4 {
        font-size: 15px;
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 2px;
    }

    .theme-preview p {
        font-size: 12px;
        color: var(--gray-500);
    }

    .summary-card {
        background: var(--gray-50);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .summary-section {
        padding: 16px 0;
        border-bottom: 1px solid var(--gray-200);
    }

    .summary-section:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .summary-section:first-child {
        padding-top: 0;
    }

    .summary-section h4 {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--gray-500);
        margin-bottom: 4px;
    }

    .summary-section p {
        font-size: 15px;
        color: var(--gray-900);
    }

    .info-box {
        display: flex;
        gap: 16px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 32px;
    }

    .info-box svg {
        width: 24px;
        height: 24px;
        color: #3b82f6;
        flex-shrink: 0;
    }

    .info-box strong {
        display: block;
        color: #1e40af;
        margin-bottom: 4px;
    }

    .info-box p {
        font-size: 14px;
        color: #1e40af;
        margin: 0;
    }

    .wizard-actions {
        display: flex;
        justify-content: space-between;
        padding-top: 24px;
        border-top: 1px solid var(--gray-100);
    }

    .btn-create {
        min-width: 180px;
    }

    @media (max-width: 640px) {
        .wizard-step {
            padding: 24px;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .event-types-grid,
        .themes-grid {
            grid-template-columns: 1fr 1fr;
        }

        .wizard-progress {
            flex-wrap: wrap;
            gap: 8px;
        }

        .progress-line {
            display: none;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('wizardForm');
    const steps = document.querySelectorAll('.wizard-step');
    const progressSteps = document.querySelectorAll('.progress-step');
    let currentStep = 1;

    // Event type data for secondary person display
    const eventTypesWithSecondary = <?= json_encode(array_filter($eventTypes, fn($t) => $t['has_secondary_person'])) ?>;
    const eventTypeIds = eventTypesWithSecondary.map(t => t.id);

    // Navigation
    document.querySelectorAll('.next-step').forEach(btn => {
        btn.addEventListener('click', () => {
            if (validateStep(currentStep)) {
                goToStep(currentStep + 1);
            }
        });
    });

    document.querySelectorAll('.prev-step').forEach(btn => {
        btn.addEventListener('click', () => {
            goToStep(currentStep - 1);
        });
    });

    function goToStep(step) {
        if (step < 1 || step > steps.length) return;

        // Update step visibility
        steps.forEach(s => s.classList.remove('active'));
        steps[step - 1].classList.add('active');

        // Update progress
        progressSteps.forEach((p, i) => {
            p.classList.remove('active', 'completed');
            if (i + 1 < step) {
                p.classList.add('completed');
            } else if (i + 1 === step) {
                p.classList.add('active');
            }
        });

        currentStep = step;

        // Update summary on last step
        if (step === 5) {
            updateSummary();
        }

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(step) {
        if (step === 1) {
            const selected = form.querySelector('input[name="event_type_id"]:checked');
            if (!selected) {
                alert('Vælg venligst en arrangementtype.');
                return false;
            }
        } else if (step === 2) {
            const name = form.querySelector('#main_person_name').value.trim();
            if (!name) {
                alert('Indtast hovedpersonens navn.');
                return false;
            }
        } else if (step === 3) {
            const date = form.querySelector('#event_date').value;
            if (!date) {
                alert('Vælg en dato for arrangementet.');
                return false;
            }
        }
        return true;
    }

    function updateSummary() {
        // Event type
        const typeInput = form.querySelector('input[name="event_type_id"]:checked');
        if (typeInput) {
            const typeCard = typeInput.closest('.event-type-card');
            document.getElementById('summary-type').textContent =
                typeCard.querySelector('h3').textContent;
        }

        // Person
        const mainPerson = form.querySelector('#main_person_name').value || '-';
        const secondaryPerson = form.querySelector('#secondary_person_name').value;
        document.getElementById('summary-person').textContent =
            secondaryPerson ? `${mainPerson} & ${secondaryPerson}` : mainPerson;

        // Date/time
        const date = form.querySelector('#event_date').value;
        const time = form.querySelector('#event_time').value;
        let datetime = '-';
        if (date) {
            const dateObj = new Date(date);
            datetime = dateObj.toLocaleDateString('da-DK', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            if (time) {
                datetime += ' kl. ' + time;
            }
        }
        document.getElementById('summary-datetime').textContent = datetime;

        // Location
        const location = form.querySelector('#location').value || '';
        const address = form.querySelector('#address').value || '';
        document.getElementById('summary-location').textContent =
            location || address ? `${location}${location && address ? ', ' : ''}${address}` : 'Ikke angivet';

        // Theme
        const themeInput = form.querySelector('input[name="theme"]:checked');
        if (themeInput) {
            const themeCard = themeInput.closest('.theme-card');
            document.getElementById('summary-theme').textContent =
                themeCard.querySelector('h4').textContent;
        }
    }

    // Show/hide secondary person field based on event type
    document.querySelectorAll('input[name="event_type_id"]').forEach(input => {
        input.addEventListener('change', function() {
            const secondaryGroup = document.querySelector('.secondary-person-group');
            if (eventTypeIds.includes(parseInt(this.value))) {
                secondaryGroup.style.display = 'block';
            } else {
                secondaryGroup.style.display = 'none';
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/app-footer.php'; ?>
