<?php
/**
 * Partner Dashboard - Verification & Documents
 */

require_once __DIR__ . '/../../includes/partner-auth.php';
require_once __DIR__ . '/../../includes/partner-verification.php';
requirePartner();

$db = getDB();
$partner = getCurrentPartner();
$partnerId = getCurrentPartnerId();

// Ensure verification tables exist
ensureVerificationTables($db);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');

    $action = $_POST['action'] ?? '';

    if ($action === 'upload_document') {
        $documentType = $_POST['document_type'] ?? '';
        $description = trim($_POST['description'] ?? '');

        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $docId = uploadVerificationDocument($db, $partnerId, $_FILES['document'], $documentType, $description);
            if ($docId) {
                setFlash('success', 'Dokument uploadet! Det vil blive gennemgået.');
            } else {
                setFlash('error', 'Kunne ikke uploade dokument. Kontroller filtype og størrelse (max 10MB).');
            }
        } else {
            setFlash('error', 'Vælg venligst en fil.');
        }
        redirect($_SERVER['REQUEST_URI']);
    }

    if ($action === 'update_cvr') {
        $cvr = trim($_POST['cvr_number'] ?? '');
        if (updatePartnerCvr($db, $partnerId, $cvr)) {
            setFlash('success', 'CVR-nummer opdateret.');
        } else {
            setFlash('error', 'Ugyldigt CVR-nummer. Det skal være 8 cifre.');
        }
        redirect($_SERVER['REQUEST_URI']);
    }

    if ($action === 'delete_document') {
        $docId = (int)($_POST['document_id'] ?? 0);
        if (deleteVerificationDocument($db, $docId, $partnerId)) {
            setFlash('success', 'Dokument slettet.');
        } else {
            setFlash('error', 'Kunne ikke slette dokument.');
        }
        redirect($_SERVER['REQUEST_URI']);
    }
}

// Get verification status and documents
$verificationStatus = getPartnerVerificationStatus($db, $partnerId);
$documents = getPartnerDocuments($db, $partnerId);
$documentTypes = getDocumentTypeLabels();

// Get flash
$flash = getFlash();

// Platform name
try {
    $stmt = $db->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_name'");
    $platformName = $stmt->fetchColumn() ?: 'EventPlatform';
} catch (Exception $e) {
    $platformName = 'EventPlatform';
}

// Status labels
$docStatusLabels = [
    'pending' => ['text' => 'Under gennemgang', 'class' => 'badge-warning'],
    'approved' => ['text' => 'Godkendt', 'class' => 'badge-success'],
    'rejected' => ['text' => 'Afvist', 'class' => 'badge-error']
];
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikation - Partner Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --color-bg: #f8fafc;
            --color-bg-subtle: #f1f5f9;
            --color-surface: #ffffff;
            --color-primary: #7c3aed;
            --color-primary-deep: #6d28d9;
            --color-primary-soft: #ede9fe;
            --color-text: #1e293b;
            --color-text-soft: #475569;
            --color-text-muted: #94a3b8;
            --color-border: #e2e8f0;
            --color-success: #22c55e;
            --color-warning: #f59e0b;
            --color-error: #ef4444;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --radius-md: 8px;
            --radius-lg: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--color-bg); color: var(--color-text); line-height: 1.6; }

        .dashboard-layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px; background: var(--color-surface); border-right: 1px solid var(--color-border);
            position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column;
        }
        .sidebar-brand { padding: 1.25rem; border-bottom: 1px solid var(--color-border); }
        .sidebar-brand-name { font-size: 1rem; font-weight: 600; color: var(--color-primary); }
        .sidebar-brand-label { font-size: 0.75rem; color: var(--color-text-muted); }
        .sidebar-nav { flex: 1; padding: 1rem; }
        .nav-menu { list-style: none; }
        .nav-link {
            display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;
            color: var(--color-text-soft); text-decoration: none; border-radius: var(--radius-md);
            font-size: 0.9rem; margin-bottom: 0.25rem;
        }
        .nav-link:hover { background: var(--color-bg-subtle); }
        .nav-link.active { background: var(--color-primary-soft); color: var(--color-primary-deep); font-weight: 500; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--color-border); }

        .main { flex: 1; margin-left: 250px; padding: 2rem; }

        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 1.5rem; font-weight: 600; }

        .card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
        .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; }

        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;
            border: none; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 500;
            cursor: pointer; text-decoration: none;
        }
        .btn-primary { background: var(--color-primary); color: white; }
        .btn-secondary { background: var(--color-bg-subtle); color: var(--color-text); border: 1px solid var(--color-border); }
        .btn-danger { background: var(--color-error); color: white; }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; }

        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-info { background: var(--color-primary-soft); color: var(--color-primary); }

        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.375rem; }
        .form-input {
            width: 100%; padding: 0.625rem 0.875rem; border: 1px solid var(--color-border);
            border-radius: var(--radius-md); font-size: 0.875rem;
        }
        .form-input:focus { outline: none; border-color: var(--color-primary); }

        .verification-status {
            display: flex; align-items: center; gap: 1rem; padding: 1.5rem;
            background: var(--color-bg-subtle); border-radius: var(--radius-lg); margin-bottom: 1.5rem;
        }
        .verification-status.verified { background: #dcfce7; }
        .verification-icon { font-size: 2.5rem; }
        .verification-info h3 { font-size: 1.1rem; margin-bottom: 0.25rem; }
        .verification-info p { color: var(--color-text-soft); font-size: 0.9rem; }

        .document-list { display: flex; flex-direction: column; gap: 0.75rem; }
        .document-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md);
            gap: 1rem;
        }
        .document-item__info { flex: 1; min-width: 0; }
        .document-item__name { font-weight: 500; word-break: break-word; }
        .document-item__meta { font-size: 0.8rem; color: var(--color-text-muted); }
        .document-item__actions { display: flex; gap: 0.5rem; flex-shrink: 0; }

        .upload-box {
            border: 2px dashed var(--color-border); border-radius: var(--radius-lg);
            padding: 2rem; text-align: center; cursor: pointer; transition: all 0.2s;
        }
        .upload-box:hover { border-color: var(--color-primary); background: var(--color-primary-soft); }
        .upload-box input[type="file"] { display: none; }

        .empty-state { text-align: center; padding: 2rem; color: var(--color-text-muted); }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-name"><?= escape($partner['company_name']) ?></div>
                <div class="sidebar-brand-label">Partner Dashboard</div>
            </div>
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/" class="nav-link">&#128200; Dashboard</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/profile.php" class="nav-link">&#128736; Profil</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/inquiries.php" class="nav-link">&#128172; Forespørgsler</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/bookings.php" class="nav-link">&#128197; Bookinger</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/commission.php" class="nav-link">&#128176; Økonomi</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/verification.php" class="nav-link active">&#9989; Verifikation</a></li>
                    <li><a href="<?= BASE_PATH ?>/partners/dashboard/gallery.php" class="nav-link">&#128247; Galleri</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="<?= BASE_PATH ?>/partners/profile.php?id=<?= $partnerId ?>" class="nav-link" target="_blank">&#128065; Se offentlig profil</a>
                <a href="<?= BASE_PATH ?>/partners/dashboard/logout.php" class="nav-link">&#128682; Log ud</a>
            </div>
        </aside>

        <!-- Main -->
        <main class="main">
            <div class="page-header">
                <h1 class="page-title">Verifikation</h1>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                    <?= escape($flash['message']) ?>
                </div>
            <?php endif; ?>

            <!-- Verification Status -->
            <div class="verification-status <?= $verificationStatus['is_verified'] ? 'verified' : '' ?>">
                <div class="verification-icon">
                    <?php if ($verificationStatus['verification_level'] === 'full'): ?>
                        &#127775;
                    <?php elseif ($verificationStatus['verification_level'] === 'basic'): ?>
                        &#9989;
                    <?php else: ?>
                        &#128679;
                    <?php endif; ?>
                </div>
                <div class="verification-info">
                    <?php if ($verificationStatus['verification_level'] === 'full'): ?>
                        <h3>Fuldt verificeret</h3>
                        <p>Din virksomhed er fuldt verificeret og vises med et "Verified" badge.</p>
                    <?php elseif ($verificationStatus['verification_level'] === 'basic'): ?>
                        <h3>Basis verifikation</h3>
                        <p>Upload forsikringsdokumentation for fuld verifikation.</p>
                    <?php else: ?>
                        <h3>Ikke verificeret</h3>
                        <p>Upload CVR-bevis for at starte verifikationsprocessen.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CVR Number -->
            <div class="card">
                <h2 class="card-title">CVR-nummer</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="update_cvr">

                    <div class="form-group">
                        <label class="form-label">CVR-nummer (8 cifre)</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="cvr_number" class="form-input" style="max-width: 200px;"
                                   value="<?= escape($verificationStatus['cvr_number'] ?? '') ?>"
                                   placeholder="12345678" maxlength="8" pattern="[0-9]{8}">
                            <button type="submit" class="btn btn-secondary">Gem</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Upload Document -->
            <div class="card">
                <h2 class="card-title">Upload dokument</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="upload_document">

                    <div class="form-group">
                        <label class="form-label">Dokumenttype *</label>
                        <select name="document_type" class="form-input" required>
                            <option value="">Vælg type...</option>
                            <?php foreach ($documentTypes as $type => $label): ?>
                                <option value="<?= $type ?>"><?= escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fil (PDF, JPG, PNG - max 10MB) *</label>
                        <input type="file" name="document" class="form-input" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Beskrivelse (valgfrit)</label>
                        <input type="text" name="description" class="form-input" placeholder="F.eks. 'Udløber dec. 2025'">
                    </div>

                    <button type="submit" class="btn btn-primary">Upload dokument</button>
                </form>
            </div>

            <!-- Document List -->
            <div class="card">
                <h2 class="card-title">Mine dokumenter</h2>

                <?php if (empty($documents)): ?>
                    <div class="empty-state">
                        <p>Ingen dokumenter uploadet endnu</p>
                    </div>
                <?php else: ?>
                    <div class="document-list">
                        <?php foreach ($documents as $doc): ?>
                            <div class="document-item">
                                <div class="document-item__info">
                                    <div class="document-item__name">
                                        <?= escape($documentTypes[$doc['document_type']] ?? $doc['document_type']) ?>
                                    </div>
                                    <div class="document-item__meta">
                                        <?= escape($doc['document_name']) ?> &bull;
                                        <?= formatFileSize($doc['file_size']) ?> &bull;
                                        <?= date('d/m/Y', strtotime($doc['uploaded_at'])) ?>
                                    </div>
                                    <?php if ($doc['review_note']): ?>
                                        <div class="document-item__meta" style="color: var(--color-error);">
                                            Note: <?= escape($doc['review_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?= $docStatusLabels[$doc['status']]['class'] ?>">
                                    <?= $docStatusLabels[$doc['status']]['text'] ?>
                                </span>
                                <div class="document-item__actions">
                                    <a href="<?= BASE_PATH ?>/<?= escape($doc['file_path']) ?>" target="_blank" class="btn btn-secondary btn-sm">
                                        Se
                                    </a>
                                    <?php if ($doc['status'] !== 'approved'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Slet dette dokument?')">
                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                            <input type="hidden" name="action" value="delete_document">
                                            <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Slet</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Requirements Info -->
            <div class="card" style="background: var(--color-bg-subtle);">
                <h2 class="card-title">Verifikationskrav</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div>
                        <h4 style="margin-bottom: 0.5rem;">&#9989; Basis verifikation</h4>
                        <ul style="margin-left: 1.25rem; color: var(--color-text-soft); font-size: 0.9rem;">
                            <li>CVR-registreringsbevis <strong>(påkrævet)</strong></li>
                        </ul>
                    </div>
                    <div>
                        <h4 style="margin-bottom: 0.5rem;">&#127775; Fuld verifikation</h4>
                        <ul style="margin-left: 1.25rem; color: var(--color-text-soft); font-size: 0.9rem;">
                            <li>CVR-registreringsbevis <strong>(påkrævet)</strong></li>
                            <li>Erhvervsansvarsforsikring <strong>(påkrævet)</strong></li>
                            <li>Relevante tilladelser (valgfrit)</li>
                            <li>Kundereference (valgfrit)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
