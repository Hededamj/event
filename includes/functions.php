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
 * Generate a unique 8-character alphanumeric guest code
 * Uses uppercase letters (excluding I, O, L) and digits (excluding 0, 1)
 * to avoid confusion. This provides 32^8 = ~1 trillion combinations.
 */
function generateGuestCode(): string {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
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
 * Validate image file with security checks
 * Checks: file error, size, magic bytes, actual image data
 */
function validateImageUpload(array $file): array {
    $errors = [];
    $maxSize = 10 * 1024 * 1024; // 10MB

    // Check upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload fejlede. Prøv igen.';
        return $errors;
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = 'Filen er for stor. Maksimum er 10MB.';
        return $errors;
    }

    // Check magic bytes (file signature)
    $allowedMagicBytes = [
        'jpeg' => ["\xFF\xD8\xFF"],                           // JPEG
        'png'  => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],        // PNG
        'gif'  => ["\x47\x49\x46\x38\x37\x61", "\x47\x49\x46\x38\x39\x61"], // GIF87a, GIF89a
        'webp' => ["\x52\x49\x46\x46"]                         // RIFF (WebP header start)
    ];

    $handle = fopen($file['tmp_name'], 'rb');
    if (!$handle) {
        $errors[] = 'Kunne ikke læse filen.';
        return $errors;
    }
    $header = fread($handle, 12);
    fclose($handle);

    $validMagic = false;
    $detectedType = null;
    foreach ($allowedMagicBytes as $type => $signatures) {
        foreach ($signatures as $sig) {
            if (substr($header, 0, strlen($sig)) === $sig) {
                $validMagic = true;
                $detectedType = $type;
                break 2;
            }
        }
    }

    // Special check for WebP (must also have WEBP at offset 8)
    if ($detectedType === 'webp' && substr($header, 8, 4) !== 'WEBP') {
        $validMagic = false;
    }

    if (!$validMagic) {
        $errors[] = 'Kun JPEG, PNG, GIF og WebP billeder er tilladt.';
        return $errors;
    }

    // Verify actual image data using getimagesize
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $errors[] = 'Filen er ikke et gyldigt billede.';
        return $errors;
    }

    // Verify image type matches magic bytes
    $typeMap = [
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp'
    ];

    if (!isset($typeMap[$imageInfo[2]]) || $typeMap[$imageInfo[2]] !== $detectedType) {
        $errors[] = 'Billedformat stemmer ikke overens.';
        return $errors;
    }

    return $errors;
}

/**
 * Process and re-encode uploaded image to strip potentially malicious content
 * Returns the new filename on success, null on failure
 */
function processUploadedImage(string $tmpPath, string $destDir, string $originalName): ?string {
    // Validate image first
    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo) {
        return null;
    }

    // Create image resource based on type
    $sourceImage = null;
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $sourceImage = @imagecreatefromjpeg($tmpPath);
            $extension = 'jpg';
            break;
        case IMAGETYPE_PNG:
            $sourceImage = @imagecreatefrompng($tmpPath);
            $extension = 'png';
            break;
        case IMAGETYPE_GIF:
            $sourceImage = @imagecreatefromgif($tmpPath);
            $extension = 'gif';
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $sourceImage = @imagecreatefromwebp($tmpPath);
                $extension = 'webp';
            }
            break;
    }

    if (!$sourceImage) {
        return null;
    }

    // Generate unique filename
    $uniqueId = bin2hex(random_bytes(8));
    $filename = date('Y-m-d') . '_' . $uniqueId . '.' . $extension;
    $destPath = rtrim($destDir, '/') . '/' . $filename;

    // Create destination directory if needed
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    // Preserve transparency for PNG and GIF
    if ($imageInfo[2] === IMAGETYPE_PNG || $imageInfo[2] === IMAGETYPE_GIF) {
        imagealphablending($sourceImage, false);
        imagesavealpha($sourceImage, true);
    }

    // Save re-encoded image
    $success = false;
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($sourceImage, $destPath, 90);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($sourceImage, $destPath, 6);
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($sourceImage, $destPath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                $success = imagewebp($sourceImage, $destPath, 90);
            }
            break;
    }

    imagedestroy($sourceImage);

    return $success ? $filename : null;
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
