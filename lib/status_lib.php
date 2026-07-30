<?php

/***************************************************************************
* status_lib.php - Daily Status Report feature
* -------------------------------------------------------------------------
* Parent link: https://.../status?c=FAMILYCODE (PIN-protected, read only)
* Admin/staff: https://.../status (admin PIN, edit view)
*
* Storage: reuses existing tables instead of adding parallel log tables.
*   - `events` gains chid, aid, daykey, timelog, plus cream/peed/pooped for
*     Potty Time. Moods, bottles, and Potty Time are all just rows here,
*     distinguished by `tag`. Old 'in'/'out' definition rows are untouched
*     (chid/aid/daykey stay 0, and every query here filters by a real chid).
*     Old tally tags (diaper/potty_success/potty_accident/clothing_change)
*     are dormant - left in place, no longer read or shown.
*   - `notes` gains daykey, so status-page notes are scoped to a child+day.
*     Only non-zero daykey rows are ever shown, so pre-existing notes are
*     never exposed on the parent report.
*   - `documents` gains evid, linking an attachment to one specific
*     Potty Time entry rather than just to the child.
*   - `accounts` gains link_code, for the shareable parent link.
*   - `status_menu` is the one genuinely new table: per-child/day menu
*     text, one row per meal (breakfast/lunch/dinner).
*
* Depends on lib/dblib.php and lib/timelib.php (loaded via lib/header.php).
***************************************************************************/

if (!isset($STATUSLIB)) {
    $STATUSLIB = true;

    // Mood options. Keys are stored as event tags - don't rename existing
    // keys once you have data.
    $GLOBALS['STATUS_MOODS'] = [
        'mood_happy'     => ['label' => 'Happy',     'emoji' => '😊', 'color' => '#3f8c42'],
        'mood_sad'       => ['label' => 'Sad',       'emoji' => '😢', 'color' => '#495fb8'],
        'mood_angry'     => ['label' => 'Angry',     'emoji' => '😠', 'color' => '#ff0000'],
        'mood_tired'     => ['label' => 'Tired',     'emoji' => '😴', 'color' => '#868E96'],
        'mood_energetic' => ['label' => 'Energetic', 'emoji' => '⚡', 'color' => '#2c297b'],
        'mood_calm'      => ['label' => 'Calm',      'emoji' => '😌', 'color' => '#22B8CF'],
        'mood_silly'     => ['label' => 'Silly',     'emoji' => '🤪', 'color' => '#e89292'],
        'mood_sick'      => ['label' => 'Not Well',  'emoji' => '🤒', 'color' => '#45c249'],
    ];

    // Potty Time: timestamped entries (like moods). Type determines extra
    // fields - Wet/Dirty ask about cream, Used Potty asks peed/pooped.
    // Every entry can also have attachments.
    $GLOBALS['STATUS_POTTY_TYPES'] = [
        'pt_wet'      => ['label' => 'Wet Diaper',   'emoji' => '💧', 'color' => '#33b0e6', 'asks_cream' => true,  'asks_potty' => false],
        'pt_dirty'    => ['label' => 'Dirty Diaper', 'emoji' => '💩', 'color' => '#51463c', 'asks_cream' => true,  'asks_potty' => false],
        'pt_potty'    => ['label' => 'Used Potty',   'emoji' => '🚽', 'color' => '#6826ae', 'asks_cream' => false, 'asks_potty' => true],
        'pt_accident' => ['label' => 'Accident',     'emoji' => '🫣', 'color' => '#e8852e', 'asks_cream' => false, 'asks_potty' => false],
    ];

    // Quick-tap notes next to Potty Time. Writes a note tagged "Request"
    // (auto-created on notes_tags if needed).
    $GLOBALS['STATUS_QUICK_NOTES'] = [
        'need_diapers' => [
            'label'     => 'Need Diapers',
            'emoji'     => '🚼',
            'tag_title' => 'Request',
            'text'      => "Running low on diapers - please bring more.",
        ],
        'clothing_pickup' => [
            'label'     => 'Clothing Change',
            'emoji'     => '👕',
            'tag_title' => 'Request',
            'text'      => "Needed a clothing change today - please pick up their dirty clothes.",
        ],
    ];

    // Three menu slots per child/day - don't rename existing keys.
    $GLOBALS['STATUS_MEALS'] = [
        'breakfast' => ['label' => 'Breakfast', 'emoji' => '🍳'],
        'lunch'     => ['label' => 'Lunch',     'emoji' => '🥪'],
        'dinner'    => ['label' => 'Dinner',    'emoji' => '🍽️'],
    ];

    // Bottles: a timestamped tap like moods, only shown under this age.
    $GLOBALS['STATUS_BOTTLE_TAG']        = 'bottle';
    $GLOBALS['STATUS_BOTTLE_INFO']       = ['label' => 'Bottle', 'emoji' => '🍼', 'color' => '#4DABF7'];
    $GLOBALS['STATUS_BOTTLE_MAX_MONTHS'] = 16;
    $GLOBALS['STATUS_BOTTLE_OUNCES']     = [1, 2, 3, 4, 5, 6, 7, 8];

    // Incidents Quick Report: one-tap injury/incident logging. Each type
    // has a default note staff can edit further; attachments reuse the
    // same events+documents linkage as Potty Time.
    $GLOBALS['STATUS_INCIDENT_TYPES'] = [
        'inc_bit'     => ['label' => 'Bit Someone',     'emoji' => '😬', 'color' => '#c62a2a', 'default_note' => 'Bit another child.'],
        'inc_gotbit'  => ['label' => 'Bitten',          'emoji' => '😫', 'color' => '#c65bbd', 'default_note' => 'Was bitten by another child.'],
        'inc_booboo'  => ['label' => 'Boo Boo',         'emoji' => '🤕', 'color' => '#926969', 'default_note' => 'Had a minor boo-boo.'],
        'inc_bandaid' => ['label' => 'Band-Aid',        'emoji' => '🩹', 'color' => '#6f5dff', 'default_note' => 'Needed a band-aid.'],
        'inc_hurt'    => ['label' => 'Hurt Someone',    'emoji' => '👊', 'color' => '#E03131', 'default_note' => 'Hurt another child.'],
    ];

    // Naptime: shown 1pm-3pm for children over this age. Duration
    // buttons backdate the entry (nap already ended when tapped).
    $GLOBALS['STATUS_NAP_TAG']        = 'nap';
    $GLOBALS['STATUS_NAP_DURATIONS']  = [30, 60, 90, 120];
    $GLOBALS['STATUS_NAP_WINDOW']     = ['start_hour' => 13, 'end_hour' => 15];
    $GLOBALS['STATUS_NAP_MAX_MONTHS'] = 24;

    // -----------------------------------------------------------------
    // Migration - creates/alters tables on first run. Safe to call every
    // request. SHOW COLUMNS/INDEX return an empty-but-truthy result when
    // nothing matches (unlike SELECT), so existence checks below go
    // through information_schema instead.
    // -----------------------------------------------------------------
    function status_column_exists($table, $column) {
        return (bool) get_db_row("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='" . dbescape($table) . "' AND column_name='" . dbescape($column) . "'");
    }

    function status_index_exists($table, $indexname) {
        return (bool) get_db_row("SELECT index_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='" . dbescape($table) . "' AND index_name='" . dbescape($indexname) . "'");
    }

    function status_migrate() {

        // events: log columns (chid/aid/daykey/timelog) plus Potty Time flags
        if (!status_column_exists('events', 'chid')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN chid int(11) NOT NULL DEFAULT '0'");
        }
        if (!status_column_exists('events', 'aid')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN aid int(11) NOT NULL DEFAULT '0'");
        }
        if (!status_column_exists('events', 'daykey')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN daykey int(11) NOT NULL DEFAULT '0'");
        }
        if (!status_column_exists('events', 'timelog')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN timelog int(11) NOT NULL DEFAULT '0'");
        }
        if (!status_index_exists('events', 'chid_day_tag')) {
            execute_db_sql("ALTER TABLE events ADD KEY chid_day_tag (chid,daykey,tag)");
        }

        // Potty Time flags - only the ones relevant to a type are ever set
        if (!status_column_exists('events', 'cream')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN cream tinyint(1) NOT NULL DEFAULT '0'");
        }
        if (!status_column_exists('events', 'peed')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN peed tinyint(1) NOT NULL DEFAULT '0'");
        }
        if (!status_column_exists('events', 'pooped')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN pooped tinyint(1) NOT NULL DEFAULT '0'");
        }

        // events.note (Incidents free text) and events.amount (generic
        // numeric field: Nap minutes, Bottle ounces)
        if (!status_column_exists('events', 'note')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN note text COLLATE utf8_unicode_ci NOT NULL DEFAULT ('')");
        }
        if (!status_column_exists('events', 'amount')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN amount int(11) NOT NULL DEFAULT '0'");
        }

        // documents.evid links an attachment to one Potty Time entry
        if (!status_column_exists('documents', 'evid')) {
            execute_db_sql("ALTER TABLE documents ADD COLUMN evid int(11) NOT NULL DEFAULT '0'");
            execute_db_sql("ALTER TABLE documents ADD KEY evid (evid)");
        }

        // notes.daykey scopes status-page notes to a child + day
        if (!status_column_exists('notes', 'daykey')) {
            execute_db_sql("ALTER TABLE notes ADD COLUMN daykey int(11) NOT NULL DEFAULT '0'");
            execute_db_sql("ALTER TABLE notes ADD KEY chid_day (chid,daykey)");
        }

        // accounts.link_code is the shareable parent link (?c=Smith)
        if (!status_column_exists('accounts', 'link_code')) {
            execute_db_sql("ALTER TABLE accounts ADD COLUMN link_code varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL");
            execute_db_sql("ALTER TABLE accounts ADD UNIQUE KEY link_code (link_code)");
        }

        // status_menu: the one new table (fresh installs get meal + the
        // 3-way unique key directly)
        execute_db_sql("
            CREATE TABLE IF NOT EXISTS `status_menu` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `chid` int(11) NOT NULL,
              `daykey` int(11) NOT NULL,
              `meal` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'breakfast',
              `menu` text COLLATE utf8_unicode_ci NOT NULL,
              `timelog` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `chid_day_meal` (`chid`,`daykey`,`meal`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

        // Upgrade path: older installs had status_menu without `meal`;
        // existing rows become that day's "breakfast" (the column default).
        if (!status_column_exists('status_menu', 'meal')) {
            execute_db_sql("ALTER TABLE status_menu ADD COLUMN meal varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'breakfast'");
            if (status_index_exists('status_menu', 'chid_day')) {
                execute_db_sql("ALTER TABLE status_menu DROP INDEX chid_day");
            }
            if (!status_index_exists('status_menu', 'chid_day_meal')) {
                execute_db_sql("ALTER TABLE status_menu ADD UNIQUE KEY chid_day_meal (chid,daykey,meal)");
            }
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------
    function status_daykey($timestamp = false) {
        // Midnight in the configured timezone, matching get_today()'s convention.
        global $CFG;
        $timestamp = $timestamp ? $timestamp : get_timestamp();
        $local = new DateTime("now", new DateTimeZone($CFG->timezone));
        $local->setTimestamp($timestamp);
        $midnight = new DateTime($local->format("m/d/Y"), new DateTimeZone($CFG->timezone));
        $utc = new DateTime($midnight->format("m/d/Y"), new DateTimeZone("UTC"));
        return $utc->getTimestamp();
    }

    // Falls back to "now" if $timelog is on a different day than today or
    // more than a few minutes in the future. status_daykey() is in the
    // same "shifted" convention display_time() uses (local wall-clock
    // expressed as if it were UTC), so $timelog has to be shifted the
    // same way before comparing against it - comparing raw UTC directly
    // against $day would miscategorize evening times near local midnight.
    function status_clamp_timelog($timelog) {
        $now = get_timestamp();
        $timelog = intval($timelog);
        if (!$timelog) {
            return $now;
        }
        $day = status_daykey();
        $shifted = $timelog + get_offset();
        if ($shifted < $day || $shifted >= $day + 86400) {
            return $now;
        }
        if ($timelog > $now + 300) {
            return $now;
        }
        return $timelog;
    }

    // Converts a local HH:MM (today) to the raw UTC timelog this app
    // stores. display_time()/get_offset() (timelib.php) show a time by
    // adding the offset to the raw timestamp and formatting as if it were
    // UTC, so building one from a chosen HH:MM has to subtract that offset
    // back out.
    function status_time_from_hm($hour, $minute) {
        $day = status_daykey();
        $offset = get_offset();
        $seconds = (intval($hour) * 3600) + (intval($minute) * 60);
        return status_clamp_timelog($day + $seconds - $offset);
    }

    // Resolves the timelog for a timed event: a specific HH:MM if given,
    // otherwise now.
    function status_resolve_timelog($hour = false, $minute = false) {
        if ($hour === false || $hour === null || $hour === '') {
            return get_timestamp();
        }
        return status_time_from_hm($hour, $minute);
    }

    function status_slugify($text) {
        $text = strtolower(trim((string) $text));
        $text = preg_replace('/[^a-z0-9]+/', '', $text);
        return $text;
    }

    function status_ensure_link_code($aid) {
        $existing = get_db_field("link_code", "accounts", "aid='" . intval($aid) . "'");
        if (!empty($existing)) {
            return $existing;
        }
        $name = get_db_field("name", "accounts", "aid='" . intval($aid) . "'");
        $base = status_slugify($name);
        $base = $base ? $base : "family" . intval($aid);
        $code = $base;
        $i = 1;
        while (get_db_count("SELECT aid FROM accounts WHERE link_code='" . dbescape($code) . "'")) {
            $i++;
            $code = $base . $i;
        }
        execute_db_sql("UPDATE accounts SET link_code='" . dbescape($code) . "' WHERE aid='" . intval($aid) . "'");
        return $code;
    }

    function status_set_link_code($aid, $code) {
        $aid  = intval($aid);
        $code = status_slugify($code);
        if (!$code) {
            return ["success" => false, "message" => "Link code can't be empty."];
        }
        if (get_db_count("SELECT aid FROM accounts WHERE link_code='" . dbescape($code) . "' AND aid != '$aid'")) {
            return ["success" => false, "message" => "That link is already used by another family."];
        }
        execute_db_sql("UPDATE accounts SET link_code='" . dbescape($code) . "' WHERE aid='$aid'");
        return ["success" => true, "link_code" => $code];
    }

    function status_find_aid_by_code($code) {
        $code = status_slugify($code);
        if (!$code) {
            return false;
        }
        $row = get_db_row("SELECT aid FROM accounts WHERE link_code='" . dbescape($code) . "' AND deleted=0");
        return $row ? $row["aid"] : false;
    }

    // -----------------------------------------------------------------
    // Session / auth
    // -----------------------------------------------------------------
    function status_start_session() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    function status_too_many_attempts() {
        status_start_session();
        $attempts = isset($_SESSION['status_attempts']) ? $_SESSION['status_attempts'] : 0;
        $lastfail = isset($_SESSION['status_lastfail']) ? $_SESSION['status_lastfail'] : 0;
        if ($attempts >= 5 && (time() - $lastfail) < 60) {
            return true;
        }
        if ($attempts >= 5 && (time() - $lastfail) >= 60) {
            $_SESSION['status_attempts'] = 0;
        }
        return false;
    }

    function status_register_failed_attempt() {
        status_start_session();
        $_SESSION['status_attempts'] = (isset($_SESSION['status_attempts']) ? $_SESSION['status_attempts'] : 0) + 1;
        $_SESSION['status_lastfail'] = time();
    }

    function status_register_success() {
        status_start_session();
        $_SESSION['status_attempts'] = 0;
    }

    function status_login_parent($code, $pin) {
        if (status_too_many_attempts()) {
            return ["success" => false, "message" => "Too many attempts. Please wait a minute and try again."];
        }
        $aid = status_find_aid_by_code($code);
        if (!$aid) {
            status_register_failed_attempt();
            return ["success" => false, "message" => "We couldn't find that family link."];
        }
        $pin = preg_replace('/[^0-9]/', '', (string) $pin);
        $account = get_db_row("SELECT * FROM accounts WHERE aid='" . intval($aid) . "' AND deleted=0 AND password='" . dbescape($pin) . "'");
        if (!$account) {
            status_register_failed_attempt();
            return ["success" => false, "message" => "Incorrect PIN."];
        }
        status_register_success();
        status_start_session();
        $_SESSION['status_role'] = 'parent';
        $_SESSION['status_aid']  = $account['aid'];
        return ["success" => true];
    }

    function status_login_admin($pin) {
        if (status_too_many_attempts()) {
            return ["success" => false, "message" => "Too many attempts. Please wait a minute and try again."];
        }
        $pin = preg_replace('/[^0-9]/', '', (string) $pin);
        $account = get_db_row("SELECT * FROM accounts WHERE admin=1 AND deleted=0 AND password='" . dbescape($pin) . "'");
        if (!$account) {
            status_register_failed_attempt();
            return ["success" => false, "message" => "Incorrect PIN."];
        }
        status_register_success();
        status_start_session();
        $_SESSION['status_role'] = 'admin';
        $_SESSION['status_aid']  = $account['aid'];
        return ["success" => true];
    }

    function status_logout() {
        status_start_session();
        $_SESSION['status_role'] = null;
        $_SESSION['status_aid']  = null;
        session_unset();
    }

    function status_current_role() {
        status_start_session();
        return isset($_SESSION['status_role']) ? $_SESSION['status_role'] : false;
    }

    function status_current_aid() {
        status_start_session();
        return isset($_SESSION['status_aid']) ? $_SESSION['status_aid'] : false;
    }

    // Returns true if the currently logged-in session is allowed to view/edit this child.
    function status_can_access_child($chid) {
        $role = status_current_role();
        if ($role == 'admin') {
            return true;
        }
        if ($role == 'parent') {
            $aid = status_current_aid();
            $child_aid = get_db_field("aid", "children", "chid='" . intval($chid) . "' AND deleted=0");
            return $child_aid !== false && intval($child_aid) === intval($aid);
        }
        return false;
    }

    // -----------------------------------------------------------------
    // Age helpers (Bottles section only applies under a certain age)
    // -----------------------------------------------------------------
    function status_age_months($birthdate, $reference = false) {
        $birthdate = intval($birthdate);
        if ($birthdate <= 0) {
            return null; // no birthdate on file
        }
        $reference = $reference ? intval($reference) : get_timestamp();
        $birth = new DateTime();
        $birth->setTimestamp($birthdate);
        $ref = new DateTime();
        $ref->setTimestamp($reference);
        if ($birth > $ref) {
            return null;
        }
        $diff = $birth->diff($ref);
        return ($diff->y * 12) + $diff->m;
    }

    // Uses age as of $reference (not "now"), so a parent swiping through
    // history still sees Bottles for days before their child aged out.
    function status_eligible_for_bottles($birthdate, $reference = false) {
        global $STATUS_BOTTLE_MAX_MONTHS;
        $months = status_age_months($birthdate, $reference);
        return $months !== null && $months < $STATUS_BOTTLE_MAX_MONTHS;
    }

    // Whether the child is young enough to have the nap-logging buttons
    // (the 1pm-3pm notice itself shows for every child, regardless of age).
    function status_eligible_for_naptime($birthdate, $reference = false) {
        global $STATUS_NAP_MAX_MONTHS;
        $months = status_age_months($birthdate, $reference);
        return $months !== null && $months < $STATUS_NAP_MAX_MONTHS;
    }

    // Whether right now falls in the 1pm-3pm naptime window.
    function status_naptime_window_now() {
        global $CFG, $STATUS_NAP_WINDOW;
        $local = new DateTime('now', new DateTimeZone($CFG->timezone));
        $local->setTimestamp(get_timestamp());
        $hour = (int) $local->format('G');
        return $hour >= $STATUS_NAP_WINDOW['start_hour'] && $hour < $STATUS_NAP_WINDOW['end_hour'];
    }

    // -----------------------------------------------------------------
    // Children / families
    // -----------------------------------------------------------------
    function status_children_for_aid($aid) {
        $children = [];
        if ($result = get_db_result("SELECT * FROM children WHERE aid='" . intval($aid) . "' AND deleted=0 ORDER BY first,last")) {
            while ($row = fetch_row($result)) {
                $children[] = ["chid" => (int) $row["chid"], "name" => $row["first"] . " " . $row["last"]];
            }
        }
        return $children;
    }

    function status_all_children() {
        $children = [];
        $SQL = "SELECT c.*, a.name AS family_name
                  FROM children c
                  JOIN accounts a ON a.aid = c.aid
                 WHERE c.deleted = 0
                 ORDER BY a.name, c.first, c.last";
        if ($result = get_db_result($SQL)) {
            while ($row = fetch_row($result)) {
                $children[] = [
                    "chid"        => (int) $row["chid"],
                    "name"        => $row["first"] . " " . $row["last"],
                    "family_name" => $row["family_name"],
                    "aid"         => (int) $row["aid"],
                ];
            }
        }
        return $children;
    }

    function status_all_family_links() {
        $families = [];
        $SQL = "SELECT * FROM accounts WHERE deleted=0 AND admin=0 ORDER BY name";
        if ($result = get_db_result($SQL)) {
            while ($row = fetch_row($result)) {
                $families[] = [
                    "aid"       => (int) $row["aid"],
                    "name"      => $row["name"],
                    "link_code" => empty($row["link_code"]) ? status_ensure_link_code($row["aid"]) : $row["link_code"],
                ];
            }
        }
        return $families;
    }

    function status_notes_tags() {
        $tags = [];
        if ($result = get_db_result("SELECT * FROM notes_tags ORDER BY title")) {
            while ($row = fetch_row($result)) {
                $tags[] = [
                    "tag"       => $row["tag"],
                    "title"     => $row["title"],
                    "color"     => $row["color"],
                    "textcolor" => $row["textcolor"],
                ];
            }
        }
        return $tags;
    }

    // Attachments tied to one Potty Time entry (events.evid), not just the child.
    function status_get_attachments($chid, $evid) {
        global $CFG;
        $chid = intval($chid);
        $evid = intval($evid);
        $attachments = [];
        if ($result = get_db_result("SELECT * FROM documents WHERE chid='$chid' AND evid='$evid' ORDER BY timelog ASC")) {
            while ($row = fetch_row($result)) {
                $attachments[] = [
                    "did"      => (int) $row["did"],
                    "filename" => $row["filename"],
                    "url"      => $CFG->userfilesurl . "/children/$chid/" . $row["filename"],
                ];
            }
        }
        return $attachments;
    }

    // -----------------------------------------------------------------
    // Day data
    // -----------------------------------------------------------------
    function status_get_day($chid, $daykey = false) {
        global $STATUS_MOODS, $STATUS_POTTY_TYPES, $STATUS_MEALS, $STATUS_INCIDENT_TYPES, $STATUS_NAP_TAG, $CFG;

        if (!isset($PAGELIB)) {
            include_once($CFG->dirroot . '/lib/pagelib.php');
        }
        $chid = intval($chid);
        $daykey = $daykey ? intval($daykey) : status_daykey();

        $child = get_db_row("SELECT * FROM children WHERE chid='$chid'");
        if (!$child) {
            return false;
        }

        // Mood timeline
        $moods = [];
        $moodtags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_MOODS))) . "'";
        if ($result = get_db_result("SELECT * FROM events WHERE chid='$chid' AND daykey='$daykey' AND tag IN ($moodtags) ORDER BY timelog ASC, evid ASC")) {
            while ($row = fetch_row($result)) {
                $info = $STATUS_MOODS[$row["tag"]];
                $moods[] = [
                    "evid"  => (int) $row["evid"],
                    "mood"  => $row["tag"],
                    "label" => $info["label"],
                    "emoji" => $info["emoji"],
                    "color" => $info["color"],
                    "time"  => get_date("g:i a", display_time($row["timelog"])),
                    "hm"    => get_date("H:i", display_time($row["timelog"])),
                ];
            }
        }

        // Bottles - only relevant under STATUS_BOTTLE_MAX_MONTHS old
        $bottles = [];
        if ($result = get_db_result("SELECT * FROM events WHERE chid='$chid' AND daykey='$daykey' AND tag='" . dbescape($GLOBALS['STATUS_BOTTLE_TAG']) . "' ORDER BY timelog ASC, evid ASC")) {
            while ($row = fetch_row($result)) {
                $bottles[] = [
                    "evid"   => (int) $row["evid"],
                    "time"   => get_date("g:i a", display_time($row["timelog"])),
                    "hm"     => get_date("H:i", display_time($row["timelog"])),
                    "amount" => (int) $row["amount"],
                ];
            }
        }
        $show_bottles = status_eligible_for_bottles($child["birthdate"], $daykey);

        // Potty Time - editable, timestamped, with type-specific flags/attachments
        $potty = [];
        $pottytags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_POTTY_TYPES))) . "'";
        if ($result = get_db_result("SELECT * FROM events WHERE chid='$chid' AND daykey='$daykey' AND tag IN ($pottytags) ORDER BY timelog ASC, evid ASC")) {
            while ($row = fetch_row($result)) {
                $info = $STATUS_POTTY_TYPES[$row["tag"]];
                $potty[] = [
                    "evid"        => (int) $row["evid"],
                    "type"        => $row["tag"],
                    "label"       => $info["label"],
                    "emoji"       => $info["emoji"],
                    "color"       => $info["color"],
                    "time"        => get_date("g:i a", display_time($row["timelog"])),
                    "hm"          => get_date("H:i", display_time($row["timelog"])),
                    "timelog"     => (int) $row["timelog"],
                    "cream"       => (bool) $row["cream"],
                    "peed"        => (bool) $row["peed"],
                    "pooped"      => (bool) $row["pooped"],
                    "attachments" => status_get_attachments($chid, $row["evid"]),
                ];
            }
        }

        // Incidents Quick Report - editable, with note + attachments
        $incidents = [];
        $inctags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_INCIDENT_TYPES))) . "'";
        if ($result = get_db_result("SELECT * FROM events WHERE chid='$chid' AND daykey='$daykey' AND tag IN ($inctags) ORDER BY timelog ASC, evid ASC")) {
            while ($row = fetch_row($result)) {
                $info = $STATUS_INCIDENT_TYPES[$row["tag"]];
                $incidents[] = [
                    "evid"        => (int) $row["evid"],
                    "type"        => $row["tag"],
                    "label"       => $info["label"],
                    "emoji"       => $info["emoji"],
                    "color"       => $info["color"],
                    "time"        => get_date("g:i a", display_time($row["timelog"])),
                    "hm"          => get_date("H:i", display_time($row["timelog"])),
                    "note"        => $row["note"],
                    "attachments" => status_get_attachments($chid, $row["evid"]),
                ];
            }
        }

        // Naptime - history is always returned. The notice is a heads-up
        // for everyone during the 1-3pm window; the logging buttons are
        // for kids under the age cutoff and available any time (naps
        // aren't confined to that window, just commonly clustered there).
        $naps = [];
        if ($result = get_db_result("SELECT * FROM events WHERE chid='$chid' AND daykey='$daykey' AND tag='" . dbescape($STATUS_NAP_TAG) . "' ORDER BY timelog ASC, evid ASC")) {
            while ($row = fetch_row($result)) {
                $naps[] = [
                    "evid"    => (int) $row["evid"],
                    "minutes" => (int) $row["amount"],
                    "time"    => get_date("g:i a", display_time($row["timelog"])),
                    "hm"      => get_date("H:i", display_time($row["timelog"])),
                ];
            }
        }
        $show_naptime_notice  = status_naptime_window_now();
        $show_naptime_buttons = status_eligible_for_naptime($child["birthdate"]);

        // Notes added via the status page only (daykey=0 = pre-existing/unrelated)
        $notes = [];
        if ($result = get_db_result("SELECT n.*, t.title AS tag_title, t.color AS tag_color, t.textcolor AS tag_textcolor
                                        FROM notes n
                                        LEFT JOIN notes_tags t ON t.tag = n.tag
                                       WHERE n.chid='$chid' AND n.daykey='$daykey' AND n.daykey != 0
                                       ORDER BY n.timelog ASC")) {
            while ($row = fetch_row($result)) {
                $notes[] = [
                    "nid"       => (int) $row["nid"],
                    "tag"       => $row["tag"],
                    "tag_title" => $row["tag_title"] ? $row["tag_title"] : $row["tag"],
                    "color"     => $row["tag_color"] ? $row["tag_color"] : "#silver",
                    "textcolor" => $row["tag_textcolor"] ? $row["tag_textcolor"] : "#000",
                    "note"      => $row["note"],
                    "notify"    => (bool) $row["notify"],
                    "time"      => get_date("g:i a", display_time($row["timelog"])),
                    "hm"        => get_date("H:i", display_time($row["timelog"])),
                ];
            }
        }

        $menus = [];
        foreach ($STATUS_MEALS as $mealkey => $mealinfo) {
            $menus[$mealkey] = "";
        }
        if ($result = get_db_result("SELECT meal, menu FROM status_menu WHERE chid='$chid' AND daykey='$daykey'")) {
            while ($row = fetch_row($result)) {
                if (array_key_exists($row["meal"], $menus)) {
                    $menus[$row["meal"]] = $row["menu"];
                }
            }
        }

        $nokidpic = "";
        if (!$kidpic = get_child_picture_style($chid)) {
            $nokidpic = 'class="blank_pic"';
        }

        return [
            "chid"       => $chid,
            "avatar"     => '<div style="text-align:center;' . $kidpic . '" ' . $nokidpic . '></div>',
            "name"       => $child["first"] . " " . $child["last"],
            "daykey"     => $daykey,
            "date_label" => get_date("l, F j, Y", $daykey + get_offset()),
            "is_today"   => $daykey == status_daykey(),
            "moods"        => $moods,
            "potty"        => $potty,
            "incidents"    => $incidents,
            "naps"         => $naps,
            "show_naptime_notice"  => $show_naptime_notice,
            "show_naptime_buttons" => $show_naptime_buttons,
            "menus"        => $menus,
            "notes"        => $notes,
            "bottles"      => $bottles,
            "show_bottles" => $show_bottles,
        ];
    }

    // -----------------------------------------------------------------
    // Writes - all scoped to "today" (daykey defaults to now) since the
    // status page only ever edits the current day.
    // -----------------------------------------------------------------
    function status_add_mood($chid, $mood) {
        global $STATUS_MOODS;
        if (!isset($STATUS_MOODS[$mood])) {
            return false;
        }
        $chid = intval($chid);
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time = get_timestamp();
        $day  = status_daykey($time);
        execute_db_sql("INSERT INTO events (pid, tag, sort, chid, aid, daykey, timelog) VALUES (0,'" . dbescape($mood) . "',0,'$chid','$aid','$day','$time')");
        return status_get_day($chid, $day);
    }

    // cream/peed/pooped are only ever stored for types that ask about them.
    // Returns the new evid (so a photo can attach to it) plus the refreshed day.
    function status_add_potty($chid, $type, $hour = false, $minute = false, $cream = false, $peed = false, $pooped = false) {
        global $STATUS_POTTY_TYPES;
        if (!isset($STATUS_POTTY_TYPES[$type])) {
            return false;
        }
        $info   = $STATUS_POTTY_TYPES[$type];
        $chid   = intval($chid);
        $aid    = intval(get_db_field("aid", "children", "chid='$chid'"));
        $timelog = status_resolve_timelog($hour, $minute);
        $day    = status_daykey($timelog);
        $cream  = ($info['asks_cream'] && $cream)  ? 1 : 0;
        $peed   = ($info['asks_potty'] && $peed)   ? 1 : 0;
        $pooped = ($info['asks_potty'] && $pooped) ? 1 : 0;
        $evid = execute_db_sql("INSERT INTO events (pid, tag, sort, chid, aid, daykey, timelog, cream, peed, pooped)
                                 VALUES (0,'" . dbescape($type) . "',0,'$chid','$aid','$day','$timelog','$cream','$peed','$pooped')");
        return ["evid" => $evid, "day" => status_get_day($chid, $day)];
    }

    // Edits type/time/flags at once; only touches a row that's a Potty Time entry for this child.
    function status_edit_potty($chid, $evid, $type, $hour, $minute, $cream, $peed, $pooped) {
        global $STATUS_POTTY_TYPES;
        if (!isset($STATUS_POTTY_TYPES[$type])) {
            return false;
        }
        $chid = intval($chid);
        $evid = intval($evid);
        $pottytags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_POTTY_TYPES))) . "'";
        if (!get_db_count("SELECT evid FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($pottytags)")) {
            return false;
        }
        $info   = $STATUS_POTTY_TYPES[$type];
        $timelog = status_resolve_timelog($hour, $minute);
        $day    = status_daykey($timelog);
        $cream  = ($info['asks_cream'] && $cream)  ? 1 : 0;
        $peed   = ($info['asks_potty'] && $peed)   ? 1 : 0;
        $pooped = ($info['asks_potty'] && $pooped) ? 1 : 0;
        execute_db_sql("UPDATE events SET tag='" . dbescape($type) . "', timelog='$timelog', daykey='$day', cream='$cream', peed='$peed', pooped='$pooped'
                         WHERE evid='$evid' AND chid='$chid'");
        return status_get_day($chid, $day);
    }

    function status_delete_potty($chid, $evid) {
        global $STATUS_POTTY_TYPES;
        $chid = intval($chid);
        $evid = intval($evid);
        $pottytags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_POTTY_TYPES))) . "'";
        if (!get_db_count("SELECT evid FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($pottytags)")) {
            return status_get_day($chid);
        }
        // Clean up attachments before removing the entry
        if ($result = get_db_result("SELECT * FROM documents WHERE chid='$chid' AND evid='$evid'")) {
            while ($row = fetch_row($result)) {
                status_delete_attachment_row($row);
            }
        }
        execute_db_sql("DELETE FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($pottytags)");
        return status_get_day($chid);
    }

    // Incidents Quick Report - one-tap create (pre-filled with a default
    // note), then editable like Potty Time. Reuses the same events+
    // documents attachment pattern.
    function status_add_incident($chid, $type) {
        global $STATUS_INCIDENT_TYPES;
        if (!isset($STATUS_INCIDENT_TYPES[$type])) {
            return false;
        }
        $chid = intval($chid);
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time = get_timestamp();
        $day  = status_daykey($time);
        $note = dbescape($STATUS_INCIDENT_TYPES[$type]['default_note']);
        $evid = execute_db_sql("INSERT INTO events (pid, tag, sort, chid, aid, daykey, timelog, note)
                                 VALUES (0,'" . dbescape($type) . "',0,'$chid','$aid','$day','$time','$note')");
        return ["evid" => $evid, "day" => status_get_day($chid, $day)];
    }

    function status_edit_incident($chid, $evid, $type, $note, $hour, $minute) {
        global $STATUS_INCIDENT_TYPES;
        if (!isset($STATUS_INCIDENT_TYPES[$type])) {
            return false;
        }
        $chid = intval($chid);
        $evid = intval($evid);
        $inctags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_INCIDENT_TYPES))) . "'";
        if (!get_db_count("SELECT evid FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($inctags)")) {
            return false;
        }
        $timelog = status_resolve_timelog($hour, $minute);
        $day = status_daykey($timelog);
        execute_db_sql("UPDATE events SET tag='" . dbescape($type) . "', note='" . dbescape($note) . "', timelog='$timelog', daykey='$day'
                         WHERE evid='$evid' AND chid='$chid'");
        return status_get_day($chid, $day);
    }

    function status_delete_incident($chid, $evid) {
        global $STATUS_INCIDENT_TYPES;
        $chid = intval($chid);
        $evid = intval($evid);
        $inctags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_INCIDENT_TYPES))) . "'";
        if (!get_db_count("SELECT evid FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($inctags)")) {
            return status_get_day($chid);
        }
        if ($result = get_db_result("SELECT * FROM documents WHERE chid='$chid' AND evid='$evid'")) {
            while ($row = fetch_row($result)) {
                status_delete_attachment_row($row);
            }
        }
        execute_db_sql("DELETE FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($inctags)");
        return status_get_day($chid);
    }

    // Naptime - backdated single tap (the nap already ended, hence a
    // duration button rather than a start/stop pair).
    function status_add_nap($chid, $minutes) {
        global $STATUS_NAP_TAG, $STATUS_NAP_DURATIONS;
        if (!in_array(intval($minutes), $STATUS_NAP_DURATIONS)) {
            return false;
        }
        $chid = intval($chid);
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        $timelog = status_clamp_timelog(get_timestamp() - (intval($minutes) * 60));
        $day = status_daykey($timelog);
        execute_db_sql("INSERT INTO events (pid, tag, sort, chid, aid, daykey, timelog, amount)
                         VALUES (0,'" . dbescape($STATUS_NAP_TAG) . "',0,'$chid','$aid','$day','$timelog','" . intval($minutes) . "')");
        return status_get_day($chid, $day);
    }

    function status_delete_nap($chid, $evid) {
        global $STATUS_NAP_TAG;
        $chid = intval($chid);
        $evid = intval($evid);
        execute_db_sql("DELETE FROM events WHERE evid='$evid' AND chid='$chid' AND tag='" . dbescape($STATUS_NAP_TAG) . "'");
        return status_get_day($chid);
    }

    // Adjust the time on an existing mood entry; leaves the mood itself alone.
    function status_edit_mood_time($chid, $evid, $hour, $minute) {
        global $STATUS_MOODS;
        $chid = intval($chid);
        $evid = intval($evid);
        $moodtags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_MOODS))) . "'";
        if (!get_db_count("SELECT evid FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($moodtags)")) {
            return false;
        }
        $timelog = status_resolve_timelog($hour, $minute);
        $day = status_daykey($timelog);
        execute_db_sql("UPDATE events SET timelog='$timelog', daykey='$day' WHERE evid='$evid' AND chid='$chid'");
        return status_get_day($chid, $day);
    }

    function status_edit_bottle_time($chid, $evid, $hour, $minute) {
        $chid = intval($chid);
        $evid = intval($evid);
        if (!get_db_count("SELECT evid FROM events WHERE evid='$evid' AND chid='$chid' AND tag='" . dbescape($GLOBALS['STATUS_BOTTLE_TAG']) . "'")) {
            return false;
        }
        $timelog = status_resolve_timelog($hour, $minute);
        $day = status_daykey($timelog);
        execute_db_sql("UPDATE events SET timelog='$timelog', daykey='$day' WHERE evid='$evid' AND chid='$chid'");
        return status_get_day($chid, $day);
    }

    // Attachments (photos/files on a Potty Time entry)
    function status_add_attachment($chid, $evid, $filename, $tag = 'attachment') {
        $chid = intval($chid);
        $evid = intval($evid);
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time = get_timestamp();
        execute_db_sql("INSERT INTO documents (aid, chid, evid, tag, filename, description, timelog)
                         VALUES ('$aid','$chid','$evid','" . dbescape($tag) . "','" . dbescape($filename) . "','','$time')");
        return status_get_attachments($chid, $evid);
    }

    function status_delete_attachment_row($row) {
        global $CFG;
        $path = $CFG->userfilespath . "/children/" . $row["chid"] . "/" . $row["filename"];
        if (file_exists($path)) {
            @unlink($path);
        }
        execute_db_sql("DELETE FROM documents WHERE did='" . intval($row["did"]) . "'");
    }

    function status_delete_attachment($chid, $did) {
        $chid = intval($chid);
        $did  = intval($did);
        if ($row = get_db_row("SELECT * FROM documents WHERE did='$did' AND chid='$chid'")) {
            $evid = $row["evid"];
            status_delete_attachment_row($row);
            return status_get_attachments($chid, $evid);
        }
        return [];
    }

    // "Need Diapers" / "Clothing Change" - pre-written notes tagged "Request".
    function status_quick_note($chid, $key) {
        global $CFG, $STATUS_QUICK_NOTES;
        if (!isset($STATUS_QUICK_NOTES[$key])) {
            return false;
        }
        if (!function_exists('make_or_get_tag')) {
            include_once($CFG->dirroot . '/lib/pagelib.php');
        }
        $info = $STATUS_QUICK_NOTES[$key];
        $tag  = make_or_get_tag($info['tag_title'], 'notes');
        return status_add_note($chid, $tag, $info['text'], true);
    }

    // Each meal (breakfast/lunch/dinner) has its own row, so saving one
    // never touches the others for the same child/day.
    function status_save_menu($chid, $meal, $menu) {
        global $STATUS_MEALS;
        if (!isset($STATUS_MEALS[$meal])) {
            return false;
        }
        $chid = intval($chid);
        $day  = status_daykey();
        $time = get_timestamp();
        $mealesc = dbescape($meal);
        $menuesc = dbescape($menu);
        if (get_db_count("SELECT id FROM status_menu WHERE chid='$chid' AND daykey='$day' AND meal='$mealesc'")) {
            execute_db_sql("UPDATE status_menu SET menu='$menuesc', timelog='$time' WHERE chid='$chid' AND daykey='$day' AND meal='$mealesc'");
        } else {
            execute_db_sql("INSERT INTO status_menu (chid, daykey, meal, menu, timelog) VALUES ('$chid','$day','$mealesc','$menuesc','$time')");
        }
        return status_get_day($chid, $day);
    }

    // Copies a menu to other kids, scoped to one meal only. Returns the chids written.
    function status_copy_menu($meal, $menu, $chids) {
        global $STATUS_MEALS;
        if (!isset($STATUS_MEALS[$meal])) {
            return [];
        }
        $day  = status_daykey();
        $time = get_timestamp();
        $mealesc = dbescape($meal);
        $menuesc = dbescape($menu);
        $written = [];
        foreach ($chids as $chid) {
            $chid = intval($chid);
            if (!$chid) {
                continue;
            }
            if (get_db_count("SELECT id FROM status_menu WHERE chid='$chid' AND daykey='$day' AND meal='$mealesc'")) {
                execute_db_sql("UPDATE status_menu SET menu='$menuesc', timelog='$time' WHERE chid='$chid' AND daykey='$day' AND meal='$mealesc'");
            } else {
                execute_db_sql("INSERT INTO status_menu (chid, daykey, meal, menu, timelog) VALUES ('$chid','$day','$mealesc','$menuesc','$time')");
            }
            $written[] = $chid;
        }
        return $written;
    }

    // Quick-fill suggestions: menus already entered today for other kids, same meal.
    function status_menu_suggestions($chid, $meal) {
        global $STATUS_MEALS;
        if (!isset($STATUS_MEALS[$meal])) {
            return [];
        }
        $chid = intval($chid);
        $day  = status_daykey();
        $mealesc = dbescape($meal);
        $suggestions = [];
        $SQL = "SELECT sm.menu,
                       COUNT(DISTINCT sm.chid) AS cnt,
                       MAX(sm.timelog) AS last_used,
                       GROUP_CONCAT(DISTINCT CONCAT(c.first,' ',c.last) ORDER BY c.first SEPARATOR ', ') AS kids
                  FROM status_menu sm
                  JOIN children c ON c.chid = sm.chid
                 WHERE sm.daykey='$day' AND sm.meal='$mealesc' AND sm.chid != '$chid' AND sm.menu != ''
                 GROUP BY sm.menu
                 ORDER BY cnt DESC, last_used DESC
                 LIMIT 8";
        if ($result = get_db_result($SQL)) {
            while ($row = fetch_row($result)) {
                $suggestions[] = [
                    "menu"  => $row["menu"],
                    "count" => (int) $row["cnt"],
                    "kids"  => $row["kids"],
                ];
            }
        }
        return $suggestions;
    }

    // notify=1 uses the same field the app's sign-out bulletin feature reads.
    function status_add_note($chid, $tag, $note, $notify) {
        $tags = array_column(status_notes_tags(), 'tag');
        if (!in_array($tag, $tags)) {
            return false;
        }
        $chid   = intval($chid);
        $aid    = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time   = get_timestamp();
        $day    = status_daykey($time);
        $notify = $notify ? 1 : 0;
        $note   = dbescape($note);
        $tag    = dbescape($tag);
        execute_db_sql("INSERT INTO notes (pid, aid, cid, actid, chid, employeeid, rnid, tag, note, data, timelog, notify, daykey)
                        VALUES (0,'$aid',0,0,'$chid',0,0,'$tag','$note','','$time','$notify','$day')");
        return status_get_day($chid, $day);
    }

    function status_delete_note($nid, $chid) {
        $nid  = intval($nid);
        $chid = intval($chid);
        // Only notes this feature created (daykey != 0)
        execute_db_sql("DELETE FROM notes WHERE nid='$nid' AND chid='$chid' AND daykey != 0");
        return status_get_day($chid, status_daykey());
    }

    // Changes a mood tap's type without losing its original time.
    function status_edit_mood($chid, $evid, $newmood) {
        global $STATUS_MOODS;
        if (!isset($STATUS_MOODS[$newmood])) {
            return false;
        }
        $chid = intval($chid);
        $evid = intval($evid);
        $moodtags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_MOODS))) . "'";
        if (!get_db_count("SELECT evid FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($moodtags)")) {
            return false;
        }
        execute_db_sql("UPDATE events SET tag='" . dbescape($newmood) . "' WHERE evid='$evid' AND chid='$chid'");
        return status_get_day($chid);
    }

    function status_delete_mood($chid, $evid) {
        global $STATUS_MOODS;
        $chid = intval($chid);
        $evid = intval($evid);
        $moodtags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_MOODS))) . "'";
        execute_db_sql("DELETE FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($moodtags)");
        return status_get_day($chid);
    }

    // Bottles have only one type, so nothing to edit - just add or delete.
    // Bottles have one type but now carry an ounces amount (0 = not given).
    function status_add_bottle($chid, $ounces = false) {
        $chid = intval($chid);
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time = get_timestamp();
        $day  = status_daykey($time);
        $amount = ($ounces !== false && $ounces !== '') ? intval($ounces) : 0;
        execute_db_sql("INSERT INTO events (pid, tag, sort, chid, aid, daykey, timelog, amount) VALUES (0,'" . dbescape($GLOBALS['STATUS_BOTTLE_TAG']) . "',0,'$chid','$aid','$day','$time','$amount')");
        return status_get_day($chid, $day);
    }

    function status_edit_bottle_ounces($chid, $evid, $ounces) {
        $chid = intval($chid);
        $evid = intval($evid);
        if (!get_db_count("SELECT evid FROM events WHERE evid='$evid' AND chid='$chid' AND tag='" . dbescape($GLOBALS['STATUS_BOTTLE_TAG']) . "'")) {
            return false;
        }
        execute_db_sql("UPDATE events SET amount='" . intval($ounces) . "' WHERE evid='$evid' AND chid='$chid'");
        return status_get_day($chid);
    }

    function status_delete_bottle($chid, $evid) {
        $chid = intval($chid);
        $evid = intval($evid);
        execute_db_sql("DELETE FROM events WHERE evid='$evid' AND chid='$chid' AND tag='" . dbescape($GLOBALS['STATUS_BOTTLE_TAG']) . "'");
        return status_get_day($chid);
    }
}