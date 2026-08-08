<?php

/***************************************************************************
* kiosk_login.php - Unlocks/locks the front-desk check-in kiosk (index.php).
* -------------------------------------------------------------------------
* Deliberately NOT dispatched through ajax.php's callfunction() (which calls
* whatever function name is passed in $_POST['action']) - this is a small,
* single-purpose, session-based endpoint instead.
***************************************************************************/

if (!isset($CFG)) {
    include_once('../config.php');
}
if (!isset($DBLIB)) {
    include_once($CFG->dirroot . '/lib/dblib.php');
}
if (!isset($TIMELIB)) {
    include_once($CFG->dirroot . '/lib/timelib.php');
}
if (!isset($STATUSLIB)) {
    include_once($CFG->dirroot . '/lib/status_lib.php');
}

status_migrate();
status_start_session();

header('Content-Type: application/json');

$kiosk_action = isset($_POST['kiosk_action']) ? $_POST['kiosk_action'] : '';

if ($kiosk_action === 'unlock') {
    $pin = isset($_POST['password']) ? $_POST['password'] : '';
    // status_login_admin() already applies the app's existing brute-force throttle
    // (5 attempts / 60 second lockout) and sets $_SESSION['status_role'] = 'admin' on success.
    $result = status_login_admin($pin);
    echo json_encode($result);
    exit;
}

if ($kiosk_action === 'lock') {
    status_logout();
    echo json_encode(["success" => true]);
    exit;
}

echo json_encode(["success" => false, "message" => "Unknown action."]);
