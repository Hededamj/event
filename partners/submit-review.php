<?php
/**
 * Handle Review Submission
 */

require_once __DIR__ . '/../config/saas.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/partner-auth.php';
require_once __DIR__ . '/../includes/partner-reviews.php';

$db = getDB();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_PATH . '/partners/');
}

// Verify CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Sikkerhedsfejl. Prøv igen.');
    redirect($_SERVER['HTTP_REFERER'] ?? BASE_PATH . '/partners/');
}

$partnerId = (int)($_POST['partner_id'] ?? 0);

// Validate partner exists
$stmt = $db->prepare("SELECT id, company_name FROM partners WHERE id = ? AND status = 'approved'");
$stmt->execute([$partnerId]);
$partner = $stmt->fetch();

if (!$partner) {
    setFlash('error', 'Partner ikke fundet.');
    redirect(BASE_PATH . '/partners/');
}

// Validate required fields
$rating = (int)($_POST['rating'] ?? 0);
$reviewerName = trim($_POST['reviewer_name'] ?? '');
$reviewerEmail = trim($_POST['reviewer_email'] ?? '');
$reviewText = trim($_POST['review_text'] ?? '');

if ($rating < 1 || $rating > 5) {
    setFlash('error', 'Vælg venligst en bedømmelse (1-5 stjerner).');
    redirect(BASE_PATH . '/partners/profile.php?id=' . $partnerId);
}

if (empty($reviewerName) || empty($reviewerEmail) || empty($reviewText)) {
    setFlash('error', 'Udfyld venligst alle påkrævede felter.');
    redirect(BASE_PATH . '/partners/profile.php?id=' . $partnerId);
}

if (!filter_var($reviewerEmail, FILTER_VALIDATE_EMAIL)) {
    setFlash('error', 'Ugyldig email-adresse.');
    redirect(BASE_PATH . '/partners/profile.php?id=' . $partnerId);
}

// Check for spam (simple rate limit by email)
$stmt = $db->prepare("
    SELECT COUNT(*) FROM partner_reviews
    WHERE reviewer_email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute([$reviewerEmail]);
if ($stmt->fetchColumn() >= 3) {
    setFlash('error', 'Du har allerede skrevet flere anmeldelser i dag. Prøv igen i morgen.');
    redirect(BASE_PATH . '/partners/profile.php?id=' . $partnerId);
}

// Submit the review
$reviewData = [
    'partner_id' => $partnerId,
    'reviewer_name' => $reviewerName,
    'reviewer_email' => $reviewerEmail,
    'rating' => $rating,
    'title' => trim($_POST['title'] ?? ''),
    'review_text' => $reviewText,
    'event_type' => trim($_POST['event_type'] ?? ''),
    'event_date' => !empty($_POST['event_date']) ? $_POST['event_date'] : null,
    'account_id' => isPartner() ? getCurrentPartnerAccountId() : null
];

$reviewId = submitReview($db, $reviewData);

if ($reviewId) {
    setFlash('success', 'Tak for din anmeldelse! Den vil blive gennemgået inden publicering.');
} else {
    setFlash('error', 'Kunne ikke gemme anmeldelsen. Prøv igen.');
}

redirect(BASE_PATH . '/partners/profile.php?id=' . $partnerId . '#reviews');
