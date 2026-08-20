<?php

/***************************************************************************
* generate-vapid.php - Manual/CLI helper to (re)generate the site's VAPID
* keypair and store it in the `notifications` table (identifier='SITE').
*
* Normally you never need to run this - notifications_migrate() creates
* the keypair automatically the first time the notifications table is
* created (see notification_lib.php). This script is here for cases where
* you deliberately want to rotate the keys (e.g. `php generate-vapid.php`),
* which will invalidate every existing push subscription since browsers
* tie a subscription to the public key it was created with.
***************************************************************************/

if (!isset($CFG)) {
    include_once __DIR__ . '/../config.php';
}
if (!isset($DBLIB)) {
    include_once $CFG->dirroot . '/lib/dblib.php';
}
require_once __DIR__ . '/notification_lib.php';

$keys = notifications_generate_and_store_vapid_keys();

//echo "VAPID keys generated and stored in the notifications table (identifier='SITE').\n";
//echo "Public Key:\n" . $keys['publicKey'] . "\n";