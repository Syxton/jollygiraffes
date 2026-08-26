<?php

/**
 * status_lib.php - Daily Status Report feature.
 * Parent: /status?c=CODE (PIN, read-only). Admin: /status (admin PIN, edit).
 * Reuses events/notes/documents/accounts; adds status_menu. Needs dblib+timelib.
 */

if (!isset($NOTIFICATIONLIB)) {
    require_once(dirname(__FILE__) . '/../notifications/notification_lib.php');
}

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
    // notify: 1 = single-day notify, 2 = persist (keep notifying until cleared).
    $GLOBALS['STATUS_QUICK_NOTES'] = [
        'need_diapers' => [
            'label'     => 'Need Diapers',
            'emoji'     => '🚼',
            'tag_title' => 'Request',
            'text'      => "Running low on diapers - please bring more.",
            'notify'    => 2,
        ],
        'clothing_pickup' => [
            'label'     => 'Clothing Change',
            'emoji'     => '👕',
            'tag_title' => 'Request',
            'text'      => "Needed a clothing change today - please pick up their dirty clothes.",
            'notify'    => 1,
        ],
    ];

    // Three menu slots per child/day - don't rename existing keys.
    $GLOBALS['STATUS_MEALS'] = [
        'breakfast' => ['label' => 'Breakfast', 'emoji' => '🍳'],
        'lunch'     => ['label' => 'Lunch',     'emoji' => '🥪'],
        'dinner'    => ['label' => 'Dinner',    'emoji' => '🍽️'],
    ];

    // Per-child rating of how a meal went. Stored on the same status_menu
    // row as the menu text (one rating per child/day/meal) - don't rename
    // existing keys once you have data.
    $GLOBALS['STATUS_MEAL_RATINGS'] = [
        'ate_well'   => ['label' => 'Ate Well',   'emoji' => '😋'],
        'ate_ok'     => ['label' => 'Ate OK',     'emoji' => '🙂'],
        'not_hungry' => ['label' => 'Not Hungry', 'emoji' => '😕'],
    ];

    // Activities: multi-select (unlike moods/potty, several can be true
    // for the same child/day at once). Stored as one row per child/day/
    // activity in status_activity - presence of a row means it happened.
    // Don't rename existing keys once you have data.
    $GLOBALS['STATUS_ACTIVITIES'] = [
        'belly_time'         => ['label' => 'Belly Time',         'emoji' => '🦭'],
        'art'                => ['label' => 'Art',                'emoji' => '🎨'],
        'books'              => ['label' => 'Books',              'emoji' => '📚'],
        'free_play'          => ['label' => 'Free Play',          'emoji' => '🪀'],
        'indoor_playground'  => ['label' => 'Indoor Play',        'emoji' => '🏠'],
        'outdoor_playground' => ['label' => 'Outdoor Playground', 'emoji' => '🛝'],
        'learning'           => ['label' => 'Learning',           'emoji' => '🎓'],
        'pretend_play'       => ['label' => 'Pretend Play',       'emoji' => '🎭'],
        'sensory'            => ['label' => 'Sensory',            'emoji' => '🖐️'],
        'videos'             => ['label' => 'Videos',             'emoji' => '📺'],
    ];

    // Bottles: a timestamped tap like moods, only shown under this age.
    $GLOBALS['STATUS_BOTTLE_TAG']        = 'bottle';
    $GLOBALS['STATUS_BOTTLE_INFO']       = ['label' => 'Bottle', 'emoji' => '🍼', 'color' => '#4DABF7'];
    $GLOBALS['STATUS_BOTTLE_MAX_MONTHS'] = 16;
    $GLOBALS['STATUS_BOTTLE_OUNCES']     = [1, 2, 3, 4, 5, 6, 7, 8];

    // Extensions treated as a "photo" for push-notification purposes -
    // an uploaded PDF attachment shouldn't trigger a "New Photo" alert.
    $GLOBALS['STATUS_PHOTO_EXTENSIONS'] = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'];

    // Incidents Quick Report: one-tap injury/incident logging. Each type
    // has a default note staff can edit further; attachments reuse the
    // same events+documents linkage as Potty Time. The note text lives in
    // the notes table (events.nid); note_tag is the notes_tags title used
    // when the linked note is created ("behavior" vs "Injury").
    $GLOBALS['STATUS_INCIDENT_TYPES'] = [
        'inc_hurt'    => ['label' => 'Hurt Someone',    'emoji' => '👊', 'color' => '#E03131', 'default_note' => 'Hurt another child.',           'note_tag' => 'behavior'],
        'inc_bit'     => ['label' => 'Bit Someone',     'emoji' => '😬', 'color' => '#c62a2a', 'default_note' => 'Bit another child.',            'note_tag' => 'behavior'],
        'inc_gotbit'  => ['label' => 'Bitten',          'emoji' => '😫', 'color' => '#c65bbd', 'default_note' => 'Was bitten by another child.',  'note_tag' => 'Injury'],
        'inc_booboo'  => ['label' => 'Boo Boo',         'emoji' => '🤕', 'color' => '#926969', 'default_note' => 'Had a minor boo-boo.',          'note_tag' => 'Injury'],
        'inc_bandaid' => ['label' => 'Band-Aid',        'emoji' => '🩹', 'color' => '#6f5dff', 'default_note' => 'Needed a band-aid.',            'note_tag' => 'Medical'],
        'inc_sick'    => ['label' => 'Sick',            'emoji' => '🤢', 'color' => '#8cea84', 'default_note' => 'Got sick',                      'note_tag' => 'Medical'],
    ];

    // Naptime: shown 1pm-3pm for children over this age. Duration
    // buttons backdate the entry (nap already ended when tapped).
    $GLOBALS['STATUS_NAP_TAG']        = 'nap';
    $GLOBALS['STATUS_NAP_DURATIONS']  = [30, 60, 90, 120];
    $GLOBALS['STATUS_NAP_WINDOW']     = ['start_hour' => 13, 'end_hour' => 15];
    $GLOBALS['STATUS_NAP_MAX_MONTHS'] = 24;

    // Simple nap rating for kids at/above STATUS_NAP_MAX_MONTHS - they're
    // not clocked in/out of naps individually, but staff can still record
    // how the nap went. One rating per child/day (see status_nap_rating
    // table) - don't rename existing keys once you have data.
    $GLOBALS['STATUS_NAP_RATINGS'] = [
        'slept_well' => ['label' => 'Slept Well', 'emoji' => '😴'],
        'slept_ok'   => ['label' => 'Slept OK',   'emoji' => '😐'],
        'restless'   => ['label' => 'Restless',   'emoji' => '😣'],
    ];

    /**
     *
     * True if $table has column $column (via information_schema).
     *
     *
     * @param string $table  Database table name.
     * @param string $column Column name.
     */
    function status_column_exists($table, $column) {
        return (bool) get_db_row("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='" . dbescape($table) . "' AND column_name='" . dbescape($column) . "'");
    }

    /**
     *
     * True if $table has index $indexname (via information_schema).
     *
     *
     * @param string $table     Database table name.
     * @param string $indexname Index name.
     */
    function status_index_exists($table, $indexname) {
        return (bool) get_db_row("SELECT index_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='" . dbescape($table) . "' AND index_name='" . dbescape($indexname) . "'");
    }

    /**
     *
     * Create or alter tables and columns used by the Daily Status feature.
     * Safe to call on every request; no-ops when schema is already current.
     *
     *
     */
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
        // Note: no DEFAULT clause on the TEXT column - MySQL < 8.0.13
        // rejects any default on TEXT/BLOB, 8.0.13+ requires the
        // parenthesized DEFAULT ('') form, and MariaDB accepts the bare
        // literal - no single syntax satisfies all three. Omitting DEFAULT
        // is portable: ADD COLUMN backfills existing rows with the type's
        // implicit default ('' for TEXT) regardless of engine/version.
        if (!status_column_exists('events', 'note')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN note text COLLATE utf8_unicode_ci NOT NULL");
        }
        if (!status_column_exists('events', 'amount')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN amount int(11) NOT NULL DEFAULT '0'");
        }

        // events.nid links an incident event to its notes-table row (the
        // free-text note for Incidents lives in notes, not events.note).
        if (!status_column_exists('events', 'nid')) {
            execute_db_sql("ALTER TABLE events ADD COLUMN nid int(11) NOT NULL DEFAULT '0'");
            execute_db_sql("ALTER TABLE events ADD KEY nid (nid)");
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

        // One-time: move legacy events.note text for incident rows into the
        // notes table and set events.nid. Safe to re-run (only touches rows
        // that still have note text and nid=0).
        status_migrate_incident_notes();

        // accounts.link_code is the shareable parent link (?c=Smith)
        if (!status_column_exists('accounts', 'link_code')) {
            execute_db_sql("ALTER TABLE accounts ADD COLUMN link_code varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL");
            execute_db_sql("ALTER TABLE accounts ADD UNIQUE KEY link_code (link_code)");
        }

        // accounts.link_disabled: true when the family currently has no
        // enrolled children - link_code gets replaced with an unguessable
        // hash and the family disappears from the Family Links list.
        if (!status_column_exists('accounts', 'link_disabled')) {
            execute_db_sql("ALTER TABLE accounts ADD COLUMN link_disabled tinyint(1) NOT NULL DEFAULT '0'");
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
              `rating` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
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

        // Upgrade path: older installs had status_menu without `rating`
        // (per-child "how did they eat" rating - ate_well/ate_ok/not_hungry).
        if (!status_column_exists('status_menu', 'rating')) {
            execute_db_sql("ALTER TABLE status_menu ADD COLUMN rating varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''");
        }

        // status_nap_rating: simple one-rating-per-child/day nap rating for
        // kids at/above STATUS_NAP_MAX_MONTHS, who don't get individual
        // logged nap entries (see status_nap_rating() below).
        execute_db_sql("
            CREATE TABLE IF NOT EXISTS `status_nap_rating` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `chid` int(11) NOT NULL,
              `daykey` int(11) NOT NULL,
              `rating` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
              `timelog` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `chid_day` (`chid`,`daykey`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

        // documents.arid links an attachment to one Activities entry
        // (status_activity row) - kept separate from evid (events table)
        // so ids from the two tables can never collide for the same child.
        if (!status_column_exists('documents', 'arid')) {
            execute_db_sql("ALTER TABLE documents ADD COLUMN arid int(11) NOT NULL DEFAULT '0'");
            execute_db_sql("ALTER TABLE documents ADD KEY arid (arid)");
        }

        // status_activity: multi-select, so unlike status_menu (one row
        // per meal slot) this is one row per child/day/activity. The row
        // persists once created even when unchecked (active=0) so photo
        // attachments stay linked to it if the activity gets re-checked
        // later the same day - only explicit attachment deletion removes
        // them.
        execute_db_sql("
            CREATE TABLE IF NOT EXISTS `status_activity` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `chid` int(11) NOT NULL,
              `daykey` int(11) NOT NULL,
              `activity` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
              `active` tinyint(1) NOT NULL DEFAULT '1',
              `timelog` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `chid_day_activity` (`chid`,`daykey`,`activity`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

        // Upgrade path: `active` didn't exist when this table was first
        // introduced (presence of a row = on, toggling off deleted it).
        // Existing rows all represented "on", so they default to active=1.
        if (!status_column_exists('status_activity', 'active')) {
            execute_db_sql("ALTER TABLE status_activity ADD COLUMN active tinyint(1) NOT NULL DEFAULT '1'");
        }

        status_sync_family_access();

        // Web Push: creates the notifications table (identifier/contents)
        // on first run and seeds the site's VAPID keypair under
        // identifier='SITE'. See notifications/notification_lib.php.
        notifications_migrate();
    }

    /**
     *
     * SHA-256 of a PushSubscription endpoint; unique per device registration.
     *
     *
     * @param string $endpoint PushSubscription endpoint URL.
     */
    function status_push_identifier($endpoint) {
        return hash('sha256', $endpoint);
    }

    /**
     *
     * Store or replace this device's PushSubscription (endpoint + keys).
     * Associates the subscription with account $aid for later lookup.
     *
     *
     * @param int   $aid          Account id.
     * @param array $subscription Parsed PushSubscription (endpoint and keys).
     */
    function status_push_subscribe($aid, $subscription) {
        if (empty($subscription['endpoint'])) {
            return ["success" => false, "message" => "Invalid subscription."];
        }
        $subscription['aid'] = intval($aid);
        $identifier = status_push_identifier($subscription['endpoint']);
        notifications_set($identifier, $subscription);
        return ["success" => true];
    }

    /**
     *
     * Remove this device's subscription by endpoint only.
     * Checks stored aid so one account cannot delete another's subscription.
     *
     *
     * @param int    $aid      Account id.
     * @param string $endpoint PushSubscription endpoint URL.
     */
    function status_push_unsubscribe($aid, $endpoint) {
        if (empty($endpoint)) {
            return ["success" => false, "message" => "Invalid subscription."];
        }
        $identifier = status_push_identifier($endpoint);
        $existing = notifications_get($identifier);
        if (!$existing || intval($existing['aid']) !== intval($aid)) {
            return ["success" => false, "message" => "Subscription not found."];
        }
        notifications_delete($identifier);
        return ["success" => true];
    }

    /**
     *
     * All device subscriptions belonging to one account.
     *
     *
     * @param int $aid Account id.
     */
    function status_push_subscriptions_for_account($aid) {
        $aid = intval($aid);
        $matches = [];
        foreach (notifications_all_subscriptions() as $identifier => $subscription) {
            if (isset($subscription['aid']) && intval($subscription['aid']) === $aid) {
                $matches[$identifier] = $subscription;
            }
        }
        return $matches;
    }

    /**
     *
     * Parent-facing status page URL for one account.
     * Same form as admin Family Links "Copy Link"; default push click target.
     *
     *
     * @param int $aid Account id.
     */
    function status_parent_url($aid) {
        global $CFG;
        $aid = intval($aid);
        $code = get_db_field("link_code", "accounts", "aid='$aid'");
        if (empty($code)) {
            $code = status_ensure_link_code($aid);
        }
        $base = rtrim($CFG->wwwroot, '/') . '/status.php';
        if (empty($code)) {
            return $base;
        }
        return $base . '?c=' . rawurlencode($code);
    }

    /**
     *
     * Send a push notification to every enabled device for a family.
     * Never throws so a push failure cannot block the admin action.
     * $url defaults to that family's parent status link.
     *
     *
     * @param int         $aid   Account id.
     * @param string      $title Notification title.
     * @param string      $body  Notification body text.
     * @param string|null $url   Click target URL; null uses the parent status link.
     */
    function status_push_notify_family($aid, $title, $body, $url = null) {
        global $CFG;
        $subscriptions = status_push_subscriptions_for_account($aid);
        if (empty($subscriptions)) {
            return;
        }
        if ($url === null || $url === '') {
            $url = status_parent_url($aid);
        }
        try {
            require_once($CFG->dirroot . '/notifications/WebPushHelper.php');
            $push = new WebPushHelper();
            $payload = ["title" => $title, "body" => $body, "url" => $url];
            $push->sendToSubscriptions($subscriptions, $payload);
        } catch (\Throwable $e) {
            error_log('status_push_notify_family: ' . $e->getMessage());
        }
    }

    /**
     *
     * Notify family of a new photo on Potty Time or Activity (images only).
     *
     *
     * @param int    $aid      Account id.
     * @param int    $chid     Child id.
     * @param string $filename Stored filename.
     */
    function status_push_notify_photo($aid, $chid, $filename) {
        global $STATUS_PHOTO_EXTENSIONS;
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $STATUS_PHOTO_EXTENSIONS)) {
            return;
        }
        $first = get_db_field("first", "children", "chid='" . intval($chid) . "'");
        $body = $first ? "$first has a new photo!" : "A new photo was added.";
        status_push_notify_family($aid, "📸 New Photo", $body);
    }

    /**
     *
     * Notify family immediately when a quick incident is logged.
     *
     *
     * @param int    $aid  Account id.
     * @param int    $chid Child id.
     * @param string $type Type key (mood, potty, incident, etc.).
     */
    function status_push_notify_incident($aid, $chid, $type) {
        global $STATUS_INCIDENT_TYPES;
        $info  = isset($STATUS_INCIDENT_TYPES[$type]) ? $STATUS_INCIDENT_TYPES[$type] : null;
        $label = $info ? $info['label'] : 'Incident';
        $emoji = $info ? $info['emoji'] : '⚠️';
        $first = get_db_field("first", "children", "chid='" . intval($chid) . "'");
        $body  = $first ? "$first: $label" : $label;
        status_push_notify_family($aid, "$emoji Incident Report", $body);
    }

    /**
     *
     * Midnight in the configured timezone as a UTC epoch.
     * Matches get_today() convention for day-scoped queries.
     *
     *
     * @param int|false $timestamp Unix timestamp; false means now.
     */
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

    /**
     *
     * Return $timelog if it falls on today and is not far in the future.
     * Otherwise fall back to now. Uses status_daykey() for day comparison.
     *
     *
     * @param int $timelog Stored event timestamp (app UTC convention).
     */
    function status_clamp_timelog($timelog) {
        global $CFG;
        $now = get_timestamp();
        $timelog = intval($timelog);
        if (!$timelog) {
            return $now;
        }

        // Make DateTime objects for comparison.
        $nowObj = new DateTime("@" . $now, new DateTimeZone($CFG->timezone));
        $timelogObj = new DateTime("@" . $timelog, new DateTimeZone($CFG->timezone));

        // Must be the same day. }
        if ($nowObj->format('d-m-Y') !== $timelogObj->format('d-m-Y')) {
            return $now;
        }
        return $timelog;
    }

    /**
     *
     * Convert local HH:MM (today) to the raw UTC timelog this app stores.
     * Subtracts display_time offset so values match formatted times.
     *
     *
     * @param int|false $hour   Local hour, or false to use now.
     * @param int|false $minute Local minute, or false to use now.
     */
    function status_time_from_hm($hour, $minute) {
        $day = status_daykey();
        $offset = get_offset();
        $seconds = (intval($hour) * 3600) + (intval($minute) * 60);
        return status_clamp_timelog($day + $seconds - $offset);
    }

    /**
     *
     * Resolve optional hour/minute to a clamped UTC timelog (or now).
     *
     *
     * @param int|false $hour   Local hour, or false to use now.
     * @param int|false $minute Local minute, or false to use now.
     */
    function status_resolve_timelog($hour = false, $minute = false) {
        if ($hour === false || $hour === null || $hour === '') {
            return get_timestamp();
        }
        return status_time_from_hm($hour, $minute);
    }

    /**
     *
     * Slugify text for family link codes (alphanumeric and dashes).
     *
     *
     * @param string $text Input text to slugify.
     */
    function status_slugify($text) {
        $text = strtolower(trim((string) $text));
        $text = preg_replace('/[^a-z0-9]+/', '', $text);
        return $text;
    }

    /**
     *
     * True if the child has a current enrollment record.
     *
     *
     * @param int $chid Child id.
     */
    function status_child_currently_enrolled($chid) {
        $chid = intval($chid);
        return (bool) get_db_count("
            SELECT e.eid FROM enrollments e
              JOIN programs p ON p.pid = e.pid
             WHERE e.chid='$chid' AND e.deleted=0 AND p.deleted=0");
    }

    /**
     *
     * True if the account has any enrolled children.
     *
     *
     * @param int $aid Account id.
     */
    function status_account_has_enrolled_children($aid) {
        $aid = intval($aid);
        return (bool) get_db_count("
            SELECT c.chid FROM children c
              JOIN enrollments e ON e.chid = c.chid
              JOIN programs p ON p.pid = e.pid
             WHERE c.aid='$aid' AND c.deleted=0 AND e.deleted=0 AND p.deleted=0");
    }

    /**
     *
     * Unguessable hash used when a family has no enrolled children.
     *
     *
     */
    function status_generate_link_hash() {
        return bin2hex(random_bytes(16));
    }

    /**
     *
     * Sync link_code and link_disabled with current enrollment.
     * No enrolled kids: unguessable hash and hide. Re-enrolled: normal slug link.
     *
     *
     */
    function status_sync_family_access() {
        $SQL = "SELECT aid, link_disabled FROM accounts WHERE deleted=0 AND admin=0";
        if ($result = get_db_result($SQL)) {
            while ($row = fetch_row($result)) {
                $aid = (int) $row['aid'];
                $hasEnrolled = status_account_has_enrolled_children($aid);
                if (!$hasEnrolled && !$row['link_disabled']) {
                    $hash = status_generate_link_hash();
                    execute_db_sql("UPDATE accounts SET link_code='" . dbescape($hash) . "', link_disabled=1 WHERE aid='$aid'");
                } elseif ($hasEnrolled && $row['link_disabled']) {
                    execute_db_sql("UPDATE accounts SET link_disabled=0, link_code=NULL WHERE aid='$aid'");
                    status_ensure_link_code($aid);
                }
            }
        }
    }

    /**
     *
     * Ensure the account has a link_code; create from name if missing.
     *
     *
     * @param int $aid Account id.
     */
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

    /**
     *
     * Set or clear a family's shareable parent link code.
     *
     *
     * @param int    $aid  Account id.
     * @param string $code Family link code.
     */
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

    /**
     *
     * Resolve a family link code to an account id, or false.
     *
     *
     * @param string $code Family link code.
     */
    function status_find_aid_by_code($code) {
        $code = status_slugify($code);
        if (!$code) {
            return false;
        }
        $row = get_db_row("SELECT aid FROM accounts WHERE link_code='" . dbescape($code) . "' AND deleted=0 AND link_disabled=0");
        return $row ? $row["aid"] : false;
    }

    /**
     *
     * Start the PHP session used by the status page auth flow.
     *
     *
     */
    function status_start_session() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.save_path', realpath(dirname(__FILE__) . '/tmp'));
            session_start();
        }
    }

    /**
     *
     * True if PIN login attempts have exceeded the rate limit.
     *
     *
     */
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

    /**
     *
     * Record a failed PIN attempt for rate limiting.
     *
     *
     */
    function status_register_failed_attempt() {
        status_start_session();
        $_SESSION['status_attempts'] = (isset($_SESSION['status_attempts']) ? $_SESSION['status_attempts'] : 0) + 1;
        $_SESSION['status_lastfail'] = time();
    }

    /**
     *
     * Clear failed-attempt counters after a successful login.
     *
     *
     */
    function status_register_success() {
        status_start_session();
        $_SESSION['status_attempts'] = 0;
    }

    /**
     *
     * Parent login by link code and PIN. Sets session role and aid.
     *
     *
     * @param string $code Family link code.
     * @param string $pin  4-digit PIN.
     */
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
        if (!status_account_has_enrolled_children($aid)) {
            status_register_failed_attempt();
            return ["success" => false, "message" => "This account doesn't currently have any enrolled children."];
        }
        status_register_success();
        status_start_session();
        $_SESSION['status_role'] = 'parent';
        $_SESSION['status_aid']  = $account['aid'];
        return ["success" => true];
    }

    /**
     *
     * Admin login by admin PIN. Sets session role to admin.
     *
     *
     * @param string $pin 4-digit PIN.
     */
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

    /**
     *
     * Change the 4-digit PIN used for status login and the check-in kiosk.
     * Requires the current PIN to match before writing the new one.
     *
     *
     * @param string $current_pin Current 4-digit PIN.
     * @param string $new_pin     New 4-digit PIN.
     */
    function status_change_pin($current_pin, $new_pin) {
        $aid = status_current_aid();
        if (!$aid) {
            return ["success" => false, "message" => "Please log in again."];
        }
        if (status_too_many_attempts()) {
            return ["success" => false, "message" => "Too many attempts. Please wait a minute and try again."];
        }

        $current_pin = preg_replace('/[^0-9]/', '', (string) $current_pin);
        $new_pin     = preg_replace('/[^0-9]/', '', (string) $new_pin);

        if (strlen($new_pin) != 4) {
            return ["success" => false, "message" => "New PIN must be exactly 4 digits."];
        }

        $account = get_db_row("SELECT * FROM accounts WHERE aid='" . intval($aid) . "' AND deleted=0 AND password='" . dbescape($current_pin) . "'");
        if (!$account) {
            status_register_failed_attempt();
            return ["success" => false, "message" => "Current PIN is incorrect."];
        }

        status_register_success();
        execute_db_sql("UPDATE accounts SET password='" . dbescape($new_pin) . "' WHERE aid='" . intval($aid) . "'");
        return ["success" => true];
    }

    /**
     *
     * Clear the status page session.
     *
     *
     */
    function status_logout() {
        status_start_session();
        $_SESSION['status_role'] = null;
        $_SESSION['status_aid']  = null;
        session_unset();
    }

    /**
     *
     * Current session role: 'admin', 'parent', or null.
     *
     *
     */
    function status_current_role() {
        status_start_session();
        return isset($_SESSION['status_role']) ? $_SESSION['status_role'] : false;
    }

    /**
     *
     * Current session account id, or null if not logged in.
     *
     *
     */
    function status_current_aid() {
        status_start_session();
        return isset($_SESSION['status_aid']) ? $_SESSION['status_aid'] : false;
    }

    /**
     *
     * True if the current session may view or edit this child.
     *
     *
     * @param int $chid Child id.
     */
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

    /**
     *
     * True if the current session may view or edit this account.
     *
     *
     * @param int $aid Account id.
     */
    function status_can_access_account($aid) {
        $role = status_current_role();
        if ($role == 'admin') {
            return true;
        }
        if ($role == 'parent') {
            return intval(status_current_aid()) === intval($aid);
        }
        return false;
    }

    /**
     *
     * True if the current session may view or edit this contact.
     *
     *
     * @param int $cid Contact id.
     */
    function status_can_access_contact($cid) {
        $role = status_current_role();
        if ($role == 'admin') {
            return true;
        }
        if ($role == 'parent') {
            $aid = status_current_aid();
            $contact_aid = get_db_field("aid", "contacts", "cid='" . intval($cid) . "' AND deleted=0");
            return $contact_aid !== false && intval($contact_aid) === intval($aid);
        }
        return false;
    }

    /**
     *
     * True if the current session may view this documents row.
     * Used by files.php before streaming; single gate for uploads.
     *
     *
     * @param array $document Documents table row.
     */
    function status_can_access_document($document) {
        if (!status_current_role()) {
            return false; // not logged in at all
        }
        if (status_current_role() == 'admin') {
            return true;
        }
        if (!empty($document["chid"])) {
            return status_can_access_child($document["chid"]);
        }
        if (!empty($document["cid"])) {
            return status_can_access_contact($document["cid"]);
        }
        if (!empty($document["aid"])) {
            return status_can_access_account($document["aid"]);
        }
        if (!empty($document["actid"])) {
            // Activity attachments (classroom bulletins etc.) aren't tied to one family -
            // any authenticated user (admin or parent) may view them.
            return true;
        }
        return false;
    }

    /**
     *
     * Age in whole months as of $reference (or now if omitted).
     *
     *
     * @param int       $birthdate Birth date as Unix timestamp.
     * @param int|false $reference Reference timestamp for age; false means now.
     */
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

    /**
     *
     * True if the child is young enough for bottle logging as of $reference.
     *
     *
     * @param int       $birthdate Birth date as Unix timestamp.
     * @param int|false $reference Reference timestamp for age; false means now.
     */
    function status_eligible_for_bottles($birthdate, $reference = false) {
        global $STATUS_BOTTLE_MAX_MONTHS;
        $months = status_age_months($birthdate, $reference);
        return $months !== null && $months < $STATUS_BOTTLE_MAX_MONTHS;
    }

    /**
     *
     * True if the child is young enough for nap-log buttons as of $reference.
     *
     *
     * @param int       $birthdate Birth date as Unix timestamp.
     * @param int|false $reference Reference timestamp for age; false means now.
     */
    function status_eligible_for_naptime($birthdate, $reference = false) {
        global $STATUS_NAP_MAX_MONTHS;
        $months = status_age_months($birthdate, $reference);
        return $months !== null && $months < $STATUS_NAP_MAX_MONTHS;
    }

    /**
     *
     * True if the current time falls in the 1pm–3pm nap window.
     *
     *
     */
    function status_naptime_window_now() {
        global $CFG, $STATUS_NAP_WINDOW;
        $local = new DateTime('now', new DateTimeZone($CFG->timezone));
        $local->setTimestamp(get_timestamp());
        $hour = (int) $local->format('G');
        return $hour >= $STATUS_NAP_WINDOW['start_hour'] && $hour < $STATUS_NAP_WINDOW['end_hour'];
    }

    /**
     *
     * Children belonging to account $aid.
     *
     *
     * @param int $aid Account id.
     */
    function status_children_for_aid($aid) {
        $children = [];
        if ($result = get_db_result("SELECT * FROM children WHERE aid='" . intval($aid) . "' AND deleted=0 ORDER BY first,last")) {
            while ($row = fetch_row($result)) {
                $children[] = ["chid" => (int) $row["chid"], "name" => $row["first"] . " " . $row["last"]];
            }
        }
        return $children;
    }

    /**
     *
     * All currently enrolled children for the admin child list.
     *
     *
     */
    function status_all_children() {
        global $CFG;
        if (!isset($PAGELIB)) {
            include_once($CFG->dirroot . '/lib/pagelib.php');
        }

        $children = [];
        $SQL = 'SELECT c.*, a.name AS family_name
                FROM children c
                JOIN accounts a ON a.aid = c.aid
                WHERE c.deleted = 0
                AND chid IN (SELECT chid FROM enrollments WHERE pid = ' . get_pid() . ')
                ORDER BY family_name, c.first';
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

    /**
     *
     * Family link codes for the admin Family Links panel.
     *
     *
     */
    function status_all_family_links() {
        $families = [];
        $SQL = "SELECT * FROM accounts WHERE deleted=0 AND admin=0 AND link_disabled=0 ORDER BY name";
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

    /**
     *
     * Note category tags available when adding status notes.
     *
     *
     */
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

    /**
     *
     * Attachments linked to one Potty Time or incident entry (by evid).
     *
     *
     * @param int $chid Child id.
     * @param int $evid Event id (events.evid).
     */
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
                    "url"      => $CFG->fileserveurl . "?did=" . (int) $row["did"],
                ];
            }
        }
        return $attachments;
    }

    /**
     *
     * Full day payload for one child: moods, potty, bottles, menu, notes,
     * activities, incidents, naps, and related attachment metadata.
     *
     *
     * @param int        $chid   Child id.
     * @param bool|false $daykey Daykey.
     */
    function status_get_day($chid, $daykey = false) {
        global $STATUS_MOODS, $STATUS_POTTY_TYPES, $STATUS_MEALS, $STATUS_ACTIVITIES, $STATUS_INCIDENT_TYPES, $STATUS_NAP_TAG, $CFG;

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

        // Incidents Quick Report - editable, with note (from notes table via
        // events.nid) + attachments. Falls back to events.note for any row
        // that has not been migrated yet.
        $incidents = [];
        $inctags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_INCIDENT_TYPES))) . "'";
        if ($result = get_db_result("SELECT e.*, n.note AS note_text, n.nid AS note_nid
                                       FROM events e
                                  LEFT JOIN notes n ON n.nid = e.nid AND n.nid != 0
                                      WHERE e.chid='$chid' AND e.daykey='$daykey' AND e.tag IN ($inctags)
                                   ORDER BY e.timelog ASC, e.evid ASC")) {
            while ($row = fetch_row($result)) {
                $info = $STATUS_INCIDENT_TYPES[$row["tag"]];
                $note_text = $row["note_text"] !== null && $row["note_text"] !== ''
                    ? $row["note_text"]
                    : $row["note"];
                $incidents[] = [
                    "evid"        => (int) $row["evid"],
                    "nid"         => (int) ($row["note_nid"] ? $row["note_nid"] : $row["nid"]),
                    "type"        => $row["tag"],
                    "label"       => $info["label"],
                    "emoji"       => $info["emoji"],
                    "color"       => $info["color"],
                    "time"        => get_date("g:i a", display_time($row["timelog"])),
                    "hm"          => get_date("H:i", display_time($row["timelog"])),
                    "note"        => $note_text,
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

        // For kids at/above the nap-logging age cutoff, we don't track
        // individual nap entries - we assume they napped and just record
        // how it went via a simple per-day rating instead.
        $nap_rating = "";
        if (!$show_naptime_buttons) {
            $row = get_db_row("SELECT rating FROM status_nap_rating WHERE chid='$chid' AND daykey='$daykey'");
            if ($row) {
                $nap_rating = $row["rating"];
            }
        }

        // Notes added via the status page (daykey=0 = pre-existing/unrelated).
        // Also include persist notes (notify=2) for this child even if their
        // daykey is not today, so admin can see/edit them and parents keep
        // seeing the notification until cleared.
        $notes = [];
        if ($result = get_db_result("SELECT n.*, t.title AS tag_title, t.color AS tag_color, t.textcolor AS tag_textcolor
                                        FROM notes n
                                        LEFT JOIN notes_tags t ON t.tag = n.tag
                                       WHERE n.chid='$chid' AND n.daykey != 0
                                         AND (n.daykey='$daykey' OR n.notify = 2)
                                       ORDER BY n.timelog ASC")) {
            while ($row = fetch_row($result)) {
                $notes[] = [
                    "nid"       => (int) $row["nid"],
                    "tag"       => $row["tag"],
                    "tag_title" => $row["tag_title"] ? $row["tag_title"] : $row["tag"],
                    "color"     => $row["tag_color"] ? $row["tag_color"] : "#silver",
                    "textcolor" => $row["tag_textcolor"] ? $row["tag_textcolor"] : "#000",
                    "note"      => $row["note"],
                    "notify"    => (int) $row["notify"],
                    "persist"   => ((int) $row["notify"] === 2),
                    "time"      => get_date("g:i a", display_time($row["timelog"])),
                    "hm"        => get_date("H:i", display_time($row["timelog"])),
                ];
            }
        }

        $menus = [];
        $ratings = [];
        foreach ($STATUS_MEALS as $mealkey => $mealinfo) {
            $menus[$mealkey] = "";
            $ratings[$mealkey] = "";
        }
        if ($result = get_db_result("SELECT meal, menu, rating FROM status_menu WHERE chid='$chid' AND daykey='$daykey'")) {
            while ($row = fetch_row($result)) {
                if (array_key_exists($row["meal"], $menus)) {
                    $menus[$row["meal"]] = $row["menu"];
                    $ratings[$row["meal"]] = $row["rating"];
                }
            }
        }

        // Activities - multi-select, so this is the set of activity keys
        // with a row for this child/day, each carrying whether it's
        // currently checked plus its own photo attachments (rows persist
        // across on/off toggles - see status_migrate() - so attachments
        // survive an accidental uncheck).
        $activities = [];
        foreach ($STATUS_ACTIVITIES as $actkey => $actinfo) {
            $activities[$actkey] = ["on" => false, "arid" => 0, "attachments" => []];
        }
        if ($result = get_db_result("SELECT * FROM status_activity WHERE chid='$chid' AND daykey='$daykey'")) {
            while ($row = fetch_row($result)) {
                if (array_key_exists($row["activity"], $activities)) {
                    $arid = (int) $row["id"];
                    $activities[$row["activity"]] = [
                        "on"          => (bool) $row["active"],
                        "arid"        => $arid,
                        "attachments" => status_get_activity_attachments($chid, $arid),
                    ];
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
            "date_label" => get_date("l, F j, Y", $daykey),
            "is_today"   => $daykey == status_daykey(),
            "moods"        => $moods,
            "potty"        => $potty,
            "incidents"    => $incidents,
            "naps"         => $naps,
            "show_naptime_notice"  => $show_naptime_notice,
            "show_naptime_buttons" => $show_naptime_buttons,
            "nap_rating"       => $nap_rating,
            "show_nap_rating"  => !$show_naptime_buttons,
            "menus"        => $menus,
            "ratings"      => $ratings,
            "activities"   => $activities,
            "notes"        => $notes,
            "bottles"      => $bottles,
            "show_bottles" => $show_bottles,
        ];
    }

    /**
     *
     * Log a mood for the child today. Returns the updated day payload.
     *
     *
     * @param int    $chid Child id.
     * @param string $mood Mood tag key.
     */
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

    /**
     *
     * Log a Potty Time entry with optional cream, peed, and pooped flags.
     * Optional hour/minute set the entry time; otherwise uses now.
     *
     *
     * @param int        $chid   Child id.
     * @param string     $type   Type key (mood, potty, incident, etc.).
     * @param int|false  $hour   Local hour, or false to use now.
     * @param int|false  $minute Local minute, or false to use now.
     * @param bool|false $cream  Whether cream was applied.
     * @param bool|false $peed   Whether the child peed.
     * @param bool|false $pooped Whether the child pooped.
     */
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

    /**
     *
     * Edit an existing Potty Time entry (type, time, and flags).
     *
     *
     * @param int        $chid   Child id.
     * @param int        $evid   Event id (events.evid).
     * @param string     $type   Type key (mood, potty, incident, etc.).
     * @param int|false  $hour   Local hour, or false to use now.
     * @param int|false  $minute Local minute, or false to use now.
     * @param bool|false $cream  Whether cream was applied.
     * @param bool|false $peed   Whether the child peed.
     * @param bool|false $pooped Whether the child pooped.
     */
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

    /**
     *
     * Delete a Potty Time entry and any attachments on it.
     *
     *
     * @param int $chid Child id.
     * @param int $evid Event id (events.evid).
     */
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

    /**
     *
     * notes_tags title to use when creating the linked incident note.
     *
     *
     * @param string $type Type key (mood, potty, incident, etc.).
     */
    function status_incident_note_tag_title($type) {
        global $STATUS_INCIDENT_TYPES;
        if (!isset($STATUS_INCIDENT_TYPES[$type])) {
            return 'Injury';
        }
        return isset($STATUS_INCIDENT_TYPES[$type]['note_tag'])
            ? $STATUS_INCIDENT_TYPES[$type]['note_tag']
            : 'Injury';
    }

    /**
     *
     * One-time migration: move legacy events.note text into notes and set nid.
     * Safe to re-run; only touches rows that still have note text and nid=0.
     *
     *
     */
    function status_migrate_incident_notes() {
        global $CFG, $STATUS_INCIDENT_TYPES;
        if (!status_column_exists('events', 'nid')) {
            return;
        }
        if (!function_exists('make_or_get_tag')) {
            include_once($CFG->dirroot . '/lib/pagelib.php');
        }
        $inctags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_INCIDENT_TYPES))) . "'";
        $result = get_db_result("SELECT evid, chid, aid, daykey, timelog, tag, note
                                   FROM events
                                  WHERE tag IN ($inctags)
                                    AND nid = 0
                                    AND note != ''");
        if (!$result) {
            return;
        }
        while ($row = fetch_row($result)) {
            $tag_title = status_incident_note_tag_title($row['tag']);
            $tag = make_or_get_tag($tag_title, 'notes');
            $note = dbescape($row['note']);
            $tag  = dbescape($tag);
            $chid = intval($row['chid']);
            $aid  = intval($row['aid']);
            $day  = intval($row['daykey']);
            $time = intval($row['timelog']);
            $evid = intval($row['evid']);
            // notify=1 (single-day) for migrated incident notes
            $nid = execute_db_sql("INSERT INTO notes (pid, aid, cid, actid, chid, employeeid, rnid, tag, note, data, timelog, notify, daykey)
                                   VALUES (0,'$aid',0,0,'$chid',0,0,'$tag','$note','','$time',1,'$day')");
            if ($nid) {
                execute_db_sql("UPDATE events SET nid='$nid', note='' WHERE evid='$evid'");
            }
        }
    }

    /**
     *
     * Log a quick incident with optional note text and time.
     *
     *
     * @param int       $chid   Child id.
     * @param string    $type   Type key (mood, potty, incident, etc.).
     * @param string    $note   Note text.
     * @param int|false $hour   Local hour, or false to use now.
     * @param int|false $minute Local minute, or false to use now.
     */
    function status_add_incident($chid, $type, $note = null, $hour = false, $minute = 0) {
        global $CFG, $STATUS_INCIDENT_TYPES;
        if (!isset($STATUS_INCIDENT_TYPES[$type])) {
            return false;
        }
        if (!function_exists('make_or_get_tag')) {
            include_once($CFG->dirroot . '/lib/pagelib.php');
        }
        $chid = intval($chid);
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        // Optional time from the draft editor; fall back to "now".
        if ($hour !== false && $hour !== null && $hour !== '') {
            $time = status_resolve_timelog($hour, $minute);
        } else {
            $time = get_timestamp();
        }
        $day  = status_daykey($time);
        // Optional note from the draft editor; fall back to the type default.
        $note_text = ($note !== null && $note !== '')
            ? $note
            : $STATUS_INCIDENT_TYPES[$type]['default_note'];
        $tag_title = status_incident_note_tag_title($type);
        $tag = make_or_get_tag($tag_title, 'notes');
        // Single-day parent notify for incidents
        $nid = execute_db_sql("INSERT INTO notes (pid, aid, cid, actid, chid, employeeid, rnid, tag, note, data, timelog, notify, daykey)
                               VALUES (0,'$aid',0,0,'$chid',0,0,'" . dbescape($tag) . "','" . dbescape($note_text) . "','','$time',1,'$day')");
        $nid = intval($nid);
        $evid = execute_db_sql("INSERT INTO events (pid, tag, sort, chid, aid, daykey, timelog, note, nid)
                                 VALUES (0,'" . dbescape($type) . "',0,'$chid','$aid','$day','$time','','$nid')");
        status_push_notify_incident($aid, $chid, $type);
        return ["evid" => $evid, "nid" => $nid, "day" => status_get_day($chid, $day)];
    }

    /**
     *
     * Edit an existing incident (type, note, and time).
     *
     *
     * @param int       $chid   Child id.
     * @param int       $evid   Event id (events.evid).
     * @param string    $type   Type key (mood, potty, incident, etc.).
     * @param string    $note   Note text.
     * @param int|false $hour   Local hour, or false to use now.
     * @param int|false $minute Local minute, or false to use now.
     */
    function status_edit_incident($chid, $evid, $type, $note, $hour, $minute) {
        global $CFG, $STATUS_INCIDENT_TYPES;
        if (!isset($STATUS_INCIDENT_TYPES[$type])) {
            return false;
        }
        if (!function_exists('make_or_get_tag')) {
            include_once($CFG->dirroot . '/lib/pagelib.php');
        }
        $chid = intval($chid);
        $evid = intval($evid);
        $inctags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_INCIDENT_TYPES))) . "'";
        $row = get_db_row("SELECT * FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($inctags)");
        if (!$row) {
            return false;
        }
        $timelog = status_resolve_timelog($hour, $minute);
        $day = status_daykey($timelog);
        $nid = intval($row['nid']);
        $tag_title = status_incident_note_tag_title($type);
        $tag = make_or_get_tag($tag_title, 'notes');

        if ($nid) {
            execute_db_sql("UPDATE notes SET tag='" . dbescape($tag) . "', note='" . dbescape($note) . "', daykey='$day', timelog='$timelog'
                             WHERE nid='$nid' AND chid='$chid'");
        } else {
            // Legacy row still on events.note - create the notes row now
            $aid = intval($row['aid']);
            $nid = execute_db_sql("INSERT INTO notes (pid, aid, cid, actid, chid, employeeid, rnid, tag, note, data, timelog, notify, daykey)
                                   VALUES (0,'$aid',0,0,'$chid',0,0,'" . dbescape($tag) . "','" . dbescape($note) . "','','$timelog',1,'$day')");
            $nid = intval($nid);
        }
        execute_db_sql("UPDATE events SET tag='" . dbescape($type) . "', note='', nid='$nid', timelog='$timelog', daykey='$day'
                         WHERE evid='$evid' AND chid='$chid'");
        return status_get_day($chid, $day);
    }

    /**
     *
     * Delete an incident event and its linked note and attachments.
     *
     *
     * @param int $chid Child id.
     * @param int $evid Event id (events.evid).
     */
    function status_delete_incident($chid, $evid) {
        global $STATUS_INCIDENT_TYPES;
        $chid = intval($chid);
        $evid = intval($evid);
        $inctags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_INCIDENT_TYPES))) . "'";
        $row = get_db_row("SELECT * FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($inctags)");
        if (!$row) {
            return status_get_day($chid);
        }
        if ($result = get_db_result("SELECT * FROM documents WHERE chid='$chid' AND evid='$evid'")) {
            while ($doc = fetch_row($result)) {
                status_delete_attachment_row($doc);
            }
        }
        $nid = intval($row['nid']);
        if ($nid) {
            execute_db_sql("DELETE FROM notes WHERE nid='$nid' AND chid='$chid' AND daykey != 0");
        }
        execute_db_sql("DELETE FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($inctags)");
        return status_get_day($chid);
    }

    /**
     *
     * Log a nap of $minutes duration (entry is backdated from now).
     *
     *
     * @param int $chid    Child id.
     * @param int $minutes Duration in minutes.
     */
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

    /**
     *
     * Edit the start time of an existing nap entry.
     *
     *
     * @param int       $chid   Child id.
     * @param int       $evid   Event id (events.evid).
     * @param int|false $hour   Local hour, or false to use now.
     * @param int|false $minute Local minute, or false to use now.
     */
    function status_edit_nap_time($chid, $evid, $hour, $minute) {
        global $STATUS_NAP_TAG;
        $chid = intval($chid);
        $evid = intval($evid);
        if (!get_db_count("SELECT evid FROM events WHERE evid='$evid' AND chid='$chid' AND tag='" . dbescape($STATUS_NAP_TAG) . "'")) {
            return false;
        }
        $timelog = status_resolve_timelog($hour, $minute);
        $day = status_daykey($timelog);
        execute_db_sql("UPDATE events SET timelog='$timelog', daykey='$day' WHERE evid='$evid' AND chid='$chid'");
        return status_get_day($chid, $day);
    }

    /**
     *
     * Delete a nap entry.
     *
     *
     * @param int $chid Child id.
     * @param int $evid Event id (events.evid).
     */
    function status_delete_nap($chid, $evid) {
        global $STATUS_NAP_TAG;
        $chid = intval($chid);
        $evid = intval($evid);
        execute_db_sql("DELETE FROM events WHERE evid='$evid' AND chid='$chid' AND tag='" . dbescape($STATUS_NAP_TAG) . "'");
        return status_get_day($chid);
    }

    /**
     *
     * Set the simple nap rating for one child for today.
     *
     *
     * @param int    $chid   Child id.
     * @param string $rating Rating key.
     */
    function status_set_nap_rating($chid, $rating) {
        global $STATUS_NAP_RATINGS;
        if ($rating !== '' && !isset($STATUS_NAP_RATINGS[$rating])) {
            return false;
        }
        $chid = intval($chid);
        $day  = status_daykey();
        $time = get_timestamp();
        $ratingesc = dbescape($rating);
        if (get_db_count("SELECT id FROM status_nap_rating WHERE chid='$chid' AND daykey='$day'")) {
            execute_db_sql("UPDATE status_nap_rating SET rating='$ratingesc', timelog='$time' WHERE chid='$chid' AND daykey='$day'");
        } else {
            execute_db_sql("INSERT INTO status_nap_rating (chid, daykey, rating, timelog) VALUES ('$chid','$day','$ratingesc','$time')");
        }
        return status_get_day($chid, $day);
    }

    /**
     *
     * Apply one nap rating to enrolled kids in the simple-rating age group
     * who do not already have a rating today. Fills gaps only; returns chids written.
     *
     *
     * @param string $rating Rating key.
     */
    function status_set_nap_rating_for_all($rating) {
        global $STATUS_NAP_RATINGS;
        if ($rating !== '' && !isset($STATUS_NAP_RATINGS[$rating])) {
            return [];
        }
        $day  = status_daykey();
        $time = get_timestamp();
        $ratingesc = dbescape($rating);
        $written = [];
        $SQL = "SELECT chid, birthdate FROM children
                 WHERE deleted = 0
                   AND chid IN (SELECT chid FROM enrollments WHERE pid = " . get_pid() . ")";
        if ($result = get_db_result($SQL)) {
            while ($row = fetch_row($result)) {
                if (status_eligible_for_naptime($row["birthdate"])) {
                    continue; // under the age cutoff - uses logged nap entries instead
                }
                $chid = intval($row["chid"]);
                $existing = get_db_row("SELECT id, rating FROM status_nap_rating WHERE chid='$chid' AND daykey='$day'");
                if ($existing) {
                    if ($existing["rating"] !== '') {
                        continue; // already rated - don't clobber it
                    }
                    execute_db_sql("UPDATE status_nap_rating SET rating='$ratingesc', timelog='$time' WHERE chid='$chid' AND daykey='$day'");
                } else {
                    execute_db_sql("INSERT INTO status_nap_rating (chid, daykey, rating, timelog) VALUES ('$chid','$day','$ratingesc','$time')");
                }
                $written[] = $chid;
            }
        }
        return $written;
    }

    /**
     *
     * Edit the time on an existing mood entry.
     *
     *
     * @param int       $chid   Child id.
     * @param int       $evid   Event id (events.evid).
     * @param int|false $hour   Local hour, or false to use now.
     * @param int|false $minute Local minute, or false to use now.
     */
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

    /**
     *
     * Edit the time on an existing bottle entry.
     *
     *
     * @param int       $chid   Child id.
     * @param int       $evid   Event id (events.evid).
     * @param int|false $hour   Local hour, or false to use now.
     * @param int|false $minute Local minute, or false to use now.
     */
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

    /**
     *
     * Attach an uploaded file to a Potty Time or incident event.
     *
     *
     * @param int    $chid     Child id.
     * @param int    $evid     Event id (events.evid).
     * @param string $filename Stored filename.
     * @param string $tag      Tag or category key.
     */
    function status_add_attachment($chid, $evid, $filename, $tag = 'attachment') {
        $chid = intval($chid);
        $evid = intval($evid);
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time = get_timestamp();
        execute_db_sql("INSERT INTO documents (aid, chid, evid, tag, filename, description, timelog)
                         VALUES ('$aid','$chid','$evid','" . dbescape($tag) . "','" . dbescape($filename) . "','','$time')");
        status_push_notify_photo($aid, $chid, $filename);
        return status_get_attachments($chid, $evid);
    }

    /**
     *
     * Delete one documents row and remove its file from disk.
     *
     *
     * @param array $row Database row.
     */
    function status_delete_attachment_row($row) {
        global $CFG;
        $path = $CFG->userfilespath . "/children/" . $row["chid"] . "/" . $row["filename"];
        if (file_exists($path)) {
            @unlink($path);
        }
        execute_db_sql("DELETE FROM documents WHERE did='" . intval($row["did"]) . "'");
    }

    /**
     *
     * Delete an attachment by did if the session may access it.
     *
     *
     * @param int $chid Child id.
     * @param int $did  Document id (documents.did).
     */
    function status_delete_attachment($chid, $did) {
        $chid = intval($chid);
        $did  = intval($did);
        if ($row = get_db_row("SELECT * FROM documents WHERE did='$did' AND chid='$chid'")) {
            $evid = intval($row["evid"]);
            $arid = isset($row["arid"]) ? intval($row["arid"]) : 0;
            status_delete_attachment_row($row);
            if ($arid) {
                return status_get_activity_attachments($chid, $arid);
            }
            return status_get_attachments($chid, $evid);
        }
        return [];
    }

    /**
     *
     * Create a quick-tap note (Request tag) for a predefined key.
     *
     *
     * @param int    $chid Child id.
     * @param string $key  Quick-note preset key.
     */
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
        $notify_level = isset($info['notify']) ? intval($info['notify']) : 1;
        $chid = intval($chid);

        // Toggle-off for persistent Need Diapers
        if ($key === 'need_diapers' && $notify_level === 2) {
            $existing = get_db_row("SELECT nid FROM notes
                                     WHERE chid='$chid' AND daykey != 0 AND notify = 2
                                       AND note = '" . dbescape($info['text']) . "'
                                     ORDER BY timelog DESC LIMIT 1");
            if ($existing) {
                execute_db_sql("UPDATE notes SET notify=0 WHERE nid='" . intval($existing['nid']) . "' AND chid='$chid'");
                return status_get_day($chid);
            }
        }

        return status_add_note($chid, $tag, $info['text'], $notify_level);
    }

    /**
     *
     * Save menu text for one meal for one child for today.
     *
     *
     * @param int    $chid Child id.
     * @param string $meal Meal key (breakfast, lunch, dinner).
     * @param string $menu Menu text.
     */
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

    /**
     *
     * Set the meal rating for one child and meal for today.
     *
     *
     * @param int    $chid   Child id.
     * @param string $meal   Meal key (breakfast, lunch, dinner).
     * @param string $rating Rating key.
     */
    function status_set_meal_rating($chid, $meal, $rating) {
        global $STATUS_MEALS, $STATUS_MEAL_RATINGS;
        if (!isset($STATUS_MEALS[$meal])) {
            return false;
        }
        if ($rating !== '' && !isset($STATUS_MEAL_RATINGS[$rating])) {
            return false;
        }
        $chid = intval($chid);
        $day  = status_daykey();
        $time = get_timestamp();
        $mealesc   = dbescape($meal);
        $ratingesc = dbescape($rating);
        if (get_db_count("SELECT id FROM status_menu WHERE chid='$chid' AND daykey='$day' AND meal='$mealesc'")) {
            execute_db_sql("UPDATE status_menu SET rating='$ratingesc', timelog='$time' WHERE chid='$chid' AND daykey='$day' AND meal='$mealesc'");
        } else {
            execute_db_sql("INSERT INTO status_menu (chid, daykey, meal, menu, rating, timelog) VALUES ('$chid','$day','$mealesc','','$ratingesc','$time')");
        }
        return status_get_day($chid, $day);
    }

    /**
     *
     * Apply one meal rating to enrolled kids missing a rating for that meal today.
     * Fills gaps only; does not change menu text. Returns chids written.
     *
     *
     * @param string $meal   Meal key (breakfast, lunch, dinner).
     * @param string $rating Rating key.
     */
    function status_set_meal_rating_for_all($meal, $rating) {
        global $STATUS_MEALS, $STATUS_MEAL_RATINGS;
        if (!isset($STATUS_MEALS[$meal])) {
            return [];
        }
        if ($rating !== '' && !isset($STATUS_MEAL_RATINGS[$rating])) {
            return [];
        }
        $day  = status_daykey();
        $time = get_timestamp();
        $mealesc   = dbescape($meal);
        $ratingesc = dbescape($rating);
        $written = [];
        $SQL = "SELECT chid FROM children
                 WHERE deleted = 0
                   AND chid IN (SELECT chid FROM enrollments WHERE pid = " . get_pid() . ")";
        if ($result = get_db_result($SQL)) {
            while ($row = fetch_row($result)) {
                $chid = intval($row["chid"]);
                $existing = get_db_row("SELECT id, rating FROM status_menu WHERE chid='$chid' AND daykey='$day' AND meal='$mealesc'");
                if ($existing) {
                    if ($existing["rating"] !== '') {
                        continue; // already rated - don't clobber it
                    }
                    execute_db_sql("UPDATE status_menu SET rating='$ratingesc', timelog='$time' WHERE chid='$chid' AND daykey='$day' AND meal='$mealesc'");
                } else {
                    execute_db_sql("INSERT INTO status_menu (chid, daykey, meal, menu, rating, timelog) VALUES ('$chid','$day','$mealesc','','$ratingesc','$time')");
                }
                $written[] = $chid;
            }
        }
        return $written;
    }

    /**
     *
     * Copy menu text for a meal onto the selected children.
     *
     *
     * @param string $meal  Meal key (breakfast, lunch, dinner).
     * @param string $menu  Menu text.
     * @param array  $chids Target child ids.
     */
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

    /**
     *
     * Recent menu text suggestions for a meal (for quick-fill UI).
     *
     *
     * @param int    $chid Child id.
     * @param string $meal Meal key (breakfast, lunch, dinner).
     */
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

    /**
     *
     * Toggle an activity on or off for the child for today.
     * Rows persist when unchecked so photo attachments stay linked.
     *
     *
     * @param int    $chid     Child id.
     * @param string $activity Activity key.
     * @param bool   $on       True to check the activity, false to uncheck.
     */
    function status_toggle_activity($chid, $activity, $on) {
        global $STATUS_ACTIVITIES;
        if (!isset($STATUS_ACTIVITIES[$activity])) {
            return false;
        }
        $chid = intval($chid);
        $day  = status_daykey();
        $time = get_timestamp();
        $actesc = dbescape($activity);
        $activeval = $on ? 1 : 0;
        if ($existing = get_db_row("SELECT id FROM status_activity WHERE chid='$chid' AND daykey='$day' AND activity='$actesc'")) {
            execute_db_sql("UPDATE status_activity SET active='$activeval', timelog='$time' WHERE id='" . intval($existing["id"]) . "'");
        } else {
            execute_db_sql("INSERT INTO status_activity (chid, daykey, activity, active, timelog) VALUES ('$chid','$day','$actesc','$activeval','$time')");
        }
        return status_get_day($chid, $day);
    }

    /**
     *
     * Copy $fromChid's checked activities for today onto each target child.
     * Toggles existing rows so photo attachments are preserved. Returns chids written.
     *
     *
     * @param int   $fromChid Source child id whose activities are copied.
     * @param array $chids    Target child ids.
     */
    function status_copy_activities($fromChid, $chids) {
        global $STATUS_ACTIVITIES;
        $fromChid = intval($fromChid);
        $day  = status_daykey();
        $time = get_timestamp();

        $selected = [];
        if ($result = get_db_result("SELECT activity FROM status_activity WHERE chid='$fromChid' AND daykey='$day' AND active=1")) {
            while ($row = fetch_row($result)) {
                if (isset($STATUS_ACTIVITIES[$row["activity"]])) {
                    $selected[] = $row["activity"];
                }
            }
        }
        $selectedSet = array_flip($selected);

        $written = [];
        foreach ($chids as $chid) {
            $chid = intval($chid);
            if (!$chid) {
                continue;
            }

            // Uncheck anything currently on that isn't in the new set -
            // the row (and any attachments) stays, just marked inactive.
            if ($result = get_db_result("SELECT id, activity FROM status_activity WHERE chid='$chid' AND daykey='$day' AND active=1")) {
                while ($row = fetch_row($result)) {
                    if (!isset($selectedSet[$row["activity"]])) {
                        execute_db_sql("UPDATE status_activity SET active=0, timelog='$time' WHERE id='" . intval($row["id"]) . "'");
                    }
                }
            }

            foreach ($selected as $activity) {
                $actesc = dbescape($activity);
                if ($existing = get_db_row("SELECT id FROM status_activity WHERE chid='$chid' AND daykey='$day' AND activity='$actesc'")) {
                    execute_db_sql("UPDATE status_activity SET active=1, timelog='$time' WHERE id='" . intval($existing["id"]) . "'");
                } else {
                    execute_db_sql("INSERT INTO status_activity (chid, daykey, activity, active, timelog) VALUES ('$chid','$day','$actesc','1','$time')");
                }
            }
            $written[] = $chid;
        }
        return $written;
    }

    /**
     *
     * Attachments for one status_activity row.
     *
     *
     * @param int $chid Child id.
     * @param int $arid Activity row id (status_activity).
     */
    function status_get_activity_attachments($chid, $arid) {
        global $CFG;
        $chid = intval($chid);
        $arid = intval($arid);
        $attachments = [];
        if (!$arid) {
            return $attachments;
        }
        if ($result = get_db_result("SELECT * FROM documents WHERE chid='$chid' AND arid='$arid' ORDER BY timelog ASC")) {
            while ($row = fetch_row($result)) {
                $attachments[] = [
                    "did"      => (int) $row["did"],
                    "filename" => $row["filename"],
                    "url"      => $CFG->fileserveurl . "?did=" . (int) $row["did"],
                ];
            }
        }
        return $attachments;
    }

    /**
     *
     * Attach an uploaded file to an activity row.
     *
     *
     * @param int    $chid     Child id.
     * @param int    $arid     Activity row id (status_activity).
     * @param string $filename Stored filename.
     */
    function status_add_activity_attachment($chid, $arid, $filename) {
        $chid = intval($chid);
        $arid = intval($arid);
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time = get_timestamp();
        execute_db_sql("INSERT INTO documents (aid, chid, arid, tag, filename, description, timelog)
                         VALUES ('$aid','$chid','$arid','activity','" . dbescape($filename) . "','','$time')");
        status_push_notify_photo($aid, $chid, $filename);
        return status_get_activity_attachments($chid, $arid);
    }

    /**
     *
     * Set the child's avatar image (documents tag=avatar).
     *
     *
     * @param int    $chid     Child id.
     * @param string $filename Stored filename.
     */
    function status_set_avatar($chid, $filename) {
        global $CFG;
        $chid = intval($chid);
        if ($existing = get_db_row("SELECT * FROM documents WHERE chid='$chid' AND tag='avatar'")) {
            status_delete_attachment_row($existing);
        }
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time = get_timestamp();
        execute_db_sql("INSERT INTO documents (aid, chid, tag, filename, description, timelog)
                         VALUES ('$aid','$chid','avatar','" . dbescape($filename) . "','','$time')");
        return status_get_day($chid);
    }

    /**
     *
     * Normalize a notify value to 0 (none), 1 (single-day), or 2 (persist).
     * Accepts bool (legacy) or int 0/1/2.
     *
     *
     * @param int|bool $notify Notify flag: 0 none, 1 single-day, 2 persist (or bool legacy).
     */
    function status_normalize_notify($notify) {
        if ($notify === true || $notify === '1' || $notify === 1) {
            return 1;
        }
        if ($notify === '2' || $notify === 2) {
            return 2;
        }
        return 0;
    }

    /**
     *
     * Add a day-scoped note with optional parent notify flag.
     *
     *
     * @param int      $chid   Child id.
     * @param string   $tag    Tag or category key.
     * @param string   $note   Note text.
     * @param int|bool $notify Notify flag: 0 none, 1 single-day, 2 persist (or bool legacy).
     */
    function status_add_note($chid, $tag, $note, $notify) {
        $tags = array_column(status_notes_tags(), 'tag');
        if (!in_array($tag, $tags)) {
            return false;
        }
        $chid   = intval($chid);
        $aid    = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time   = get_timestamp();
        $day    = status_daykey($time);
        $notify = status_normalize_notify($notify);
        $note   = dbescape($note);
        $tag    = dbescape($tag);
        execute_db_sql("INSERT INTO notes (pid, aid, cid, actid, chid, employeeid, rnid, tag, note, data, timelog, notify, daykey)
                        VALUES (0,'$aid',0,0,'$chid',0,0,'$tag','$note','','$time','$notify','$day')");
        return status_get_day($chid, $day);
    }

    /**
     *
     * Edit a day-scoped note (tag, text, and notify flag).
     *
     *
     * @param int      $nid    Note id (notes.nid).
     * @param int      $chid   Child id.
     * @param string   $tag    Tag or category key.
     * @param string   $note   Note text.
     * @param int|bool $notify Notify flag: 0 none, 1 single-day, 2 persist (or bool legacy).
     */
    function status_edit_note($nid, $chid, $tag, $note, $notify) {
        $tags = array_column(status_notes_tags(), 'tag');
        if (!in_array($tag, $tags)) {
            return false;
        }
        $nid    = intval($nid);
        $chid   = intval($chid);
        $notify = status_normalize_notify($notify);
        $note   = dbescape($note);
        $tag    = dbescape($tag);
        // Only notes this feature created (daykey != 0)
        execute_db_sql("UPDATE notes SET tag='$tag', note='$note', notify='$notify'
                         WHERE nid='$nid' AND chid='$chid' AND daykey != 0");
        return status_get_day($chid, status_daykey());
    }

    /**
     *
     * Delete a day-scoped note belonging to this child.
     *
     *
     * @param int $nid  Note id (notes.nid).
     * @param int $chid Child id.
     */
    function status_delete_note($nid, $chid) {
        $nid  = intval($nid);
        $chid = intval($chid);
        // Only notes this feature created (daykey != 0)
        execute_db_sql("DELETE FROM notes WHERE nid='$nid' AND chid='$chid' AND daykey != 0");
        // Clear any incident event that pointed at this note
        if (status_column_exists('events', 'nid')) {
            execute_db_sql("UPDATE events SET nid=0 WHERE nid='$nid' AND chid='$chid'");
        }
        return status_get_day($chid, status_daykey());
    }

    /**
     *
     * Change the mood type on an existing mood event.
     *
     *
     * @param int    $chid    Child id.
     * @param int    $evid    Event id (events.evid).
     * @param string $newmood New mood tag key.
     */
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

    /**
     *
     * Delete a mood event.
     *
     *
     * @param int $chid Child id.
     * @param int $evid Event id (events.evid).
     */
    function status_delete_mood($chid, $evid) {
        global $STATUS_MOODS;
        $chid = intval($chid);
        $evid = intval($evid);
        $moodtags = "'" . implode("','", array_map('dbescape', array_keys($STATUS_MOODS))) . "'";
        execute_db_sql("DELETE FROM events WHERE evid='$evid' AND chid='$chid' AND tag IN ($moodtags)");
        return status_get_day($chid);
    }

    /**
     *
     * Log a bottle entry with optional ounces amount.
     *
     *
     * @param int       $chid   Child id.
     * @param int|false $ounces Bottle ounces, or false if unset.
     */
    function status_add_bottle($chid, $ounces = false) {
        $chid = intval($chid);
        $aid  = intval(get_db_field("aid", "children", "chid='$chid'"));
        $time = get_timestamp();
        $day  = status_daykey($time);
        $amount = ($ounces !== false && $ounces !== '') ? intval($ounces) : 0;
        execute_db_sql("INSERT INTO events (pid, tag, sort, chid, aid, daykey, timelog, amount) VALUES (0,'" . dbescape($GLOBALS['STATUS_BOTTLE_TAG']) . "',0,'$chid','$aid','$day','$time','$amount')");
        return status_get_day($chid, $day);
    }

    /**
     *
     * Edit the ounces amount on an existing bottle entry.
     *
     *
     * @param int       $chid   Child id.
     * @param int       $evid   Event id (events.evid).
     * @param int|false $ounces Bottle ounces, or false if unset.
     */
    function status_edit_bottle_ounces($chid, $evid, $ounces) {
        $chid = intval($chid);
        $evid = intval($evid);
        if (!get_db_count("SELECT evid FROM events WHERE evid='$evid' AND chid='$chid' AND tag='" . dbescape($GLOBALS['STATUS_BOTTLE_TAG']) . "'")) {
            return false;
        }
        execute_db_sql("UPDATE events SET amount='" . intval($ounces) . "' WHERE evid='$evid' AND chid='$chid'");
        return status_get_day($chid);
    }

    /**
     *
     * Delete a bottle entry.
     *
     *
     * @param int $chid Child id.
     * @param int $evid Event id (events.evid).
     */
    function status_delete_bottle($chid, $evid) {
        $chid = intval($chid);
        $evid = intval($evid);
        execute_db_sql("DELETE FROM events WHERE evid='$evid' AND chid='$chid' AND tag='" . dbescape($GLOBALS['STATUS_BOTTLE_TAG']) . "'");
        return status_get_day($chid);
    }
}
