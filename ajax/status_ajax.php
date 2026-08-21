<?php

/***************************************************************************
* status_ajax.php - AJAX backend for the Daily Status Report feature.
* Session-based (unlike the rest of the app's stateless numpad flows).
* Actions are POSTed with an "action" field and return JSON.
***************************************************************************/

$LIBHEADER = true;

if (!isset($CFG)) {
    include_once('../config.php');
}
if (!isset($DBLIB)) {
    include_once($CFG->dirroot . '/lib/dblib.php');
}
if (!isset($TIMELIB)) {
    include_once($CFG->dirroot . '/lib/timelib.php');
}
if (!isset($FILELIB)) {
    include_once($CFG->dirroot . '/lib/filelib.php');
}
if (!isset($STATUSLIB)) {
    include_once($CFG->dirroot . '/lib/status_lib.php');
}

status_migrate();
status_start_session();

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

function status_json($data) {
    echo json_encode($data);
    exit();
}

function status_require_auth() {
    if (!status_current_role()) {
        status_json(["success" => false, "message" => "Please log in again.", "expired" => true]);
    }
}

function status_require_admin() {
    if (status_current_role() != 'admin') {
        status_json(["success" => false, "message" => "Admin access required.", "expired" => true]);
    }
}

function status_require_child_access($chid) {
    if (!status_can_access_child($chid)) {
        status_json(["success" => false, "message" => "You don't have access to that child."]);
    }
}

switch ($action) {

    case 'session_check':
        $role = status_current_role();
        if (!$role) {
            status_json(["success" => true, "loggedIn" => false]);
        }
        if ($role == 'admin') {
            status_json([
                "success"  => true,
                "loggedIn" => true,
                "role"     => "admin",
                "children" => status_all_children(),
                "moods"    => $GLOBALS['STATUS_MOODS'],
                "pottyTypes" => $GLOBALS['STATUS_POTTY_TYPES'],
                "quickNotes" => $GLOBALS['STATUS_QUICK_NOTES'],
                "incidentTypes" => $GLOBALS['STATUS_INCIDENT_TYPES'],
                "napDurations"  => $GLOBALS['STATUS_NAP_DURATIONS'],
                "bottleOunces"  => $GLOBALS['STATUS_BOTTLE_OUNCES'],
                "meals"    => $GLOBALS['STATUS_MEALS'],
                "mealRatings" => $GLOBALS['STATUS_MEAL_RATINGS'],
                "activities" => $GLOBALS['STATUS_ACTIVITIES'],
                "napRatings" => $GLOBALS['STATUS_NAP_RATINGS'],
                "bottle"   => $GLOBALS['STATUS_BOTTLE_INFO'],
                "tags"     => status_notes_tags(),
            ]);
        } else {
            $children = status_children_for_aid(status_current_aid());
            status_json([
                "success"  => true,
                "loggedIn" => true,
                "role"     => "parent",
                "children" => $children,
                "moods"    => $GLOBALS['STATUS_MOODS'],
                "pottyTypes" => $GLOBALS['STATUS_POTTY_TYPES'],
                "meals"    => $GLOBALS['STATUS_MEALS'],
                "mealRatings" => $GLOBALS['STATUS_MEAL_RATINGS'],
                "activities" => $GLOBALS['STATUS_ACTIVITIES'],
                "napRatings" => $GLOBALS['STATUS_NAP_RATINGS'],
                "bottle"   => $GLOBALS['STATUS_BOTTLE_INFO'],
            ]);
        }
        break;

    case 'login_parent':
        $code = isset($_POST['code']) ? $_POST['code'] : '';
        $pin  = isset($_POST['pin']) ? $_POST['pin'] : '';
        $result = status_login_parent($code, $pin);
        if ($result['success']) {
            $result['children'] = status_children_for_aid(status_current_aid());
            $result['moods']    = $GLOBALS['STATUS_MOODS'];
            $result['pottyTypes'] = $GLOBALS['STATUS_POTTY_TYPES'];
            $result['meals']    = $GLOBALS['STATUS_MEALS'];
            $result['mealRatings'] = $GLOBALS['STATUS_MEAL_RATINGS'];
            $result['activities'] = $GLOBALS['STATUS_ACTIVITIES'];
            $result['napRatings'] = $GLOBALS['STATUS_NAP_RATINGS'];
            $result['bottle']   = $GLOBALS['STATUS_BOTTLE_INFO'];
        }
        status_json($result);
        break;

    case 'login_admin':
        $pin = isset($_POST['pin']) ? $_POST['pin'] : '';
        $result = status_login_admin($pin);
        if ($result['success']) {
            $result['children'] = status_all_children();
            $result['moods']    = $GLOBALS['STATUS_MOODS'];
            $result['pottyTypes'] = $GLOBALS['STATUS_POTTY_TYPES'];
            $result['quickNotes'] = $GLOBALS['STATUS_QUICK_NOTES'];
            $result['incidentTypes'] = $GLOBALS['STATUS_INCIDENT_TYPES'];
            $result['napDurations']  = $GLOBALS['STATUS_NAP_DURATIONS'];
            $result['bottleOunces']  = $GLOBALS['STATUS_BOTTLE_OUNCES'];
            $result['meals']    = $GLOBALS['STATUS_MEALS'];
            $result['mealRatings'] = $GLOBALS['STATUS_MEAL_RATINGS'];
            $result['activities'] = $GLOBALS['STATUS_ACTIVITIES'];
            $result['napRatings'] = $GLOBALS['STATUS_NAP_RATINGS'];
            $result['bottle']   = $GLOBALS['STATUS_BOTTLE_INFO'];
            $result['tags']     = status_notes_tags();
        }
        status_json($result);
        break;

    case 'logout':
        status_logout();
        status_json(["success" => true]);
        break;

    case 'get_day':
        status_require_auth();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $daykey = isset($_POST['daykey']) ? intval($_POST['daykey']) : false;
        status_require_child_access($chid);
        $day = status_get_day($chid, $daykey);
        if (!$day) {
            status_json(["success" => false, "message" => "Child not found."]);
        }
        status_json(["success" => true, "day" => $day]);
        break;

    case 'add_mood':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $mood = isset($_POST['mood']) ? $_POST['mood'] : '';
        $day  = status_add_mood($chid, $mood);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't log that."]);
        break;

    case 'add_potty':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $type   = isset($_POST['type']) ? $_POST['type'] : '';
        $hour   = (isset($_POST['hour']) && $_POST['hour'] !== '') ? intval($_POST['hour']) : false;
        $minute = isset($_POST['minute']) ? intval($_POST['minute']) : 0;
        $cream  = !empty($_POST['cream']);
        $peed   = !empty($_POST['peed']);
        $pooped = !empty($_POST['pooped']);
        $result = status_add_potty($chid, $type, $hour, $minute, $cream, $peed, $pooped);
        status_json($result ? ["success" => true, "day" => $result['day'], "evid" => $result['evid']] : ["success" => false, "message" => "Couldn't log that."]);
        break;

    case 'edit_potty':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid   = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $type   = isset($_POST['type']) ? $_POST['type'] : '';
        $hour   = (isset($_POST['hour']) && $_POST['hour'] !== '') ? intval($_POST['hour']) : false;
        $minute = isset($_POST['minute']) ? intval($_POST['minute']) : 0;
        $cream  = !empty($_POST['cream']);
        $peed   = !empty($_POST['peed']);
        $pooped = !empty($_POST['pooped']);
        $day    = status_edit_potty($chid, $evid, $type, $hour, $minute, $cream, $peed, $pooped);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't update that."]);
        break;

    case 'delete_potty':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $day  = status_delete_potty($chid, $evid);
        status_json(["success" => true, "day" => $day]);
        break;

    case 'add_incident':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $type   = isset($_POST['type']) ? $_POST['type'] : '';
        $note   = array_key_exists('note', $_POST) ? $_POST['note'] : null;
        $hour   = (isset($_POST['hour']) && $_POST['hour'] !== '') ? intval($_POST['hour']) : false;
        $minute = isset($_POST['minute']) ? intval($_POST['minute']) : 0;
        $result = status_add_incident($chid, $type, $note, $hour, $minute);
        status_json($result ? ["success" => true, "day" => $result['day'], "evid" => $result['evid']] : ["success" => false, "message" => "Couldn't log that."]);
        break;

    case 'edit_incident':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid   = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $type   = isset($_POST['type']) ? $_POST['type'] : '';
        $note   = isset($_POST['note']) ? $_POST['note'] : '';
        $hour   = (isset($_POST['hour']) && $_POST['hour'] !== '') ? intval($_POST['hour']) : false;
        $minute = isset($_POST['minute']) ? intval($_POST['minute']) : 0;
        $day    = status_edit_incident($chid, $evid, $type, $note, $hour, $minute);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't update that."]);
        break;

    case 'delete_incident':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $day  = status_delete_incident($chid, $evid);
        status_json(["success" => true, "day" => $day]);
        break;

    case 'add_nap':
        status_require_admin();
        $chid    = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $minutes = isset($_POST['minutes']) ? intval($_POST['minutes']) : 0;
        $day     = status_add_nap($chid, $minutes);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't log that."]);
        break;

    case 'edit_nap_time':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid   = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $hour   = (isset($_POST['hour']) && $_POST['hour'] !== '') ? intval($_POST['hour']) : false;
        $minute = isset($_POST['minute']) ? intval($_POST['minute']) : 0;
        $day    = status_edit_nap_time($chid, $evid, $hour, $minute);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't update that."]);
        break;

    case 'delete_nap':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $day  = status_delete_nap($chid, $evid);
        status_json(["success" => true, "day" => $day]);
        break;

    case 'set_nap_rating':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $rating = isset($_POST['rating']) ? $_POST['rating'] : '';
        $day    = status_set_nap_rating($chid, $rating);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Invalid rating."]);
        break;

    case 'set_nap_rating_all':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $rating = isset($_POST['rating']) ? $_POST['rating'] : '';
        $written = status_set_nap_rating_for_all($rating);
        $day = $chid ? status_get_day($chid) : false;
        status_json(["success" => true, "written" => $written, "day" => $day]);
        break;

    case 'upload_attachment':
        status_require_admin();
        $chid    = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid    = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $arid    = isset($_POST['arid']) ? intval($_POST['arid']) : 0;
        $context = isset($_POST['context']) ? $_POST['context'] : 'attachment';
        status_require_child_access($chid);
        if (empty($_FILES['file']['name']) || empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            status_json(["success" => false, "message" => "No file received."]);
        }
        // Photos plus common document types, capped at 15MB.
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            status_json(["success" => false, "message" => "That file type isn't supported."]);
        }
        if ($_FILES['file']['size'] > 15 * 1024 * 1024) {
            status_json(["success" => false, "message" => "File is too large (15MB max)."]);
        }
        $folder = $CFG->userfilespath . "/children/$chid";
        recursive_mkdir($folder);
        $newname = preg_replace('/[^a-z0-9]/', '', $context) . "_" . time() . "_" . mt_rand(1000, 9999) . "." . $ext;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], "$folder/$newname")) {
            status_json(["success" => false, "message" => "Upload failed."]);
        }
        if ($arid) {
            $attachments = status_add_activity_attachment($chid, $arid, $newname);
        } else {
            $attachments = status_add_attachment($chid, $evid, $newname, $context);
        }
        status_json(["success" => true, "attachments" => $attachments]);
        break;

    case 'delete_attachment':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $did  = isset($_POST['did']) ? intval($_POST['did']) : 0;
        $attachments = status_delete_attachment($chid, $did);
        status_json(["success" => true, "attachments" => $attachments]);
        break;

    case 'quick_note':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $key  = isset($_POST['key']) ? $_POST['key'] : '';
        $day  = status_quick_note($chid, $key);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't add that note."]);
        break;

    case 'edit_mood_time':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid   = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $hour   = (isset($_POST['hour']) && $_POST['hour'] !== '') ? intval($_POST['hour']) : false;
        $minute = isset($_POST['minute']) ? intval($_POST['minute']) : 0;
        $day    = status_edit_mood_time($chid, $evid, $hour, $minute);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't update that."]);
        break;

    case 'edit_bottle_time':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid   = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $hour   = (isset($_POST['hour']) && $_POST['hour'] !== '') ? intval($_POST['hour']) : false;
        $minute = isset($_POST['minute']) ? intval($_POST['minute']) : 0;
        $day    = status_edit_bottle_time($chid, $evid, $hour, $minute);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't update that."]);
        break;

    case 'edit_mood':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $mood = isset($_POST['mood']) ? $_POST['mood'] : '';
        $day  = status_edit_mood($chid, $evid, $mood);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't update that."]);
        break;

    case 'delete_mood':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $day  = status_delete_mood($chid, $evid);
        status_json(["success" => true, "day" => $day]);
        break;

    case 'add_bottle':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $ounces = (isset($_POST['ounces']) && $_POST['ounces'] !== '') ? intval($_POST['ounces']) : false;
        $day    = status_add_bottle($chid, $ounces);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't log that."]);
        break;

    case 'edit_bottle_ounces':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid   = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $ounces = isset($_POST['ounces']) ? intval($_POST['ounces']) : 0;
        $day    = status_edit_bottle_ounces($chid, $evid, $ounces);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't update that."]);
        break;

    case 'delete_bottle':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $evid = isset($_POST['evid']) ? intval($_POST['evid']) : 0;
        $day  = status_delete_bottle($chid, $evid);
        status_json(["success" => true, "day" => $day]);
        break;

    case 'save_menu':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $meal = isset($_POST['meal']) ? $_POST['meal'] : '';
        $menu = isset($_POST['menu']) ? $_POST['menu'] : '';
        $day  = status_save_menu($chid, $meal, $menu);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Invalid meal."]);
        break;

    case 'set_meal_rating':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $meal   = isset($_POST['meal']) ? $_POST['meal'] : '';
        $rating = isset($_POST['rating']) ? $_POST['rating'] : '';
        $day    = status_set_meal_rating($chid, $meal, $rating);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Invalid meal or rating."]);
        break;

    case 'set_meal_rating_all':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $meal   = isset($_POST['meal']) ? $_POST['meal'] : '';
        $rating = isset($_POST['rating']) ? $_POST['rating'] : '';
        $written = status_set_meal_rating_for_all($meal, $rating);
        $day = $chid ? status_get_day($chid) : false;
        status_json(["success" => true, "written" => $written, "day" => $day]);
        break;

    case 'copy_menu_to_children':
        status_require_admin();
        $meal = isset($_POST['meal']) ? $_POST['meal'] : '';
        $menu = isset($_POST['menu']) ? $_POST['menu'] : '';
        $chids_raw = isset($_POST['chids']) ? $_POST['chids'] : '';
        $chids = array_filter(array_map('intval', explode(',', $chids_raw)));
        if (empty($chids)) {
            status_json(["success" => false, "message" => "Choose at least one child."]);
        }
        $written = status_copy_menu($meal, $menu, $chids);
        status_json(["success" => true, "written" => $written]);
        break;

    case 'get_menu_suggestions':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $meal = isset($_POST['meal']) ? $_POST['meal'] : '';
        status_json(["success" => true, "suggestions" => status_menu_suggestions($chid, $meal)]);
        break;

    case 'toggle_activity':
        status_require_admin();
        $chid     = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $activity = isset($_POST['activity']) ? $_POST['activity'] : '';
        $on       = !empty($_POST['on']);
        $day      = status_toggle_activity($chid, $activity, $on);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Invalid activity."]);
        break;

    case 'copy_activities_to_children':
        status_require_admin();
        $chid      = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $chids_raw = isset($_POST['chids']) ? $_POST['chids'] : '';
        $chids     = array_filter(array_map('intval', explode(',', $chids_raw)));
        if (empty($chid) || empty($chids)) {
            status_json(["success" => false, "message" => "Choose at least one child."]);
        }
        $written = status_copy_activities($chid, $chids);
        status_json(["success" => true, "written" => $written]);
        break;

    case 'upload_avatar':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        status_require_child_access($chid);
        if (empty($_FILES['file']['name']) || empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            status_json(["success" => false, "message" => "No file received."]);
        }
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            status_json(["success" => false, "message" => "That file type isn't supported."]);
        }
        if ($_FILES['file']['size'] > 15 * 1024 * 1024) {
            status_json(["success" => false, "message" => "File is too large (15MB max)."]);
        }
        $folder = $CFG->userfilespath . "/children/$chid";
        recursive_mkdir($folder);
        $newname = "avatar_" . time() . "_" . mt_rand(1000, 9999) . "." . $ext;
        $dest = "$folder/$newname";
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            status_json(["success" => false, "message" => "Upload failed."]);
        }
        // Matches the main app's avatar handling (square thumbnail).
        smart_resize_image($dest, 150, 150, true, "file", "true", "false", "60");
        $day = status_set_avatar($chid, $newname);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't save that photo."]);
        break;

    case 'add_note':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $tag    = isset($_POST['tag']) ? $_POST['tag'] : '';
        $note   = isset($_POST['note']) ? trim($_POST['note']) : '';
        // notify: 0 none, 1 single-day, 2 persist. Accept legacy bool-ish too.
        $notify = isset($_POST['notify']) ? $_POST['notify'] : 0;
        if ($note === '') {
            status_json(["success" => false, "message" => "Note can't be empty."]);
        }
        $day = status_add_note($chid, $tag, $note, $notify);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Please choose a valid tag."]);
        break;

    case 'edit_note':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $nid    = isset($_POST['nid']) ? intval($_POST['nid']) : 0;
        $tag    = isset($_POST['tag']) ? $_POST['tag'] : '';
        $note   = isset($_POST['note']) ? trim($_POST['note']) : '';
        $notify = isset($_POST['notify']) ? $_POST['notify'] : 0;
        if ($note === '') {
            status_json(["success" => false, "message" => "Note can't be empty."]);
        }
        $day = status_edit_note($nid, $chid, $tag, $note, $notify);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Please choose a valid tag."]);
        break;

    case 'delete_note':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $nid  = isset($_POST['nid']) ? intval($_POST['nid']) : 0;
        $day  = status_delete_note($nid, $chid);
        status_json(["success" => true, "day" => $day]);
        break;

    case 'change_pin':
        status_require_auth();
        $current_pin = isset($_POST['current_pin']) ? $_POST['current_pin'] : '';
        $new_pin     = isset($_POST['new_pin']) ? $_POST['new_pin'] : '';
        $result = status_change_pin($current_pin, $new_pin);
        status_json($result);
        break;

    case 'get_links':
        status_require_admin();
        status_json(["success" => true, "families" => status_all_family_links()]);
        break;

    case 'set_link_code':
        status_require_admin();
        $aid  = isset($_POST['aid']) ? intval($_POST['aid']) : 0;
        $code = isset($_POST['code']) ? $_POST['code'] : '';
        $result = status_set_link_code($aid, $code);
        status_json($result);
        break;

    // Public VAPID key the frontend needs before it can call
    // pushManager.subscribe(). Requires a logged-in session (parent or
    // admin/staff), same as the rest of the status app.
    case 'push_vapid_key':
        status_require_auth();
        $vapid = notifications_get_vapid_keys();
        status_json(["success" => true, "publicKey" => $vapid["publicKey"]]);
        break;

    // Called after the browser hands back a PushSubscription from
    // pushManager.subscribe(). Stored under a hash of this device's own
    // subscription endpoint, tagged with the session's aid - see
    // status_push_identifier() / status_push_subscribe().
    case 'push_subscribe':
        status_require_auth();
        $aid = status_current_aid();
        $subscription = json_decode(isset($_POST['subscription']) ? $_POST['subscription'] : '', true);
        if (!is_array($subscription)) {
            status_json(["success" => false, "message" => "Invalid subscription."]);
        }
        status_json(status_push_subscribe($aid, $subscription));
        break;

    case 'push_unsubscribe':
        status_require_auth();
        $endpoint = isset($_POST['endpoint']) ? $_POST['endpoint'] : '';
        status_json(status_push_unsubscribe(status_current_aid(), $endpoint));
        break;

    default:
        status_json(["success" => false, "message" => "Unknown action."]);
}