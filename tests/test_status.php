<?php
/**
 * Unit tests for pure / session-local helpers in lib/status_lib.php.
 * Exercises production helpers without a database.
 *
 * CLI:  php tests/test_status.php
 * Web:  open in browser (auto-detects and renders HTML)
 *
 * All session state and temporary side-effects are cleaned up at the end.
 */

$is_cli = (php_sapi_name() === 'cli');

// ---------------------------------------------------------------------------
// Minimal bootstrap so status_lib can load without DB / full app stack
// ---------------------------------------------------------------------------

$CFG = new \stdClass;
$CFG->timezone   = 'America/New_York';
$CFG->sitename   = 'Jolly Giraffes Test';
$CFG->wwwroot    = 'http://localhost/jollygiraffes';
$CFG->dirroot    = dirname(__DIR__);
$CFG->docroot    = $CFG->dirroot;
$CFG->directory  = '';

// Skip heavy requires inside status_lib / dependent libs
$NOTIFICATIONLIB = true;
$LIBHEADER       = true;
$TIMELIB         = true;
$DBLIB           = true;
// Leave $STATUSLIB unset so status_lib defines its functions and catalogs

// Controlled "now" for time-based tests (Unix timestamp, UTC convention)
$GLOBALS['_test_now'] = null;

function get_timestamp($timezone = 'UTC') {
    if (isset($GLOBALS['_test_now']) && $GLOBALS['_test_now'] !== null) {
        return (int) $GLOBALS['_test_now'];
    }
    // Match production: set requested tz, read time, reset to UTC
    date_default_timezone_set($timezone);
    $time = time();
    date_default_timezone_set('UTC');
    return $time;
}

function get_offset($timezone = false) {
    global $CFG;
    $timezone = empty($timezone) ? $CFG->timezone : $timezone;
    $LOCAL = new DateTimeZone($timezone);
    $timeLOCAL = new DateTime('now', $LOCAL);
    return timezone_offset_get($LOCAL, $timeLOCAL);
}

function get_today($timezone = 'UTC') {
    global $CFG;
    $dateinmytimezone = new DateTime('now', new DateTimeZone($CFG->timezone));
    $UTCdate = new DateTime($dateinmytimezone->format('m/d/Y'), new DateTimeZone('UTC'));
    return $UTCdate->getTimestamp();
}

// DB stubs — pure tests must never call these; they fail loudly if they do
function get_db_row($sql) {
    throw new RuntimeException('get_db_row called in pure unit test: ' . $sql);
}
function get_db_result($sql) {
    throw new RuntimeException('get_db_result called in pure unit test: ' . $sql);
}
function get_db_count($sql) {
    throw new RuntimeException('get_db_count called in pure unit test: ' . $sql);
}
function get_db_field($field, $table, $where) {
    throw new RuntimeException('get_db_field called in pure unit test');
}
function execute_db_sql($sql) {
    throw new RuntimeException('execute_db_sql called in pure unit test: ' . $sql);
}
function fetch_row($result) {
    return false;
}
function dbescape($s) {
    return addslashes((string) $s);
}

// Session path for status_start_session (cleaned up at end)
$session_tmp = $CFG->dirroot . '/lib/tmp';
if (!is_dir($session_tmp)) {
    @mkdir($session_tmp, 0777, true);
}
$GLOBALS['_test_session_tmp'] = $session_tmp;

require_once dirname(__DIR__) . '/lib/status_lib.php';

// Start session once before any CLI output so status_start_session() is quiet.
if (session_status() !== PHP_SESSION_ACTIVE) {
    @ini_set('session.save_path', $session_tmp);
    @session_start();
}

// ---------------------------------------------------------------------------
// Output helpers (same pattern as test_billing_math.php)
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

function assert_eq(string $label, $expected, $actual, float $tol = 0.0): void {
    if (is_float($expected) || is_float($actual) || $tol > 0) {
        $ok = abs((float) $expected - (float) $actual) <= $tol;
        $detail = $ok ? '' : "expected $expected, got $actual";
    } else {
        $ok = $expected === $actual;
        $detail = $ok ? '' : 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
    record_result($label, $ok, $detail);
}

function assert_true(string $label, bool $cond, string $detail = ''): void {
    record_result($label, $cond, $cond ? '' : ($detail !== '' ? $detail : 'condition was false'));
}

function assert_false(string $label, bool $cond, string $detail = ''): void {
    record_result($label, !$cond, !$cond ? '' : ($detail !== '' ? $detail : 'condition was true'));
}

function assert_in_array(string $label, $needle, array $haystack): void {
    $ok = in_array($needle, $haystack, true);
    record_result($label, $ok, $ok ? '' : var_export($needle, true) . ' not in array');
}

function assert_array_has_key(string $label, $key, array $arr): void {
    $ok = array_key_exists($key, $arr);
    record_result($label, $ok, $ok ? '' : "missing key $key");
}

// ---------------------------------------------------------------------------
// Helpers used only by these tests
// ---------------------------------------------------------------------------

/**
 * Build a Unix timestamp for a local civil time in $CFG->timezone,
 * expressed in the same UTC-epoch convention the app uses.
 */
function test_local_timestamp(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0): int {
    global $CFG;
    $dt = new DateTime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second), new DateTimeZone($CFG->timezone));
    return $dt->getTimestamp();
}

function test_clear_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
    } else {
        // Ensure $_SESSION exists for direct assignment in tests
        if (!isset($_SESSION)) {
            $_SESSION = [];
        } else {
            $_SESSION = [];
        }
    }
    unset($GLOBALS['STATUS_FORCE_RELEASED_ONLY']);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

// --- Catalog / configuration globals ---

start_section('STATUS_MOODS catalog');
assert_true('STATUS_MOODS is non-empty array', is_array($GLOBALS['STATUS_MOODS']) && count($GLOBALS['STATUS_MOODS']) > 0);
foreach (['mood_happy', 'mood_sad', 'mood_angry', 'mood_tired', 'mood_energetic', 'mood_calm', 'mood_silly', 'mood_sick'] as $k) {
    assert_array_has_key("mood key $k", $k, $GLOBALS['STATUS_MOODS']);
    assert_true("$k has label", isset($GLOBALS['STATUS_MOODS'][$k]['label']) && $GLOBALS['STATUS_MOODS'][$k]['label'] !== '');
    assert_true("$k has emoji", isset($GLOBALS['STATUS_MOODS'][$k]['emoji']) && $GLOBALS['STATUS_MOODS'][$k]['emoji'] !== '');
    assert_true("$k has color", isset($GLOBALS['STATUS_MOODS'][$k]['color']) && $GLOBALS['STATUS_MOODS'][$k]['color'] !== '');
}
end_section();

start_section('STATUS_POTTY_TYPES catalog');
assert_true('STATUS_POTTY_TYPES is non-empty', is_array($GLOBALS['STATUS_POTTY_TYPES']) && count($GLOBALS['STATUS_POTTY_TYPES']) >= 4);
foreach (['pt_wet', 'pt_dirty', 'pt_potty', 'pt_accident'] as $k) {
    assert_array_has_key("potty key $k", $k, $GLOBALS['STATUS_POTTY_TYPES']);
}
assert_true('pt_wet asks_cream', !empty($GLOBALS['STATUS_POTTY_TYPES']['pt_wet']['asks_cream']));
assert_true('pt_dirty asks_cream', !empty($GLOBALS['STATUS_POTTY_TYPES']['pt_dirty']['asks_cream']));
assert_false('pt_potty asks_cream', !empty($GLOBALS['STATUS_POTTY_TYPES']['pt_potty']['asks_cream']));
assert_true('pt_potty asks_potty', !empty($GLOBALS['STATUS_POTTY_TYPES']['pt_potty']['asks_potty']));
assert_false('pt_wet asks_potty', !empty($GLOBALS['STATUS_POTTY_TYPES']['pt_wet']['asks_potty']));
end_section();

start_section('STATUS_MEALS / RATINGS / ACTIVITIES catalogs');
assert_true('meals has breakfast/lunch/dinner', isset($GLOBALS['STATUS_MEALS']['breakfast'], $GLOBALS['STATUS_MEALS']['lunch'], $GLOBALS['STATUS_MEALS']['dinner']));
assert_true('meal ratings has ate_well/ate_ok/not_hungry', isset($GLOBALS['STATUS_MEAL_RATINGS']['ate_well'], $GLOBALS['STATUS_MEAL_RATINGS']['ate_ok'], $GLOBALS['STATUS_MEAL_RATINGS']['not_hungry']));
assert_true('activities is non-empty', is_array($GLOBALS['STATUS_ACTIVITIES']) && count($GLOBALS['STATUS_ACTIVITIES']) >= 5);
foreach (['belly_time', 'art', 'books', 'free_play', 'learning'] as $k) {
    assert_array_has_key("activity $k", $k, $GLOBALS['STATUS_ACTIVITIES']);
}
end_section();

start_section('Bottle / Nap / Incident catalogs');
assert_eq('STATUS_BOTTLE_TAG', 'bottle', $GLOBALS['STATUS_BOTTLE_TAG']);
assert_eq('STATUS_BOTTLE_MAX_MONTHS', 16, $GLOBALS['STATUS_BOTTLE_MAX_MONTHS']);
assert_true('STATUS_BOTTLE_OUNCES contains 1..8', $GLOBALS['STATUS_BOTTLE_OUNCES'] === [1, 2, 3, 4, 5, 6, 7, 8]);
assert_eq('STATUS_NAP_TAG', 'nap', $GLOBALS['STATUS_NAP_TAG']);
assert_eq('STATUS_NAP_MAX_MONTHS', 24, $GLOBALS['STATUS_NAP_MAX_MONTHS']);
assert_true('STATUS_NAP_DURATIONS has 30/60/90/120', $GLOBALS['STATUS_NAP_DURATIONS'] === [30, 60, 90, 120]);
assert_eq('nap window start', 13, $GLOBALS['STATUS_NAP_WINDOW']['start_hour']);
assert_eq('nap window end', 15, $GLOBALS['STATUS_NAP_WINDOW']['end_hour']);
assert_true('nap ratings present', isset($GLOBALS['STATUS_NAP_RATINGS']['slept_well'], $GLOBALS['STATUS_NAP_RATINGS']['slept_ok'], $GLOBALS['STATUS_NAP_RATINGS']['restless']));
assert_true('incident types present', isset($GLOBALS['STATUS_INCIDENT_TYPES']['inc_hurt'], $GLOBALS['STATUS_INCIDENT_TYPES']['inc_fever']));
assert_true('photo extensions include jpg/png', in_array('jpg', $GLOBALS['STATUS_PHOTO_EXTENSIONS'], true) && in_array('png', $GLOBALS['STATUS_PHOTO_EXTENSIONS'], true));
end_section();

start_section('STATUS_QUICK_NOTES');
assert_true('need_diapers quick note', isset($GLOBALS['STATUS_QUICK_NOTES']['need_diapers']));
assert_eq('need_diapers notify=2 (persist)', 2, $GLOBALS['STATUS_QUICK_NOTES']['need_diapers']['notify']);
assert_eq('clothing_pickup notify=1', 1, $GLOBALS['STATUS_QUICK_NOTES']['clothing_pickup']['notify']);
assert_true('quick notes have non-empty text', $GLOBALS['STATUS_QUICK_NOTES']['need_diapers']['text'] !== '');
end_section();

// --- Pure string / hash helpers ---

start_section('status_slugify');
assert_eq('simple word', 'hello', status_slugify('Hello'));
assert_eq('spaces and punctuation stripped', 'jollygiraffes', status_slugify('Jolly Giraffes!'));
assert_eq('mixed case + numbers', 'abc123', status_slugify('  AbC-123  '));
assert_eq('empty string', '', status_slugify(''));
assert_eq('only symbols', '', status_slugify('!!!@@@'));
// Non-ASCII bytes are stripped by the ascii-only regex (multi-byte letters
// may leave partial ASCII residue depending on encoding).
assert_eq('unicode stripped to ascii residue', 'testcaf', status_slugify('test café'));
end_section();

start_section('status_normalize_notify');
assert_eq('true → 1', 1, status_normalize_notify(true));
assert_eq('1 → 1', 1, status_normalize_notify(1));
assert_eq("'1' → 1", 1, status_normalize_notify('1'));
assert_eq('2 → 2', 2, status_normalize_notify(2));
assert_eq("'2' → 2", 2, status_normalize_notify('2'));
assert_eq('0 → 0', 0, status_normalize_notify(0));
assert_eq('false → 0', 0, status_normalize_notify(false));
assert_eq('null → 0', 0, status_normalize_notify(null));
assert_eq("'foo' → 0", 0, status_normalize_notify('foo'));
assert_eq('3 → 0', 0, status_normalize_notify(3));
end_section();

start_section('status_push_identifier');
$ep = 'https://fcm.googleapis.com/fcm/send/abc123';
$id = status_push_identifier($ep);
assert_eq('sha256 hex length 64', 64, strlen($id));
assert_true('hex charset', (bool) preg_match('/^[a-f0-9]{64}$/', $id));
assert_eq('deterministic', $id, status_push_identifier($ep));
assert_true('different endpoint → different id', status_push_identifier($ep) !== status_push_identifier($ep . 'x'));
end_section();

start_section('status_generate_link_hash');
$h1 = status_generate_link_hash();
$h2 = status_generate_link_hash();
assert_eq('hash length 32 (16 bytes hex)', 32, strlen($h1));
assert_true('hex charset', (bool) preg_match('/^[a-f0-9]{32}$/', $h1));
assert_true('two calls differ', $h1 !== $h2);
end_section();

start_section('status_incident_note_tag_title');
assert_eq('inc_hurt → behavior', 'behavior', status_incident_note_tag_title('inc_hurt'));
assert_eq('inc_gotbit → Injury', 'Injury', status_incident_note_tag_title('inc_gotbit'));
assert_eq('inc_bandaid → Medical', 'Medical', status_incident_note_tag_title('inc_bandaid'));
assert_eq('inc_fever → Medical', 'Medical', status_incident_note_tag_title('inc_fever'));
assert_eq('unknown type → Injury default', 'Injury', status_incident_note_tag_title('inc_unknown_xyz'));
end_section();

// --- Age helpers ---

start_section('status_age_months');
// Use plain UTC epochs so DateTime::diff is not skewed by local TZ display.
$ref = gmmktime(12, 0, 0, 6, 15, 2024);
$birth_exact_12m = gmmktime(12, 0, 0, 6, 15, 2023);
$birth_6m = gmmktime(12, 0, 0, 12, 15, 2023);
$birth_0 = gmmktime(12, 0, 0, 6, 15, 2024);
$birth_future = gmmktime(0, 0, 0, 1, 1, 2025);

assert_eq('exactly 12 months', 12, status_age_months($birth_exact_12m, $ref));
assert_eq('exactly 6 months', 6, status_age_months($birth_6m, $ref));
assert_eq('same day → 0 months', 0, status_age_months($birth_0, $ref));
assert_true('future birth → null', status_age_months($birth_future, $ref) === null);
assert_true('zero/invalid birth → null', status_age_months(0, $ref) === null);
assert_true('negative birth → null', status_age_months(-1, $ref) === null);

// 24 months boundary
$birth_24m = gmmktime(12, 0, 0, 6, 15, 2022);
assert_eq('exactly 24 months', 24, status_age_months($birth_24m, $ref));
$birth_25m = gmmktime(12, 0, 0, 5, 15, 2022);
assert_eq('25 months', 25, status_age_months($birth_25m, $ref));
end_section();

start_section('status_eligible_for_bottles / naptime');
// bottles: < 16 months
assert_true('6mo eligible bottles', status_eligible_for_bottles($birth_6m, $ref));
assert_true('12mo eligible bottles', status_eligible_for_bottles($birth_exact_12m, $ref));
$birth_16m = gmmktime(12, 0, 0, 2, 15, 2023); // exactly 16 months before 2024-06-15
assert_eq('16mo age check', 16, status_age_months($birth_16m, $ref));
assert_false('16mo NOT eligible bottles', status_eligible_for_bottles($birth_16m, $ref));
assert_false('24mo NOT eligible bottles', status_eligible_for_bottles($birth_24m, $ref));
assert_false('invalid birth not eligible bottles', status_eligible_for_bottles(0, $ref));

// nap log buttons: < 24 months
assert_true('6mo eligible naptime', status_eligible_for_naptime($birth_6m, $ref));
assert_true('12mo eligible naptime', status_eligible_for_naptime($birth_exact_12m, $ref));
$birth_23m = gmmktime(12, 0, 0, 7, 15, 2022);
assert_true('23mo eligible naptime', status_eligible_for_naptime($birth_23m, $ref));
assert_false('24mo NOT eligible naptime (uses rating instead)', status_eligible_for_naptime($birth_24m, $ref));
assert_false('invalid birth not eligible naptime', status_eligible_for_naptime(0, $ref));
end_section();

// --- Daykey / timelog ---

start_section('status_daykey');
// Fix "now" to a known local afternoon
$fixed_now = test_local_timestamp(2024, 3, 10, 14, 30, 0); // EDT or EST depending on date
$GLOBALS['_test_now'] = $fixed_now;

$daykey = status_daykey($fixed_now);
// daykey should be local midnight expressed as UTC epoch (get_today style)
$expected_midnight_local = test_local_timestamp(2024, 3, 10, 0, 0, 0);
// status_daykey builds midnight in local tz then reinterprets the date string as UTC
$local = new DateTime('now', new DateTimeZone($CFG->timezone));
$local->setTimestamp($fixed_now);
$midnight_local = new DateTime($local->format('m/d/Y'), new DateTimeZone($CFG->timezone));
$utc_midnight = new DateTime($midnight_local->format('m/d/Y'), new DateTimeZone('UTC'));
$expected_daykey = $utc_midnight->getTimestamp();

assert_eq('daykey matches local-midnight-as-UTC convention', $expected_daykey, $daykey);
assert_eq('status_daykey() without arg uses get_timestamp()', status_daykey($fixed_now), status_daykey());

// Same day different times → same daykey
$later = test_local_timestamp(2024, 3, 10, 23, 59, 0);
assert_eq('same calendar day → same daykey', status_daykey($fixed_now), status_daykey($later));

// Next calendar day → different daykey
$next = test_local_timestamp(2024, 3, 11, 0, 30, 0);
assert_true('next day → different daykey', status_daykey($fixed_now) !== status_daykey($next));
$GLOBALS['_test_now'] = null;
end_section();

start_section('status_clamp_timelog');
$GLOBALS['_test_now'] = test_local_timestamp(2024, 7, 4, 15, 0, 0);
$now = get_timestamp();

assert_eq('zero → now', $now, status_clamp_timelog(0));
assert_eq('falsey → now', $now, status_clamp_timelog(null));

$same_day = test_local_timestamp(2024, 7, 4, 10, 15, 0);
assert_eq('same day earlier → kept', $same_day, status_clamp_timelog($same_day));

$other_day = test_local_timestamp(2024, 7, 3, 12, 0, 0);
assert_eq('other day → now', $now, status_clamp_timelog($other_day));

// status_clamp_timelog compares calendar days via DateTime("@ts") which is
// always UTC, so use a future time that is still the same UTC day as $now.
$future_same_utc_day = $now + 3600; // one hour later, still same UTC day for midday
assert_eq('same UTC day future → kept', $future_same_utc_day, status_clamp_timelog($future_same_utc_day));
$GLOBALS['_test_now'] = null;
end_section();

start_section('status_time_from_hm / status_resolve_timelog');
$GLOBALS['_test_now'] = test_local_timestamp(2024, 8, 20, 12, 0, 0);
$day = status_daykey();
$offset = get_offset();

// 9:30 local → day + seconds - offset, then clamped
$expected_930 = status_clamp_timelog($day + (9 * 3600) + (30 * 60) - $offset);
assert_eq('9:30 local → expected timelog', $expected_930, status_time_from_hm(9, 30));

$expected_0 = status_clamp_timelog($day + 0 - $offset);
assert_eq('0:00 local', $expected_0, status_time_from_hm(0, 0));

assert_eq('resolve false → now', get_timestamp(), status_resolve_timelog(false, false));
assert_eq('resolve null hour → now', get_timestamp(), status_resolve_timelog(null, 0));
assert_eq('resolve empty hour → now', get_timestamp(), status_resolve_timelog('', 0));
assert_eq('resolve 14:45 → time_from_hm', status_time_from_hm(14, 45), status_resolve_timelog(14, 45));
$GLOBALS['_test_now'] = null;
end_section();

start_section('status_naptime_window_now');
// Window is 13:00–15:00 local
$GLOBALS['_test_now'] = test_local_timestamp(2024, 9, 1, 13, 0, 0);
assert_true('13:00 is in window', status_naptime_window_now());
$GLOBALS['_test_now'] = test_local_timestamp(2024, 9, 1, 14, 59, 0);
assert_true('14:59 is in window', status_naptime_window_now());
$GLOBALS['_test_now'] = test_local_timestamp(2024, 9, 1, 15, 0, 0);
assert_false('15:00 is outside window', status_naptime_window_now());
$GLOBALS['_test_now'] = test_local_timestamp(2024, 9, 1, 12, 59, 0);
assert_false('12:59 is outside window', status_naptime_window_now());
$GLOBALS['_test_now'] = test_local_timestamp(2024, 9, 1, 9, 0, 0);
assert_false('morning outside window', status_naptime_window_now());
$GLOBALS['_test_now'] = null;
end_section();

// --- Session / role / release filters (no DB) ---

start_section('status role / viewer helpers');
test_clear_session();
// Without starting a real session, status_start_session may start one —
// call it so $_SESSION works consistently
status_start_session();
test_clear_session();

assert_true('no role → not parent viewer', !status_is_parent_viewer());
assert_true('no role → current_role falsy', !status_current_role());
assert_true('no role → current_aid falsy', !status_current_aid());

$_SESSION['status_role'] = 'parent';
$_SESSION['status_aid']  = 42;
assert_true('parent role → is_parent_viewer', status_is_parent_viewer());
assert_eq('current_role parent', 'parent', status_current_role());
assert_eq('current_aid 42', 42, status_current_aid());
assert_true('parent → view_released_only', status_view_released_only());
assert_eq('released filter for parent', ' AND released=1', status_released_filter());
assert_eq('released filter with alias', ' AND e.released=1', status_released_filter('e.'));

$_SESSION['status_role'] = 'admin';
assert_false('admin → not parent viewer', status_is_parent_viewer());
assert_false('admin → view_released_only false (default)', status_view_released_only());
assert_eq('admin released filter empty', '', status_released_filter());

$GLOBALS['STATUS_FORCE_RELEASED_ONLY'] = true;
assert_true('admin + FORCE_RELEASED_ONLY → view_released_only', status_view_released_only());
assert_eq('forced filter', ' AND released=1', status_released_filter());
unset($GLOBALS['STATUS_FORCE_RELEASED_ONLY']);

// logout clears session keys
$_SESSION['status_role'] = 'admin';
$_SESSION['status_aid']  = 1;
status_logout();
assert_true('after logout role cleared', !status_current_role());
assert_true('after logout aid cleared', !status_current_aid());
end_section();

start_section('PIN attempt rate limiting');
test_clear_session();
status_start_session();
test_clear_session();

assert_false('fresh session not rate-limited', status_too_many_attempts());

for ($i = 0; $i < 5; $i++) {
    status_register_failed_attempt();
}
assert_true('5 failures → rate limited', status_too_many_attempts());

// Simulate lockout window expired
$_SESSION['status_lastfail'] = time() - 61;
assert_false('after 60s cooldown → not rate limited (counter reset)', status_too_many_attempts());
assert_eq('attempts reset to 0 after cooldown check', 0, $_SESSION['status_attempts'] ?? -1);

status_register_failed_attempt();
status_register_failed_attempt();
status_register_success();
assert_eq('success clears attempts', 0, $_SESSION['status_attempts'] ?? -1);
assert_false('after success not rate limited', status_too_many_attempts());
end_section();

// ---------------------------------------------------------------------------
// Cleanup — restore globals / session so nothing leaks into later requests
// ---------------------------------------------------------------------------

$GLOBALS['_test_now'] = null;
test_clear_session();
unset($GLOBALS['STATUS_FORCE_RELEASED_ONLY']);

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    // Do not session_destroy() in web context if it would break the caller;
    // clearing data is enough for isolation. In CLI we can destroy.
    if ($is_cli) {
        @session_destroy();
    }
}

// Remove any session files we may have written under lib/tmp (best-effort)
if (!empty($GLOBALS['_test_session_tmp']) && is_dir($GLOBALS['_test_session_tmp'])) {
    foreach (glob($GLOBALS['_test_session_tmp'] . '/sess_*') as $f) {
        @unlink($f);
    }
}

// ---------------------------------------------------------------------------
// Summary / HTML output (mirrors test_billing_math.php)
// ---------------------------------------------------------------------------

end_section(); // safety if a section left open

$total   = $passed + $failed;
$all_ok  = ($failed === 0);
$status_text  = $all_ok ? 'ALL PASSED' : 'FAILURES';
$status_class = $all_ok ? 'pass' : 'fail';

if ($is_cli) {
    echo "\n----------------------------------------\n";
    echo $all_ok ? "ALL PASSED" : "FAILURES";
    echo " — $passed passed, $failed failed, $total total\n";
    exit($all_ok ? 0 : 1);
}

// HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Status Lib Tests</title>
<style>
  :root {
    --bg: #0f1419; --card: #1a2332; --text: #e7ecf3; --muted: #8b9bb4;
    --pass: #3dd68c; --fail: #ff6b6b; --border: #2a3548;
  }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
         background: var(--bg); color: var(--text); line-height: 1.45; }
  .wrap { max-width: 720px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
  h1 { font-size: 1.35rem; margin: 0 0 0.25rem; }
  .subtitle { color: var(--muted); font-size: 0.9rem; margin: 0 0 1.25rem; }
  .summary { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
             background: var(--card); border: 1px solid var(--border);
             border-radius: 10px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; }
  .summary.pass { border-color: color-mix(in srgb, var(--pass) 40%, var(--border)); }
  .summary.fail { border-color: color-mix(in srgb, var(--fail) 40%, var(--border)); }
  .badge { font-weight: 700; letter-spacing: 0.04em; font-size: 0.85rem; }
  .summary.pass .badge { color: var(--pass); }
  .summary.fail .badge { color: var(--fail); }
  .counts { color: var(--muted); font-size: 0.9rem; }
  details { background: var(--card); border: 1px solid var(--border);
            border-radius: 10px; margin-bottom: 0.6rem; overflow: hidden; }
  summary { cursor: pointer; padding: 0.7rem 1rem; display: flex;
            justify-content: space-between; align-items: center; gap: 0.75rem;
            list-style: none; user-select: none; }
  summary::-webkit-details-marker { display: none; }
  .sec-title { font-weight: 600; }
  .sec-meta { font-size: 0.8rem; color: var(--muted); white-space: nowrap; }
  .ok { color: var(--pass); } .bad { color: var(--fail); }
  ul.results { list-style: none; margin: 0; padding: 0 0.75rem 0.75rem; }
  li.result { display: grid; grid-template-columns: auto 1fr; gap: 0.5rem 0.75rem;
              padding: 0.4rem 0.25rem; border-top: 1px solid var(--border); font-size: 0.9rem; }
  .tag { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em;
         padding: 0.15rem 0.4rem; border-radius: 4px; align-self: start; }
  .tag.pass { background: color-mix(in srgb, var(--pass) 18%, transparent); color: var(--pass); }
  .tag.fail { background: color-mix(in srgb, var(--fail) 18%, transparent); color: var(--fail); }
  .label { word-break: break-word; }
  .detail { grid-column: 2; color: var(--fail); font-size: 0.8rem; margin-top: 0.15rem; }
  footer { margin-top: 1.5rem; color: var(--muted); font-size: 0.8rem; }
  code { background: rgba(255,255,255,0.06); padding: 0.1em 0.35em; border-radius: 4px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Status Lib Tests</h1>
  <p class="subtitle">Pure helpers from <code>lib/status_lib.php</code> (no database) — catalogs, slugify, notify, daykey/timelog, age eligibility, session role filters</p>

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

  <footer>Run via CLI with <code>php tests/test_status.php</code> for plain-text output and exit codes. Session/tmp artifacts are cleaned up after the run.</footer>
</div>
</body>
</html>
<?php
exit($all_ok ? 0 : 1);
