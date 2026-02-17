<?php
/**
 * Test: CommissionService
 * Run: php tests/subcontractor/CommissionServiceTest.php
 *
 * Tests the pure calculation functions in commission-service.php:
 * calculateDepositum, calculateCommission, calculateVendorPayout.
 */

$passed = 0;
$failed = 0;
$errors = [];

function assert_equals($expected, $actual, string $message): void {
    global $passed, $failed, $errors;
    if ($expected === $actual) {
        $passed++;
        echo "  PASS: {$message}\n";
    } else {
        $failed++;
        $errors[] = $message;
        echo "  FAIL: {$message}\n";
        echo "    Expected: " . var_export($expected, true) . "\n";
        echo "    Actual:   " . var_export($actual, true) . "\n";
    }
}

function assert_true($value, string $message): void {
    assert_equals(true, (bool) $value, $message);
}

function assert_empty($value, string $message): void {
    assert_equals(true, empty($value), $message);
}

function assert_not_empty($value, string $message): void {
    assert_equals(true, !empty($value), $message);
}

// ============================================================
// Bootstrap: define constants and mock database.php so we can
// load commission-service.php without a real DB connection.
// ============================================================

// Pre-define the constants that commission-service.php expects
define('DEPOSITUM_RATE', 0.25);
define('COMMISSION_RATE', 0.15);

// Pre-define DB constants to satisfy database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'test');
define('DB_USER', 'test');
define('DB_PASS', 'test');
define('DB_CHARSET', 'utf8mb4');
define('BASE_PATH', '');
define('APP_URL', '');

// Provide stub functions that database.php would normally define
function env(string $key, $default = null) { return $default; }
function getDB(): PDO { throw new RuntimeException('DB not available in tests'); }
function getBaseUrl(): string { return 'http://localhost'; }

// Now require commission-service.php (it will skip re-defining constants
// and skip re-defining getDB because of the if-guards)
require_once __DIR__ . '/../../subcontractor/includes/commission-service.php';

// ============================================================
// Tests
// ============================================================

echo "CommissionServiceTest\n";
echo str_repeat('-', 50) . "\n";

// --- calculateDepositum ---
echo "\ncalculateDepositum:\n";

assert_equals(
    2500.0,
    calculateDepositum(10000),
    'calculateDepositum(10000) should return 2500'
);

assert_equals(
    0.0,
    calculateDepositum(0),
    'calculateDepositum(0) should return 0'
);

assert_equals(
    0.25,
    calculateDepositum(1),
    'calculateDepositum(1) should return 0.25'
);

// --- calculateCommission ---
echo "\ncalculateCommission:\n";

assert_equals(
    375.0,
    calculateCommission(2500),
    'calculateCommission(2500) should return 375'
);

assert_equals(
    0.0,
    calculateCommission(0),
    'calculateCommission(0) should return 0'
);

// --- calculateVendorPayout ---
echo "\ncalculateVendorPayout:\n";

assert_equals(
    2125.0,
    calculateVendorPayout(2500),
    'calculateVendorPayout(2500) should return 2125'
);

// --- Full chain ---
echo "\nFull chain (10000 quoted):\n";

$quoted = 10000;
$depositum = calculateDepositum($quoted);
$commission = calculateCommission($depositum);
$payout = calculateVendorPayout($depositum);

assert_equals(2500.0, $depositum, 'Full chain: depositum should be 2500');
assert_equals(375.0, $commission, 'Full chain: commission should be 375');
assert_equals(2125.0, $payout, 'Full chain: vendor payout should be 2125');

// Verify the relationship: payout + commission = depositum
assert_equals(
    $depositum,
    $commission + $payout,
    'Full chain: commission + payout should equal depositum'
);

// ============================================================
// Results
// ============================================================

echo "\n" . str_repeat('=', 50) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";
if ($failed > 0) {
    echo "Failed tests:\n";
    foreach ($errors as $e) echo "  - {$e}\n";
    exit(1);
}
echo "All tests passed!\n";
exit(0);
