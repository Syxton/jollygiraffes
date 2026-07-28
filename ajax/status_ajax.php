<?php

/***************************************************************************
* status_ajax.php - AJAX backend for the Daily Status Report feature.
* -------------------------------------------------------------------------
* Session-based (unlike the rest of this app's stateless numpad flows)
* since this is a page people stay logged into for a while, not a single
* kiosk action. All actions are POSTed with an "action" field and return
* JSON.
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
                "tallies"  => $GLOBALS['STATUS_TALLIES'],
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
                "tallies"  => $GLOBALS['STATUS_TALLIES'],
            ]);
        }
        break;

    case 'login_parent':
        $code = isset($_POST['code']) ? $_POST['code'] : '';
        $pin  = isset($_POST['pin']) ? $_POST['pin'] : '';
        $result = status_login_parent($code, $pin);
        if ($result['success']) {
            $result['children'] = status_children_for_aid(status_current_aid());
        }
        status_json($result);
        break;

    case 'login_admin':
        $pin = isset($_POST['pin']) ? $_POST['pin'] : '';
        $result = status_login_admin($pin);
        if ($result['success']) {
            $result['children'] = status_all_children();
            $result['moods']    = $GLOBALS['STATUS_MOODS'];
            $result['tallies']  = $GLOBALS['STATUS_TALLIES'];
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

    case 'add_tally':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $tag  = isset($_POST['tag']) ? $_POST['tag'] : '';
        $day  = status_add_tally($chid, $tag);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't log that."]);
        break;

    case 'undo_tally':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $tag  = isset($_POST['tag']) ? $_POST['tag'] : '';
        $day  = status_undo_tally($chid, $tag);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Couldn't undo that."]);
        break;

    case 'save_menu':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $menu = isset($_POST['menu']) ? $_POST['menu'] : '';
        $day  = status_save_menu($chid, $menu);
        status_json(["success" => true, "day" => $day]);
        break;

    case 'copy_menu_to_children':
        status_require_admin();
        $menu = isset($_POST['menu']) ? $_POST['menu'] : '';
        $chids_raw = isset($_POST['chids']) ? $_POST['chids'] : '';
        $chids = array_filter(array_map('intval', explode(',', $chids_raw)));
        if (empty($chids)) {
            status_json(["success" => false, "message" => "Choose at least one child."]);
        }
        $written = status_copy_menu($menu, $chids);
        status_json(["success" => true, "written" => $written]);
        break;

    case 'get_menu_suggestions':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        status_json(["success" => true, "suggestions" => status_menu_suggestions($chid)]);
        break;

    case 'add_note':
        status_require_admin();
        $chid   = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $tag    = isset($_POST['tag']) ? $_POST['tag'] : '';
        $note   = isset($_POST['note']) ? trim($_POST['note']) : '';
        $notify = !empty($_POST['notify']);
        if ($note === '') {
            status_json(["success" => false, "message" => "Note can't be empty."]);
        }
        $day = status_add_note($chid, $tag, $note, $notify);
        status_json($day ? ["success" => true, "day" => $day] : ["success" => false, "message" => "Please choose a valid tag."]);
        break;

    case 'delete_note':
        status_require_admin();
        $chid = isset($_POST['chid']) ? intval($_POST['chid']) : 0;
        $nid  = isset($_POST['nid']) ? intval($_POST['nid']) : 0;
        $day  = status_delete_note($nid, $chid);
        status_json(["success" => true, "day" => $day]);
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

    default:
        status_json(["success" => false, "message" => "Unknown action."]);
}