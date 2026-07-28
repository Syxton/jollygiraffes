<?php

/***************************************************************************
* status_lib.php - Daily Status Report feature
* -------------------------------------------------------------------------
* Adds a lightweight, read-only "daily status" page for parents
* (https://.../status?c=FAMILYCODE) and a simple entry page for staff/admin
* (https://.../status, unlocked with the admin PIN).
*
* Storage: this feature intentionally reuses the app's existing `events`
* and `notes` tables instead of creating parallel log tables:
*   - `events` gets three new columns: chid, aid, daykey (plus timelog,
*     which the table didn't have yet and every other log table in this
*     app relies on). Mood and tally taps are logged as ordinary rows in
*     `events` using the existing `tag` column (e.g. 'mood_happy',
*     'diaper', 'potty_success', 'potty_accident', 'clothing_change').
*     Existing rows (the 'in'/'out' event *definitions*) are untouched -
*     they simply keep chid/aid/daykey at their default of 0, and every
*     query in this file always filters by a real chid, so the two never
*     mix.
*   - `notes` gets one new column: daykey. The daily "note area" is just
*     a normal note (tag chosen from notes_tags, text, optional
*     notify-parent-at-signout flag) with chid + daykey set. Only notes
*     that have a non-zero daykey are ever shown on the status page, so
*     older/unrelated notes already in the table are never exposed to
*     parents.
*   - `status_menu` is the one new table - a simple per-child, per-day
*     menu field that doesn't fit the append-only log pattern of the
*     other two.
*   - `accounts` gets one new column: link_code, for the shareable
*     parent link (?c=Smith).
*
* This file only depends on functions already provided by lib/dblib.php
* (get_db_row, get_db_result, get_db_count, get_db_field, execute_db_sql,
* dbescape, fetch_row) and lib/timelib.php (get_today, get_timestamp,
* get_date, get_offset, display_time), which are loaded by lib/header.php.
***************************************************************************/

if (!isset($STATUSLIB)) {
    $STATUSLIB = true;

    // -----------------------------------------------------------------
    // Config: mood options and tally options shown in the UI. Keys are
    // stored in the database as event tags (prefixed "mood_" for moods),
    // so don't rename existing keys once you have data - add new ones
    // instead.
    // -----------------------------------------------------------------
    $GLOBALS['STATUS_MOODS'] = [
        'mood_happy'     => ['label' => 'Happy',     'emoji' => '😊', 'color' => '#4CAF50'],
        'mood_sad'       => ['label' => 'Sad',       'emoji' => '😢', 'color' => '#5C7CFA'],
        'mood_angry'     => ['label' => 'Angry',     'emoji' => '😠', 'color' => '#E03131'],
        'mood_tired'     => ['label' => 'Tired',     'emoji' => '😴', 'color' => '#868E96'],
        'mood_energetic' => ['label' => 'Energetic', 'emoji' => '⚡', 'color' => '#F59F00'],
        'mood_calm'      => ['label' => 'Calm',      'emoji' => '😌', 'color' => '#22B8CF'],
        'mood_silly'     => ['label' => 'Silly',     'emoji' => '🤪', 'color' => '#BE4BDB'],
        'mood_sick'      => ['label' => 'Not Feeling Well', 'emoji' => '🤒', 'color' => '#FA5252'],
    ];

    $GLOBALS['STATUS_TALLIES'] = [
        'diaper'          => ['label' => 'Diaper Change',   'emoji' => '🧷', 'color' => '#20C997'],
        'potty_success'   => ['label' => 'Potty Success',   'emoji' => '🚽', 'color' => '#40C057'],
        'potty_accident'  => ['label' => 'Potty Accident',  'emoji' => '💧', 'color' => '#FA5252'],
        'clothing_change' => ['label' => 'Clothing Change', 'emoji' => '👕', 'color' => '#FAB005'],
    ];

    // -----------------------------------------------------------------
    // Migration - creates/alters the tables this feature needs the
    // first time it runs. Safe to call on every request.
    // -----------------------------------------------------------------
    // NOTE: get_db_result() only treats a zero-row result as false for
    // SELECT statements (see dblib_mysqli.php), so SHOW COLUMNS / SHOW
    // INDEX (which return an empty-but-truthy result when nothing
    // matches) can't be used directly for existence checks. We query
    // information_schema instead, which goes through the normal SELECT
    // codepath.
    function status_column_exists($table, $column) {
        return (bool) get_db_row("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='" . dbescape($table) . "' AND column_name='" . dbescape($column) . "'");
    }

    function status_index_exists($table, $indexname) {
        return (bool) get_db_row("SELECT index_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='" . dbescape($table) . "' AND index_name='" . dbescape($indexname) . "'");
    }

    function status_migrate() {

        // events: add chid, aid, daykey, timelog (log columns) so it can
        // double as the tap/mood log, per existing "tag" convention.
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

        // notes: add daykey so the status "note area" entries can be
        // scoped to a specific child + calendar day.
        if (!status_column_exists('notes', 'daykey')) {
            execute_db_sql("ALTER TABLE notes ADD COLUMN daykey int(11) NOT NULL DEFAULT '0'");
            execute_db_sql("ALTER TABLE notes ADD KEY chid_day (chid,daykey)");
        }

        // accounts: add link_code for the shareable parent link (?c=Smith)
        if (!status_column_exists('accounts', 'link_code')) {
            execute_db_sql("ALTER TABLE accounts ADD COLUMN link_code varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL");
            execute_db_sql("ALTER TABLE accounts ADD UNIQUE KEY link_code (link_code)");
        }

        // status_menu: the one new table - a simple per-child/per-day menu.
        execute_db_sql("
            CREATE TABLE IF NOT EXISTS `status_menu` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `chid` int(11) NOT NULL,
              `daykey` int(11) NOT NULL,
              `menu` text COLLATE utf8_unicode_ci NOT NULL,
              `timelog` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `chid_day` (`chid`,`daykey`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------
    function status_daykey($timestamp = false) {
        // Midnight (in the configured timezone) for the given/current time,
        // matching the same convention get_today() uses elsewhere in the app.
        global $CFG;
        $timestamp = $timestamp ? $timestamp : get_timestamp();
        $local = new DateTime("now", new DateTimeZone($CFG->timezone));
        $local->setTimestamp($timestamp);
        $midnight = new DateTime($local->format("m/d/Y"), new DateTimeZone($CFG->timezone));
        $utc = new DateTime($midnight->format("m/d/Y"), new DateTimeZone("UTC"));
        return $utc->getTimestamp();
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

    // -----------------------------------------------------------------
    // Day data
    // -----------------------------------------------------------------
    function status_get_day($chid, $daykey = false) {
        global $STATUS_MOODS, $STATUS_TALLIES, $CFG;

        if (!isset($PAGELIB)) {
            include_once($CFG->dirroot . '/lib/pagelib.php');
        }
        $chid = intval($chid);
        $daykey = $daykey ? intval($daykey) : status_daykey();

        $child = get_db_row("SELECT * FROM children WHERE chid='$chid'");
        if (!$child) {
            return false;
        }

        // Mood timeline - every mood_* tag logged today, in order.
        $moods = [];
        $moodtags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_MOODS))) . "'";
        if ($result = get_db_result("SELECT * FROM events WHERE chid='$chid' AND daykey='$daykey' AND tag IN ($moodtags) ORDER BY timelog ASC, evid ASC")) {
            while ($row = fetch_row($result)) {
                $info = $STATUS_MOODS[$row["tag"]];
                $moods[] = [
                    "mood"  => $row["tag"],
                    "label" => $info["label"],
                    "emoji" => $info["emoji"],
                    "color" => $info["color"],
                    "time"  => get_date("g:i a", display_time($row["timelog"])),
                ];
            }
        }

        // Tally counts for the day.
        $counts = [];
        foreach ($STATUS_TALLIES as $key => $info) {
            $counts[$key] = get_db_count("SELECT * FROM events WHERE chid='$chid' AND daykey='$daykey' AND tag='" . dbescape($key) . "'");
        }

        // Notes logged for this child on this day via the status page only
        // (daykey=0 means it's a pre-existing/unrelated note from elsewhere
        // in the app, so it's excluded from the parent-facing report).
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
                ];
            }
        }

        $menu = get_db_field("menu", "status_menu", "chid='$chid' AND daykey='$daykey'");

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
            "moods"      => $moods,
            "counts"     => $counts,
            "menu"       => $menu === false ? "" : $menu,
            "notes"      => $notes,
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

    function status_add_tally($chid, $tag) {
        global $STATUS_TALLIES;
        if (!isset($STATUS_TALLIES[$tag])) {
            return false;
        }
        $chid = intval($chid);
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time = get_timestamp();
        $day  = status_daykey($time);
        execute_db_sql("INSERT INTO events (pid, tag, sort, chid, aid, daykey, timelog) VALUES (0,'" . dbescape($tag) . "',0,'$chid','$aid','$day','$time')");
        return status_get_day($chid, $day);
    }

    function status_undo_tally($chid, $tag) {
        global $STATUS_TALLIES;
        if (!isset($STATUS_TALLIES[$tag])) {
            return false;
        }
        $chid = intval($chid);
        $day  = status_daykey();
        $row = get_db_row("SELECT evid FROM events WHERE chid='$chid' AND daykey='$day' AND tag='" . dbescape($tag) . "' ORDER BY evid DESC LIMIT 1");
        if ($row) {
            execute_db_sql("DELETE FROM events WHERE evid='" . intval($row["evid"]) . "'");
        }
        return status_get_day($chid, $day);
    }

    function status_save_menu($chid, $menu) {
        $chid = intval($chid);
        $day  = status_daykey();
        $time = get_timestamp();
        $menu = dbescape($menu);
        if (get_db_count("SELECT id FROM status_menu WHERE chid='$chid' AND daykey='$day'")) {
            execute_db_sql("UPDATE status_menu SET menu='$menu', timelog='$time' WHERE chid='$chid' AND daykey='$day'");
        } else {
            execute_db_sql("INSERT INTO status_menu (chid, daykey, menu, timelog) VALUES ('$chid','$day','$menu','$time')");
        }
        return status_get_day($chid, $day);
    }

    // Adds a new note-area entry for today: staff picks a tag from
    // notes_tags, writes text, and optionally flags it to notify the
    // parent at sign-out (sets notes.notify=1, the same field the app's
    // existing sign-out bulletin feature reads).
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
        // Only ever allow deleting notes this feature created (daykey != 0),
        // and only for the child it's scoped to.
        execute_db_sql("DELETE FROM notes WHERE nid='$nid' AND chid='$chid' AND daykey != 0");
        return status_get_day($chid, status_daykey());
    }
}
