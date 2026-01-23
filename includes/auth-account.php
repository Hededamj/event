<?php
/**
 * Account Authentication Functions
 * Handles authentication for platform users (accounts)
 * Separate from event-level auth (auth.php)
 */

// Start session with extended lifetime (30 days)
if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = 60 * 60 * 24 * 30; // 30 days

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.gc_maxlifetime', $sessionLifetime);
    session_start();

    // Refresh session cookie on each request
    if (isset($_COOKIE[session_name()])) {
        setcookie(
            session_name(),
            session_id(),
            time() + $sessionLifetime,
            '/',
            '',
            isset($_SERVER['HTTPS']),
            true
        );
    }
}

/**
 * Check if user is logged in as account owner
 */
function isAccountLoggedIn(): bool {
    return isset($_SESSION['account_id']) && !empty($_SESSION['account_id']);
}

/**
 * Require account login - redirect if not logged in
 */
function requireAccountLogin(): void {
    if (!isAccountLoggedIn()) {
        $returnUrl = urlencode($_SERVER['REQUEST_URI'] ?? '/app/dashboard.php');
        redirect('/app/auth/login.php?return=' . $returnUrl);
    }
}

/**
 * Get current account ID
 */
function getCurrentAccountId(): ?int {
    return $_SESSION['account_id'] ?? null;
}

/**
 * Get current account data from session
 */
function getCurrentAccount(): ?array {
    if (!isAccountLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['account_id'],
        'email' => $_SESSION['account_email'] ?? null,
        'name' => $_SESSION['account_name'] ?? null
    ];
}

/**
 * Login as account
 */
function accountLogin(int $accountId, string $email, string $name): void {
    session_regenerate_id(true);

    $_SESSION['account_id'] = $accountId;
    $_SESSION['account_email'] = $email;
    $_SESSION['account_name'] = $name;
    $_SESSION['account_login_time'] = time();

    // Update last login in database
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE accounts SET last_login_at = NOW(), login_count = login_count + 1 WHERE id = ?");
        $stmt->execute([$accountId]);
    } catch (Exception $e) {
        // Log error but don't fail login
        error_log("Failed to update last login: " . $e->getMessage());
    }
}

/**
 * Logout account
 */
function accountLogout(): void {
    // Clear account session data
    unset($_SESSION['account_id']);
    unset($_SESSION['account_email']);
    unset($_SESSION['account_name']);
    unset($_SESSION['account_login_time']);

    // Regenerate session ID
    session_regenerate_id(true);
}

/**
 * Verify account password
 */
function verifyAccountPassword(string $email, string $password): ?array {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, email, name, password_hash, is_active
        FROM accounts
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if (!$account) {
        return null;
    }

    if (!$account['is_active']) {
        return ['error' => 'account_inactive'];
    }

    if (!password_verify($password, $account['password_hash'])) {
        return null;
    }

    return $account;
}

/**
 * Register new account
 */
function registerAccount(string $email, string $password, string $name, ?string $phone = null): array {
    $db = getDB();

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM accounts WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Email er allerede registreret'];
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Ugyldig email-adresse'];
    }

    // Validate password strength
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Adgangskode skal være mindst 8 tegn'];
    }

    // Create account
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $verificationToken = bin2hex(random_bytes(32));

    try {
        $db->beginTransaction();

        // Insert account
        $stmt = $db->prepare("
            INSERT INTO accounts (email, password_hash, name, phone, email_verification_token)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$email, $passwordHash, $name, $phone, $verificationToken]);
        $accountId = $db->lastInsertId();

        // Get free plan
        $stmt = $db->prepare("SELECT id FROM plans WHERE slug = 'free' LIMIT 1");
        $stmt->execute();
        $freePlan = $stmt->fetch();

        if ($freePlan) {
            // Create subscription to free plan
            $stmt = $db->prepare("
                INSERT INTO subscriptions (account_id, plan_id, status)
                VALUES (?, ?, 'active')
            ");
            $stmt->execute([$accountId, $freePlan['id']]);
        }

        $db->commit();

        return [
            'success' => true,
            'account_id' => $accountId,
            'verification_token' => $verificationToken
        ];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Registration failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Der opstod en fejl. Prøv igen.'];
    }
}

/**
 * Get account's subscription and plan
 */
function getAccountSubscription(int $accountId): ?array {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT s.*, p.name as plan_name, p.slug as plan_slug,
               p.max_guests, p.max_events, p.features, p.price_monthly
        FROM subscriptions s
        JOIN plans p ON s.plan_id = p.id
        WHERE s.account_id = ? AND s.status IN ('active', 'trialing')
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$accountId]);
    $subscription = $stmt->fetch();

    if ($subscription && $subscription['features']) {
        $subscription['features'] = json_decode($subscription['features'], true);
    }

    return $subscription ?: null;
}

/**
 * Check if account has access to a specific feature
 */
function hasFeature(int $accountId, string $feature): bool {
    $subscription = getAccountSubscription($accountId);

    if (!$subscription) {
        return false;
    }

    $features = $subscription['features'] ?? [];
    return isset($features[$feature]) && $features[$feature] === true;
}

/**
 * Get account's event count
 */
function getAccountEventCount(int $accountId): int {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM event_owners
        WHERE account_id = ? AND role = 'owner'
    ");
    $stmt->execute([$accountId]);
    $result = $stmt->fetch();

    return (int)($result['count'] ?? 0);
}

/**
 * Check if account can create more events
 */
function canCreateEvent(int $accountId): bool {
    $subscription = getAccountSubscription($accountId);

    if (!$subscription) {
        return false;
    }

    $currentCount = getAccountEventCount($accountId);
    return $currentCount < $subscription['max_events'];
}

/**
 * Create password reset token
 */
function createPasswordReset(string $email): ?string {
    $db = getDB();

    // Check if account exists
    $stmt = $db->prepare("SELECT id FROM accounts WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        return null;
    }

    // Generate token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Insert reset record
    $stmt = $db->prepare("
        INSERT INTO password_resets (email, token, expires_at)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$email, $token, $expiresAt]);

    return $token;
}

/**
 * Validate password reset token
 */
function validatePasswordResetToken(string $token): ?string {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT email FROM password_resets
        WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $result = $stmt->fetch();

    return $result['email'] ?? null;
}

/**
 * Reset password using token
 */
function resetPasswordWithToken(string $token, string $newPassword): bool {
    $db = getDB();

    $email = validatePasswordResetToken($token);
    if (!$email) {
        return false;
    }

    if (strlen($newPassword) < 8) {
        return false;
    }

    try {
        $db->beginTransaction();

        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE accounts SET password_hash = ? WHERE email = ?");
        $stmt->execute([$passwordHash, $email]);

        // Mark token as used
        $stmt = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = ?");
        $stmt->execute([$token]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

/**
 * CSRF token functions (using same approach as auth.php)
 */
function generateAccountCsrfToken(): string {
    if (empty($_SESSION['account_csrf_token'])) {
        $_SESSION['account_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['account_csrf_token'];
}

function verifyAccountCsrfToken(string $token): bool {
    return isset($_SESSION['account_csrf_token']) && hash_equals($_SESSION['account_csrf_token'], $token);
}

function accountCsrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateAccountCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}
