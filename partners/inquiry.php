<?php
/**
 * Partner Marketplace - Handle Inquiry Submission
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/partner-auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_PATH . '/partners/');
}

$db = getDB();

// Verify CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Ugyldig formular. Prøv igen.');
    redirect($_SERVER['HTTP_REFERER'] ?? BASE_PATH . '/partners/');
}

// Get form data
$partnerId = (int)($_POST['partner_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$eventDate = $_POST['event_date'] ?? null;
$guestCount = (int)($_POST['guest_count'] ?? 0);
$message = trim($_POST['message'] ?? '');

// Validate
$errors = [];

if (!$partnerId) {
    $errors[] = 'Ugyldig partner';
}

if (empty($name)) {
    $errors[] = 'Navn er påkrævet';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Gyldig email er påkrævet';
}

if (empty($message)) {
    $errors[] = 'Besked er påkrævet';
}

// Verify partner exists and is approved
$stmt = $db->prepare("SELECT id, company_name, email FROM partners WHERE id = ? AND status = 'approved'");
$stmt->execute([$partnerId]);
$partner = $stmt->fetch();

if (!$partner) {
    $errors[] = 'Partner ikke fundet';
}

if (!empty($errors)) {
    setFlash('error', implode('. ', $errors));
    redirect(BASE_PATH . '/partners/profile.php?id=' . $partnerId);
}

// Insert inquiry
$stmt = $db->prepare("
    INSERT INTO partner_inquiries (partner_id, name, email, phone, event_date, guest_count, message)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $partnerId,
    $name,
    $email,
    $phone ?: null,
    $eventDate ?: null,
    $guestCount ?: null,
    $message
]);

// Update inquiry count
$stmt = $db->prepare("UPDATE partners SET inquiry_count = inquiry_count + 1 WHERE id = ?");
$stmt->execute([$partnerId]);

// Send email notification to partner (if email sending is configured)
// For now, just show success message

setFlash('success', 'Din forespørgsel er sendt til ' . $partner['company_name'] . '. De vil kontakte dig snarest.');
redirect(BASE_PATH . '/partners/profile.php?id=' . $partnerId);
