<?php
/**
 * Vendor Validation Service
 * Input validation functions for vendor/subcontractor forms.
 * Each function takes an associative array and returns an array of error strings.
 * An empty array means the input is valid. Error messages are in Danish.
 */

// ============================================================
// Helper
// ============================================================

/**
 * Sanitize a string: trim whitespace and strip HTML tags.
 *
 * @param string $input Raw input string
 * @return string Sanitized string
 */
function sanitizeString(string $input): string {
    return trim(strip_tags($input));
}

// ============================================================
// 1. validateVendorRegistration
// ============================================================

/**
 * Validate vendor registration data.
 *
 * @param array $data Submitted registration fields
 * @return array Error messages (empty = valid)
 */
function validateVendorRegistration(array $data): array {
    $errors = [];

    // Email: required, valid format
    $email = $data['email'] ?? '';
    if (empty(trim($email))) {
        $errors[] = 'Email er påkrævet.';
    } elseif (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Ugyldig email-adresse.';
    }

    // Password: required, 8+ chars
    $password = $data['password'] ?? '';
    if (empty($password)) {
        $errors[] = 'Adgangskode er påkrævet.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Adgangskode skal være mindst 8 tegn.';
    }

    // Company name: required, max 255
    $companyName = $data['company_name'] ?? '';
    if (empty(trim($companyName))) {
        $errors[] = 'Firmanavn er påkrævet.';
    } elseif (mb_strlen(trim($companyName)) > 255) {
        $errors[] = 'Firmanavn må højst være 255 tegn.';
    }

    // Contact name: required, max 255
    $contactName = $data['contact_name'] ?? '';
    if (empty(trim($contactName))) {
        $errors[] = 'Kontaktperson er påkrævet.';
    } elseif (mb_strlen(trim($contactName)) > 255) {
        $errors[] = 'Kontaktperson må højst være 255 tegn.';
    }

    return $errors;
}

// ============================================================
// Onboarding validation
// ============================================================

/**
 * Validate onboarding category selection.
 *
 * @param array $categoryIds Array of selected category IDs
 * @return array Error messages (empty = valid)
 */
function validateOnboardingCategories(array $categoryIds): array {
    $errors = [];
    $filtered = array_filter(array_map('intval', $categoryIds));
    if (empty($filtered)) {
        $errors[] = 'Vælg mindst én kategori.';
    }
    return $errors;
}

/**
 * Validate onboarding description (short_description).
 *
 * @param string $description The short description text
 * @return array Error messages (empty = valid)
 */
function validateOnboardingDescription(string $description): array {
    $errors = [];
    $trimmed = trim($description);
    if (empty($trimmed)) {
        $errors[] = 'Kort beskrivelse er påkrævet.';
    } elseif (mb_strlen($trimmed) < 50) {
        $errors[] = 'Kort beskrivelse skal være mindst 50 tegn.';
    } elseif (mb_strlen($trimmed) > 500) {
        $errors[] = 'Kort beskrivelse må højst være 500 tegn.';
    }
    return $errors;
}

// ============================================================
// 2. validateVendorProfile
// ============================================================

/**
 * Validate vendor profile update data.
 *
 * @param array $data Submitted profile fields
 * @return array Error messages (empty = valid)
 */
function validateVendorProfile(array $data): array {
    $errors = [];

    // Company name: required, max 255
    $companyName = $data['company_name'] ?? '';
    if (empty(trim($companyName))) {
        $errors[] = 'Firmanavn er påkrævet.';
    } elseif (mb_strlen(trim($companyName)) > 255) {
        $errors[] = 'Firmanavn må højst være 255 tegn.';
    }

    // Contact name: required, max 255
    $contactName = $data['contact_name'] ?? '';
    if (empty(trim($contactName))) {
        $errors[] = 'Kontaktperson er påkrævet.';
    } elseif (mb_strlen(trim($contactName)) > 255) {
        $errors[] = 'Kontaktperson må højst være 255 tegn.';
    }

    // Description: optional, max 5000
    if (isset($data['description']) && mb_strlen(trim($data['description'])) > 5000) {
        $errors[] = 'Beskrivelse må højst være 5000 tegn.';
    }

    // Short description: optional, max 500
    if (isset($data['short_description']) && mb_strlen(trim($data['short_description'])) > 500) {
        $errors[] = 'Kort beskrivelse må højst være 500 tegn.';
    }

    // Phone: optional, max 50
    if (isset($data['phone']) && mb_strlen(trim($data['phone'])) > 50) {
        $errors[] = 'Telefonnummer må højst være 50 tegn.';
    }

    // Website: optional, valid URL if provided, max 255
    if (!empty(trim($data['website'] ?? ''))) {
        $website = trim($data['website']);
        if (mb_strlen($website) > 255) {
            $errors[] = 'Webadresse må højst være 255 tegn.';
        } elseif (!filter_var($website, FILTER_VALIDATE_URL)) {
            $errors[] = 'Ugyldig webadresse.';
        }
    }

    // City: optional, max 100
    if (isset($data['city']) && mb_strlen(trim($data['city'])) > 100) {
        $errors[] = 'By må højst være 100 tegn.';
    }

    // Postal code: optional, max 20
    if (isset($data['postal_code']) && mb_strlen(trim($data['postal_code'])) > 20) {
        $errors[] = 'Postnummer må højst være 20 tegn.';
    }

    return $errors;
}

// ============================================================
// 3. validateService
// ============================================================

/**
 * Validate vendor service data.
 *
 * @param array $data Submitted service fields
 * @return array Error messages (empty = valid)
 */
function validateService(array $data): array {
    $errors = [];

    // Title: required, max 255
    $title = $data['title'] ?? '';
    if (empty(trim($title))) {
        $errors[] = 'Titel er påkrævet.';
    } elseif (mb_strlen(trim($title)) > 255) {
        $errors[] = 'Titel må højst være 255 tegn.';
    }

    // Price from: required, positive number
    $priceFrom = $data['price_from'] ?? '';
    if ($priceFrom === '' || $priceFrom === null) {
        $errors[] = 'Pris fra er påkrævet.';
    } elseif (!is_numeric($priceFrom) || (float)$priceFrom <= 0) {
        $errors[] = 'Pris fra skal være et positivt tal.';
    }

    // Price to: optional, positive, >= price_from
    if (isset($data['price_to']) && $data['price_to'] !== '' && $data['price_to'] !== null) {
        if (!is_numeric($data['price_to']) || (float)$data['price_to'] <= 0) {
            $errors[] = 'Pris til skal være et positivt tal.';
        } elseif (is_numeric($priceFrom) && (float)$data['price_to'] < (float)$priceFrom) {
            $errors[] = 'Pris til skal være større end eller lig med pris fra.';
        }
    }

    // Price unit: must be one of the allowed values
    $allowedUnits = ['fixed', 'per_person', 'per_hour'];
    if (isset($data['price_unit']) && $data['price_unit'] !== '' && $data['price_unit'] !== null) {
        if (!in_array($data['price_unit'], $allowedUnits, true)) {
            $errors[] = 'Prisenhed skal være fast pris, pr. person eller pr. time.';
        }
    }

    // Duration hours: optional, positive if set
    if (isset($data['duration_hours']) && $data['duration_hours'] !== '' && $data['duration_hours'] !== null) {
        if (!is_numeric($data['duration_hours']) || (float)$data['duration_hours'] <= 0) {
            $errors[] = 'Varighed skal være et positivt tal.';
        }
    }

    return $errors;
}

// ============================================================
// 4. validateBookingRequest
// ============================================================

/**
 * Validate a booking request from an organizer.
 *
 * @param array $data Submitted booking request fields
 * @return array Error messages (empty = valid)
 */
function validateBookingRequest(array $data): array {
    $errors = [];

    // Event date: required, valid date, must be in the future
    $eventDate = $data['event_date'] ?? '';
    if (empty(trim($eventDate))) {
        $errors[] = 'Eventdato er påkrævet.';
    } else {
        $parsed = date_create($eventDate);
        if (!$parsed) {
            $errors[] = 'Ugyldig datoformat.';
        } else {
            $today = new DateTime('today');
            if ($parsed <= $today) {
                $errors[] = 'Eventdato skal være i fremtiden.';
            }
        }
    }

    // Message: required, max 2000
    $message = $data['message'] ?? '';
    if (empty(trim($message))) {
        $errors[] = 'Besked er påkrævet.';
    } elseif (mb_strlen(trim($message)) > 2000) {
        $errors[] = 'Besked må højst være 2000 tegn.';
    }

    // Guest count: optional, positive integer if set
    if (isset($data['guest_count']) && $data['guest_count'] !== '' && $data['guest_count'] !== null) {
        $guestCount = $data['guest_count'];
        if (!is_numeric($guestCount) || (int)$guestCount != $guestCount || (int)$guestCount <= 0) {
            $errors[] = 'Antal gæster skal være et positivt heltal.';
        }
    }

    return $errors;
}

// ============================================================
// 5. validateReview
// ============================================================

/**
 * Validate review submission data.
 *
 * @param array $data Submitted review fields
 * @return array Error messages (empty = valid)
 */
function validateReview(array $data): array {
    $errors = [];

    // Rating: required, integer 1-5
    $rating = $data['rating'] ?? '';
    if ($rating === '' || $rating === null) {
        $errors[] = 'Bedømmelse er påkrævet.';
    } elseif (!is_numeric($rating) || (int)$rating != $rating || (int)$rating < 1 || (int)$rating > 5) {
        $errors[] = 'Bedømmelse skal være et heltal mellem 1 og 5.';
    }

    // Title: optional, max 255
    if (isset($data['title']) && mb_strlen(trim($data['title'])) > 255) {
        $errors[] = 'Titel må højst være 255 tegn.';
    }

    // Review text: optional, max 5000
    if (isset($data['review_text']) && mb_strlen(trim($data['review_text'])) > 5000) {
        $errors[] = 'Anmeldelsestekst må højst være 5000 tegn.';
    }

    return $errors;
}

// ============================================================
// 6. validateImageUpload (vendor-specific, using finfo)
// ============================================================

/**
 * Validate an uploaded image file for vendor use (portfolio, logo, etc.).
 * Uses finfo_file for content-type detection and verifies extension matches.
 *
 * @param array $file Entry from $_FILES
 * @return array Error messages (empty = valid)
 */
function validateVendorImageUpload(array $file): array {
    $errors = [];
    $maxSize = 5 * 1024 * 1024; // 5MB

    // Allowed MIME types and their valid extensions
    $allowedTypes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif'],
        'image/webp' => ['webp'],
    ];

    // Check upload error
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload fejlede. Prøv igen.';
        return $errors;
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = 'Filen er for stor. Maksimum er 5MB.';
        return $errors;
    }

    // Detect actual content type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        $errors[] = 'Kunne ikke verificere filtypen.';
        return $errors;
    }

    $detectedType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!$detectedType || !array_key_exists($detectedType, $allowedTypes)) {
        $errors[] = 'Kun JPEG, PNG, GIF og WebP billeder er tilladt.';
        return $errors;
    }

    // Verify file extension matches the detected content type
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes[$detectedType], true)) {
        $errors[] = 'Filtypenavn stemmer ikke overens med indholdet.';
        return $errors;
    }

    return $errors;
}
