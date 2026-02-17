<?php
/**
 * Test: BookingService (State Machine)
 * Run: php tests/subcontractor/BookingServiceTest.php
 *
 * Tests the booking status transition state machine WITHOUT requiring
 * a database connection. The valid transitions are defined here based
 * on the booking-service.php implementation:
 *
 *   requested -> quoted      (submitQuote)
 *   requested -> cancelled   (cancelBooking)
 *   quoted    -> accepted    (acceptQuote)
 *   quoted    -> cancelled   (cancelBooking)
 *   accepted  -> deposited   (confirmBooking / Stripe webhook)
 *   accepted  -> cancelled   (cancelBooking)
 *   deposited -> confirmed   (vendorConfirmBooking)
 *   deposited -> refunded    (refund flow)
 *   confirmed -> completed   (completeBooking)
 *   confirmed -> cancelled   (late cancellation)
 *   completed -> reviewed    (review submission)
 *   reviewed  -> (terminal)
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
// Define the state machine (extracted from booking-service.php)
// ============================================================

$validTransitions = [
    'requested' => ['quoted', 'cancelled'],
    'quoted'    => ['accepted', 'cancelled'],
    'accepted'  => ['deposited', 'cancelled'],
    'deposited' => ['confirmed', 'refunded'],
    'confirmed' => ['completed', 'cancelled'],
    'completed' => ['reviewed'],
    'reviewed'  => [],       // terminal state
    'cancelled' => [],       // terminal state
    'refunded'  => [],       // terminal state
];

/**
 * Check whether a status transition is valid.
 *
 * @param string $from Current status
 * @param string $to   Target status
 * @return bool
 */
function isValidTransition(string $from, string $to): bool {
    global $validTransitions;
    if (!isset($validTransitions[$from])) {
        return false;
    }
    return in_array($to, $validTransitions[$from], true);
}

// ============================================================
// Tests
// ============================================================

echo "BookingServiceTest (State Machine)\n";
echo str_repeat('-', 50) . "\n";

// --- 'requested' transitions ---
echo "\nFrom 'requested':\n";

assert_true(
    isValidTransition('requested', 'quoted'),
    "requested -> quoted is valid"
);

assert_true(
    isValidTransition('requested', 'cancelled'),
    "requested -> cancelled is valid"
);

assert_equals(
    false,
    isValidTransition('requested', 'accepted'),
    "requested -> accepted is NOT valid"
);

assert_equals(
    false,
    isValidTransition('requested', 'completed'),
    "requested -> completed is NOT valid"
);

// --- 'quoted' transitions ---
echo "\nFrom 'quoted':\n";

assert_true(
    isValidTransition('quoted', 'accepted'),
    "quoted -> accepted is valid"
);

assert_true(
    isValidTransition('quoted', 'cancelled'),
    "quoted -> cancelled is valid"
);

assert_equals(
    false,
    isValidTransition('quoted', 'deposited'),
    "quoted -> deposited is NOT valid"
);

// --- 'accepted' transitions ---
echo "\nFrom 'accepted':\n";

assert_true(
    isValidTransition('accepted', 'deposited'),
    "accepted -> deposited is valid"
);

assert_true(
    isValidTransition('accepted', 'cancelled'),
    "accepted -> cancelled is valid"
);

assert_equals(
    false,
    isValidTransition('accepted', 'confirmed'),
    "accepted -> confirmed is NOT valid (must go through deposited)"
);

// --- 'deposited' transitions ---
echo "\nFrom 'deposited':\n";

assert_true(
    isValidTransition('deposited', 'confirmed'),
    "deposited -> confirmed is valid"
);

assert_true(
    isValidTransition('deposited', 'refunded'),
    "deposited -> refunded is valid"
);

assert_equals(
    false,
    isValidTransition('deposited', 'completed'),
    "deposited -> completed is NOT valid (must go through confirmed)"
);

assert_equals(
    false,
    isValidTransition('deposited', 'cancelled'),
    "deposited -> cancelled is NOT valid (use refunded after payment)"
);

// --- 'confirmed' transitions ---
echo "\nFrom 'confirmed':\n";

assert_true(
    isValidTransition('confirmed', 'completed'),
    "confirmed -> completed is valid"
);

assert_true(
    isValidTransition('confirmed', 'cancelled'),
    "confirmed -> cancelled is valid"
);

assert_equals(
    false,
    isValidTransition('confirmed', 'reviewed'),
    "confirmed -> reviewed is NOT valid (must go through completed)"
);

// --- 'completed' transitions ---
echo "\nFrom 'completed':\n";

assert_true(
    isValidTransition('completed', 'reviewed'),
    "completed -> reviewed is valid"
);

assert_equals(
    false,
    isValidTransition('completed', 'cancelled'),
    "completed -> cancelled is NOT valid"
);

assert_equals(
    false,
    isValidTransition('completed', 'confirmed'),
    "completed -> confirmed is NOT valid (no going backward)"
);

// --- 'reviewed' is terminal ---
echo "\nFrom 'reviewed' (terminal):\n";

assert_empty(
    $validTransitions['reviewed'],
    "reviewed has no outgoing transitions"
);

assert_equals(
    false,
    isValidTransition('reviewed', 'completed'),
    "reviewed -> completed is NOT valid"
);

assert_equals(
    false,
    isValidTransition('reviewed', 'cancelled'),
    "reviewed -> cancelled is NOT valid"
);

// --- 'cancelled' is terminal ---
echo "\nFrom 'cancelled' (terminal):\n";

assert_empty(
    $validTransitions['cancelled'],
    "cancelled has no outgoing transitions"
);

assert_equals(
    false,
    isValidTransition('cancelled', 'requested'),
    "cancelled -> requested is NOT valid"
);

// --- 'refunded' is terminal ---
echo "\nFrom 'refunded' (terminal):\n";

assert_empty(
    $validTransitions['refunded'],
    "refunded has no outgoing transitions"
);

// --- Full happy path ---
echo "\nFull happy path:\n";

$happyPath = ['requested', 'quoted', 'accepted', 'deposited', 'confirmed', 'completed', 'reviewed'];
$allValid = true;
for ($i = 0; $i < count($happyPath) - 1; $i++) {
    if (!isValidTransition($happyPath[$i], $happyPath[$i + 1])) {
        $allValid = false;
        break;
    }
}
assert_true($allValid, 'Full happy path: requested -> quoted -> accepted -> deposited -> confirmed -> completed -> reviewed');

// --- Invalid backwards transition ---
echo "\nBackwards transitions:\n";

assert_equals(
    false,
    isValidTransition('quoted', 'requested'),
    "quoted -> requested is NOT valid (no going backward)"
);

assert_equals(
    false,
    isValidTransition('completed', 'deposited'),
    "completed -> deposited is NOT valid (no going backward)"
);

// --- Unknown status ---
echo "\nUnknown status:\n";

assert_equals(
    false,
    isValidTransition('nonexistent', 'requested'),
    "nonexistent -> requested is NOT valid (unknown source status)"
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
