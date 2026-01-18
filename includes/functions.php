<?php
/**
 * Helper Functions
 */

/**
 * Escape string for HTML output
 */
function escape(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Redirect to URL and exit
 */
function redirect(string $url): never {
    // Clean any output buffer to allow redirect after output
    while (ob_get_level()) {
        ob_end_clean();
    }
    header("Location: $url");
    exit;
}

/**
 * Generate a unique 6-digit guest code
 */
function generateGuestCode(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Format date in Danish
 */
function formatDate(string $date, bool $includeDay = false): string {
    $months = [
        1 => 'januar', 2 => 'februar', 3 => 'marts', 4 => 'april',
        5 => 'maj', 6 => 'juni', 7 => 'juli', 8 => 'august',
        9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december'
    ];
    $days = ['søndag', 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lørdag'];

    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);

    if ($includeDay) {
        $dayName = $days[date('w', $timestamp)];
        return ucfirst($dayName) . ' d. ' . $day . '. ' . $month . ' ' . $year;
    }

    return $day . '. ' . $month . ' ' . $year;
}

/**
 * Format short date (d/m)
 */
function formatShortDate(string $date): string {
    return date('d/m', strtotime($date));
}

/**
 * Format currency in Danish kroner
 */
function formatCurrency(float $amount): string {
    return number_format($amount, 0, ',', '.') . ' kr';
}

/**
 * Send JSON response
 */
function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Check if request is AJAX
 */
function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get base URL
 */
function baseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'];
}

/**
 * Sanitize filename
 */
function sanitizeFilename(string $filename): string {
    // Remove path info
    $filename = basename($filename);
    // Replace non-alphanumeric with underscores
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    // Remove multiple underscores
    $filename = preg_replace('/_+/', '_', $filename);
    return $filename;
}

/**
 * Generate unique filename for uploads
 */
function generateUploadFilename(string $originalName): string {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $uniqueId = bin2hex(random_bytes(8));
    return date('Y-m-d') . '_' . $uniqueId . '.' . strtolower($ext);
}

/**
 * Validate image file
 */
function validateImageUpload(array $file): array {
    $errors = [];

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 10 * 1024 * 1024; // 10MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload fejlede. Prøv igen.';
        return $errors;
    }

    if (!in_array($file['type'], $allowedTypes)) {
        $errors[] = 'Kun JPEG, PNG, GIF og WebP billeder er tilladt.';
    }

    if ($file['size'] > $maxSize) {
        $errors[] = 'Filen er for stor. Maksimum er 10MB.';
    }

    return $errors;
}

/**
 * Flash message system
 */
function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
