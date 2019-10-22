<?php
/***************************************************************************
 * pagelib.php - Page function library
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 12/4/2013
 * Revision: 3.1.2
 ***************************************************************************/

if (!isset($LIBHEADER)) {
    include('header.php');
}
$PAGELIB = true;

$MYVARS = new stdClass();

//TURN OFF MAGICQUOTES
if (get_magic_quotes_gpc()) {
    $process = array(
        &$_GET,
        &$_POST,
        &$_COOKIE,
        &$_REQUEST
    );
    while (list($key, $val) = each($process)) {
        foreach ($val as $k => $v) {
            unset($process[$key][$k]);
            if (is_array($v)) {
                $process[$key][stripslashes($k)] = $v;
                $process[] =& $process[$key][stripslashes($k)];
            } else {
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}

function callfunction() {
    global $MYVARS;
    if (empty($_POST["aslib"])) {
        //Retrieve from Javascript
        $postorget   = isset($_POST["action"]) ? $_POST : false;
        $MYVARS->GET = !$postorget && isset($_GET["action"]) ? $_GET : $postorget;
        if (function_exists($MYVARS->GET["action"])) {
            $action = $MYVARS->GET["action"];
            $action(); //Go to the function that was called.
        } else {
            echo get_page_error_message("no_function", array(
                $MYVARS->GET["action"]
            ));
        }
    }
}

function postorget() {
    global $MYVARS;
    //Retrieve from Javascript
    $postorget   = isset($_GET["action"]) ? $_GET : $_POST;
    $postorget   = isset($postorget["action"]) ? $postorget : "";
    $MYVARS->GET = $postorget;
    if ($postorget != "") {
        return $postorget["action"];
    }
    return false;
}

function make_select($name, $values, $valuename, $displayname, $class = "", $selected = false, $onchange = "", $leadingblank = false, $size = 1, $style = "", $leadingblanktitle = "", $excludevalue = false) {
    $returnme = '<select class=' . $class . ' size="' . $size . '" id="' . $name . '" name="' . $name . '" ' . $onchange . ' style="' . $style . '" >';
    if ($leadingblank) {
        $returnme .= '<option value="">' . $leadingblanktitle . '</option>';
    }
    if ($values) {
        while ($row = fetch_row($values)) {
            if (!$excludevalue || ($excludevalue && $excludevalue != $row[$valuename])) {
                $returnme .= $row[$valuename] == $selected ? '<option value="' . $row[$valuename] . '" selected="selected">' . $row[$displayname] . '</option>' : '<option value="' . $row[$valuename] . '">' . $row[$displayname] . '</option>';
            }
        }
    }
    $returnme .= '</select>';
    return $returnme;
}

function make_select_from_object($name, $values, $valuename, $displayname, $class = "", $selected = false, $width = "", $onchange = "", $leadingblank = false, $size = 1, $style = "", $leadingblanktitle = "", $excludevalue = false) {
    $returnme = '<select class=' . $class . ' size="' . $size . '" id="' . $name . '" name="' . $name . '" ' . $onchange . ' ' . $width . ' style="' . $style . '">';
    if ($leadingblank) {
        $returnme .= '<option value="">' . $leadingblanktitle . '</option>';
    }
    foreach ($values as $value) {
        if (!$excludevalue || ($excludevalue && $excludevalue != $value->$valuename)) {
            $returnme .= $value->$valuename == $selected ? '<option value="' . $value->$valuename . '" selected="selected">' . $value->$displayname . '</option>' : '<option value="' . $value->$valuename . '">' . $value->$displayname . '</option>';
        }
    }

    $returnme .= '</select>';
    return $returnme;
}

function make_select_from_array($name, $values, $valuename, $displayname, $selected = false, $onchange = "", $leadingblank = false, $size = 1, $style = "", $leadingblanktitle = "", $excludevalue = false) {
    $returnme = '<select size="' . $size . '" id="' . $name . '" name="' . $name . '" ' . 'onchange="' . $onchange . '" ' . ' style="' . $style . '">';
    if ($leadingblank) {
        $returnme .= '<option value="">' . $leadingblanktitle . '</option>';
    }
    foreach ($values as $value) {
        $exclude = false;
        if ($excludevalue) { //exclude value
            switch (gettype($excludevalue)) {
                case "string":
                    if (!$valuename) {
                        $exclude = $excludevalue == $value ? true : false;
                    } else {
                        $exclude = $excludevalue == $value[$valuename] ? true : false;
                    }
                    break;
                case "array":
                    foreach ($excludevalue as $e) {
                        if (!$valuename) {
                            if ($e == $value) {
                                $exclude = true;
                            }
                        } else {
                            if ($e == $value[$valuename]) {
                                $exclude = true;
                            }
                        }
                    }
                    break;
                case "object":
                    while ($e = fetch_row($excludevalue)) {
                        if (!$valuename) {
                            if ($e == $value) {
                                $exclude = true;
                            }
                        } else {
                            if ($e[$valuename] == $value[$valuename]) {
                                $exclude = true;
                            }
                        }
                    }

                    db_goto_row($excludevalue);
                    break;
            }
        }
        if (!$excludevalue || !$exclude) {
            if (!$valuename) {
                $returnme .= $value == $selected ? '<option value="' . $value . '" selected="selected">' . $value . '</option>' : '<option value="' . $value . '">' . $value . '</option>';
            } else {
                $returnme .= $value[$valuename] == $selected ? '<option value="' . $value[$valuename] . '" selected="selected">' . $value[$displayname] . '</option>' : '<option value="' . $value[$valuename] . '">' . $value[$displayname] . '</option>';
            }
        }
    }

    $returnme .= '</select>';
    return $returnme;
}

function checked_in_children($count = false) {
    $pid = get_pid();
    $SQL = "SELECT * FROM children WHERE deleted=0 AND chid IN (SELECT chid FROM enrollments WHERE deleted=0 AND pid='$pid')";
    $i   = 0;
    if ($result = get_db_result($SQL)) {
        while ($row = fetch_row($result)) {
            if (is_checked_in($row["chid"])) {
                if ($count) {
                    $i++;
                } else {
                    return true; // Immediately return true if any active child is checked out.
                }
            }
        }
        if ($count) {
            return $i;
        }
    }
    return false;
}

function checked_out_children($count = false) {
    $pid = get_pid();
    $SQL = "SELECT * FROM children WHERE deleted=0 AND chid IN (SELECT chid FROM enrollments WHERE deleted=0 AND pid='$pid')";
    $i   = 0;
    if ($result = get_db_result($SQL)) {
        while ($row = fetch_row($result)) {
            if (!is_checked_in($row["chid"])) {
                if ($count) {
                    $i++;
                } else {
                    return true; // Immediately return true if any active child is checked in.
                }
            }
        }
        if ($count) {
            return $i;
        }
    }
    return false;
}

function is_enrolled($pid, $chid) {
    if ($result = get_db_result("SELECT * FROM enrollments WHERE chid='$chid' AND pid='$pid'")) {
        while ($row = fetch_row($result)) {
            return $row["eid"];
        }
        return false;
    }
    return false;
}

function get_home_page() {
    global $CFG;

    echo '<div style="height:5%;"></div>
          <div class="mylogo" style="background-image: url(\'' . $CFG->wwwroot . '/images/' . $CFG->logo . '\');"></div>
          <div style="height:5%;"></div>';
    $checkout_button = checked_in_children();
    $checkin_button  = checked_out_children();
    echo '<div class="middle-center" style="top: initial;height: 30%;">';

    if ($checkin_button) {
        echo '<button style="margin:0 auto" onclick="
            $(\'.employee_button\').hide();
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'get_check_in_out_form\', type: \'in\' },
              success: function(data) { $(\'#display_level\').html(data); refresh_all(); }
              });
            " class="big_button bb_middle textfill"><span style="font-size:10px;">Check In <br />' . checked_out_children(true) . ' available</span>
            </button>';
    }

    if ($checkin_button && $checkout_button) {
        echo '<span style="width: 5%;display: inline-block;"></span>';
    }

    if ($checkout_button) {
        echo '<button style="margin:0 auto" onclick="
            $(\'.employee_button\').hide();
            $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'get_check_in_out_form\', type: \'out\' },
              success: function(data) { $(\'#display_level\').html(data); refresh_all(); }
              });
            " class="big_button bb_middle textfill"><span style="font-size:10px;">Check Out <br />' . checked_in_children(true) . ' available</span>
            </button>';
    }

    if (empty($checkin_button) && empty($checkout_button)) {
        echo "<h1>No Active Programs</h1>";
    }

    echo '</div>';

    echo '<div class="bottom-center" style="width: auto;">
            <div class="footer-text">' . $CFG->sitename . "<br />" . $CFG->streetaddress . '</div>
          </div>';
}

function get_admin_button() {
    return get_numpad("", true, "", "#display_level", 'admin_numpad1') . '<div class="top-right"><button class="admin_button topright_button" onclick="if(typeof(autoback) != \'undefined\'){ clearTimeout(autoback); } numpad(\'admin_numpad1\');">Admin</button></div>';
}

function get_employee_button($employeeid, $class = "", $style = "", $action = "") {
    $row = get_db_row("SELECT * FROM employee WHERE employeeid='$employeeid' AND deleted=0");
    $action .= 'numpad(\'employee_numpad\');';
    $class .= 'blank_pic';
    $class .= empty($action) ? " noaction" : "";
    $top    = strstr($style, "width:") ? "top:" . (substr($style, (strpos($style, "width:") + 6), (strpos($style, "px", strpos($style, "width:")) - (strpos($style, "width:") + 6))) * (.4)) . "px;" : "";
    $name   = '<span class="slider-item-text" style="' . $top . '">' . $row["first"] . '<br />' . $row["last"] . '</span>';
    $status = get_employee_status($employeeid);
    return '<button class="child button slider-item-text ' . $class . ' emp_' . $row["employeeid"] . ' slider-item ui-corner-all" style="' . $style . 'background-size: cover;" onclick="$(\'#selectedemployee\').val(\'' . $row["employeeid"] . '\');' . $action . '">
                <span class="ui-corner-all" style="font-size:9px;width: 100%;left: 0;top: 0;position: absolute;background:rgba(0, 0, 0, 0.35);display: block;">' . $status . '</span>
                <span class="ui-corner-all" style="width: 100%;left: 0;bottom: 0;position: absolute;background:rgba(0, 0, 0, 0.35);display: block;">' . $name . '</span>
            </button>';
}

function get_employee_status($employeeid) {
    global $CFG;
    $today = get_today();
    if ($row = get_db_row("SELECT * FROM employee_activity WHERE employeeid='$employeeid' AND timelog > $today ORDER BY timelog DESC LIMIT 1")) {
        if ($row["tag"] == "in") { //last thing they did was sign in.
            return "Signed in at " . get_date("h:i a", $row["timelog"], $CFG->timezone);
        } else {
            return "Signed out at " . get_date("h:i a", $row["timelog"], $CFG->timezone);
        }
    } else {
        return "Has not worked today";
    }
}

function get_employee_timeclock_button() {
    return '<div class="top-left"><button class="employee_button topleft_button" onclick="$.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              timeout: 10000,
              data: { action: \'employee_timesheet\' },
              success: function(data) { $(\'.employee_button\').hide(); $(\'#display_level\').html(data); refresh_all(); }
              });"">Employee</button></div>';
}

function get_numpad($aid = "\'\'", $admin = "false", $type = "\'\'", $display = "#display_level", $id = "numpad") {
    $admin_text   = empty($admin) ? 'Enter your Password' : 'Administrator Password';
    $buttonaction = 'if($(this).prevAll(\'input:first\').val().length < 4){ $(this).prevAll(\'input:first\').val($(this).prevAll(\'input:first\').val() + $(\'.keypad:first\',this).html())} if($(this).prevAll(\'input:first\').val().length == 4){ $(\'.' . $id . 'keypad_submit\').button(\'option\', \'disabled\', false); }else{ $(\'.' . $id . 'keypad_submit\').button(\'option\', \'disabled\', true); }';
    return '<div id="' . $id . '" title="' . $admin_text . '" style="display:none;text-align:center;padding:.5em .5em;">
            <label for="password">Password</label>
              <input size="4" maxlength="4" type="password" disabled name="' . $id . '_password" id="' . $id . '_password" value="" class="text ui-widget-content ui-corner-all" style="text-align: center;font-size:3em;width:225px;padding:0px 10px;" />
            <button onclick="' . $buttonaction . '" class="keypad_button_big ui-corner-all" ><span class="keypad">1</span></button>
            <button onclick="' . $buttonaction . '" class="keypad_button_big ui-corner-all" ><span class="keypad">2</span></button>
            <button onclick="' . $buttonaction . '" class="keypad_button_big ui-corner-all" ><span class="keypad">3</span></button>
            <button onclick="' . $buttonaction . '" class="keypad_button_big ui-corner-all" ><span class="keypad">4</span></button>
            <button onclick="' . $buttonaction . '" class="keypad_button_big ui-corner-all" ><span class="keypad">5</span></button>
            <button onclick="' . $buttonaction . '" class="keypad_button_big ui-corner-all" ><span class="keypad">6</span></button>
            <button onclick="' . $buttonaction . '" class="keypad_button_big ui-corner-all" ><span class="keypad">7</span></button>
            <button onclick="' . $buttonaction . '" class="keypad_button_big ui-corner-all" ><span class="keypad">8</span></button>
            <button onclick="' . $buttonaction . '" class="keypad_button_big ui-corner-all" ><span class="keypad">9</span></button>
            <button onclick="' . $buttonaction . '" class="keypad_button_big ui-corner-all" ><span class="keypad">0</span></button><div style="clear:both;"></div>
            <button onclick="$(\'.' . $id . 'keypad_submit\').button(\'option\', \'disabled\', true); $(this).prevAll(\'input:first\').val(\'\')" class="keypad_button_big ui-corner-all" >Clear</button>
            <button disabled class="' . $id . 'keypad_submit keypad_button_big ui-corner-all" style="width:140px;" onclick="var submitbutton = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              timeout: 10000,
              error: function(x, t, m) {
                $(submitbutton).button(\'option\', \'disabled\', false);
              },
              data: { action: \'validate\',type:\'' . $type . '\',values: $(\'.notes_values\').serializeArray(),rnid: $(\'.rnid\').serializeArray(),cid: $(\'.contact input.cid\',\'.ui-selected\').serializeArray(),chid: $(\'.child input.chid\').serializeArray(), employeeid: $(\'#selectedemployee\').val(), aid: \'' . $aid . '\', admin: \'' . $admin . '\', password: $(\'#' . $id . '_password:input\',\'.ui-dialog\').val() },
              success: function(data) { $(submitbutton).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'.' . $id . 'keypad_submit\').closest(\'#' . $id . '\').dialog(\'close\'); $(\'' . $display . '\').html(data); refresh_all(); }else{ $(\'.' . $id . 'keypad_submit\').button(\'option\', \'disabled\', true); $(\'.' . $id . 'keypad_submit\').prevAll(\'input:first\').val(\'\'); $(\'.' . $id . 'keypad_submit\').closest(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
              });">Submit</button>
        </div>';
}

function is_checked_in($chid) {
    global $CFG;
    $pid     = get_pid();
    $lastout = get_db_row("SELECT * FROM activity WHERE pid='$pid' AND chid='$chid' AND tag='out' AND chid IN (SELECT chid FROM enrollments WHERE pid='$pid') ORDER BY timelog DESC");
    $lastin  = get_db_row("SELECT * FROM activity WHERE pid='$pid' AND chid='$chid' AND tag='in' AND chid IN (SELECT chid FROM enrollments WHERE pid='$pid') ORDER BY timelog DESC");
    $today   = get_today();

    if ($lastout["timelog"] > $lastin["timelog"] && $lastout["timelog"] > $today) { //have signed out today
        return false;
    } elseif ($lastin["timelog"] > $lastout["timelog"] && $today > $lastin["timelog"]) { //haven't signed in today
        return false;
    } elseif ($lastout["timelog"] > $lastin["timelog"] && $today > $lastout["timelog"]) { //new day
        return false;
    } elseif (!$lastin["timelog"]) { //have never signed in
        return false;
    }

    return $lastin["actid"];
}

function is_working($employeeid) {
    global $CFG;
    $lastout = get_db_row("SELECT * FROM employee_activity WHERE employeeid='$employeeid' AND tag='out' ORDER BY timelog DESC");
    $lastin  = get_db_row("SELECT * FROM employee_activity WHERE employeeid='$employeeid' AND tag='in' ORDER BY timelog DESC");
    $today   = get_today();

    if ($lastout["timelog"] > $lastin["timelog"] && $lastout["timelog"] > $today) { //have signed out today
        return false;
    } elseif ($lastin["timelog"] > $lastout["timelog"] && $today > $lastin["timelog"]) { //haven't signed in today
        return false;
    } elseif ($lastout["timelog"] > $lastin["timelog"] && $today > $lastout["timelog"]) { //new day
        return false;
    } elseif (!$lastin["timelog"]) { //have never signed in
        return false;
    }

    return $lastin["actid"];
}

function get_pid() {
    return get_db_field("pid", "programs", "active=1");
}

function get_icon($icon) {
    global $CFG;
    return '<img style="background:0;" src="' . $CFG->wwwroot . "/images/icons/$icon.png" . '" />';
}

function go_home_button($button_text = 'Back') {
    return '<div style="height:55px;"><button class="topleft_button" onclick="if(typeof(autoback) != \'undefined\'){ clearTimeout(autoback); }
            location.reload();
            ">' . $button_text . '</button></div>';
}

function get_name($vars) {
    $name = "";
    if (!empty($vars["type"]) && !empty($vars["id"])) {
        switch ($vars["type"]) {
            case "pid":
                $program = get_db_row("SELECT * FROM programs WHERE pid='" . $vars["id"] . "'");
                $name    = $program["name"];
                break;
            case "aid":
                $account = get_db_row("SELECT * FROM accounts WHERE aid='" . $vars["id"] . "'");
                $name    = $account["name"];
                break;
            case "chid":
                $child = get_db_row("SELECT * FROM children WHERE chid='" . $vars["id"] . "'");
                $name  = $child["first"] . ' ' . $child["last"];
                break;
            case "cid":
                $contact = get_db_row("SELECT * FROM contacts WHERE cid='" . $vars["id"] . "'");
                $name    = $contact["first"] . ' ' . $contact["last"];
                break;
            case "employeeid":
                $employee = get_db_row("SELECT * FROM employee WHERE employeeid='" . $vars["id"] . "'");
                $name     = $employee["first"] . ' ' . $employee["last"];
            case "actid":
                $activity = get_db_row("SELECT * FROM activity WHERE actid='" . $vars["id"] . "'");
                if (!empty($activity["chid"])) {
                    $name = get_name(array(
                        "type" => "chid",
                        "id" => $activity["chid"]
                    ));
                } elseif (!empty($activity["cid"])) {
                    $name = get_name(array(
                        "type" => "cid",
                        "id" => $activity["chid"]
                    ));
                }
                break;
        }
    }
    return $name;
}

function get_tag($vars) {
    $tag = false;
    if (!empty($vars["type"]) && !empty($vars["tag"])) {
        switch ($vars["type"]) {
            case "notes":
                if ($vars["tag"] == "bulletin") {
                    $tag = array(
                        "tag" => "bulletin",
                        "title" => "Bulletin",
                        "color" => "orange",
                        "textcolor" => "black"
                    );
                } else {
                    $tag = get_db_row("SELECT * FROM notes_tags WHERE tag='" . $vars["tag"] . "'");
                }
                break;
            case "events":
                $tag = get_db_row("SELECT * FROM events_tags WHERE tag='" . $vars["tag"] . "' UNION SELECT tag,title,'black','silver' FROM notes_required WHERE tag='" . $vars["tag"] . "'");
                break;
            case "documents":
                $tag = get_db_row("SELECT * FROM documents_tags WHERE tag='" . $vars["tag"] . "'");
                break;
        }
    }
    return $tag;
}

function get_note_type_array() {
    //Yes No type
    $yesno       = new stdClass();
    $yesno->id   = "Yes,No";
    $yesno->name = "Yes/No";

    //No Yes type
    $noyes       = new stdClass();
    $noyes->id   = "No,Yes";
    $noyes->name = "No/Yes";

    return array(
        $yesno,
        $noyes
    );
}

function get_required_notes_header($tag) {
    $notes      = "";
    $pid        = get_pid();
    //see if there are any require notes for the event: "in"
    $SQL        = "SELECT * FROM notes_required n JOIN (SELECT * FROM events_required_notes WHERE evid IN (SELECT evid FROM events WHERE tag='$tag' AND (pid='$pid' or pid='0'))) r ON r.rnid=n.rnid WHERE n.deleted=0 ORDER BY r.sort";
    $note_count = get_db_count($SQL);
    if ($result = get_db_result($SQL)) {
        while ($row = fetch_row($result)) {
            $notes .= '<span style="display:inline-block;padding:10px 0px;width:' . (70 / $note_count) . '%;text-align:center;">';
            switch ($row["question_type"]) {
                case "Yes,No":
                    $notes .= '<button style="font-size: 12px;" onclick="$(\'.' . $row["rnid"] . '.notes_values\').each(function(){ $(this).toggleSwitch({toggle:\'0\'}); })"><span>All Yes</span></button><button style="font-size: 12px;" onclick="$(\'.' . $row["rnid"] . '.notes_values\').each(function(){ $(this).toggleSwitch({toggle:\'1\'}); })"><span>All No</span></button>';
                    break;
                case "No,Yes":
                    $notes .= '<button style="font-size: 12px;" onclick="$(\'.' . $row["rnid"] . '.notes_values\').each(function(){ $(this).toggleSwitch({toggle:\'0\'}); })"><span>All No</span></button><button style="font-size: 12px;" onclick="$(\'.' . $row["rnid"] . '.notes_values\').each(function(){ $(this).toggleSwitch({toggle:\'1\'}); })"><span>All Yes</span></button>';
                    break;
            }
            $notes .= "</span>";
        }
    }
    return $notes;
}

function get_required_notes_forms($tag) {
    $notes      = "";
    $pid        = get_pid();
    //see if there are any require notes for the event: "in"
    $SQL        = "SELECT * FROM notes_required n JOIN (SELECT * FROM events_required_notes WHERE evid IN (SELECT evid FROM events WHERE tag='$tag' AND (pid='$pid' OR pid='0'))) r ON r.rnid=n.rnid WHERE n.deleted=0 ORDER BY r.sort";
    $note_count = get_db_count($SQL);
    if ($result = get_db_result($SQL)) {
        while ($row = fetch_row($result)) {
            $notes .= '<span style="display:inline-block;padding:10px 0px;width:' . (70 / $note_count) . '%;text-align:center;">';
            switch ($row["question_type"]) {
                case "Yes,No":
                    $notes .= '<h3>' . $row["title"] . '</h3><br /><select id="notes_values" name="notes_values" class="' . $row["rnid"] . ' notes_values toggleswitch" style="color:white;font-size: 20px;"><option value="1">Yes</option><option value="0">No</option></select><input class="rnid" id="rnid" name="rnid" type="hidden" value="' . $row["rnid"] . '" />';
                    break;
                case "No,Yes":
                    $notes .= '<h3>' . $row["title"] . '</h3><br /><select id="notes_values" name="notes_values" class="' . $row["rnid"] . ' notes_values toggleswitch" style="color:white;font-size: 20px;"><option value="0">No</option><option value="1">Yes</option></select><input class="rnid" id="rnid" name="rnid" type="hidden" value="' . $row["rnid"] . '" />';
                    break;
            }
            $notes .= "</span>";
        }
    }
    return $notes;
}

function get_children_button($chid, $class = "", $style = "", $action = "", $piconly = false, $name = true) {
    global $CFG;
    $row = get_db_row("SELECT * FROM children WHERE chid='$chid' AND deleted=0");
    $pic = children_document_link($row["chid"], "avatar");

    if ($pic) {
        if (file_exists($CFG->docroot . str_replace($CFG->wwwroot, "", $pic))) {
            $style .= 'background: whitesmoke url(\'' . $pic . '\') no-repeat;';
        } else {
            // File doesn't exist so clean it up.
            execute_db_sql("DELETE FROM documents WHERE tag='avatar' AND chid='" . $row["chid"] . "'");
            $class .= 'blank_pic';
        }

    } else {
        $class .= 'blank_pic';
    }
    $letter  = strtoupper(substr($row["last"], 0, 1));
    $piconly = $piconly ? "" : "button";
    $class .= empty($action) ? " noaction" : "";
    $top  = strstr($style, "width:") ? "top:" . (substr($style, (strpos($style, "width:") + 6), (strpos($style, "px", strpos($style, "width:")) - (strpos($style, "width:") + 6))) * (.4)) . "px;" : "";
    $name = $name ? '<span class="slider-item-text" style="' . $top . '">' . $row["first"] . '<br />' . $row["last"] . '</span>' : "";
    return '<button class="child ' . $piconly . ' ' . $class . ' chid_' . $row["chid"] . ' account_' . $row["aid"] . ' letter_' . $letter . ' slider-item ui-corner-all" style="' . $style . 'background-size: cover;" onclick="' . $action . '">
                <input type="hidden" class="chid" id="chid_' . $row["chid"] . '" name="chid_' . $row["chid"] . '" value="' . $row["chid"] . '" />
                <span class="ui-corner-all" style="width: 100%;left: 0;bottom: 0;position: absolute;background:rgba(0, 0, 0, 0.35);display: block;">' . $name . '</span>
            </button>';
}

function make_or_get_tag($tag, $type = "documents") {
    switch ($type) {
        case "documents":
            if ($tags = get_db_row("SELECT * FROM documents_tags WHERE tag='$tag' OR title='$tag'")) {
                return $tags["tag"];
            } else { //New
                $title = $tag;
                $tag   = str_replace(" ", "_", strtolower($tag));
                execute_db_sql("INSERT INTO documents_tags (tag,title) VALUES('$tag','$title')");
                return $tag;
            }
            break;
        case "notes":
            if ($tags = get_db_row("SELECT * FROM notes_tags WHERE tag='$tag' OR title='$tag'")) {
                return $tags["tag"];
            } else { //New
                $title = $tag;
                $tag   = str_replace(" ", "_", strtolower($tag));
                execute_db_sql("INSERT INTO notes_tags (tag,title) VALUES('$tag','$title')");
                return $tag;
            }
        case "events":
            if ($tags = get_db_row("SELECT * FROM events_tags WHERE tag='$tag' OR title='$tag'")) {
                return $tags["tag"];
            } else { //New
                $title = $tag;
                $tag   = str_replace(" ", "_", strtolower($tag));
                execute_db_sql("INSERT INTO events_tags (tag,title) VALUES('$tag','$title')");
                return $tag;
            }
    }
}

function get_note_text($row, $setting) {
    $note = "";
    switch ($row["question_type"]) {
        case "Yes,No":
            $note = $setting == 1 ? $row["title"] . ': Yes' : $row["title"] . ': No';
            break;
        case "No,Yes":
            $note = $setting == 1 ? $row["title"] . ': Yes' : $row["title"] . ': No';
            break;
    }
    return $note;
}



//  GetColor  returns  an  associative  array  with  the  red,  green  and  blue
//  values  of  the  desired  color
function gethexcolor($colorname) {
    $colors = array(
        'aliceblue' => 'F0F8FF',
        'antiquewhite' => 'FAEBD7',
        'aqua' => '00FFFF',
        'aquamarine' => '7FFFD4',
        'azure' => 'F0FFFF',
        'beige' => 'F5F5DC',
        'bisque' => 'FFE4C4',
        'black' => '000000',
        'blanchedalmond ' => 'FFEBCD',
        'blue' => '0000FF',
        'blueviolet' => '8A2BE2',
        'brown' => 'A52A2A',
        'burlywood' => 'DEB887',
        'cadetblue' => '5F9EA0',
        'chartreuse' => '7FFF00',
        'chocolate' => 'D2691E',
        'coral' => 'FF7F50',
        'cornflowerblue' => '6495ED',
        'cornsilk' => 'FFF8DC',
        'crimson' => 'DC143C',
        'cyan' => '00FFFF',
        'darkblue' => '00008B',
        'darkcyan' => '008B8B',
        'darkgoldenrod' => 'B8860B',
        'darkgray' => 'A9A9A9',
        'darkgreen' => '006400',
        'darkgrey' => 'A9A9A9',
        'darkkhaki' => 'BDB76B',
        'darkmagenta' => '8B008B',
        'darkolivegreen' => '556B2F',
        'darkorange' => 'FF8C00',
        'darkorchid' => '9932CC',
        'darkred' => '8B0000',
        'darksalmon' => 'E9967A',
        'darkseagreen' => '8FBC8F',
        'darkslateblue' => '483D8B',
        'darkslategray' => '2F4F4F',
        'darkslategrey' => '2F4F4F',
        'darkturquoise' => '00CED1',
        'darkviolet' => '9400D3',
        'deeppink' => 'FF1493',
        'deepskyblue' => '00BFFF',
        'dimgray' => '696969',
        'dimgrey' => '696969',
        'dodgerblue' => '1E90FF',
        'firebrick' => 'B22222',
        'floralwhite' => 'FFFAF0',
        'forestgreen' => '228B22',
        'fuchsia' => 'FF00FF',
        'gainsboro' => 'DCDCDC',
        'ghostwhite' => 'F8F8FF',
        'gold' => 'FFD700',
        'goldenrod' => 'DAA520',
        'gray' => '808080',
        'green' => '008000',
        'greenyellow' => 'ADFF2F',
        'grey' => '808080',
        'honeydew' => 'F0FFF0',
        'hotpink' => 'FF69B4',
        'indianred' => 'CD5C5C',
        'indigo' => '4B0082',
        'ivory' => 'FFFFF0',
        'khaki' => 'F0E68C',
        'lavender' => 'E6E6FA',
        'lavenderblush' => 'FFF0F5',
        'lawngreen' => '7CFC00',
        'lemonchiffon' => 'FFFACD',
        'lightblue' => 'ADD8E6',
        'lightcoral' => 'F08080',
        'lightcyan' => 'E0FFFF',
        'lightgoldenrodyellow' => 'FAFAD2',
        'lightgray' => 'D3D3D3',
        'lightgreen' => '90EE90',
        'lightgrey' => 'D3D3D3',
        'lightpink' => 'FFB6C1',
        'lightsalmon' => 'FFA07A',
        'lightseagreen' => '20B2AA',
        'lightskyblue' => '87CEFA',
        'lightslategray' => '778899',
        'lightslategrey' => '778899',
        'lightsteelblue' => 'B0C4DE',
        'lightyellow' => 'FFFFE0',
        'lime' => '00FF00',
        'limegreen' => '32CD32',
        'linen' => 'FAF0E6',
        'magenta' => 'FF00FF',
        'maroon' => '800000',
        'mediumaquamarine' => '66CDAA',
        'mediumblue' => '0000CD',
        'mediumorchid' => 'BA55D3',
        'mediumpurple' => '9370D0',
        'mediumseagreen' => '3CB371',
        'mediumslateblue' => '7B68EE',
        'mediumspringgreen' => '00FA9A',
        'mediumturquoise' => '48D1CC',
        'mediumvioletred' => 'C71585',
        'midnightblue' => '191970',
        'mintcream' => 'F5FFFA',
        'mistyrose' => 'FFE4E1',
        'moccasin' => 'FFE4B5',
        'navajowhite' => 'FFDEAD',
        'navy' => '000080',
        'oldlace' => 'FDF5E6',
        'olive' => '808000',
        'olivedrab' => '6B8E23',
        'orange' => 'FFA500',
        'orangered' => 'FF4500',
        'orchid' => 'DA70D6',
        'palegoldenrod' => 'EEE8AA',
        'palegreen' => '98FB98',
        'paleturquoise' => 'AFEEEE',
        'palevioletred' => 'DB7093',
        'papayawhip' => 'FFEFD5',
        'peachpuff' => 'FFDAB9',
        'peru' => 'CD853F',
        'pink' => 'FFC0CB',
        'plum' => 'DDA0DD',
        'powderblue' => 'B0E0E6',
        'purple' => '800080',
        'red' => 'FF0000',
        'rosybrown' => 'BC8F8F',
        'royalblue' => '4169E1',
        'saddlebrown' => '8B4513',
        'salmon' => 'FA8072',
        'sandybrown' => 'F4A460',
        'seagreen' => '2E8B57',
        'seashell' => 'FFF5EE',
        'sienna' => 'A0522D',
        'silver' => 'C0C0C0',
        'skyblue' => '87CEEB',
        'slateblue' => '6A5ACD',
        'slategray' => '708090',
        'slategrey' => '708090',
        'snow' => 'FFFAFA',
        'springgreen' => '00FF7F',
        'steelblue' => '4682B4',
        'tan' => 'D2B48C',
        'teal' => '008080',
        'thistle' => 'D8BFD8',
        'tomato' => 'FF6347',
        'turquoise' => '40E0D0',
        'violet' => 'EE82EE',
        'wheat' => 'F5DEB3',
        'white' => 'FFFFFF',
        'whitesmoke' => 'F5F5F5',
        'yellow' => 'FFFF00',
        'yellowgreen' => '9ACD32'
    );

    if (!empty($colors[$colorname])) {
        return '#' . $colors[$colorname];
    } else {
        return $colorname;
    }
}

function closeout_workdays($employeeid, $startofweek, $refresh = false) {
    global $CFG;
    $employee = get_db_row("SELECT * FROM employee WHERE employeeid='$employeeid'");

    if ($refresh) {
        execute_db_sql("DELETE FROM employee_timecard WHERE employeeid='$employeeid' AND fromdate = '$startofweek'");
    }
    $endofweek = strtotime("+1 week -1 second", $startofweek);

    $SQL = "SELECT actid,CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'" . get_date('P', time(), $CFG->timezone) . "','" . get_date('P', time(), $CFG->timezone) . "'))) as order_day, tag, timelog FROM employee_activity WHERE employeeid='$employeeid' AND (tag='out' OR tag='in') AND timelog >= '$startofweek' AND timelog <= '$endofweek' ORDER BY timelog";
    if ($results = get_db_result($SQL)) {
        $lasttag   = $lasttime = $lastactid = $day = "";
        $hours     = 0;
        $endofwork = seconds_from_midnight(get_db_field("timeclosed", "programs", "pid='" . get_pid() . "'"));
        while ($row = fetch_row($results)) {
            //Set day to the first day
            $day = $day == $row["order_day"] ? $row["order_day"] : false;

            //If it has moved to the next day, return.
            if (empty($day)) {
                if ($lasttag == "in") { //didn't sign out
                    $outtime = seconds_from_midnight(get_date('g:ia', $lasttime, $CFG->timezone)) > $endofwork ? mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + 86399 : mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + $endofwork;
                    $outtime -= get_offset();
                    $readabletime = get_date("l, F j, Y \a\\t g:i a", display_time($outtime));
                    $event        = get_db_row("SELECT * FROM events WHERE tag='out'");
                    $actid        = execute_db_sql("INSERT INTO employee_activity (employeeid,evid,tag,timelog) VALUES('$employeeid','" . $event["evid"] . "','" . $event["tag"] . "',$outtime) ");
                    $note         = $employee["first"] . " " . $employee["last"] . ": Signed out at $readabletime";
                    execute_db_sql("INSERT INTO notes (actid,employeeid,tag,note,data,timelog) VALUES('$actid','$employeeid','" . $event["tag"] . "','$note',1,$outtime) ");
                }

                if ($row["tag"] == "out" && $lasttag == "in") {
                    $hours += (($row["timelog"] - $lasttime) / 3600);
                    //echo "<br /> $hours";
                }
            } else {
                //remove extra check ins and outs
                if ($row["tag"] == "in" && $lasttag == "in") {
                    execute_db_sql("DELETE FROM employee_activity WHERE actid='" . $row["actid"] . "'");
                    execute_db_sql("DELETE FROM notes WHERE actid='" . $row["actid"] . "' AND employeeid='$employeeid'");
                }

                if ($row["tag"] == "out" && $lasttag == "out") {
                    execute_db_sql("DELETE FROM employee_activity WHERE actid='$lastactid'");
                    execute_db_sql("DELETE FROM notes WHERE actid='$lastactid' AND employeeid='$employeeid'");
                }
            }

            $day       = $row["order_day"];
            $lastactid = $row["actid"];
            $lasttime  = $row["timelog"];
            $lasttag   = $row["tag"];
        }

        if ($lasttag == "in") { //didn't sign out
            $outtime = seconds_from_midnight(get_date('g:ia', $lasttime, $CFG->timezone)) > $endofwork ? mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + 86399 : mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + $endofwork;
            $outtime -= get_offset();
            $readabletime = get_date("l, F j, Y \a\\t g:i a", display_time($outtime));
            $event        = get_db_row("SELECT * FROM events WHERE tag='out'");
            $actid        = execute_db_sql("INSERT INTO employee_activity (employeeid,evid,tag,timelog) VALUES('$employeeid','" . $event["evid"] . "','" . $event["tag"] . "',$outtime) ");
            $note         = $employee["first"] . " " . $employee["last"] . ": Signed out at $readabletime";
            execute_db_sql("INSERT INTO notes (actid,employeeid,tag,note,data,timelog) VALUES('$actid','$employeeid','" . $event["tag"] . "','$note',1,$outtime) ");

            if (($outtime - $lasttime) < 61200 && ($outtime - $lasttime) > 0) {
                $hours += (($outtime - $lasttime) / 3600);
            }
        }

        //Save timecard data
        if (!get_db_row("SELECT * FROM employee_timecard WHERE employeeid='$employeeid' AND fromdate='$startofweek'")) {
            $hours = hours_worked($employeeid, $startofweek, $endofweek);
            $wage  = get_wage($employeeid, $startofweek);
            execute_db_sql("INSERT INTO employee_timecard (employeeid,fromdate,todate,hours,hours_override,wage) VALUES('$employeeid','$startofweek','$endofweek','$hours','','" . $wage . "') ");
        }
    }
}


function closeout_thisweek() {
    global $CFG;
    if (date('N', get_timestamp()) == "7") { //is already a sunday
        $startofweek = strtotime(date('m/d/Y', get_timestamp()));
    } else {
        $startofweek = strtotime(" -7 days", strtotime(date('m/d/Y', get_timestamp())));
    }

    $endofweek = strtotime("-1 second", strtotime(date('m/d/Y', get_timestamp())));

    if ($employees = get_db_result("SELECT * FROM employee WHERE deleted=0")) {
        while ($employee = fetch_row($employees)) {
            $employeeid = $employee["employeeid"];
            $SQL        = "SELECT actid,CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'" . get_date('P', time(), $CFG->timezone) . "','" . get_date('P', time(), $CFG->timezone) . "'))) as order_day, tag, timelog FROM employee_activity WHERE employeeid='$employeeid' AND (tag='out' OR tag='in') AND timelog >= '$startofweek' AND timelog <= '$endofweek' ORDER BY timelog";
            if ($results = get_db_result($SQL)) {
                $lasttag   = $lasttime = $lastactid = $day = "";
                $hours     = 0;
                $endofwork = seconds_from_midnight(get_db_field("timeclosed", "programs", "pid='" . get_pid() . "'"));
                while ($row = fetch_row($results)) {
                    //Set day to the first day
                    $day = $day == $row["order_day"] ? $row["order_day"] : false;

                    //make sure note exists and make it if it doesn't
                    if (!$note = get_db_row("SELECT * FROM notes WHERE employeeid='$employeeid' AND actid='" . $row["actid"] . "'")) {
                        $readabletime = get_date("l, F j, Y \a\\t g:i a", display_time($row["timelog"]));
                        $note         = $employee["first"] . " " . $employee["last"] . ": Signed out at $readabletime";
                        execute_db_sql("INSERT INTO notes (actid,employeeid,tag,note,data,timelog) VALUES('" . $row["actid"] . "','$employeeid','" . $row["tag"] . "','$note',1,'" . $row["timelog"] . "') ");
                    }

                    //If it has moved to the next day, return.
                    if (empty($day)) {
                        if ($lasttag == "in") { //didn't sign out
                            $outtime = seconds_from_midnight(get_date('g:ia', $lasttime, $CFG->timezone)) > $endofwork ? mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + 86399 : mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + $endofwork;
                            $outtime -= get_offset();
                            $readabletime = get_date("l, F j, Y \a\\t g:i a", display_time($outtime));
                            $event        = get_db_row("SELECT * FROM events WHERE tag='out'");
                            $actid        = execute_db_sql("INSERT INTO employee_activity (employeeid,evid,tag,timelog) VALUES('$employeeid','" . $event["evid"] . "','" . $event["tag"] . "',$outtime) ");
                            $note         = $employee["first"] . " " . $employee["last"] . ": Signed out at $readabletime";
                            execute_db_sql("INSERT INTO notes (actid,employeeid,tag,note,data,timelog) VALUES('$actid','$employeeid','" . $event["tag"] . "','$note',1,$outtime) ");
                        }
                    } else {
                        //remove extra check ins and outs
                        if ($row["tag"] == "in" && $lasttag == "in") {
                            execute_db_sql("DELETE FROM employee_activity WHERE actid='" . $row["actid"] . "'");
                            execute_db_sql("DELETE FROM notes WHERE actid='" . $row["actid"] . "' AND employeeid='$employeeid'");
                        }

                        if ($row["tag"] == "out" && $lasttag == "out") {
                            execute_db_sql("DELETE FROM employee_activity WHERE actid='$lastactid'");
                            execute_db_sql("DELETE FROM notes WHERE actid='$lastactid' AND employeeid='$employeeid'");
                        }
                    }

                    $day       = $row["order_day"];
                    $lastactid = $row["actid"];
                    $lasttime  = $row["timelog"];
                    $lasttag   = $row["tag"];
                }

                if ($lasttag == "in") { //didn't sign out
                    $outtime = seconds_from_midnight(get_date('g:ia', $lasttime, $CFG->timezone)) > $endofwork ? mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + 86399 : mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + $endofwork;
                    $outtime -= get_offset();
                    $readabletime = get_date("l, F j, Y \a\\t g:i a", display_time($outtime));
                    $event        = get_db_row("SELECT * FROM events WHERE tag='out'");
                    $actid        = execute_db_sql("INSERT INTO employee_activity (employeeid,evid,tag,timelog) VALUES('$employeeid','" . $event["evid"] . "','" . $event["tag"] . "',$outtime) ");
                    $note         = $employee["first"] . " " . $employee["last"] . ": Signed out at $readabletime";
                    execute_db_sql("INSERT INTO notes (actid,employeeid,tag,note,data,timelog) VALUES('$actid','$employeeid','" . $event["tag"] . "','$note',1,$outtime) ");
                }
            }
        }
    }

}

function get_wage($employeeid, $time) {
    if ($row = get_db_row("SELECT wage FROM employee_wage WHERE employeeid='$employeeid' AND dategiven <= $time ORDER BY dategiven DESC LIMIT 1")) {
        return $row["wage"];
    } elseif ($row = get_db_row("SELECT wage FROM employee_wage WHERE employeeid='$employeeid' ORDER BY dategiven DESC LIMIT 1")) { //no wage given in that timeframe
        return $row["wage"];
    }
    return false;
}

function get_wages_for_week($time) {
    if (date('N', $time) == "7") { //is already a sunday
        $startofweek = strtotime(date('m/d/Y', $time));
    } else {
        $startofweek = strtotime("previous Sunday", strtotime(date('m/d/Y', $time)));
    }

    $sum = 0;
    if ($result = get_db_result("SELECT * FROM employee_timecard WHERE fromdate='$startofweek'")) {
        while ($row = fetch_row($result)) {
            $sum += empty($row["hours_override"]) ? $row["hours"] * $row["wage"] : $row["hours_override"] * $row["wage"];
        }
    }

    return $sum;
}

function hours_worked($employeeid, $startofweek, $endofweek) {
    global $CFG;
    $SQL   = "SELECT CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'" . get_date('P', time(), $CFG->timezone) . "','" . get_date('P', time(), $CFG->timezone) . "'))) as order_day, tag, timelog FROM employee_activity WHERE employeeid='$employeeid' AND (tag='out' OR tag='in') AND timelog >= '$startofweek' AND timelog <= '$endofweek' ORDER BY timelog";
    $hours = 0;
    if ($results = get_db_result($SQL)) {
        $lasttag   = $lasttime = $day = "";
        $endofwork = seconds_from_midnight(get_db_field("timeclosed", "programs", "pid='" . get_pid() . "'"));

        while ($row = fetch_row($results)) {
            //Set day to the first day
            $day = $day == $row["order_day"] ? $row["order_day"] : false;

            //If it has moved to the next day, return.
            if (empty($day)) {
                if ($lasttag == "in") { //didn't sign out
                    $outtime = seconds_from_midnight(get_date('g:ia', $lasttime, $CFG->timezone)) > $endofwork ? mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + 86399 : mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + $endofwork;
                    $outtime -= get_offset();
                    if (($outtime - $lasttime) < 61200 && ($outtime - $lasttime) > 0) {
                        //echo $hours . " " . ($outtime - $lasttime);
                        $hours += (($outtime - $lasttime) / 3600);
                    }
                }
            } elseif ($row["tag"] == "out" && $lasttag == "in") {
                $hours += (($row["timelog"] - $lasttime) / 3600);
            }

            $day      = $row["order_day"];
            $lasttime = $row["timelog"];
            $lasttag  = $row["tag"];
        }

        if ($lasttag == "in") { //didn't sign out
            $outtime = seconds_from_midnight(get_date('g:ia', $lasttime, $CFG->timezone)) > $endofwork ? mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + 86399 : mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + $endofwork;
            $outtime -= get_offset();

            if (($outtime - $lasttime) < 61200 && ($outtime - $lasttime) > 0) {
                $hours += (($outtime - $lasttime) / 3600);
            }
        }
    }
    return $hours;
}

function hours_attended($chid, $starttime) {
    global $CFG;
    $SQL = "SELECT CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'" . get_date('P', time(), $CFG->timezone) . "','" . get_date('P', time(), $CFG->timezone) . "'))) as order_day, tag, timelog FROM activity WHERE chid='$chid' AND (tag='out' OR tag='in') AND timelog >= '$starttime' ORDER BY timelog";
    if ($results = get_db_result($SQL)) {
        $lasttag   = $lasttime = $day = "";
        $hours     = 0;
        $endofwork = seconds_from_midnight(get_db_field("timeclosed", "programs", "pid='" . get_pid() . "'"));
        while ($row = fetch_row($results)) {
            //Set day to the first day
            $day = empty($day) || $day == $row["order_day"] ? $row["order_day"] : false;

            //If it has moved to the next day, return.
            if (empty($day)) {
                if ($lasttag == "in") { //didn't sign out
                    $outtime = mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + $endofwork;

                    if (($outtime - $lasttime) < 61200 && ($outtime - $lasttime) > 0) {
                        //echo $hours . " " . ($outtime - $lasttime);
                        $hours += (($outtime - $lasttime) / 3600);
                    }
                    return $hours;
                } else {
                    return $hours;
                }
            }

            if ($row["tag"] == "out" && $lasttag == "in") {
                $hours += (($row["timelog"] - $lasttime) / 3600);
                //echo "<br /> $hours";
            }

            $lasttime = $row["timelog"];
            $lasttag  = $row["tag"];
        }

        if ($lasttag == "in") { //didn't sign out
            $outtime = mktime(0, 0, 0, get_date('n', $lasttime), get_date('j', $lasttime), get_date('Y', $lasttime)) + $endofwork;

            if (($outtime - $lasttime) < 61200 && ($outtime - $lasttime) > 0) {
                //echo $hours . " " . ($outtime - $lasttime);
                $hours += (($outtime - $lasttime) / 3600);
            }
            return $hours;
        } else {
            return $hours;
        }
    }
}

function grade_convert($set) {
    switch ($set) {
        case "0":
            return "Kindergarten";
        case "1":
            return "1st Grade";
        case "2":
            return "2nd Grade";
        case "3":
            return "3rd Grade";
        case "4":
            return "4th Grade";
        case "5":
            return "5th Grade";
        case "6":
            return "6th Grade";
        case "7":
            return "Infant";
        case "8":
            return "Pre-K";
        default:
            return "Not Set";
    }
}

function check_and_run_upgrades() {
    $version = get_db_field("version", "version", "version != ''");
    if (!$version) {
        $version = 20120910;
        execute_db_sql("CREATE TABLE `version` (`version` VARCHAR( 16 ) NOT NULL , INDEX ( `version` ) ) ENGINE = MYISAM ;");
        execute_db_sql("INSERT INTO  `version` (`version`) VALUES ('$version');");
        execute_db_sql("ALTER TABLE `children`  ADD `exempt` TINYINT(1) NOT NULL DEFAULT '0' AFTER `grade`,  ADD INDEX (`exempt`) ");
    }

    $thisversion = 20120911;
    if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
        $SQL1 = "ALTER TABLE `enrollments`  ADD `exempt` TINYINT(1) NOT NULL DEFAULT '0' AFTER `days_attending`,  ADD INDEX (`exempt`) ";
        $SQL2 = "ALTER TABLE `children` DROP  `exempt`";
        execute_db_sql($SQL1);
        execute_db_sql($SQL2);
        execute_db_sql("UPDATE version SET version='$thisversion'");
    }

    $thisversion = 20130117; //Major fix for dates to UTC instead of recorded in the timezone format.
    if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
        $SQL = "UPDATE activity SET timelog = (timelog-14400)";
        execute_db_sql($SQL);
        $SQL = "UPDATE billing SET fromdate = (fromdate-14400),todate = (todate-14400)";
        execute_db_sql($SQL);
        $SQL = "UPDATE billing_payments SET timelog = (timelog-14400)";
        execute_db_sql($SQL);
        $SQL = "UPDATE billing_perchild SET fromdate = (fromdate-14400),todate = (todate-14400)";
        execute_db_sql($SQL);
        $SQL = "UPDATE children SET birthdate = (birthdate-14400)";
        execute_db_sql($SQL);
        $SQL = "UPDATE documents SET timelog = (timelog-14400)";
        execute_db_sql($SQL);
        $SQL = "UPDATE notes SET timelog = (timelog-14400)";
        execute_db_sql($SQL);
        execute_db_sql("UPDATE version SET version='$thisversion'");
    }

    $thisversion = 20140326; //Major fix for dates to UTC instead of recorded in the timezone format.
    if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
        $SQL = "CREATE TABLE IF NOT EXISTS `employee` (
              `employeeid` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(255) CHARACTER SET latin1 NOT NULL,
              `pin` int(4) NOT NULL,
              PRIMARY KEY (`employeeid`),
              KEY `pin` (`pin`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1";
        execute_db_sql($SQL);
        $SQL = "CREATE TABLE IF NOT EXISTS `employee_activity` (
              `actid` int(11) NOT NULL AUTO_INCREMENT,
              `employeeid` int(11) NOT NULL,
              `evid` int(11) NOT NULL,
              `tag` varchar(100) NOT NULL,
              `timelog` int(11) NOT NULL,
              PRIMARY KEY (`actid`),
              KEY `chid` (`tag`,`timelog`),
              KEY `eid` (`evid`),
              KEY `pid` (`employeeid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1";
        execute_db_sql($SQL);
        $SQL = "CREATE TABLE IF NOT EXISTS `employee_timecard` (
              `id` int(11) NOT NULL,
              `employeeid` int(11) NOT NULL,
              `fromdate` int(11) NOT NULL,
              `todate` int(11) NOT NULL,
              `hours` float NOT NULL,
              `wage` float NOT NULL,
              PRIMARY KEY (`id`),
              KEY `employeeid` (`employeeid`,`fromdate`,`todate`,`hours`,`wage`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        execute_db_sql($SQL);
        $SQL = "CREATE TABLE IF NOT EXISTS `employee_wage` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `employeeid` int(11) NOT NULL,
              `wage` int(11) NOT NULL,
              `dategiven` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              KEY `employeeid` (`employeeid`,`wage`,`dategiven`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1";
        execute_db_sql($SQL);
        execute_db_sql("UPDATE version SET version='$thisversion'");
    }

    $thisversion = 20190624;
    if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
        $SQL1 = "ALTER TABLE `programs` CHANGE `minimum` `minimumactive` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0'";
        $SQL2 = "ALTER TABLE `programs`  ADD `minimuminactive` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0' AFTER `minimumactive`,  ADD INDEX (`minimumactive`)";
        $SQL3 = "ALTER TABLE `programs` DROP INDEX `minimum`, ADD INDEX `minimumactive` (`minimumactive`) USING BTREE";

        $SQL4 = "ALTER TABLE `billing_override` CHANGE `minimum` `minimumactive` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0'";
        $SQL5 = "ALTER TABLE `billing_override`  ADD `minimuminactive` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0' AFTER `minimumactive`";

        execute_db_sql($SQL1);
        execute_db_sql($SQL2);
        execute_db_sql($SQL3);
        execute_db_sql($SQL4);
        execute_db_sql($SQL5);

        execute_db_sql("UPDATE version SET version='$thisversion'");
    }

    $thisversion = 2019082300;
    if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
        $SQL1 = "ALTER TABLE `programs` ADD `payahead` TINYINT(1) NOT NULL DEFAULT '0' AFTER `discount_rule`,  ADD INDEX (`payahead`) ";
        execute_db_sql($SQL1);
        execute_db_sql("UPDATE version SET version='$thisversion'");
    }

    $thisversion = 2019082301;
    if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
        $SQL1 = "ALTER TABLE `billing_override` ADD `payahead` TINYINT(1) NULL DEFAULT NULL AFTER `discount_rule`,  ADD INDEX (`payahead`) ";
        execute_db_sql($SQL1);

        $SQL2 = "ALTER TABLE `billing_override` MODIFY bill_by VARCHAR(20) NULL DEFAULT NULL";
        execute_db_sql($SQL2);
        $SQL2 = "ALTER TABLE `billing_override` MODIFY perday VARCHAR(10) NULL DEFAULT NULL";
        execute_db_sql($SQL2);
        $SQL2 = "ALTER TABLE `billing_override` MODIFY fulltime VARCHAR(10) NULL DEFAULT NULL";
        execute_db_sql($SQL2);
        $SQL2 = "ALTER TABLE `billing_override` MODIFY minimumactive VARCHAR(10) NULL DEFAULT NULL";
        execute_db_sql($SQL2);
        $SQL2 = "ALTER TABLE `billing_override` MODIFY minimuminactive VARCHAR(10) NULL DEFAULT NULL";
        execute_db_sql($SQL2);
        $SQL2 = "ALTER TABLE `billing_override` MODIFY vacation VARCHAR(10) NULL DEFAULT NULL";
        execute_db_sql($SQL2);
        $SQL2 = "ALTER TABLE `billing_override` MODIFY multiple_discount VARCHAR(10) NULL DEFAULT NULL";
        execute_db_sql($SQL2);
        $SQL2 = "ALTER TABLE `billing_override` MODIFY consider_full TINYINT(1) NULL DEFAULT NULL";
        execute_db_sql($SQL2);
        $SQL2 = "ALTER TABLE `billing_override` MODIFY discount_rule TINYINT(1) NULL DEFAULT NULL";
        execute_db_sql($SQL2);

        execute_db_sql("UPDATE version SET version='$thisversion'");
    }

    $thisversion = 2019082302;
    if ($version < $thisversion) { //# = new version number.  If this is the first...start at 1
        $SQL2 = "UPDATE `billing_override` SET payahead=NULL WHERE payahead=0";
        execute_db_sql($SQL2);
        $SQL2 = "UPDATE `billing_override` SET bill_by=NULL WHERE bill_by=0";
        execute_db_sql($SQL2);
        $SQL2 = "UPDATE `billing_override` SET perday=NULL WHERE perday=0";
        execute_db_sql($SQL2);
        $SQL2 = "UPDATE `billing_override` SET fulltime=NULL WHERE fulltime=0";
        execute_db_sql($SQL2);
        $SQL2 = "UPDATE `billing_override` SET minimumactive=NULL WHERE minimumactive=0";
        execute_db_sql($SQL2);
        $SQL2 = "UPDATE `billing_override` SET minimuminactive=NULL WHERE minimuminactive=0";
        execute_db_sql($SQL2);
        $SQL2 = "UPDATE `billing_override` SET vacation=NULL WHERE vacation=0";
        execute_db_sql($SQL2);
        $SQL2 = "UPDATE `billing_override` SET multiple_discount=NULL WHERE multiple_discount=0";
        execute_db_sql($SQL2);
        $SQL2 = "UPDATE `billing_override` SET consider_full=NULL WHERE consider_full=0";
        execute_db_sql($SQL2);
        $SQL2 = "UPDATE `billing_override` SET discount_rule=NULL WHERE discount_rule=0";
        execute_db_sql($SQL2);

        execute_db_sql("UPDATE version SET version='$thisversion'");
    }
    //    $thisversion = YYYYMMDD;
    //    if($version < $thisversion){ //# = new version number.  If this is the first...start at 1
    //        $SQL = "";
    //        if(execute_db_sql($SQL)) //if successful upgrade
    //        {
    //            execute_db_sql("UPDATE version SET version='$thisversion'");
    //        }
    //    }
}
?>