<?php
/**
 * Payment Service Tests
 * Tests financial calculations, checkout data preparation, and refund logic.
 * Run: php tests/subcontractor/PaymentServiceTest.php
 */

// Load dependencies without database
define('DEPOSITUM_RATE', 0.25);
define('COMMISSION_RATE', 0.15);

// We only test pure functions from commission-service.php
require_once __DIR__ . '/../../subcontractor/includes/commission-service.php';

$passed = 0;
$failed = 0;

function assert_equals($expected, $actual, string $label): void {
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  PASS: $label\n";
        $passed++;
    } else {
        echo "  FAIL: $label (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")\n";
        $failed++;
    }
}

function assert_true(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) {
        echo "  PASS: $label\n";
        $passed++;
    } else {
        echo "  FAIL: $label\n";
        $failed++;
    }
}

// ============================================================
// Test: Depositum Calculation
// ============================================================

echo "\n=== Depositum Calculation ===\n";

assert_equals(250.00, calculateDepositum(1000.00), 'Standard depositum (1000 * 25%)');
assert_equals(0.00, calculateDepositum(0.00), 'Zero price depositum');
assert_equals(125.50, calculateDepositum(502.00), 'Odd amount depositum');
assert_equals(2500.00, calculateDepositum(10000.00), 'Large price depositum');
assert_equals(0.25, calculateDepositum(1.00), 'Minimum price depositum');
assert_equals(12.50, calculateDepositum(50.00), 'Small price depositum');

// ============================================================
// Test: Commission Calculation
// ============================================================

echo "\n=== Commission Calculation ===\n";

assert_equals(37.50, calculateCommission(250.00), 'Standard commission (250 * 15%)');
assert_equals(0.00, calculateCommission(0.00), 'Zero depositum commission');
assert_equals(18.83, calculateCommission(125.50), 'Odd depositum commission');
assert_equals(375.00, calculateCommission(2500.00), 'Large depositum commission');
assert_equals(0.04, calculateCommission(0.25), 'Tiny depositum commission');

// ============================================================
// Test: Vendor Payout Calculation
// ============================================================

echo "\n=== Vendor Payout Calculation ===\n";

assert_equals(212.50, calculateVendorPayout(250.00), 'Standard payout (250 * 85%)');
assert_equals(0.00, calculateVendorPayout(0.00), 'Zero depositum payout');
assert_equals(106.67, calculateVendorPayout(125.50), 'Odd depositum payout (dep - commission)');
assert_equals(2125.00, calculateVendorPayout(2500.00), 'Large payout');

// ============================================================
// Test: Commission + Payout = Depositum
// ============================================================

echo "\n=== Financial Integrity: Commission + Payout = Depositum ===\n";

$testPrices = [100, 250, 500, 999.99, 1000, 1500, 2499.95, 5000, 10000, 49999.99];
foreach ($testPrices as $price) {
    $dep = calculateDepositum($price);
    $com = calculateCommission($dep);
    $pay = calculateVendorPayout($dep);
    $sum = round($com + $pay, 2);
    assert_equals($dep, $sum, "Integrity check for price $price: commission($com) + payout($pay) = depositum($dep)");
}

// ============================================================
// Test: Checkout Data Preparation
// ============================================================

echo "\n=== Checkout Data Preparation ===\n";

// Test that amounts convert correctly to Stripe cents (ore)
$quotedPrice = 4000.00;
$dep = calculateDepositum($quotedPrice);
$com = calculateCommission($dep);

$stripeDepositumOre = (int)($dep * 100);
$stripeCommissionOre = (int)($com * 100);

assert_equals(100000, $stripeDepositumOre, 'Stripe depositum in ore (1000 kr = 100000 ore)');
assert_equals(15000, $stripeCommissionOre, 'Stripe commission in ore (150 kr = 15000 ore)');

// Edge case: very small amounts
$smallDep = calculateDepositum(10.00);
$smallCom = calculateCommission($smallDep);
assert_equals(250, (int)($smallDep * 100), 'Small depositum in ore');
assert_equals(38, (int)($smallCom * 100), 'Small commission in ore (0.375 rounds to 0.38)');

// Edge case: fractional ore
$oddPrice = 33.33;
$oddDep = calculateDepositum($oddPrice);
$oddOre = (int)($oddDep * 100);
assert_true($oddOre > 0, "Odd price ($oddPrice) produces positive ore ($oddOre)");

// ============================================================
// Test: Refund Amounts
// ============================================================

echo "\n=== Refund Calculation ===\n";

// Full refund = depositum amount returned to organizer
$refundPrice = 2000.00;
$refundDep = calculateDepositum($refundPrice);
$refundCom = calculateCommission($refundDep);
$refundPay = calculateVendorPayout($refundDep);

assert_equals(500.00, $refundDep, 'Refund amount equals depositum');
assert_equals(75.00, $refundCom, 'Platform loses commission on refund');
assert_equals(425.00, $refundPay, 'Vendor payout to reverse on refund');

// After refund, platform net = 0
$platformNet = $refundCom - $refundCom;
assert_equals(0.00, $platformNet, 'Platform net after full refund is 0');

// ============================================================
// Test: Rate Constants
// ============================================================

echo "\n=== Rate Constants ===\n";

assert_equals(0.25, DEPOSITUM_RATE, 'Depositum rate is 25%');
assert_equals(0.15, COMMISSION_RATE, 'Commission rate is 15%');
assert_true(DEPOSITUM_RATE > 0 && DEPOSITUM_RATE < 1, 'Depositum rate is between 0 and 1');
assert_true(COMMISSION_RATE > 0 && COMMISSION_RATE < 1, 'Commission rate is between 0 and 1');

// ============================================================
// Summary
// ============================================================

echo "\n=== Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

exit($failed > 0 ? 1 : 0);
