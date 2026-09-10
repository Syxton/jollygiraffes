<?php
/**
 * Self-contained unit tests for billing calculation logic.
 * No database required.
 */

function assert_eq(string $label, float $expected, float $actual, float $tol = 0.001): void {
    global $passed, $failed;
    $ok = abs($expected - $actual) < $tol;
    if ($ok) {
        $passed++;
        echo "[PASS] $label\n";
    } else {
        $failed++;
        echo "[FAIL] $label (expected $expected, got $actual)\n";
    }
}

$passed = 0;
$failed = 0;

/**
 * Core rate calculation used by week_balance (activity / estimate path).
 */
function calc_raw_bill(array $program, int $days): float {
    if ($days <= 0) {
        return ($program['bill_by'] === 'enrollment')
            ? (float)$program['vacation']
            : (float)$program['minimuminactive'];
    }
    $bill = $days * (float)$program['perday'];
    if ((float)$program['minimumactive'] > 0 && $bill < (float)$program['minimumactive']) {
        $bill = (float)$program['minimumactive'];
    }
    // consider_full = 8 => never fulltime (pay-by-day)
    if ($days >= (int)$program['consider_full']) {
        $bill = (float)$program['fulltime'];
    }
    return $bill;
}

function apply_individual_discount(float $raw, int $exempt, float $discount): float {
    if ($exempt) {
        return 0.0;
    }
    return max(0.0, $raw - $discount);
}

function apply_multi_child_discount(array $children, float $multiple_discount): array {
    $i = 0;
    foreach ($children as $id => &$info) {
        $info['_ord'] = $i++;
    }
    unset($info);
    uasort($children, function ($a, $b) {
        $cmp = $b['final'] <=> $a['final'];
        return $cmp !== 0 ? $cmp : ($a['_ord'] <=> $b['_ord']);
    });
    $first = true;
    $result = [];
    foreach ($children as $id => $info) {
        $bill = $info['final'];
        if (!$first && empty($info['exempt']) && $bill > 0) {
            $bill = max(0.0, $bill - $multiple_discount);
        }
        $first = false;
        $result[$id] = $bill;
    }
    return $result;
}

$program = [
    'bill_by'           => 'enrollment',
    'fulltime'          => 100.00,
    'perday'            => 25.00,
    'consider_full'     => 4,
    'minimumactive'     => 40.00,
    'minimuminactive'   => 20.00,
    'vacation'          => 15.00,
    'multiple_discount' => 10.00,
];

$pay_by_day = $program;
$pay_by_day['consider_full'] = 8; // never hits fulltime

$att = $program;
$att['bill_by'] = 'attendance';

echo "=== consider_full gate (standard = 4) ===\n";
assert_eq('3 days → 75 then min not needed; under full → 75', 75.00, calc_raw_bill($program, 3));
assert_eq('4 days → fulltime 100', 100.00, calc_raw_bill($program, 4));
assert_eq('1 day → minimumactive 40', 40.00, calc_raw_bill($program, 1));
assert_eq('0 days enrollment → vacation 15', 15.00, calc_raw_bill($program, 0));
assert_eq('0 days attendance → minimuminactive 20', 20.00, calc_raw_bill($att, 0));

echo "\n=== consider_full = 8 (pay-by-day) ===\n";
assert_eq('pay-by-day 1 day → minimumactive 40', 40.00, calc_raw_bill($pay_by_day, 1));
assert_eq('pay-by-day 2 days → 50', 50.00, calc_raw_bill($pay_by_day, 2));
assert_eq('pay-by-day 5 days → 125 (NOT fulltime)', 125.00, calc_raw_bill($pay_by_day, 5));
assert_eq('pay-by-day 8 days → fulltime 100', 100.00, calc_raw_bill($pay_by_day, 8));

echo "\n=== Individual exempt / discount ===\n";
assert_eq('exempt forces 0', 0.00, apply_individual_discount(100.00, 1, 25.00));
assert_eq('discount 25 off 100 → 75', 75.00, apply_individual_discount(100.00, 0, 25.00));
assert_eq('large discount floors at 0', 0.00, apply_individual_discount(50.00, 0, 999.00));

echo "\n=== Multi-child (most expensive first) ===\n";
$kids = [
    'A' => ['final' => 100.00, 'exempt' => 0],
    'B' => ['final' => 50.00,  'exempt' => 0],
    'C' => ['final' => 0.00,   'exempt' => 1],
];
$after = apply_multi_child_discount($kids, 10.00);
assert_eq('top keeps 100', 100.00, $after['A']);
assert_eq('second gets 40', 40.00, $after['B']);
assert_eq('exempt stays 0', 0.00, $after['C']);

echo "\n========================================\n";
echo "Passed: $passed   Failed: $failed\n";
echo ($failed === 0 ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n");
exit($failed === 0 ? 0 : 1);
