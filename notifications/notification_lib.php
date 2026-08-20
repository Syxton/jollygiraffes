<?php

/***************************************************************************
* notification_lib.php - Web Push storage layer
* -------------------------------------------------------------------------
* One generic table backs the whole notifications feature:
*
*   notifications
*     identifier  - unique string key
*     contents    - JSON blob for that key
*
* Two kinds of rows live here:
*   - identifier = 'SITE'  -> contents = {publicKey, privateKey} (VAPID
*     keypair, generated once and reused for every push sent by this site)
*   - identifier = <hash>  -> contents = a browser PushSubscription object
*     (endpoint + keys), one row per subscribed device, keyed by whatever
*     the caller considers that device/subscriber's identity (see
*     status_push_identifier() in status_lib.php, which hashes the
*     subscription's own endpoint and tags the contents with an `aid` so
*     rows can still be looked up per family).
*
* This file only knows about identifier/contents storage - it doesn't know
* who a subscriber is or how their identifier is derived, so it stays
* usable by anything that wants to store a push subscription
* (status_lib.php, WebPushHelper.php, api.php).
*
* Depends on lib/dblib.php having already been loaded (get_db_row,
* execute_db_sql, get_db_result, fetch_row).
***************************************************************************/

if (!isset($NOTIFICATIONLIB)) {
    $NOTIFICATIONLIB = true;

    define('NOTIFICATIONS_SITE_IDENTIFIER', 'SITE');

    // Safe to call every request - creates the table on first run, and
    // makes sure a site VAPID keypair exists once it does.
    function notifications_migrate() {
        execute_db_sql("
            CREATE TABLE IF NOT EXISTS `notifications` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `identifier` varchar(191) COLLATE utf8_unicode_ci NOT NULL,
              `contents` text COLLATE utf8_unicode_ci NOT NULL,
              `timelog` int(11) NOT NULL DEFAULT '0',
              PRIMARY KEY (`id`),
              UNIQUE KEY `identifier` (`identifier`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

        if (!notifications_get(NOTIFICATIONS_SITE_IDENTIFIER)) {
            notifications_generate_and_store_vapid_keys();
        }
    }

    // Returns the decoded contents array for an identifier, or false if
    // that identifier has no row (or its contents aren't valid JSON).
    function notifications_get($identifier) {
        $row = get_db_row("SELECT contents FROM notifications WHERE identifier = ||identifier||", false, ["identifier" => $identifier]);
        if (!$row) {
            return false;
        }
        $decoded = json_decode($row["contents"], true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : false;
    }

    // Inserts or replaces the row for an identifier. $contents can be an
    // array/object (will be json_encode'd) or an already-encoded string.
    function notifications_set($identifier, $contents) {
        $json = is_string($contents) ? $contents : json_encode($contents);
        return execute_db_sql("
            INSERT INTO notifications (identifier, contents, timelog)
            VALUES (||identifier||, ||contents||, ||timelog||)
            ON DUPLICATE KEY UPDATE contents = ||contents||, timelog = ||timelog||",
            ["identifier" => $identifier, "contents" => $json, "timelog" => time()]
        );
    }

    function notifications_delete($identifier) {
        return execute_db_sql("DELETE FROM notifications WHERE identifier = ||identifier||", ["identifier" => $identifier]);
    }

    // All stored push subscriptions, keyed by identifier - i.e. every row
    // except the SITE VAPID keypair. Used when broadcasting to everyone.
    function notifications_all_subscriptions() {
        $subs = [];
        $result = get_db_result(
            "SELECT identifier, contents FROM notifications WHERE identifier != ||site||",
            ["site" => NOTIFICATIONS_SITE_IDENTIFIER]
        );
        if (!$result) {
            return $subs;
        }
        while ($row = fetch_row($result)) {
            $decoded = json_decode($row["contents"], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $subs[$row["identifier"]] = $decoded;
            }
        }
        return $subs;
    }

    // Generates a fresh VAPID keypair and stores it under the SITE
    // identifier. Only ever needs to run once per site.
    function notifications_generate_and_store_vapid_keys() {
        require_once __DIR__ . '/vendor/autoload.php';
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        notifications_set(NOTIFICATIONS_SITE_IDENTIFIER, $keys);
        return $keys;
    }

    // Fetches the site's VAPID keypair, generating/storing it first if
    // this is somehow called before notifications_migrate() ran.
    function notifications_get_vapid_keys() {
        $keys = notifications_get(NOTIFICATIONS_SITE_IDENTIFIER);
        return $keys ? $keys : notifications_generate_and_store_vapid_keys();
    }
}