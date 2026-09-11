<?php
/**
 * Self-contained unit tests for billing calculation logic.
 * Mirrors compute_child_week_bill() / apply_individual_discount() /
 * multi-child discount from billinglib.php. No database required.
 *
 * CLI:  php test_billing_math.php
 * Web:  open in browser (auto-detects and renders HTML)
 */

$is_cli = (php_sapi_name() === 'cli');

// ---------------------------------------------------------------------------
// Output helpers
// ---------------------------------------------------------------------------

$passed = 0;
$failed = 0;
$sections = [];
$current_section = null;

function start_section(string $title): void {
    global $is_cli, $current_section, $sections;
    $current_section = ['title' => $title, 'results' => []];
    if ($is_cli) {
        echo "\n=== $title ===\n";
    }
}

function end_section(): void {
    global $current_section, $sections;
    if ($current_section !== null) {
        $sections[] = $current_section;
        $current_section = null;
    }
}

function record_result(string $label, bool $ok, string $detail = ''): void {
    global $passed, $failed, $is_cli, $current_section;
    if ($ok) {
        $passed++;
        if ($is_cli) {
            echo "[PASS] $label\n";
        }
    } else {
        $failed++;
        if ($is_cli) {
            echo "[FAIL] $label" . ($detail !== '' ? " ($detail)" : '') . "\n";
        }
    }
    if ($current_section !== null) {
        $current_section['results'][] = [
            'label'  => $label,
            'ok'     => $ok,
            'detail' => $detail,
        ];
    }
}

function assert_eq(string $label, float $expected, float $actual, float $tol = 0.001): void {
    $ok = abs($expected - $actual) < $tol;
    $detail = $ok ? '' : "expected $expected, got $actual";
    record_result($label, $ok, $detail);
}

function assert_true(string $label, bool $cond): void {
    record_result($label, $cond, $cond ? '' : 'condition was false');
}

// ---------------------------------------------------------------------------
// Billing math (mirrors billinglib.php)
// ---------------------------------------------------------------------------

function compute_child_week_bill(array $program, int $day_count, bool $is_enrollment_billing): float {
    if ($is_enrollment_billing) {
        return (float)$program['fulltime'];
    }
    if ($day_count > 0) {
        if ($day_count >= (int)$program['consider_full']) {
            return (float)$program['fulltime'];
        }
        $bill = $day_count * (float)$program['perday'];
        $min_active = (float)$program['minimumactive'];
        if ($min_active > 0 && $bill < $min_active) {
            $bill = $min_active;
        }
        return $bill;
    }
    return (float)$program['minimuminactive'];
}

function apply_individual_discount(
    array $program,
    float $raw_bill,
    float $discount,
    bool $is_enrollment_billing,
    int $day_count
): float {
    $final = (float)$raw_bill - (float)$discount;
    if (!$is_enrollment_billing) {
        $floor = $day_count > 0
            ? (float)$program['minimumactive']
            : (float)$program['minimuminactive'];
        if ($floor > 0 && $final < $floor) {
            $final = $floor;
        }
    }
    return max(0.0, $final);
}

function apply_multi_child_discount(
    array $children,
    float $multiple,
    float $threshold = 0.0
): array {
    $family_total = 0.0;
    foreach ($children as $info) {
        if (empty($info['exempt']) && $info['final'] > 0) {
            $family_total += (float)$info['final'];
        }
    }
    if ($multiple <= 0 || ($threshold > 0 && $family_total <= $threshold)) {
        $out = [];
        foreach ($children as $id => $info) {
            $out[$id] = (float)$info['final'];
        }
        return $out;
    }

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
        $bill = (float)$info['final'];
        if (!$first && empty($info['exempt']) && $bill > 0) {
            $bill = max(0.0, round($bill - $multiple, 2));
        }
        $first = false;
        $result[$id] = $bill;
    }
    return $result;
}

function format_money(float $amount): string {
    return '$' . number_format($amount, 2, '.', '');
}

// ---------------------------------------------------------------------------
// Programs under test
// ---------------------------------------------------------------------------

$program = [
    'bill_by'           => 'enrollment',
    'fulltime'          => 100.00,
    'perday'            => 25.00,
    'consider_full'     => 4,
    'minimumactive'     => 40.00,
    'minimuminactive'   => 20.00,
    'vacation'          => 15.00,
    'multiple_discount' => 10.00,
    'discount_rule'     => 0.00,
];

$pay_by_day = $program;
$pay_by_day['consider_full'] = 8;

$att = $program;
$att['bill_by'] = 'attendance';

$user_override = [
    'bill_by'           => 'attendance',
    'fulltime'          => 200.00,
    'perday'            => 20.00,
    'consider_full'     => 8,
    'minimumactive'     => 0.00,
    'minimuminactive'   => 0.00,
    'vacation'          => 20.00,
    'multiple_discount' => 5.00,
    'discount_rule'     => 0.00,
];

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

start_section('Enrollment billing (always fulltime)');
assert_eq('enrollment 0 days → fulltime 100', 100.00, compute_child_week_bill($program, 0, true));
assert_eq('enrollment 1 day → fulltime 100', 100.00, compute_child_week_bill($program, 1, true));
assert_eq('enrollment 7 days → fulltime 100', 100.00, compute_child_week_bill($program, 7, true));
end_section();

start_section('Attendance: consider_full gate (standard = 4)');
assert_eq('3 days → 75 (under full, above min)', 75.00, compute_child_week_bill($program, 3, false));
assert_eq('4 days → fulltime 100', 100.00, compute_child_week_bill($program, 4, false));
assert_eq('1 day → minimumactive 40', 40.00, compute_child_week_bill($program, 1, false));
assert_eq('0 days attendance → minimuminactive 20', 20.00, compute_child_week_bill($att, 0, false));
assert_eq('2 days → 50', 50.00, compute_child_week_bill($program, 2, false));
end_section();

start_section('Attendance: consider_full = 8 (pay-by-day)');
assert_eq('pay-by-day 1 day → minimumactive 40', 40.00, compute_child_week_bill($pay_by_day, 1, false));
assert_eq('pay-by-day 2 days → 50', 50.00, compute_child_week_bill($pay_by_day, 2, false));
assert_eq('pay-by-day 5 days → 125 (NOT fulltime)', 125.00, compute_child_week_bill($pay_by_day, 5, false));
assert_eq('pay-by-day 8 days → fulltime 100', 100.00, compute_child_week_bill($pay_by_day, 8, false));
end_section();

start_section('Individual discount (enrollment: no floor beyond 0)');
$raw = compute_child_week_bill($program, 5, true);
assert_eq('enrollment discount 25 off 100 → 75', 75.00, apply_individual_discount($program, $raw, 25.00, true, 5));
assert_eq('enrollment large discount floors at 0', 0.00, apply_individual_discount($program, $raw, 999.00, true, 5));
end_section();

start_section('Individual discount (attendance: floors at min active/inactive)');
$raw1 = compute_child_week_bill($program, 1, false);
assert_eq('1 day raw is min active 40', 40.00, $raw1);
assert_eq('1 day discount 10 still floored at min active 40', 40.00, apply_individual_discount($program, $raw1, 10.00, false, 1));
assert_eq('1 day discount 0 → 40', 40.00, apply_individual_discount($program, $raw1, 0.00, false, 1));
$raw3 = compute_child_week_bill($program, 3, false);
assert_eq('3 days discount 20 → 55 (above min)', 55.00, apply_individual_discount($program, $raw3, 20.00, false, 3));
assert_eq('3 days discount 50 → floored at min active 40', 40.00, apply_individual_discount($program, $raw3, 50.00, false, 3));
$raw0 = compute_child_week_bill($program, 0, false);
assert_eq('0 days discount 5 → floored at min inactive 20', 20.00, apply_individual_discount($program, $raw0, 5.00, false, 0));
assert_eq('0 days discount 25 → still floored at 20', 20.00, apply_individual_discount($program, $raw0, 25.00, false, 0));
end_section();

start_section('Vacation / exempt (caller skips individual discount)');
assert_eq('vacation rate is 15', 15.00, (float)$program['vacation']);
assert_eq('exempt forces 0 regardless of raw', 0.00, 0.00);
end_section();

start_section('Multi-child (most expensive first, threshold 0 = always)');
$kids = [
    'A' => ['final' => 100.00, 'exempt' => 0],
    'B' => ['final' => 50.00,  'exempt' => 0],
    'C' => ['final' => 0.00,   'exempt' => 1],
];
$after = apply_multi_child_discount($kids, 10.00, 0.00);
assert_eq('top keeps 100', 100.00, $after['A']);
assert_eq('second gets 40', 40.00, $after['B']);
assert_eq('exempt stays 0', 0.00, $after['C']);
end_section();

start_section('Multi-child threshold blocks discount');
$after_blocked = apply_multi_child_discount($kids, 10.00, 200.00);
assert_eq('threshold blocks: A still 100', 100.00, $after_blocked['A']);
assert_eq('threshold blocks: B still 50', 50.00, $after_blocked['B']);
end_section();

start_section('Multi-child equal bills: stable order by insertion');
$equal = [
    'X' => ['final' => 60.00, 'exempt' => 0],
    'Y' => ['final' => 60.00, 'exempt' => 0],
];
$after_eq = apply_multi_child_discount($equal, 5.00, 0.00);
assert_eq('equal bills: first keeps 60', 60.00, $after_eq['X']);
assert_eq('equal bills: second gets 55', 55.00, $after_eq['Y']);
end_section();

start_section('User regression: attendance override, 1-day + 3-day, indiv + multi');
$c1_raw = compute_child_week_bill($user_override, 1, false);
$c1_final = apply_individual_discount($user_override, $c1_raw, 5.00, false, 1);
$c2_raw = compute_child_week_bill($user_override, 3, false);
$c2_final = apply_individual_discount($user_override, $c2_raw, 0.00, false, 3);
assert_eq('user Child1 raw 1-day → 20', 20.00, $c1_raw);
assert_eq('user Child1 after indiv $5 → 15', 15.00, $c1_final);
assert_eq('user Child2 raw 3-day → 60', 60.00, $c2_raw);
assert_eq('user Child2 after indiv $0 → 60', 60.00, $c2_final);
$user_kids = [
    'child1' => ['final' => $c1_final, 'exempt' => 0],
    'child2' => ['final' => $c2_final, 'exempt' => 0],
];
$user_after = apply_multi_child_discount($user_kids, 5.00, 0.00);
assert_eq('user after multi: child2 (highest) stays 60', 60.00, $user_after['child2']);
assert_eq('user after multi: child1 becomes 10', 10.00, $user_after['child1']);
assert_eq('user account total 70', 70.00, $user_after['child1'] + $user_after['child2']);
end_section();

start_section('Money formatting (receipt amount)');
assert_true('format 10 → $10.00', format_money(10.00) === '$10.00');
assert_true('format 0 → $0.00', format_money(0.00) === '$0.00');
assert_true('format 15.5 → $15.50', format_money(15.50) === '$15.50');
end_section();

start_section('Edge: multi larger than bill floors at 0');
$small = [
    'H' => ['final' => 30.00, 'exempt' => 0],
    'L' => ['final' => 4.00,  'exempt' => 0],
];
$after_small = apply_multi_child_discount($small, 10.00, 0.00);
assert_eq('multi > bill → 0', 0.00, $after_small['L']);
assert_eq('highest still 30', 30.00, $after_small['H']);
end_section();

start_section('Edge: single child → no multi applied');
$solo = ['only' => ['final' => 50.00, 'exempt' => 0]];
$after_solo = apply_multi_child_discount($solo, 10.00, 0.00);
assert_eq('solo child keeps full amount', 50.00, $after_solo['only']);
end_section();

start_section('Edge: all exempt → nothing reduced');
$all_ex = [
    'E1' => ['final' => 0.00, 'exempt' => 1],
    'E2' => ['final' => 0.00, 'exempt' => 1],
];
$after_ex = apply_multi_child_discount($all_ex, 10.00, 0.00);
assert_eq('all exempt stay 0', 0.00, $after_ex['E1'] + $after_ex['E2']);
end_section();

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------

$total = $passed + $failed;
$all_ok = ($failed === 0);

if ($is_cli) {
    echo "\n========================================\n";
    echo "Passed: $passed   Failed: $failed\n";
    echo ($all_ok ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n");
    exit($all_ok ? 0 : 1);
}

header('Content-Type: text/html; charset=utf-8');
$status_class = $all_ok ? 'pass' : 'fail';
$status_text  = $all_ok ? 'ALL TESTS PASSED' : 'SOME TESTS FAILED';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Billing Math Tests</title>
<style>
  :root {
    --bg: #0f1419;
    --surface: #1a2332;
    --border: #2d3a4f;
    --text: #e7ecf3;
    --muted: #8b9bb4;
    --pass: #3dd68c;
    --pass-bg: rgba(61, 214, 140, 0.12);
    --fail: #f07178;
    --fail-bg: rgba(240, 113, 120, 0.12);
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.45;
    padding: 1.5rem;
  }
  .wrap { max-width: 820px; margin: 0 auto; }
  h1 { font-size: 1.35rem; font-weight: 600; margin: 0 0 0.25rem; }
  .subtitle { color: var(--muted); font-size: 0.9rem; margin-bottom: 1.25rem; }
  .summary {
    display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;
    padding: 1rem 1.15rem; border-radius: 10px; border: 1px solid var(--border);
    background: var(--surface); margin-bottom: 1.5rem;
  }
  .summary.pass { border-color: rgba(61, 214, 140, 0.45); background: var(--pass-bg); }
  .summary.fail { border-color: rgba(240, 113, 120, 0.45); background: var(--fail-bg); }
  .badge { font-weight: 700; font-size: 0.95rem; letter-spacing: 0.02em; }
  .summary.pass .badge { color: var(--pass); }
  .summary.fail .badge { color: var(--fail); }
  .counts { color: var(--muted); font-size: 0.9rem; }
  .counts strong { color: var(--text); }
  details {
    border: 1px solid var(--border); border-radius: 10px; background: var(--surface);
    margin-bottom: 0.65rem; overflow: hidden;
  }
  details[open] { border-color: #3d4f6a; }
  summary {
    cursor: pointer; list-style: none; padding: 0.75rem 1rem;
    display: flex; align-items: center; gap: 0.6rem; user-select: none;
  }
  summary::-webkit-details-marker { display: none; }
  summary::before {
    content: "▸"; color: var(--muted); font-size: 0.75rem; transition: transform 0.15s;
  }
  details[open] summary::before { transform: rotate(90deg); }
  .sec-title { flex: 1; font-weight: 550; font-size: 0.95rem; }
  .sec-meta { font-size: 0.8rem; color: var(--muted); font-variant-numeric: tabular-nums; }
  .sec-meta .ok { color: var(--pass); }
  .sec-meta .bad { color: var(--fail); }
  ul.results { list-style: none; margin: 0; padding: 0 0 0.5rem; border-top: 1px solid var(--border); }
  li.result {
    display: grid; grid-template-columns: 3.25rem 1fr; gap: 0.5rem;
    padding: 0.4rem 1rem; font-size: 0.875rem; align-items: start;
  }
  li.result + li.result { border-top: 1px solid rgba(45, 58, 79, 0.5); }
  .tag {
    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em;
    padding: 0.15rem 0.4rem; border-radius: 4px; text-align: center;
  }
  .tag.pass { background: var(--pass-bg); color: var(--pass); }
  .tag.fail { background: var(--fail-bg); color: var(--fail); }
  .label { word-break: break-word; }
  .detail { grid-column: 2; color: var(--fail); font-size: 0.8rem; margin-top: 0.15rem; }
  footer { margin-top: 1.5rem; color: var(--muted); font-size: 0.8rem; }
  code { background: rgba(255,255,255,0.06); padding: 0.1em 0.35em; border-radius: 4px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Billing Math Tests</h1>
  <p class="subtitle">Mirrors compute_child_week_bill / apply_individual_discount / multi-child discount (no database)</p>

  <div class="summary <?= $status_class ?>">
    <span class="badge"><?= htmlspecialchars($status_text) ?></span>
    <span class="counts">
      <strong><?= (int)$passed ?></strong> passed
      · <strong><?= (int)$failed ?></strong> failed
      · <?= (int)$total ?> total
    </span>
  </div>

  <?php foreach ($sections as $sec):
      $sec_pass = 0;
      $sec_fail = 0;
      foreach ($sec['results'] as $r) {
          if ($r['ok']) { $sec_pass++; } else { $sec_fail++; }
      }
      $open = $sec_fail > 0 ? ' open' : '';
  ?>
  <details<?= $open ?>>
    <summary>
      <span class="sec-title"><?= htmlspecialchars($sec['title']) ?></span>
      <span class="sec-meta">
        <?php if ($sec_fail === 0): ?>
          <span class="ok"><?= $sec_pass ?> passed</span>
        <?php else: ?>
          <span class="ok"><?= $sec_pass ?> pass</span>
          · <span class="bad"><?= $sec_fail ?> fail</span>
        <?php endif; ?>
      </span>
    </summary>
    <ul class="results">
      <?php foreach ($sec['results'] as $r): ?>
      <li class="result">
        <span class="tag <?= $r['ok'] ? 'pass' : 'fail' ?>"><?= $r['ok'] ? 'PASS' : 'FAIL' ?></span>
        <div>
          <div class="label"><?= htmlspecialchars($r['label']) ?></div>
          <?php if (!$r['ok'] && $r['detail'] !== ''): ?>
            <div class="detail"><?= htmlspecialchars($r['detail']) ?></div>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </details>
  <?php endforeach; ?>

  <footer>Run via CLI with <code>php test_billing_math.php</code> for plain-text output and exit codes.</footer>
</div>
</body>
</html>
<?php
exit($all_ok ? 0 : 1);
