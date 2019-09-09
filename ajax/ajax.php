<?php
/***************************************************************************
 * ajax.php - Main backend ajax script.  Usually sends off to feature libraries.
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 08/23/2019
 * Revision: 3.1.1
 ***************************************************************************/

include('header.php');

callfunction();

function employee_timesheet() {
    global $CFG, $MYVARS;
    $returnme = go_home_button('Exit');

    //Get all active employees
    $SQL = "SELECT * FROM employee WHERE deleted=0 ORDER BY last,first";
    if ($result = get_db_result($SQL)) {
        $in = $out = "";

        while ($row = fetch_row($result)) {
            $checked_in = is_working($row["employeeid"]);
            if ($checked_in) {
                $action = '';
            } else {
                $action = '';
            }

            $tmp = '<div class="employee_wrapper ui-corner-all">';
            $tmp .= get_employee_button($row["employeeid"], "", "", $action);
            $tmp .= '</div>';

            if ($checked_in) {
                $in .= $tmp;
            } else {
                $out .= $tmp;
            }
        }
        $returnme .= get_numpad("", false, "employee", "#display_level", 'employee_numpad') . '<input type="hidden" id="selectedemployee" /><div class="container_list ui-corner-all fill_height" style="width: 49%;border:none"><div class="ui-corner-all list_box" style="width:initial;color: white;text-align: center;font-size: 20px;">Sign In</div>' . $out . '</div>
                        <div class="container_list ui-corner-all fill_height" style="background: transparent; width: 1%;border:none"></div>
                        <div class="container_list ui-corner-all fill_height" style="width: 49%;border:none"><div class="ui-corner-all list_box" style="width:initial;color: white;text-align: center;font-size: 20px;">Sign Out</div>' . $in . '</div>';
    }

    echo $returnme;
}

function check_in_out_employee() {
    global $CFG, $MYVARS;
    $employeeid   = $MYVARS->GET["employeeid"];
    $employee     = get_db_row("SELECT * FROM employee WHERE employeeid='$employeeid'");
    $time         = get_timestamp();
    $readabletime = get_date("l, F j, Y \a\\t g:i a", display_time($time));

    if (is_working($employeeid)) { //check out
        $event = get_db_row("SELECT * FROM events WHERE tag='out'");
        $actid = execute_db_sql("INSERT INTO employee_activity (employeeid,evid,tag,timelog) VALUES('$employeeid','" . $event["evid"] . "','" . $event["tag"] . "',$time) ");
        $note  = $employee["first"] . " " . $employee["last"] . ": Signed out at $readabletime";
        execute_db_sql("INSERT INTO notes (actid,employeeid,tag,note,data,timelog) VALUES('$actid','$employeeid','" . $event["tag"] . "','$note',1,$time) ");
    } else { //check in
        $event = get_db_row("SELECT * FROM events WHERE tag='in'");
        $actid = execute_db_sql("INSERT INTO employee_activity (employeeid,evid,tag,timelog) VALUES('$employeeid','" . $event["evid"] . "','" . $event["tag"] . "',$time) ");
        $note  = $employee["first"] . " " . $employee["last"] . ": Signed in at: $readabletime";
        execute_db_sql("INSERT INTO notes (actid,employeeid,tag,note,data,timelog) VALUES('$actid','$employeeid','" . $event["tag"] . "','$note',1,$time) ");
    }

    echo employee_timesheet();
}

function get_check_in_out_form() {
    global $CFG, $MYVARS;
    $type        = $MYVARS->GET["type"];
    $returnme    = $alphabet = $children = '';
    $lastinitial = false;
    $pid         = get_pid();
    $returnme .= go_home_button();
    $returnme .= '<input type="hidden" id="askme" value="1" />
    <div id="dialog-confirm" title="Confirm" style="display:none;">
       <p><span class="ui-icon ui-icon-alert" style="margin-right: auto;margin-left: auto;"></span><label>Check for other children on this account?</label></p>
    </div>';

    //Get all active children
    $SQL = "SELECT * FROM children WHERE deleted=0 AND chid IN (SELECT chid FROM enrollments WHERE deleted=0 AND pid='$pid') ORDER BY last,first";
    if ($result = get_db_result($SQL)) {
        $alphabet .= '<div class="fill_width_middle" style="margin:0px 10px;padding:5px;white-space:nowrap;"><div class="label" style="display:inline-block;width:80px;height:45px;float:left;padding-top:10px;">Last Initial: </div><div style="white-space:normal;"><button style="font-size: 20px;" class="keypad_buttons selected_button ui-corner-all" onclick="$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not(this).toggleClass(\'selected_button\',false); $(\'.child_wrapper\').show(\'fade\'); $(\'.scroll-pane\').sbscroller(\'refresh\');">Show All</button>';
        $children .= '<div style="clear:both;"></div><div class="container_main scroll-pane ui-corner-all fill_height_middle">';
        while ($row = fetch_row($result)) {
            $checked_in = is_checked_in($row["chid"]);
            if (($type == "in" && !$checked_in) || ($type == "out" && $checked_in)) {
                $letter = strtoupper(substr($row["last"], 0, 1));
                $alphabet .= !$lastinitial || ($lastinitial != substr($row["last"], 0, 1)) ? '<button style="font-size: 20px;" class="keypad_buttons ui-corner-all" onclick="$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not(this).toggleClass(\'selected_button\',false); $(\'.child_wrapper\').children().not(\'.letter_' . $letter . '\').parent().hide(); $(\'.letter_' . $letter . '\').parent(\'.child_wrapper\').show(\'fade\'); $(\'.scroll-pane\').sbscroller(\'refresh\');">' . strtoupper(substr($row["last"], 0, 1)) . '</button>' : '';
                $action = 'if($(\'.chid_' . $row["chid"] . '.checked_pic\').length > 0){
                            if($(\'#askme\').val()==1 && $(\'.account_' . $row["aid"] . '.checked_pic\').not(\'.chid_' . $row["chid"] . '\').length){
                                CreateConfirm(\'dialog-confirm\',\'Deselect all other children from this account?\',\'Yes\',\'No\',
                                    function(){
                                        $(\'.account_' . $row["aid"] . '.checked_pic\').toggleClass(\'checked_pic\',false);
                                        if($(\'.checked_pic\').length){
                                            $(\'.submit_buttons\').button(\'enable\');
                                        }else{
                                            $(\'.submit_buttons\').button(\'disable\');
                                        }
                                    },
                                    function(){
                                        $(\'#askme\').val(\'0\');
                                        $(\'.chid_' . $row["chid"] . '\').toggleClass(\'checked_pic\',false);
                                        if($(\'.checked_pic\').length > 0){
                                            $(\'.submit_buttons\').button(\'enable\');
                                        }else{
                                            $(\'.submit_buttons\').button(\'disable\');
                                        }
                                    }
                                );
                            }else{
                                $(\'.chid_' . $row["chid"] . '\').toggleClass(\'checked_pic\',false);
                                if($(\'.checked_pic\').length > 0){
                                    $(\'.submit_buttons\').button(\'enable\');
                                }else{
                                    $(\'.submit_buttons\').button(\'disable\');
                                }
                            }
                        }else{
                            if($(\'#askme\').val()==1 && $(\'.account_' . $row["aid"] . '\').not(\'.chid_' . $row["chid"] . '\').not(\'.checked_pic\').length > 0){
                                CreateConfirm(\'dialog-confirm\',\'Select all other children from this account?\',\'Yes\',\'No\',
                                    function(){
                                        $(\'.account_' . $row["aid"] . '\').toggleClass(\'checked_pic\',true);
                                        if($(\'.checked_pic\').length > 0){
                                            $(\'.submit_buttons\').button(\'enable\');
                                        }else{
                                            $(\'.submit_buttons\').button(\'disable\');
                                        }
                                    },
                                    function(){
                                        $(\'#askme\').val(\'0\');
                                        $(\'.chid_' . $row["chid"] . '\').toggleClass(\'checked_pic\',true);
                                        if($(\'.checked_pic\').length){
                                            $(\'.submit_buttons\').button(\'enable\');
                                        }else{
                                            $(\'.submit_buttons\').button(\'disable\');
                                        }
                                    }
                                );
                            }else{
                                $(\'.chid_' . $row["chid"] . '\').toggleClass(\'checked_pic\',true);
                                $(\'.submit_buttons\').button(\'enable\');
                            }
                        }';

                //Highlight Expected kids
                $enrollment = explode(",", get_db_field("days_attending", "enrollments", "pid='$pid' AND chid='" . $row["chid"] . "'"));
                $days       = array(
                    "S",
                    "M",
                    "T",
                    "W",
                    "Th",
                    "F",
                    "Sa"
                );
                $expected   = ""; // Reset expected.
                foreach ($enrollment as $e) {
                    if ($e == $days[date("w")]) {
                        $expected = "expected-today";
                    }
                }

                $children .= '<div class="child_wrapper ui-corner-all ' . $expected . '">';
                $children .= get_children_button($row["chid"], "", "", $action);
                $children .= '</div>';
                $lastinitial = substr($row["last"], 0, 1); //store last initial
            }
        }
        $alphabet .= '</div></div>';
        $children .= '</div>';
    }

    $returnme .= $alphabet . $children;

    //Admin button
    $returnme .= '<div class="top-right side" style="top:50px"><button class="submit_buttons" style="font-size: 150%;" disabled="true" onclick="if($(\'.checked_pic\').length){ var account = \'\'; $(\'.checked_pic\').each(function(index){ account = account == \'\' || account == $(this).attr(\'class\').match(/account_[1-9]+/ig).toString() ? $(this).attr(\'class\').match(/account_[1-9]+/ig) : \'false\'; }); $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'check_in_out_form\',type: \'' . $type . '\',chid: $(\'.checked_pic input.chid\').serializeArray(),admin: true },
              success: function(data) { $(\'#display_level\').html(data); refresh_all(); }
              }); }" >Admin Check ' . ucfirst($type) . '</button></div>';
    $returnme .= '<div class="bottom center ui-corner-all"><button class="submit_buttons big_button textfill" style="font-size: 150%;" disabled="true" onclick="if($(\'.checked_pic\').length){ var account = \'\'; $(\'.checked_pic\').each(function(index){ account = account == \'\' || account == $(this).attr(\'class\').match(/account_[1-9]+/ig).toString() ? $(this).attr(\'class\').match(/account_[1-9]+/ig) : \'false\'; }); if(account == \'false\'){ CreateAlert(\'dialog-confirm\',\'All selected children must be on the same account.\', \'Ok\', function(){}); }else{ $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'check_in_out_form\',type: \'' . $type . '\',chid: $(\'.checked_pic input.chid\').serializeArray(),admin: false },
              success: function(data) { $(\'#display_level\').html(data); refresh_all(); }
              }); }}" >Next</button></div>';

    echo $returnme;
}

function check_in_out_form() {
    global $CFG, $MYVARS;
    $type     = $MYVARS->GET["type"];
    $admin    = !empty($MYVARS->GET["admin"]) && $MYVARS->GET["admin"] != "false" ? true : false;
    $chids    = $MYVARS->GET["chid"];
    $aid      = $admin ? 0 : get_db_field("aid", "children", "chid='" . $chids[0]["value"] . "' AND deleted=0");
    $returnme = $notes = "";
    $returnme .= go_home_button();
    $returnme .= '<div id="dialog-confirm" title="Confirm" style="display:none;">
                   <p><span class="ui-icon ui-icon-alert" style="margin-right: auto;margin-left: auto;"></span><label>Confirmed?</label></p>
                </div>';
    if (!$admin) {
        $returnme .= get_numpad($aid, true, $type, '#display_level', 'other_numpad');
    }
    $returnme .= get_numpad($aid, $admin, $type);
    $note_headers = get_required_notes_header($type);
    $returnme .= '<div style="margin:0px 10px;height:70px;width:60%;float:left;white-space:nowrap;"><span style="display:inline-block;width:145px;"></span>' . $note_headers . '</div>';
    $returnme .= '<div class="fill_width" style="margin:0px 10px;height:70px;width:23%;text-align:center;white-space:nowrap;"><div style="font-weight:bold;color:white;margin-top:20px;font-size:200%;text-shadow: black 0px 0px 10px;">Who is checking them ' . $type . '?</div></div>';
    $returnme .= '<div class="container_main scroll-pane ui-corner-all fill_height_middle" style="float:left;width:60%">';
    $notes    = get_required_notes_forms($type);
    $contacts = get_contacts_selector($chids, $admin);
    foreach ($chids as $chid) {
        $returnme .= empty($notes) ? '<div class="child_wrapper ui-corner-all">' : '<div style="white-space:nowrap;clear:both;">';
        $returnme .= get_children_button($chid["value"], "", "float:left;", "", true);
        $returnme .= $notes;
        $returnme .= '</div>';
    }
    $returnme .= "</div>";

    //questions validator
    $qnum             = get_db_count("SELECT * FROM notes_required n JOIN (SELECT * FROM events_required_notes WHERE evid IN (SELECT evid FROM events WHERE tag='$type' AND (pid='" . get_pid() . "' OR pid='0'))) r ON r.rnid=n.rnid WHERE n.deleted=0 ORDER BY r.sort");
    $questions_open   = $qnum ? 'var selected = true; $(\'.notes_values\').each(function(){ selected = $(this).toggleSwitch({toggleset:true}) ? selected : false; }); if(selected){' : "";
    $questions_closed = $qnum ? '}else{ CreateAlert(\'dialog-confirm\',\'You must answer every question.\', \'Ok\', function(){}); }' : "";

    $returnme .= '<div class="container_main scroll-pane ui-corner-all fill_height_middle">' . $contacts . '</div>';
    $returnme .= '<div class="bottom center ui-corner-all"><button class="submit_buttons big_button textfill" onclick="if($(\'.ui-selected\').length){ if($(\'.ui-selected #cid_other\').length && $(\'#other_numpad\').length){ if($(\'.ui-selected #cid_other\').val().length > 0){ ' . $questions_open . ' numpad(\'other_numpad\'); ' . $questions_closed . ' }else{ CreateAlert(\'dialog-confirm\',\'You must type a name for this person.\', \'Ok\', function(){}); } }else{ ' . $questions_open . ' numpad(\'numpad\'); ' . $questions_closed . ' } }else{ CreateAlert(\'dialog-confirm\',\'You must select a contact.\', \'Ok\', function(){}); }" ><span style="font-size:10px;">Check ' . ucfirst($type) . '</span></button></div>';

    echo $returnme;
}

function check_in_out($chids, $cid, $type, $time = false) {
    global $CFG, $MYVARS;
    $returnme = $notify = "";
    $rnids    = !empty($MYVARS->GET["rnid"]) ? $MYVARS->GET["rnid"] : false;
    $values   = !empty($MYVARS->GET["values"]) ? $MYVARS->GET["values"] : false;

    $pid = get_pid();

    $lastinvoice = get_db_field("MAX(todate)", "billing_perchild", "pid='$pid'");

    if ($lastinvoice < strtotime("previous Saturday")) {
        //no invoices made lately, build them all now
        create_invoices(true, $pid, false);
    }

    $event        = get_db_row("SELECT * FROM events WHERE pid='$pid' OR pid='0' AND tag='$type'");
    $time = empty($time) ? get_timestamp() : $time;
    $readabletime = get_date("l, F j, Y \a\\t g:i a", display_time($time));
    $contact      = get_contact_name($cid);

    $returnme .= go_home_button();
    $remaining_balance = "";
    if ($type == "out" && $cid != "admin") {
        $aid           = get_db_field("aid", "children", "chid='" . $chids[0]["value"] . "'");
        $balance       = account_balance($pid, $aid); //Previous weeks combined total - paid
        $current_week  = week_balance($pid, $aid); //Current weeks total
        $method        = get_enrollment_method($pid, $aid);
        $exempt        = get_db_field("exempt", "enrollments", "chid='" . $chids[0]["value"] . "' AND pid='$pid'");
        $payahead      = get_db_field("payahead", "programs", "pid='$pid'");
        $float_balance = (float) $balance;
        $float_current = (float) $current_week;
        $combined_balance = $float_balance + $float_current;

        if (!$exempt) {
            if ($method == "enrollment") { // Flat rate based on days they are expected to attend
                if ($combined_balance <= 0) { // They have paid more than they previously owed
                    if ($payahead) {
                        $next_week          = week_balance($pid, $aid, true, true); // Next weeks total
                        $combined_balance  += (float) $next_week;
                        $remaining_balance .= "<span style='color:orange;font-weight:bold;font-size:24px;text-shadow: black 0px 0px 10px;'>" .
                                              "You are currently paid up. Thanks!" .
                                              "<br />Payment of $" . number_format($combined_balance, 2) . " is due ahead of next weeks services." .
                                              "</span>";
                    } else {
                        $remaining_balance .= "<span style='color:orange;font-weight:bold;font-size:24px;text-shadow: black 0px 0px 10px;'>" .
                                              "You are currently paid up. Thanks!" .
                                              "</span>";
                    }
                } else {
                    if ($payahead) {
                        $next_week          = week_balance($pid, $aid, true, true); // Next weeks total
                        $next_week          = (float) $next_week;
                        $remaining_balance .= "<span style='color:orange;font-weight:bold;font-size:24px;text-shadow: black 0px 0px 10px;'>" .
                                              "Your account is overdue $" . number_format($combined_balance, 2) . "." .
                                              "<br />An additional payment of $" . number_format($next_week, 2) . " is due ahead of next weeks services." .
                                              "</span>";
                    } else {
                        $remaining_balance .= "<span style='color:orange;font-weight:bold;font-size:24px;text-shadow: black 0px 0px 10px;'>" .
                                              "Your account has a balance of $" . number_format($combined_balance, 2) . " due." .
                                              "</span>";
                    }
                }
            } else { // Rate based on actual attendance
                if ($combined_balance <= 0) {
                    if ($payahead) {
                        $next_week          = week_balance($pid, $aid, true, true); // Next weeks total
                        $combined_balance  += (float) $next_week;
                        $remaining_balance .= "<span style='color:orange;font-weight:bold;font-size:24px;text-shadow: black 0px 0px 10px;'>" .
                                              "You are currently paid up. Thanks!" .
                                              "<br />An estimated $" . number_format($combined_balance, 2) . " is expected for next week." .
                                              "</span>";
                    } else {
                        $remaining_balance .= "<span style='color:orange;font-weight:bold;font-size:24px;text-shadow: black 0px 0px 10px;'>" .
                                              "You are currently paid up. Thanks!" .
                                              "</span>";
                    }
                } else {
                    if ($payahead) {
                        $next_week          = week_balance($pid, $aid, true, true); // Next weeks total
                        $next_week          = (float) $next_week;
                        $remaining_balance .= "<span style='color:orange;font-weight:bold;font-size:24px;text-shadow: black 0px 0px 10px;'>" .
                                              "Your account is overdue $" . number_format($combined_balance, 2) . "." .
                                              "<br />An estimated $" . number_format($next_week, 2) . " is expected for next week." .
                                              "</span>";
                    } else {
                        $remaining_balance .= "<span style='color:orange;font-weight:bold;font-size:24px;text-shadow: black 0px 0px 10px;'>" .
                                              "Your account has a balance of $" . number_format($combined_balance, 2) . "." .
                                              "<br />So far this week you owe $" . number_format($float_current, 2) . "." .
                                              "</span>";
                    }
                }
            }
        }
    }

    $childcount  = count($chids);
    $notes_count = !empty($rnids) && count($rnids) ? count($rnids) / $childcount : false;
    $notified    = array();
    $c           = 1; //Child counter
    $i           = 0; //Note counter
    $content = "";
    foreach ($chids as $chid) {
        //Signed out
        $child = get_db_row("SELECT * FROM children WHERE chid='" . $chid["value"] . "' AND deleted=0");
        $note  = $child["first"] . " " . $child["last"] . ": Checked $type by $contact: $readabletime";

        // birthday flag
        $confetti_start = $$confetti_stop = $bday = "";
        if (date("md",$child["birthdate"]) == date("md", get_timestamp())) {
            $confetti_start = 'confetti.start();';
            $bday = '<h1 class="heading" style="font-size:4em">Happy Birthday!</h1>';
        }

        //prevents duplicate entries -- not sure why it is happening
        if (!get_db_row("SELECT timelog FROM activity WHERE timelog='$time' AND chid='" . $chid["value"] . "'") && $actid = execute_db_sql("INSERT INTO activity (pid,aid,chid,cid,evid,tag,timelog) VALUES('$pid','" . $child["aid"] . "','" . $chid["value"] . "','$cid','" . $event["evid"] . "','" . $event["tag"] . "',$time) ")) {
            //Record a note with who checked them in
            execute_db_sql("INSERT INTO notes (pid,aid,chid,actid,cid,tag,note,data,timelog) VALUES('" . $pid . "','" . $child["aid"] . "','" . $chid["value"] . "','$actid','$cid','" . $event["tag"] . "','$note',1,$time) ");
            $req_notes_text = "";
            //If there are notes, record them now
            if (!empty($notes_count)) {
                $req_notes_text .= '<span style="display: inline-block; padding: 4px; margin: 4px;">';
                while ($i < ($notes_count * $c)) {
                    $rnid          = $rnids[$i]["value"];
                    $setting       = $values[$i]["value"];
                    $req_note      = get_db_row("SELECT * FROM notes_required WHERE rnid='$rnid'");
                    $req_note_text = get_note_text($req_note, $setting);
                    $req_notes_text .= $req_note_text . "<br />";
                    execute_db_sql("INSERT INTO notes (pid,aid,chid,actid,cid,rnid,tag,note,data,timelog) VALUES('" . $pid . "','" . $child["aid"] . "','" . $chid["value"] . "','$actid','$cid','$rnid','" . $req_note["tag"] . "','$req_note_text','$setting',$time) ");
                    $i++;
                }
                $req_notes_text .= "</span>";
            }
            $c++;
            $content .= '<div class="child_wrapper ui-corner-all">';
            $content .= get_children_button($chid["value"], "", "", "", true);
            $content .= $req_notes_text;
            $content .= '</div>';

            if ($type == "out") {
                if (array_search($child["aid"], $notified) === FALSE) {
                    $notify     = get_notifications($pid, false, $child["aid"], true) . $notify; //add account bulletins to the top
                    $notified[] = $child["aid"];
                }
                $notify .= get_notifications($pid, $chid["value"], false, true);
            }
        }
    }

    if ($type == "out") { //Program bulletins
        $notify = get_notifications($pid, false, false, true) . $notify; //add program bulletins to the top
    }

    $wait = empty($notify) ? "6000" : "15000"; //if there are notifications, give them more time to read

    $returnme .= $bday . '<div class="heading" style="margin:0px 10px;"><h1>Checked ' . ucwords($type) . ' on ' . $readabletime . ' by ' . $contact . '</h1>' . $remaining_balance . '</div>
                 <div class="container_main scroll-pane ui-corner-all fill_height_middle">' . $content . '</div>';

    $returnme .= $type == "out" && !empty($notify) ? '<br /><div class="bottom center ui-corner-all" style="padding-bottom:10px;position:initial;max-height:initial;height:30%;">' . get_icon("alert") . ' <span style="position:relative;top:-8px;font-size:24px"><strong>Attention</strong></span><br />' . $notify . '</div>' : '';

    $returnme .= '<script type="text/javascript">
        '.$confetti_start.'
        var autoback = setTimeout(function(){
            '.$confetti_stop.'
            location.reload();
        },' . $wait . ');
    </script>';
    return $returnme;
}

function get_notifications($pid, $chid = false, $aid = false, $separate = false) {
    global $CFG;
    $notify = "";
    if (empty($aid)) { //Get aid from chid
        $aid = get_db_field("aid", "children", "chid='$chid'");
    }

    if (empty($separate)) { //any combine notifications?
        if ($chid) { //child and bulletin material
            $SQL = "SELECT * FROM notes WHERE ((chid='$chid' AND pid='$pid') || (tag='bulletin' AND (aid='$aid' OR pid='$pid'))) AND ((notify=1 AND CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(FROM_UNIXTIME(timelog+" . get_offset($CFG->servertz) . ")))='" . date("Ynj", get_timestamp($CFG->timezone)) . "') OR (notify=2)) ORDER BY timelog";
        } else { //bulletin only
            $SQL = "SELECT * FROM notes WHERE (tag='bulletin' AND (aid='$aid' OR pid='$pid')) AND ((notify=1 AND CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'" . get_date('P', time(), $CFG->servertz) . "','" . get_date('P', time(), $CFG->timezone) . "')))='" . date("Ynj", get_timestamp($CFG->timezone)) . "') OR (notify=2)) ORDER BY timelog";
        }
    } else { //specific context notifications, usually for display purposes
        if (!empty($chid)) { //child notes
            $name = get_name(array(
                "type" => "chid",
                "id" => $chid
            ));
            $SQL  = "SELECT * FROM notes WHERE (chid='$chid' AND pid='$pid') AND ((notify=1 AND CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(FROM_UNIXTIME(timelog+" . get_offset($CFG->servertz) . ")))='" . date("Ynj", get_timestamp($CFG->timezone)) . "') OR (notify=2)) ORDER BY timelog";
        } elseif (!empty($aid)) { //account bulletins
            $name = get_name(array(
                "type" => "aid",
                "id" => $aid
            ));
            $SQL  = "SELECT * FROM notes WHERE (tag='bulletin' AND aid='$aid') AND ((notify=1 AND CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(FROM_UNIXTIME(timelog+" . get_offset($CFG->servertz) . ")))='" . date("Ynj", get_timestamp($CFG->timezone)) . "') OR (notify=2)) ORDER BY timelog";
        } else { //program bulletins
            $name = get_name(array(
                "type" => "pid",
                "id" => $pid
            ));
            $SQL  = "SELECT * FROM notes WHERE (tag='bulletin' AND pid='$pid') AND ((notify=1 AND CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(FROM_UNIXTIME(timelog+" . get_offset($CFG->servertz) . ")))='" . date("Ynj", get_timestamp($CFG->timezone)) . "') OR (notify=2)) ORDER BY timelog";
        }
    }

    if ($notifications = get_db_result($SQL)) {
        while ($notification = fetch_row($notifications)) {
            $tag = get_tag(array(
                "type" => "notes",
                "tag" => $notification["tag"]
            ));

            //Name based on each tag.
            if (!empty($notification["chid"])) {
                $name = get_name(array(
                    "type" => "chid",
                    "id" => $notification["chid"]
                ));
            } elseif (!empty($notification["aid"])) {
                $name = get_name(array(
                    "type" => "aid",
                    "id" => $aid
                )) . " Account";
            } else {
                $name = get_name(array(
                    "type" => "pid",
                    "id" => $pid
                ));
            }

            //save bulletins and compare so duplicates are not shown
            $notify .= '<div class="notify"><span class="tag ui-corner-all" style="color:' . $tag["textcolor"] . ';background-color:' . $tag["color"] . '">' . $tag["title"] . '</span> <strong>' . $name . '</strong>: ' . $notification["note"] . '</div>';
        }
    } else {
        return false;
    }
    return $notify;
}

function get_contact_name($cid) {
    if ($cid == "admin") {
        $contact = get_db_field("name", "accounts", "admin='1'");
    } elseif ($cid == "other") {
        $contact = "Alternate Pickup";
    } else {
        $contact = get_db_row("SELECT * FROM contacts WHERE cid='$cid'");
        $contact = $contact["first"] . " " . $contact["last"];
    }
    return $contact;
}

function validate() {
    global $MYVARS;
    $aid        = empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"];
    $employeeid = empty($MYVARS->GET["employeeid"]) ? false : $MYVARS->GET["employeeid"];
    $chids      = empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"];
    $cid        = empty($MYVARS->GET["cid"][0]["value"]) ? false : $MYVARS->GET["cid"][0]["value"];
    $type       = empty($MYVARS->GET["type"]) ? false : $MYVARS->GET["type"];
    $admin      = !empty($MYVARS->GET["admin"]) && $MYVARS->GET["admin"] != "false" ? true : false;
    $password   = empty($MYVARS->GET["password"]) ? false : $MYVARS->GET["password"];

    if (empty($type) && $admin && get_db_row("SELECT aid FROM accounts WHERE admin=1 AND password='$password'")) { //admin login
        $returnme = get_admin_page();
    } elseif (((!$admin && $type != "employee") || ($admin && empty($aid)) || ($admin && !empty($aid) && strstr($MYVARS->GET["cid"][0]["name"], "_other"))) && get_db_row("SELECT aid FROM accounts WHERE (aid='$aid' AND deleted=0 AND password='$password') OR (admin=1 AND password='$password')")) { //student check in / out
        if (strstr($MYVARS->GET["cid"][0]["name"], "_other")) { // Make "other" contact
            if (strstr($cid, " ")) { //has space so assume first and last name
                $name  = explode(" ", $cid);
                $first = $name[0];
                $last  = $name[1];
            } else {
                $first = $cid;
                $last  = "";
            }

            if (!$cid = get_db_field("cid", "contacts", "aid='$aid' AND first='$first' AND last='$last'")) {
                $SQL = "INSERT INTO contacts (aid,first,last,relation,home_address,phone1,phone2,phone3,phone4,employer,employer_address,hours,emergency) VALUES('$aid','$first','$last','','','','','','','','','',0)";
                if (!$cid = execute_db_sql($SQL)) { //Fails
                    echo "false";
                    exit();
                }
            }
        }
        $returnme = check_in_out($chids, $cid, $type);
    } elseif (!$admin && $type == "employee" && get_db_row("SELECT employeeid FROM employee WHERE (employeeid='$employeeid' AND deleted=0 AND password='$password') OR 1 = (SELECT admin FROM accounts WHERE admin=1 AND password='$password')")) { //employee sign in / out
        $returnme = check_in_out_employee($employeeid);
    } else { //Failed validation
        echo "false";
        exit();
    }
    echo $returnme;
}

function get_admin_page($type = false, $id = false) {
    $returnme  = "";
    $activepid = get_pid();

    //checks for software updates
    check_and_run_upgrades();

    //checks employee check in and out status
    closeout_thisweek();

    //run book keeping if needed
    $lastinvoice = get_db_field("MAX(todate)", "billing_perchild", "pid='$activepid'");
    if ($lastinvoice < strtotime("previous Saturday")) {
        //no invoices made lately, build them all now
        create_invoices(true, $activepid, false);
    }

    $programname = get_db_field("name", "programs", "pid='$activepid'");
    $programname = empty($programname) ? "No Active Program" : $programname;
    $account     = get_db_row("SELECT * FROM accounts WHERE admin='1'");
    $identifier  = time() . "edit_account_" . $account["aid"];
    $returnme .= get_form("add_edit_account", array(
        "account" => $account
    ), $identifier);
    $admin_button = '<button title="Edit Admin" style="float:right;font-size: 150%;" type="button" onclick="CreateDialog(\'add_edit_account_' . $identifier . '\',200,315)">Edit Admin</button>';
    $returnme .= '<div id="dialog-confirm" title="Confirm" style="display:none;">
                       <p><span class="ui-icon ui-icon-alert" style="margin-right: auto;margin-left: auto;"></span><label></label></p>
                    </div>';
    $returnme .= '<span id="activepidname" class="top-center">' . $programname . '</span>' . $admin_button;
    $returnme .= go_home_button('Exit Admin');

    $enrollment_selected = $account_selected = $contacts_selected = $tag_selected = $employees_selected = $billing_selected = $children_selected = "";
    if (!empty($type)) {
        if ($type == "pid") {
            $form                = get_admin_enrollment_form(true, $id);
            $enrollment_selected = 'selected_button';
        } elseif ($type == "aid") {
            $form             = get_admin_accounts_form(true, $id);
            $account_selected = 'selected_button';
        } elseif ($type == "cid") {
            $form              = get_admin_contacts_form(true, $id);
            $contacts_selected = 'selected_button';
        } elseif ($type == "chid") {
            $form              = get_admin_children_form(true, $id);
            $children_selected = 'selected_button';
        } else {
            $form             = get_admin_accounts_form(true);
            $account_selected = 'selected_button';
        }
    } else {
        $form             = get_admin_accounts_form(true);
        $account_selected = 'selected_button';
    }

    $returnme .= '<div class="admin_menu">
                    <button class="keypad_buttons ' . $account_selected . '" id="accounts" onclick="$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not(this).toggleClass(\'selected_button\',false);
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_admin_accounts_form\', aid: \'\' },
                      success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); });  }
                      });">Accounts</button>
                    <button class="keypad_buttons ' . $enrollment_selected . '" id="admin_menu_programs" onclick="$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not(this).toggleClass(\'selected_button\',false);
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_admin_enrollment_form\', chid: \'\' },
                      success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); refresh_all(); });  }
                      });">Programs</button>
                    <button class="keypad_buttons ' . $tag_selected . '" id="admin_menu_tags" onclick="$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not(this).toggleClass(\'selected_button\',false);
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_admin_tags_form\', cid: \'\' },
                      success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); });  }
                      });">Tags</button>
                    ';

    $active_display = $activepid ? "" : "display:none;";
    $returnme .= '<span class="only_when_active" style="' . $active_display . '">
                    <button class="keypad_buttons ' . $children_selected . '" id="admin_menu_children" onclick="$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not(this).toggleClass(\'selected_button\',false);
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_admin_children_form\', chid: \'\' },
                      success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); });  }
                      });">Children</button>
                    <button class="keypad_buttons ' . $contacts_selected . '" id="admin_menu_contacts" onclick="$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not(this).toggleClass(\'selected_button\',false);
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_admin_contacts_form\', cid: \'\' },
                      success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); });  }
                      });">Contacts</button>
                    <button class="keypad_buttons ' . $employees_selected . '" id="admin_menu_employees" onclick="$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not(this).toggleClass(\'selected_button\',false);
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_admin_employees_form\', employeeid: \'\' },
                      success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); });  }
                      });">Employees</button>
                    <button class="keypad_buttons ' . $billing_selected . '" id="admin_menu_billing" onclick="$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not(this).toggleClass(\'selected_button\',false);
                    $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_admin_billing_form\', pid: \'' . $activepid . '\' },
                      success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); });  }
                      });">Billing</button></span>';
    $returnme .= '</div><div id="admin_display" class="admin_display">';
    $returnme .= $form;
    $returnme .= '</div>';

    return $returnme;
}

function add_edit_program() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "pid":
            case "name":
            case "timeopen":
            case "timeclosed":
            case "consider_full":
            case "bill_by":
            case "payahead":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "perday":
            case "fulltime":
            case "minimumactive":
            case "minimuminactive":
            case "multiple_discount":
            case "vacation":
            case "discount_rule":
                ${$field["name"]} = str_replace("$", "", dbescape($field["value"]));
                break;
        }
    }

    $callback = empty($callback) ? false : $callback;
    $pid      = empty($pid) ? false : $pid;

    if (!empty($name) && !empty($timeopen) && !empty($timeclosed) && is_numeric($perday) && is_numeric($fulltime)) {
        if ($pid) {
            $SQL = "UPDATE programs SET name='$name',timeopen='$timeopen',timeclosed='$timeclosed',perday='$perday',fulltime='$fulltime',minimumactive='$minimumactive',minimuminactive='$minimuminactive',vacation='$vacation',multiple_discount='$multiple_discount',consider_full='$consider_full',bill_by='$bill_by',discount_rule='$discount_rule',payahead='$payahead' WHERE pid='$pid'";
        } else {
            $SQL = "INSERT INTO programs (name,timeopen,timeclosed,perday,fulltime,minimumactive,minimuminactive,vacation,multiple_discount,consider_full,bill_by,discount_rule,payahead) VALUES('$name','$timeopen','$timeclosed','$perday','$fulltime','$minimumactive','$minimuminactive','$vacation','$multiple_discount','$consider_full','$bill_by','$discount_rule','$payahead')";
        }

        if (execute_db_sql($SQL)) { //Saved successfully
            switch ($callback) {
                case "programs":
                default:
                    get_admin_enrollment_form(false, $pid);
                    break;
            }
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}

function add_edit_expense() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "amount":
                ${$field["name"]} = str_replace("$", "", dbescape($field["value"]));
                break;
            case "timelog":
                ${$field["name"]} = strtotime(dbescape($field["value"]));
                break;
            case "note":
            case "pid":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
        }
    }

    $callback = empty($callback) ? false : $callback;
    $pid      = empty($pid) ? false : $pid;

    if (!empty($pid) && !empty($timelog) && is_numeric($timelog) && !empty($amount) && is_numeric($amount)) {
        $SQL = "INSERT INTO billing_payments (pid,aid,payment,timelog,note) VALUES('$pid','0','$amount','$timelog','$note')";

        if (execute_db_sql($SQL)) { //Saved successfully
            switch ($callback) {
                case "programs":
                    get_admin_enrollment_form(false, $pid);
                    break;
                default:
                    get_admin_enrollment_form(false, $pid);
                    break;
            }
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }

}

function billing_overrides() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $overridemade = false;
    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "perday":
            case "fulltime":
            case "minimumactive":
            case "minimuminactive":
            case "multiple_discount":
            case "vacation":
            case "discount_rule":
                if ($field["value"] == "") {
                    ${$field["name"]} = "NULL";
                } else {
                    ${$field["name"]} = "'".str_replace("$", "", dbescape($field["value"]))."'";
                    $overridemade = true;
                }
                break;
            case "consider_full":
            case "bill_by":
            case "payahead":
                if ($field["value"] == "none") {
                    ${$field["name"]} = "NULL";
                } else {
                    ${$field["name"]} = "'".dbescape($field["value"])."'";
                    $overridemade = true;
                }
                break;
            case "pid":
            case "aid":
            case "oid":
            case "callback":
            case "callbackinfo":
                ${$field["name"]} = dbescape($field["value"]);
                break;
        }
    }

    $callback     = empty($callback) ? false : $callback;
    $callbackinfo = empty($callbackinfo) ? false : $callbackinfo;
    $pid          = empty($pid) ? false : $pid;
    $aid          = empty($aid) ? false : $aid;
    $oid          = empty($oid) ? false : $oid;


    if ($oid) {
        if (!$overridemade) {
            $SQL = "DELETE FROM billing_override WHERE oid='$oid'";
        } else {
            $SQL = "UPDATE billing_override SET perday=$perday,fulltime=$fulltime,minimumactive=$minimumactive,minimuminactive=$minimuminactive,vacation=$vacation,multiple_discount=$multiple_discount,consider_full=$consider_full,bill_by=$bill_by,discount_rule=$discount_rule,payahead=$payahead WHERE oid='$oid'";
        }
    } elseif ($overridemade) {
        $SQL = "INSERT INTO billing_override (pid,aid,perday,fulltime,minimumactive,minimuminactive,vacation,multiple_discount,consider_full,bill_by,discount_rule,payahead) VALUES($pid,$aid,$perday,$fulltime,$minimumactive,$minimuminactive,$vacation,$multiple_discount,$consider_full,$bill_by,$discount_rule,$payahead)";
    }

    if (execute_db_sql($SQL)) { //Saved successfully
        switch ($callback) {
            case "billing":
            default:
                get_admin_billing_form(false, $pid, $callbackinfo);
                break;
        }
    } else {
        echo "false";
    }
}

function add_edit_tag() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "tagtype":
            case "update":
            case "tag":
            case "title":
            case "color":
            case "textcolor":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
        }
    }

    $callback = empty($callback) ? false : $callback;

    if (!empty($tag)) {
        $tag       = strtolower(str_replace(' ', '_', $tag));
        $title     = empty($title) ? ucwords(str_replace('_', ' ', $tag)) : ucwords($title);
        $color     = empty($color) ? "silver" : $color;
        $textcolor = empty($textcolor) ? "black" : $textcolor;
        if (!empty($update)) {
            $SQL = "UPDATE $tagtype" . "_tags SET tag='$tag',title='$title',color='$color',textcolor='$textcolor' WHERE tag='$update'";
        } else {
            if (get_db_row("SELECT * FROM $tagtype" . "_tags WHERE tag='$tag'")) {
                echo "false";
                exit;
            }
            $SQL = "INSERT INTO $tagtype" . "_tags (title,tag,color,textcolor) VALUES('$title','$tag','$color','$textcolor')";
        }

        if (execute_db_sql($SQL)) { //Saved successfully
            switch ($callback) {
                case "tags":
                    get_admin_tags_form(false, $tagtype);
                    break;
                default:
                    get_admin_tags_form(false, $tagtype);
                    break;
            }
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}

function add_edit_payment() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "note":
            case "aid":
            case "payid":
            case "pid":
            case "callback":
            case "callbackinfo":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "payment":
                ${$field["name"]} = str_replace("$", "", dbescape($field["value"]));
                break;
            case "timelog":
                ${$field["name"]} = strtotime(dbescape($field["value"]));
                break;
        }
    }

    $callback     = empty($callback) ? false : $callback;
    $callbackinfo = empty($callbackinfo) ? false : $callbackinfo;
    $payid        = empty($payid) ? false : $payid;

    if (!empty($aid) && !empty($pid) && is_numeric($payment) && !empty($timelog)) {
        if ($payid) {
            $SQL = "UPDATE billing_payments SET pid='$pid',aid='$aid',payment='$payment',timelog='$timelog',note='$note' WHERE payid='$payid'";
        } else {
            $SQL = "INSERT INTO billing_payments (pid,aid,payment,timelog,note) VALUES('$pid','$aid','$payment','$timelog','$note')";
        }

        if (execute_db_sql($SQL)) { //Saved successfully
            switch ($callback) {
                case "accounts":
                    get_admin_accounts_form(false, $callbackinfo);
                    break;
                case "billing":
                    get_admin_billing_form(false, $pid, $callbackinfo);
                    break;
                default:
                    get_admin_billing_form(false, $pid, $callbackinfo);
                    break;
            }
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}

function add_edit_account() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "password":
            case "name":
            case "aid":
            case "meal_status":
            case "recover":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
        }
    }

    $recover  = empty($recover) ? false : $recover;
    $callback = empty($callback) ? false : $callback;
    $aid      = empty($aid) ? false : $aid;

    if (!empty($name) && is_numeric($password) && strlen($password) == 4) {
        if ($aid) {
            $SQL = "UPDATE accounts SET name='$name',password='$password',meal_status='$meal_status' WHERE aid='$aid'";
        } else {
            $SQL = "INSERT INTO accounts (name,password,meal_status,admin) VALUES('$name','$password','$meal_status','0')";
        }

        if (execute_db_sql($SQL)) { //Saved successfully
            switch ($callback) {
                case "accounts":
                    get_admin_accounts_form(false, $aid, $recover);
                    break;
                default:
                    get_admin_accounts_form(false, $aid, $recover);
                    break;
            }
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}

function add_edit_employee() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "password":
            case "first":
            case "last":
            case "employeeid":
            case "recover":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "wage":
                ${$field["name"]} = dbescape($field["value"]);
                ${$field["name"]} = str_replace("$", "", $wage);
                break;
        }
    }

    $recover    = empty($recover) ? false : $recover;
    $callback   = empty($callback) ? false : $callback;
    $employeeid = empty($employeeid) ? false : $employeeid;
    $time       = strtotime(date("m/d/Y", time()));
    if (!empty($first) && !empty($last) && is_numeric($wage) && $wage > 0 && is_numeric($password) && strlen($password) == 4) {
        if ($employeeid) {
            if ($oldwage = get_db_row("SELECT * FROM employee_wage WHERE employeeid='$employeeid' ORDER BY dategiven DESC LIMIT 1")) { //wage existed
                if ($oldwage["wage"] != $wage) {
                    if ($oldwage["dategiven"] == $time) {
                        execute_db_sql("UPDATE employee_wage SET wage='$wage' WHERE id='" . $oldwage["id"] . "'");
                    } else {
                        execute_db_sql("INSERT INTO employee_wage (employeeid,wage,dategiven) VALUES('$employeeid','$wage','$time')");
                    }
                }
            } else { //no wage entered
                execute_db_sql("INSERT INTO employee_wage (employeeid,wage,dategiven) VALUES('$employeeid','$wage','$time')");
            }
            $SQL = "UPDATE employee SET first='$first',last='$last',password='$password' WHERE employeeid='$employeeid'";
        } else {
            $SQL = "INSERT INTO employee (first,last,password,deleted) VALUES('$first','$last','$password','0')";
        }

        if ($id = execute_db_sql($SQL)) { //Saved successfully
            if (empty($employeeid)) {
                execute_db_sql("INSERT INTO employee_wage (employeeid,wage,dategiven) VALUES('$id','$wage','$time')");
            }

            switch ($callback) {
                case "employees":
                    get_admin_employees_form(false, $employeeid, $recover);
                    break;
                default:
                    get_admin_employees_form(false, $employeeid, $recover);
                    break;
            }
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}

function add_edit_child() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];

    $first = $last = $sex = $grade = $birthdate = "";
    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "chid":
            case "aid":
            case "pid":
            case "first":
            case "last":
            case "sex":
            case "grade":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "birthdate":
                ${$field["name"]} = strtotime(dbescape($field["value"]));
                break;
        }
    }

    $aid       = empty($aid) ? false : $aid;
    $chid      = empty($chid) ? false : $chid;
    $callback  = empty($callback) ? false : $callback;
    $activepid = get_pid();
    //Validation
    if (empty($first) || empty($last) || empty($birthdate)) {
        echo "false";
    } else {
        if ($chid) {
            $pid = empty($pid) ? $activepid : $pid;
            $SQL = "UPDATE children SET first='$first',last='$last',sex='$sex',birthdate='$birthdate',grade='$grade' WHERE chid='$chid'";
            execute_db_sql($SQL);
        } else {
            $SQL = "INSERT INTO children (aid,first,last,sex,birthdate,grade) VALUES('$aid','$first','$last','$sex','$birthdate','$grade')";
            if ($chid = execute_db_sql($SQL)) { //Added successfully
                //Enroll them in the active program
                if ($activepid) {
                    $SQL = "INSERT INTO enrollments (pid,chid,days_attending,exempt) VALUES('$activepid','$chid','M,T,W,Th,F',0)";
                    execute_db_sql($SQL); //Enrolled successfully
                }
            }
        }

        switch ($callback) {
            case "accounts":
                get_admin_accounts_form(false, $aid);
                break;
            case "children":
                get_admin_children_form(false, $chid);
                break;
            case "programs":
                get_admin_enrollment_form(false, $pid);
                break;
            default:
                get_admin_accounts_form(false, $aid);
                break;
        }
    }
}

function add_edit_contact() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $first  = $last = $relation = $home_address = $phone1 = $phone2 = $phone3 = $employer = $employer_address = $phone4 = $hours = $emergency = "";
    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "cid":
            case "aid":
            case "first":
            case "last":
            case "relation":
            case "primary_address":
            case "home_address":
            case "phone1":
            case "phone2":
            case "phone3":
            case "phone4":
            case "employer":
            case "employer_address":
            case "hours":
            case "emergency":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
        }
    }

    //Validation
    if (empty($first) || empty($last)) {
        echo "false";
    } else {
        if (!empty($cid)) {
            $SQL = "UPDATE contacts SET first='$first',last='$last',relation='$relation',primary_address='$primary_address',home_address='$home_address',phone1='$phone1',phone2='$phone2',phone3='$phone3',phone4='$phone4',employer='$employer',employer_address='$employer_address',hours='$hours',emergency='$emergency' WHERE cid='$cid'";
            execute_db_sql($SQL);
        } elseif (!empty($aid)) {
            $SQL = "INSERT INTO contacts (aid,first,last,relation,primary_address,home_address,phone1,phone2,phone3,phone4,employer,employer_address,hours,emergency) VALUES('$aid','$first','$last','$relation','$primary_address','$home_address','$phone1','$phone2','$phone3','$phone4','$employer','$employer_address','$hours','$emergency')";
            if (!$cid = execute_db_sql($SQL)) { //Fails
                echo "false";
            }
        }

        switch ($callback) {
            case "accounts":
                get_admin_accounts_form(false, $aid);
                break;
            case "children":
                get_admin_children_form(false, $chid);
                break;
            case "contacts":
                get_admin_contacts_form(false, $cid);
                break;
            default:
                get_admin_accounts_form(false, $aid);
                break;
        }
    }
}

function add_edit_note() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $time   = get_timestamp();

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "note":
            case "notify":
            case "persistent":
            case "nid":
            case "chid":
            case "cid":
            case "aid":
            case "actid":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "tag":
                ${$field["name"]} = dbescape($field["value"]);
                ${$field["name"]} = make_or_get_tag($tag, "notes");
                break;
        }
    }

    $chid       = empty($chid) ? false : $chid;
    $cid        = empty($cid) ? false : $cid;
    $aid        = empty($aid) ? false : $aid;
    $actid      = empty($actid) ? false : $actid;
    $nid        = empty($nid) ? false : $nid;
    $callback   = empty($callback) ? false : $callback;
    $notify     = empty($notify) ? "0" : $notify;
    $persistent = empty($persistent) ? false : true;

    if (!empty($persistent) && !empty($notify)) {
        $notify = "2";
    }

    $pid = get_pid();
    if (!empty($note) || !empty($tag)) {
        if (!empty($nid)) {
            $SQL = "UPDATE notes SET note='$note',tag='$tag',notify='$notify' WHERE nid='$nid'";
        } else {
            if ($chid) {
                $aid = get_db_field("aid", "children", "chid='$chid'");
                $SQL = "INSERT INTO notes (pid,aid,chid,tag,note,timelog,notify) VALUES('$pid','$aid','$chid','$tag','$note','$time','$notify')";
            } elseif ($aid) {
                $SQL = "INSERT INTO notes (pid,aid,tag,note,timelog,notify) VALUES('$pid','$aid','$tag','$note','$time','$notify')";
            } elseif ($cid) {
                $aid = get_db_field("aid", "contacts", "cid='$cid'");
                $SQL = "INSERT INTO notes (pid,aid,cid,tag,note,timelog,notify) VALUES('$pid','$aid','$cid','$tag','$note','$time','$notify')";
            } elseif ($actid) {
                $SQL = "INSERT INTO notes (pid,actid,tag,note,timelog,notify) VALUES('$pid','$actid','$tag','$note','$time','$notify')";
            }
        }

        if (execute_db_sql($SQL)) { //Saved successfully
            switch ($callback) {
                case "accounts":
                    get_admin_accounts_form(false, $aid);
                    break;
                case "children":
                    get_admin_children_form(false, $chid);
                    break;
                case "contacts":
                    get_admin_contacts_form(false, $cid);
                    break;
                default:
                    get_admin_accounts_form(false, $aid);
                    break;
            }
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}

function add_edit_bulletin() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $time   = get_timestamp();

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "note":
            case "notify":
            case "aid":
            case "pid":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
        }
    }

    $aid      = empty($aid) ? false : $aid;
    $pid      = empty($pid) ? false : $pid;
    $callback = empty($callback) ? false : $callback;
    $notify   = empty($notify) ? "0" : "2"; //2 means it is persistant

    if (!empty($aid)) {
        $nid = get_db_row("SELECT * FROM notes WHERE tag='bulletin' AND pid='0' AND aid='$aid'");
    } else {
        $nid = get_db_row("SELECT * FROM notes WHERE tag='bulletin' AND pid='$pid' AND aid='0'");
    }

    if (!empty($nid)) {
        $SQL = "UPDATE notes SET note='$note',notify='$notify' WHERE nid='" . $nid["nid"] . "'";
    } else {
        if ($aid) {
            $SQL = "INSERT INTO notes (pid,aid,tag,note,timelog,notify) VALUES('0','$aid','bulletin','$note','$time','$notify')";
        } else {
            $SQL = "INSERT INTO notes (pid,aid,tag,note,timelog,notify) VALUES('$pid','0','bulletin','$note','$time','$notify')";
        }
    }

    if (execute_db_sql($SQL)) { //Saved successfully
        switch ($callback) {
            case "accounts":
                get_admin_accounts_form(false, $aid);
                break;
            default:
                get_admin_enrollment_form(false, $pid);
                break;
        }
    } else {
        echo "false";
    }
}

function add_activity() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $time   = get_timestamp();

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "chid":
            case "aid":
            case "tag":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "timelog":
                ${$field["name"]} = dbescape(strtotime($field["value"]) - get_offset());
                break;
        }
    }

    $chid     = empty($chid) ? false : $chid;
    $aid      = empty($aid) ? false : $aid;
    $callback = empty($callback) ? false : $callback;
    $pid      = get_pid();
    $evid     = get_db_field("evid", "events", "tag='$tag'");
    if (!empty($evid) && !empty($chid)) {
        $cid        = 0;
        $chids      = array(array("value" => $chid));

        check_in_out($chids, $cid, $tag, $timelog);

        if (execute_db_sql($SQL)) { //Saved successfully
            switch ($callback) {
                case "children":
                    get_admin_children_form(false, $chid);
                    break;
                default:
                    get_admin_accounts_form(false, $aid);
                    break;
            }
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}

function add_edit_notes() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $time   = get_timestamp();

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "note":
            case "nid":
            case "chid":
            case "cid":
            case "aid":
            case "actid":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "tag":
                ${$field["name"]} = dbescape($field["value"]);
                ${$field["name"]} = make_or_get_tag($tag, "events");
                break;
        }
    }

    $chid     = empty($chid) ? false : $chid;
    $cid      = empty($cid) ? false : $cid;
    $aid      = empty($aid) ? false : $aid;
    $actid    = empty($actid) ? false : $actid;
    $nid      = empty($nid) ? false : $nid;
    $callback = empty($callback) ? false : $callback;
    $pid      = get_pid();
    if (!empty($note) || !empty($tag)) {
        if (!empty($nid)) {
            $SQL = "UPDATE notes SET note='$note',tag='$tag' WHERE nid='$nid'";
        } else {
            if ($chid) {
                $aid = get_db_field("aid", "children", "chid='$chid'");
                $SQL = "INSERT INTO notes (pid,aid,chid,tag,note,timelog) VALUES('$pid','$aid','$chid','$tag','$note','$time')";
            } elseif ($aid) {
                $SQL = "INSERT INTO notes (pid,aid,tag,note,timelog) VALUES('$pid','$aid','$aid','$tag','$note','$time')";
            } elseif ($cid) {
                $aid = get_db_field("aid", "contacts", "cid='$cid'");
                $SQL = "INSERT INTO notes (pid,aid,cid,tag,note,timelog) VALUES('$pid','$aid','$cid','$tag','$note','$time')";
            } elseif ($actid) {
                $SQL = "INSERT INTO notes (pid,actid,tag,note,timelog) VALUES('$pid','$actid','$tag','$note','$time')";
            }
        }

        if (execute_db_sql($SQL)) { //Saved successfully
            switch ($callback) {
                case "accounts":
                    get_admin_accounts_form(false, $aid);
                    break;
                case "children":
                    get_admin_children_form(false, $chid);
                    break;
                case "contacts":
                    get_admin_contacts_form(false, $cid);
                    break;
                default:
                    get_admin_accounts_form(false, $aid);
                    break;
            }
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}

function add_edit_employee_activity() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "newtime":
                ${$field["name"]} = dbescape($field["value"]);
                ${$field["name"]} = seconds_from_midnight($newtime);
                break;
            case "oldtime":
                ${$field["name"]} = dbescape($field["value"]);
                ${$field["name"]} = strtotime('midnight', $oldtime);
                break;
            case "nid":
            case "actid":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
        }
    }

    $employeeid = empty($employeeid) ? false : $employeeid;
    $actid      = empty($actid) ? false : $actid;
    $nid        = empty($nid) ? false : $nid;
    $callback   = empty($callback) ? false : $callback;

    if (!empty($oldtime) && !empty($newtime) && !empty($actid) && !empty($nid)) {
        $newtime      = $oldtime + $newtime - get_offset();
        $readabletime = get_date("l, F j, Y \a\\t g:i a", $newtime);

        $employeeid = get_db_field("employeeid", "employee_activity", "actid='$actid'");
        $employee   = get_db_row("SELECT * FROM employee WHERE employeeid='$employeeid'");
        $note       = get_db_row("SELECT * FROM notes WHERE nid='$nid'");
        $note       = $employee["first"] . " " . $employee["last"] . ": Signed " . $note["tag"] . " at $readabletime";

        $SQL1 = "UPDATE notes SET note='$note' WHERE nid='$nid'";
        $SQL2 = "UPDATE employee_activity SET timelog='$newtime' WHERE actid='$actid'";

        if (execute_db_sql($SQL1) && execute_db_sql($SQL2)) { //Saved successfully
            get_admin_employees_form(false, $employeeid);
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}

function save_employee_timecard() {
    global $CFG, $MYVARS;
    $fields  = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $updated = array();
    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "employeeid":
            case "id":
            case "hours":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
        }

        if (!empty($id) && !empty($employeeid) && !empty($hours) && is_numeric($hours)) {
            if (get_db_row("SELECT * FROM employee_timecard WHERE id='$id' AND hours='$hours'")) {
                $SQL = "UPDATE employee_timecard SET hours_override='0' WHERE id='$id'";
                if ($updated[] = execute_db_sql($SQL)) { //Saved successfully
                    unset($id);
                    unset($hours);
                }
            } else {
                $SQL = "UPDATE employee_timecard SET hours_override='$hours' WHERE id='$id'";
                if ($updated[] = execute_db_sql($SQL)) { //Saved successfully
                    unset($id);
                    unset($hours);
                }
            }

        }
    }

    $callback = empty($callback) ? false : $callback;

    if (!empty($updated)) {
        get_admin_employees_form(false, $employeeid);
    } else {
        echo "false";
    }
}

function save_employee_salary_history() {
    global $CFG, $MYVARS;
    $fields  = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $updated = array();
    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "employeeid":
            case "id":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "date":
                ${$field["name"]} = dbescape($field["value"]);
                ${$field["name"]} = strtotime($date);
                break;
            case "wage":
                ${$field["name"]} = dbescape($field["value"]);
                ${$field["name"]} = str_replace("$", "", $wage);
                break;
        }

        if (!empty($date) && !empty($id) && !empty($employeeid) && !empty($wage) && is_numeric($wage) && $wage > 0) {
            $SQL = "UPDATE employee_wage SET wage='$wage',dategiven='$date' WHERE id='$id'";

            if ($updated[] = execute_db_sql($SQL)) { //Saved successfully
                unset($id);
                unset($date);
                unset($wage);
            }
        }
    }

    $callback = empty($callback) ? false : $callback;

    if (!empty($updated)) {
        get_admin_employees_form(false, $employeeid);
    } else {
        echo "false";
    }
}

function get_action_buttons($return = false, $pid = null, $aid = null, $chid = null, $cid = null, $actid = null, $recover = null, $employeeid = null) {
    global $CFG, $MYVARS;
    $activepid     = get_pid();
    $pid           = $pid !== null ? $pid : (empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"]);
    $aid           = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $chid          = $chid !== null ? $chid : (empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"]);
    $cid           = $cid !== null ? $cid : (empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"]);
    $employeeid    = $employeeid !== null ? $employeeid : (empty($MYVARS->GET["employeeid"]) ? false : $MYVARS->GET["employeeid"]);
    $actid         = $actid !== null ? $actid : (empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"]);
    $recover       = $recover !== null ? $recover : (empty($MYVARS->GET["recover"]) ? false : $MYVARS->GET["recover"]);
    $returnme      = "";
    $deleted       = $recover ? '1' : '0';
    $recover_param = $recover ? 'true' : '';

    // Expand button.
    $returnme .= '<button title="Expand View" class="image_button" type="button" onclick="$(\'.container_actions,.container_info,.container_list\').toggleClass(\'expanded\'); refresh_all();">' . get_icon('expand') . '</button>';

    if ($pid) { //Program actions
        $identifier = "pid_$pid";
        $program    = get_db_row("SELECT * FROM programs WHERE pid='$pid'");
        $returnme .= '<button title="View Program" class="image_button toggle_view" style="display:none;" type="button" onclick="$(\'.toggle_view\').toggle(); $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_info\', pid: \'' . $pid . '\' },
                      success: function(data) { $(\'#info_div\').html(data); refresh_all();  }
                      });">' . get_icon('search') . '</button>';
        $returnme .= '<button title="View Reports" class="image_button toggle_view" type="button" onclick="$(\'.toggle_view\').toggle(); $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_reports_list\', pid: \'' . $pid . '\' },
                      success: function(data) { $(\'#info_div\').html(data); refresh_all();  }
                      });">' . get_icon('graph') . '</button>';

        $returnme .= get_form("add_edit_expense", array(
            "pid" => $pid,
            "callback" => "programs"
        ), $identifier);
        $returnme .= '<button title="Donations/Expenses" class="image_button" type="button" onclick="CreateDialog(\'add_edit_expense_' . $identifier . '\',600,600)">' . get_icon('payment') . '</button>';


        $returnme .= get_form("event_editor", array(
            "pid" => $pid,
            "callback" => "programs",
            "program" => $program
        ), $identifier);
        $returnme .= '<button title="Edit Events" class="image_button" type="button" onclick="CreateDialog(\'event_editor_' . $identifier . '\',500,700)">' . get_icon('clock_big') . '</button>';

        $returnme .= get_form("bulletin", array(
            "pid" => $pid,
            "callback" => "programs"
        ), $identifier);
        $activebulletin = get_db_row("SELECT * FROM notes WHERE tag='bulletin' AND pid='$pid' AND aid='0' AND notify='2'") ? 'background:orange' : '';
        $returnme .= '<button title="Bulletin" style="' . $activebulletin . '" class="image_button" type="button" onclick="CreateDialog(\'bulletin_' . $identifier . '\',360,400)">' . get_icon('bulletin') . '</button>';

        //Edit Program Details
        $returnme .= get_form("add_edit_program", array(
            "pid" => $program["pid"],
            "callback" => "programs",
            "program" => $program
        ), $identifier);
        $returnme .= '<button title="Edit Program" class="image_button" type="button" onclick="CreateDialog(\'add_edit_program_' . $identifier . '\',450,600)">' . get_icon('config') . '</button>';

        if ($program["pid"] == $activepid) {
            $returnme .= '<button title="Deactivate Program" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to deactivate this program?\', \'Yes\', \'No\', function(){ $.ajax({
                type: \'POST\',
                url: \'ajax/ajax.php\',
                data: { action: \'deactivate_program\', pid: \'' . $pid . '\' },
                success: function(data) { $(\'#display_level\').html(data); refresh_all(); $(\'.only_when_active\').hide(); }
                });}, function(){})">' . get_icon('no') . '</button>';
        } else {
            $returnme .= '<button title="Activate Program" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to make this the active program?\', \'Yes\', \'No\', function(){ $.ajax({
                type: \'POST\',
                url: \'ajax/ajax.php\',
                data: { action: \'activate_program\', pid: \'' . $pid . '\' },
                success: function(data) { $(\'#display_level\').html(data); refresh_all(); $(\'.only_when_active\').show(); }
                });}, function(){})">' . get_icon('checkmark') . '</button>';
        }

        //DELETE PROGRAM BUTTON
        $returnme .= '<button title="Delete Program" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'READ CAREFULLY!  This will delete the program and ALL enrollments and activity associated with it.  Are you sure you wish to do this?\', \'Yes\', \'No\', function(){ $.ajax({
                type: \'POST\',
                url: \'ajax/ajax.php\',
                data: { action: \'delete_program\', pid: \'' . $pid . '\' },
                success: function(data) { $(\'#display_level\').html(data); refresh_all(); $(\'.only_when_active\').show(); }
                });}, function(){})">' . get_icon('x') . '</button>';

        //NEW YEAR BUTTON
        $returnme .= '<button title="New Year" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'This will create a new program with the same settings and enrollments as the currently selected program.  Are you sure you wish to do this?\', \'Yes\', \'No\', function(){ $.ajax({
                type: \'POST\',
                url: \'ajax/ajax.php\',
                data: { action: \'copy_program\', pid: \'' . $pid . '\' },
                success: function(data) { $(\'#display_level\').html(data); refresh_all(); $(\'.only_when_active\').show(); }
                });}, function(){})">' . get_icon('refresh') . '</button>';

    } elseif ($aid) { //Account actions
        $identifier = time() . "_aid_" . $aid;
        $returnme .= '<button title="View Account" class="image_button toggle_view" style="display:none;" type="button" onclick="$(\'.toggle_view\').toggle(); $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_info\', aid: \'' . $aid . '\' },
                      success: function(data) { $(\'#info_div\').html(data); refresh_all();  }
                      });">' . get_icon('search') . '</button>';
        $returnme .= '<button title="View Reports" class="image_button toggle_view" type="button" onclick="$(\'.toggle_view\').toggle(); $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'get_reports_list\', aid: \'' . $aid . '\' },
                      success: function(data) { $(\'#info_div\').html(data); refresh_all(); }
                      });">' . get_icon('graph') . '</button>';
        if (!$recover) {
            if ($activepid) {
                //Add Child Form
                $returnme .= get_form("add_edit_child", array(
                    "aid" => $aid
                ), $identifier);
                $returnme .= '<button title="Add Child" class="image_button" type="button" onclick="CreateDialog(\'add_edit_child_' . $identifier . '\',300,400)">' . get_icon('child-add') . '</button>';
            }
            //Add Contact Form
            $returnme .= get_form("add_edit_contact", array(
                "aid" => $aid
            ), $identifier);
            $returnme .= '<button title="Add Contact" class="image_button" type="button" onclick="CreateDialog(\'add_edit_contact_' . $identifier . '\',520,400)">' . get_icon('contact-add') . '</button>';
        }

        $returnme .= get_form("add_edit_payment", array(
            "pid" => get_pid(),
            "aid" => $aid,
            "callback" => "accounts",
            "callbackinfo" => $aid
        ), $identifier);
        $returnme .= '<button title="Make Payment" class="image_button" type="button" onclick="CreateDialog(\'add_edit_payment_' . $identifier . '\',300,400)">' . get_icon('payment') . '</button>';

        $returnme .= get_form("bulletin", array(
            "aid" => $aid,
            "callback" => "accounts"
        ), $identifier);
        $activebulletin = get_db_row("SELECT * FROM notes WHERE tag='bulletin' AND pid='0' AND aid='$aid' AND notify='2'") ? 'background:orange' : '';
        $returnme .= '<button title="Bulletin" style="' . $activebulletin . '" class="image_button" type="button" onclick="CreateDialog(\'bulletin_' . $identifier . '\',360,400)">' . get_icon('bulletin') . '</button>';

        //Edit Account
        $account = get_db_row("SELECT * FROM accounts WHERE aid='$aid' AND deleted='$deleted'");
        $returnme .= get_form("add_edit_account", array(
            "account" => $account,
            "recover_param" => $recover_param
        ), $identifier);
        $returnme .= '<button title="Edit Account" class="image_button" type="button" onclick="CreateDialog(\'add_edit_account_' . $identifier . '\',200,315)">' . get_icon('config') . '</button>';

        if (!$recover) {
            $returnme .= '<button title="Delete Account" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to delete this account?\', \'Yes\', \'No\', function(){ $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'delete_account\', aid: \'' . $aid . '\' },
                            success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });}, function(){})">' . get_icon('x') . '</button>';
        } else {
            $returnme .= '<button title="Reactivate Account" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to reactivate this account?\', \'Yes\', \'No\', function(){ $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'activate_account\', aid: \'' . $aid . '\' },
                            success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });}, function(){})">' . get_icon('checkmark') . '</button>';
        }


        //Billing
        //later

    } elseif ($chid) { //Children actions
        $identifier = time() . "child_$chid";
        $child      = get_db_row("SELECT * FROM children WHERE chid='$chid'");
        $did        = get_db_field("did", "documents", "tag='avatar' AND chid='$chid'");

        $returnme .= get_form("avatar", array(
            "did" => $did,
            "chid" => $chid,
            "callback" => "get_admin_children_form",
            "param1" => "chid",
            "param1value" => $child["chid"],
            "child" => $child
        ), $identifier);
        $returnme .= '<button title="Edit Picture" class="image_button" type="button" onclick="CreateDialog(\'avatar_' . $identifier . '\',300,400)">' . get_icon('avatar') . '</button>';

        $returnme .= get_form("attach_doc", array(
            "chid" => $child["chid"],
            "callback" => "get_admin_children_form",
            "param1" => "chid",
            "param1value" => $child["chid"],
            "child" => $child
        ), $identifier);
        $returnme .= '<button title="Attach Document" class="image_button" type="button" onclick="CreateDialog(\'attach_doc_' . $identifier . '\',300,400)">' . get_icon('doc-add') . '</button>';

        $returnme .= get_form("attach_note", array(
            "nid" => "",
            "chid" => $child["chid"],
            "callback" => "children",
            "child" => $child
        ), $identifier);
        $returnme .= '<button title="Attach Note" class="image_button" type="button" onclick="CreateDialog(\'attach_note_' . $identifier . '\',360,400)">' . get_icon('note-add') . '</button>';

        $enroll_action = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to unenroll ' . $child["first"] . ' ' . $child["last"] . '?\', \'Yes\', \'No\', function(){ $.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'toggle_enrollment\',pid:\'' . $activepid . '\',chid: \'' . $child["chid"] . '\' },
                  success: function(data) {
                    $.ajax({
                        type: \'POST\',
                        url: \'ajax/ajax.php\',
                        data: { action: \'get_admin_children_form\', chid: \'\' },
                        success: function(data) {
                            $(\'#admin_display\').html(data); refresh_all();
                        }
                    });
                  }
                  });},function(){});';

        $returnme .= get_form("add_edit_enrollment", array(
            "eid" => get_db_field("eid", "enrollments", "chid='" . $child["chid"] . "' AND pid='$activepid'"),
            "refresh" => true,
            "callback" => "children",
            "aid" => $child["aid"],
            "pid" => $activepid,
            "chid" => $child["chid"]
        ), $identifier);
        $returnme .= '<button title="Edit Enrollment" class="image_button" type="button" onclick="CreateDialog(\'add_edit_enrollment_' . $identifier . '\',200,400)">' . get_icon('enroll_edit') . '</button>';

        $returnme .= '<button title="Unenroll" class="image_button" type="button" onclick="' . $enroll_action . '">' . get_icon('enroll_delete') . '</button>';

        $returnme .= '<button title="Go to account" class="image_button" type="button" onclick="$.ajax({
                        type: \'POST\',
                        url: \'ajax/ajax.php\',
                        data: { action: \'get_admin_accounts_form\', aid: \'' . $child["aid"] . '\' },
                        success: function(data) {
                            $(\'#admin_display\').html(data); refresh_all();
                            $(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not($(\'#accounts\')).toggleClass(\'selected_button\',false);
                        }
                    });">' . get_icon('back') . '</button>';

        $returnme .= get_form("add_edit_child", array(
            "aid" => $child["aid"],
            "callback" => "children",
            "child" => $child
        ), $identifier);
        $returnme .= '<button title="Edit Child" class="image_button" type="button" onclick="CreateDialog(\'add_edit_child_' . $identifier . '\',300,400)">' . get_icon('config') . '</button>';

        //Delete Child Button
        $returnme .= '<button title="Delete Child" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to delete this child?\', \'Yes\', \'No\', function(){ $.ajax({
                        type: \'POST\',
                        url: \'ajax/ajax.php\',
                        data: { action: \'delete_child\', chid: \'' . $child["chid"] . '\' },
                        success: function(data) {
                          $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'get_admin_children_form\', chid: \'\' },
                              success: function(data) {
                                  $(\'#admin_display\').html(data); refresh_all();
                              }
                          });
                        }
                    });}, function(){})">' . get_icon('x') . '</button>';

    } elseif ($cid) { // Contact Buttons
        $identifier = time() . "contact_$cid";
        $contact    = get_db_row("SELECT * FROM contacts WHERE cid='$cid'");
        $returnme .= '<button title="Go to account" class="image_button" type="button" onclick="$.ajax({
                        type: \'POST\',
                        url: \'ajax/ajax.php\',
                        data: { action: \'get_admin_accounts_form\', aid: \'' . $contact["aid"] . '\' },
                        success: function(data) {
                            $(\'#admin_display\').html(data); refresh_all();
                            $(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not($(\'#accounts\')).toggleClass(\'selected_button\',false);
                        }
                    });">' . get_icon('back') . '</button>';
        $returnme .= get_form("add_edit_contact", array(
            "callback" => "contacts",
            "contact" => $contact
        ), $identifier);
        $returnme .= '<button title="Edit Contact" class="image_button" type="button" onclick="CreateDialog(\'add_edit_contact_' . $identifier . '\',520,400)">' . get_icon('config') . '</button>';
        //Delete Contact Button
        $returnme .= '<button title="Delete Contact" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to delete this contact?\', \'Yes\', \'No\', function(){ $.ajax({
                        type: \'POST\',
                        url: \'ajax/ajax.php\',
                        data: { action: \'delete_contact\', cid: \'' . $cid . '\' },
                        success: function(data) {
                          $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'get_admin_contacts_form\', cid: \'\' },
                              success: function(data) {
                                  $(\'#admin_display\').html(data); refresh_all();
                              }
                          });
                        }
                    });}, function(){})">' . get_icon('x') . '</button>';
    } elseif ($employeeid) {
        $identifier = time() . "_employeeid_" . $employeeid;

        $employee = get_db_row("SELECT * FROM employee WHERE employeeid='$employeeid' AND deleted='$deleted'");

        //Wage History
        $returnme .= get_form("edit_employee_wage_history", array(
            "employee" => $employee,
            "recover_param" => $recover_param
        ), $identifier);
        $returnme .= '<button title="Wage History" class="image_button" type="button" onclick="CreateDialog(\'edit_employee_wage_history_' . $identifier . '\',400,500)">' . get_icon('payment') . '</button>';

        //Timecards
        $returnme .= get_form("edit_employee_timecards", array(
            "employee" => $employee,
            "recover_param" => $recover_param
        ), $identifier);
        $returnme .= '<button title="Timecards" class="image_button" type="button" onclick="CreateDialog(\'edit_employee_timecards_' . $identifier . '\',400,500)">' . get_icon('clock_big') . '</button>';

        //Edit Employee
        $returnme .= get_form("add_edit_employee", array(
            "employee" => $employee,
            "recover_param" => $recover_param
        ), $identifier);
        $returnme .= '<button title="Edit Employee" class="image_button" type="button" onclick="CreateDialog(\'add_edit_employee_' . $identifier . '\',230,315)">' . get_icon('config') . '</button>';

        if (!$recover) {
            $returnme .= '<button title="Delete Employee" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to delete this employee?\', \'Yes\', \'No\', function(){ $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'delete_employee\', employeeid: \'' . $employeeid . '\' },
                            success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });}, function(){})">' . get_icon('x') . '</button>';
        } else {
            $returnme .= '<button title="Reactivate Employee" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to reactivate this employee?\', \'Yes\', \'No\', function(){ $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'activate_employee\', employeeid: \'' . $employeeid . '\' },
                            success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });}, function(){})">' . get_icon('checkmark') . '</button>';
        }
    } elseif ($actid) {

    }

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}


function delete_wage_history() {
    global $CFG, $MYVARS;
    $id = empty($MYVARS->GET["id"]) ? false : $MYVARS->GET["id"];
    if (execute_db_sql("DELETE FROM employee_wage WHERE id='$id'")) {
        return "true";
    }
    return "false";
}

function delete_expense() {
    global $CFG, $MYVARS;
    $payid = empty($MYVARS->GET["payid"]) ? false : $MYVARS->GET["payid"];
    if (execute_db_sql("DELETE FROM billing_payments WHERE payid='$payid'")) {
        return "true";
    }
    return "false";
}

function get_billing_buttons($return = false, $pid = null, $aid = null) {
    global $CFG, $MYVARS;
    $pid        = $pid !== null ? $pid : (empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"]);
    $aid        = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $returnme   = "";
    $identifier = time() . "pid_$pid" . "_$aid";

    // Expand button.
    $returnme .= '<button title="Expand View" class="image_button" type="button" onclick="$(\'.container_actions,.container_info,.container_list\').toggleClass(\'expanded\'); refresh_all();">' . get_icon('expand') . '</button>';

    if (!empty($pid)) {
        //view invoices
        $returnme .= '<button title="Show Invoices" class="image_button" type="button" onclick="$.ajax({
            type: \'POST\',
            url: \'ajax/ajax.php\',
            data: { action: \'view_invoices\', aid: \'' . $aid . '\',pid: \'' . $pid . '\' },
            success: function(data) { $(\'#info_div\').html(data); refresh_all(); }
            });">' . get_icon('search') . '</button>';
    }

    if (empty($aid)) {
        //print tax papers
        //Activity from / to
        $returnme .= '      <form style="display:inline" id="myValidForm" method="get" action="ajax/reports.php" onsubmit="return false;">
                            <input type="hidden" name="report" id="report" value="all_tax_papers" />
                            <input type="hidden" name="id" id="id" value="' . $pid . '" />
                            <input type="hidden" name="type" id="type" value="pid" />
                            <input type="hidden" name="actid" id="actid" value="" />
                            <input type="hidden" name="extra" id="extra" value="" />
                            <input type="hidden" id="from" name="from" value="01/01/' . date("Y", strtotime("-1 year")) . '" /><input type="hidden" id="to" name="to" value="12/31/' . date("Y", strtotime("-1 year")) . '" />

            ';
        $returnme .= '
            <script>
                $(function() {
                    var dates = $( "#from, #to" ).datepicker({
                        changeMonth: true,
                        numberOfMonths: 1,
                        onSelect: function( selectedDate ) {
                            var option = this.id == "from" ? "minDate" : "maxDate",
                                instance = $( this ).data( "datepicker" ),
                                date = $.datepicker.parseDate(
                                    instance.settings.dateFormat ||
                                    $.datepicker._defaults.dateFormat,
                                    selectedDate, instance.settings );
                            dates.not( this ).datepicker( "option", option, date );
                        }
                    });
                });
            $(function() {
              var validForm = $("#myValidForm").submit(function(e) {
                  validForm.nyroModal().nmCall();
              });
            });
            </script>';

        $returnme .= '  <button title="Print Tax Papers" class="image_button" type="button" onclick="$(\'#myValidForm\').submit();">' . get_icon('print') . '</button>
                    </form>';
    }

    if ($aid || $pid) {
        $returnme .= get_form("create_invoices", array(
            "pid" => $pid,
            "aid" => $aid
        ), $identifier);
        $returnme .= '<button title="Calculate Invoices" class="image_button" type="button" onclick="CreateDialog(\'create_invoices_' . $identifier . '\',200,550)">' . get_icon('calculator') . '</button>';
    }

    if ($aid && $pid) {
        //print tax papers
        //Activity from / to
        $returnme .= '      <form style="display:inline" id="myValidForm" method="get" action="ajax/reports.php" onsubmit="return false;">
                            <input type="hidden" name="report" id="report" value="all_tax_papers" />
                            <input type="hidden" name="id" id="id" value="' . $aid . '" />
                            <input type="hidden" name="type" id="type" value="aid" />
                            <input type="hidden" name="actid" id="actid" value="" />
                            <input type="hidden" name="extra" id="extra" value="" />
                            <input type="hidden" id="from" name="from" value="01/01/' . date("Y", strtotime("-1 year")) . '" /><input type="hidden" id="to" name="to" value="12/31/' . date("Y", strtotime("-1 year")) . '" />

            ';
        $returnme .= '
            <script>
                $(function() {
                    var dates = $( "#from, #to" ).datepicker({
                        changeMonth: true,
                        numberOfMonths: 1,
                        onSelect: function( selectedDate ) {
                            var option = this.id == "from" ? "minDate" : "maxDate",
                                instance = $( this ).data( "datepicker" ),
                                date = $.datepicker.parseDate(
                                    instance.settings.dateFormat ||
                                    $.datepicker._defaults.dateFormat,
                                    selectedDate, instance.settings );
                            dates.not( this ).datepicker( "option", option, date );
                        }
                    });
                });
            $(function() {
              var validForm = $("#myValidForm").submit(function(e) {
                  validForm.nyroModal().nmCall();
              });
            });
            </script>';

        $returnme .= '  <button title="Print Tax Papers" class="image_button" type="button" onclick="$(\'#myValidForm\').submit();">' . get_icon('print') . '</button>
                    </form>';

        $override = get_db_row("SELECT * FROM billing_override WHERE pid='$pid' AND aid='$aid'");
        $returnme .= get_form("billing_overrides", array(
            "oid" => get_db_field("oid", "billing_override", "pid='$pid' AND aid='$aid'"),
            "pid" => $pid,
            "aid" => $aid,
            "callback" => "billing",
            "override" => $override
        ), $identifier);
        $returnme .= '<button title="Billing Overrides" class="image_button" type="button" onclick="CreateDialog(\'billing_overrides_' . $identifier . '\',400,400)">' . get_icon('config') . '</button>';
    }

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function ajax_refresh_all_invoices($return = false, $pid = null, $aid = null, $startweek = null) {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "aid":
                $aid = dbescape($field["value"]);
                break;
            case "pid":
                $pid = dbescape($field["value"]);
                break;
            case "startweek":
                $startweek = dbescape($field["value"]);
                break;
            case "refresh":
                $refresh = dbescape($field["value"]);
                break;
            case "enrollment":
                $enrollment = dbescape($field["value"]);
                break;
            case "callback":
                $callback = dbescape($field["value"]);
                break;
                break;
        }
    }
    $pid        = !empty($pid) ? $pid : (empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"]);
    $aid        = !empty($aid) ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $startweek  = !empty($startweek) ? $startweek : (empty($MYVARS->GET["startweek"]) ? "0" : $MYVARS->GET["startweek"]);
    $refresh    = !empty($refresh) ? true : (empty($MYVARS->GET["refresh"]) ? false : true);
    $enrollment = !empty($enrollment) ? true : (empty($MYVARS->GET["enrollment"]) ? false : true);

    create_invoices($return, $pid, $aid, $refresh, $startweek, $enrollment);
}

function view_invoices($return = false, $pid = null, $aid = null, $print = false) {
    global $CFG, $MYVARS;
    $pid        = $pid !== null ? $pid : (empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"]);
    $aid        = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $returnme   = "";
    $total_paid = 0;
    $returnme .= '<div class="scroll-pane fill_height"><div style="display:table-cell;font-weight: bold;font-size: 110%;padding: 10px; 5px;">Invoices:</div>';
    if (empty($aid)) { //All accounts enrolled in program
        $SQL = "SELECT * FROM accounts WHERE deleted = '0' AND admin= '0' AND aid IN (SELECT aid FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE pid='$pid')) ORDER BY name";
    } else { //Only selected account
        $SQL = "SELECT * FROM accounts WHERE aid='$aid'";
    }

    if ($accounts = get_db_result($SQL)) {
        while ($account = fetch_row($accounts)) {
            $total_paid     = $total_billed = $total_fee = 0;
            $identifier     = time() . "accountpayment_" . $account["aid"];
            $payment_button = get_form("add_edit_payment", array(
                "pid" => $pid,
                "aid" => $account["aid"],
                "callback" => "billing",
                "callbackinfo" => $aid
            ), $identifier);
            $payment_button .= '<button style="font-size: 9px;" type="button" onclick="CreateDialog(\'add_edit_payment_' . $identifier . '\',300,400)">Add Payment/Fee</button>';
            $print_button = '<a style="font-size: 9px;" href="ajax/reports.php?report=invoice&pid=' . $pid . '&aid=' . $account["aid"] . '" class="nyroModal"><span class="inline-button ui-corner-all" style="padding: 0px 7px 2px 4px;">' . get_icon('magnifier') . ' Print Invoice</span></a>';
            $returnme .= '<div class="document_list_item ui-corner-all"><strong>Account: ' . $account["name"] . '</strong><div style="padding: 6px;">' . $print_button . " " . $payment_button . '</div>';

            //Fees
            $SQL = "SELECT * FROM billing_payments WHERE pid='$pid' AND aid='" . $account["aid"] . "' AND payment < 0 ORDER BY timelog,payid";
            if ($payments = get_db_result($SQL)) {
                $total_fee = abs(get_db_field("SUM(payment)", "billing_payments", "pid='$pid' AND aid='" . $account["aid"] . "' AND payment < 0"));
                $total_fee = empty($total_fee) ? "0.00" : $total_fee;
                $returnme .= '<div class="ui-corner-all list_box" style="background-color:darkRed;padding: 5px;color: white;">
                                    <div class="flexsection"><a href="javascript: void(0)" style="color: white;"><table style="width:100%;color: inherit;font: inherit;"><tr><td>Fees $' . number_format($total_fee, 2) . '</td></tr></table></a></div>
                                    <div class="ui-corner-all" style="padding: 5px;color: black;background-color:lightgray">';
                while ($payment = fetch_row($payments)) {
                    $identifier          = time() . "accountpaymentpayid_" . $payment["payid"];
                    $edit_payment_button = get_form("add_edit_payment", array(
                        "payment" => $payment,
                        "pid" => $pid,
                        "aid" => $account["aid"],
                        "callback" => "billing",
                        "callbackinfo" => $aid
                    ), $identifier);
                    $edit_payment_button .= '<button style="font-size: 9px;" type="button" onclick="CreateDialog(\'add_edit_payment_' . $identifier . '\',300,400)">Edit</button>';
                    $delete_payment = '<button style="font-size: 9px;" type="button" onclick="CreateConfirm(\'dialog-confirm\',\'Are you sure you want to delete this payment?\', \'Yes\', \'No\', function(){ $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'delete_payment\', payid: \'' . $payment["payid"] . '\' },
                          success: function(data) {
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'get_admin_billing_form\', pid: \'' . $pid . '\', aid: \'' . $aid . '\' },
                              success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); });  }
                              });
                          }
                          });},function(){});">Delete</button>';

                    $paytext = $payment["payment"] >= 0 ? "Payment of " : "Fee of ";
                    $note = empty($payment["note"]) ? "" : '<tr><td><em>Note: ' . $payment["note"] . '</em></td></tr>';
                    $returnme .= '<div>
                                     <table style="width:100%;color: inherit;font: inherit;">
                                         <tr>
                                             <td style="width: 40px;">' . $edit_payment_button . '</td>
                                             <td>
                                                 <table style="width: 100%;font-size: 11px;background-color: rgba(255,255,255,.4);border: 1px solid silver;">
                                                     <tr>
                                                         <td style="font-weight: bold;">' . $paytext . ' $' . number_format(abs($payment["payment"]), 2) . ' was added on ' . date('m/d/Y', display_time($payment["timelog"])) . '</td>
                                                     </tr>
                                                     '.$note.'
                                                 </table>
                                             </td>
                                             <td style="width: 50px;">' . $delete_payment . '</td>
                                         </tr>
                                     </table>
                                   </div>';
                }
                $returnme .= '</div></div>';
            }

            $SQL = "SELECT * FROM billing_payments WHERE pid='$pid' AND aid='" . $account["aid"] . "' AND payment >= 0 ORDER BY timelog,payid";
            if ($payments = get_db_result($SQL)) {
                $total_paid = get_db_field("SUM(payment)", "billing_payments", "pid='$pid' AND aid='" . $account["aid"] . "' AND payment >= 0");
                $total_paid = empty($total_paid) ? "0.00" : $total_paid;
                $returnme .= '<div class="ui-corner-all list_box" style="background-color:darkCyan;padding: 5px;color: white;">
                                    <div class="flexsection"><a href="javascript: void(0)" style="color: white;"><table style="width:100%;color: inherit;font: inherit;"><tr><td>Payments $' . number_format($total_paid, 2) . '</td></tr></table></a></div>
                                    <div class="ui-corner-all" style="padding: 5px;color: black;background-color:lightgray">';
                while ($payment = fetch_row($payments)) {
                    $identifier          = time() . "accountpaymentpayid_" . $payment["payid"];
                    $edit_payment_button = get_form("add_edit_payment", array(
                        "payment" => $payment,
                        "pid" => $pid,
                        "aid" => $account["aid"],
                        "callback" => "billing",
                        "callbackinfo" => $aid
                    ), $identifier);
                    $edit_payment_button .= '<button style="font-size: 9px;" type="button" onclick="CreateDialog(\'add_edit_payment_' . $identifier . '\',300,400)">Edit</button>';
                    $delete_payment = '<button style="font-size: 9px;" type="button" onclick="CreateConfirm(\'dialog-confirm\',\'Are you sure you want to delete this payment?\', \'Yes\', \'No\', function(){ $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'delete_payment\', payid: \'' . $payment["payid"] . '\' },
                          success: function(data) {
                            $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'get_admin_billing_form\', pid: \'' . $pid . '\', aid: \'' . $aid . '\' },
                              success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); });  }
                              });
                          }
                          });},function(){});">Delete</button>';

                    $paytext = $payment["payment"] >= 0 ? "Payment of " : "Fee of ";
                    $note = empty($payment["note"]) ? "" : '<tr><td><em>Note: ' . $payment["note"] . '</em></td></tr>';
                    $returnme .= '<div>
                                    <table style="width:100%;color: inherit;font: inherit;">
                                        <tr>
                                            <td style="width: 40px;">' . $edit_payment_button . '</td>
                                            <td>
                                                <table style="width: 100%;font-size: 11px;background-color: rgba(255,255,255,.4);border: 1px solid silver;">
                                                    <tr>
                                                        <td style="font-weight: bold;">' . $paytext . ' $' . number_format($payment["payment"], 2) . ' was added on ' . date('m/d/Y', display_time($payment["timelog"])) . '</td>
                                                    </tr>
                                                    '.$note.'
                                                </table>
                                            </td>
                                            <td style="width: 50px;">' . $delete_payment . '</td>
                                        </tr>
                                    </table>
                                  </div>';
                }
                $returnme .= '</div></div>';
            }
            $SQL = "SELECT * FROM billing WHERE pid='$pid' AND aid='" . $account["aid"] . "' ORDER BY fromdate";

            if ($invoices = get_db_result($SQL)) {
                while ($invoice = fetch_row($invoices)) {
                    $returnme .= '<div class="ui-corner-all list_box" style="padding: 5px;color: white;">
                                    <div class="flexsection"><a href="javascript: void(0)" style="color: white;"><table style="width:100%;color: inherit;font: inherit;"><tr><td style="width:50%">Week of ' . date('F \t\h\e jS, Y', $invoice["fromdate"]) . '</td><td style="width:50%;text-align:right"><strong>Bill: </strong>$' . number_format($invoice["owed"], 2) . '</td></tr></table></a></div>
                                    <div class="ui-corner-all" style="padding: 5px;color: black;background-color:lightgray">';
                    $SQL = "SELECT * FROM billing_perchild WHERE chid IN (SELECT chid FROM children WHERE aid='" . $account["aid"] . "') AND pid='$pid' AND fromdate = '" . $invoice["fromdate"] . "' ORDER BY id";

                    if ($perchild_invoices = get_db_result($SQL)) {
                        while ($perchild_invoice = fetch_row($perchild_invoices)) {
                            $exempt_title  = empty($perchild_invoice["exempt"]) ? "Exempt" : "Undo";
                            $exempt_show   = empty($perchild_invoice["exempt"]) || strstr($perchild_invoice["receipt"], "[Exempt]") ? "" : " - <span style='color:blue;font-weight:bold;'>Exempt $0</span>";
                            $exempt        = '<button style="font-size: 9px;" type="button" onclick="$.ajax({
                                  type: \'POST\',
                                  url: \'ajax/ajax.php\',
                                  data: { action: \'toggle_exemption\', id: \'' . $perchild_invoice["id"] . '\' },
                                  success: function(data) {
                                    $.ajax({
                                      type: \'POST\',
                                      url: \'ajax/ajax.php\',
                                      data: { action: \'get_admin_billing_form\', pid: \'' . $pid . '\', aid: \'' . $aid . '\' },
                                      success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); });  }
                                      });
                                  }
                                  });">' . $exempt_title . '</button>';
                            $exempt_button = !strstr($perchild_invoice["receipt"], "[Exempt]") ? "<span style='float:right;white-space: normal;'>$exempt</span>" : "";
                            $returnme .= '<div style="margin:5px">' . $perchild_invoice["receipt"] . "$exempt_show $exempt_button </div>";
                        }
                    }

                    $returnme .= '</div></div>';
                }

                $total_billed = get_db_field("SUM(owed)", "billing", "pid='$pid' AND aid='" . $account["aid"] . "'");
                $total_billed += $total_fee;
                $total_billed = empty($total_billed) ? "0.00" : $total_billed;

                // Add current week charges.
                if ($current_week = week_balance($pid, $account["aid"], true)) {
                    $returnme .= '<div class="ui-corner-all list_box" style="padding: 5px;color: white;">
                                    <div><a style="color: white;"><table style="width:100%;color: inherit;font: inherit;"><tr><td style="width:50%">Current Week</td><td style="width:50%;text-align:right"><strong>Bill: </strong>$' . number_format($current_week, 2) . '</td></tr></table></a></div>
                                  </div>';
                    $total_billed += (float) $current_week;
                }

                $balance       = $total_billed - $total_paid;
                $returnme .= "<div style='text-align:right;color:darkred;'><strong>Owed:</strong> $" . number_format($total_billed, 2) . "</div><div style='text-align:right;color:blue;'><strong>Paid:</strong> $" . number_format($total_paid, 2) . "</div><hr align='right' style='width:100px;'/><div style='text-align:right'><strong>Balance:</strong> $" . number_format($balance, 2) . "</div>";
            } else {
                // Add current week charges.
                if ($current_week = week_balance($pid, $account["aid"], true)) {
                    $returnme .= '<div class="ui-corner-all list_box" style="padding: 5px;color: white;">
                                    <div><a style="color: white;"><table style="width:100%;color: inherit;font: inherit;"><tr><td style="width:50%">Current Week</td><td style="width:50%;text-align:right"><strong>Bill: </strong>$' . number_format($current_week, 2) . '</td></tr></table></a></div>
                                  </div>';
                    $total_billed += (float) $current_week;

                    $balance       = $total_billed - $total_paid;
                    $returnme .= "<div style='text-align:right;color:darkred;'><strong>Owed:</strong> $" . number_format($total_billed, 2) . "</div><div style='text-align:right;color:blue;'><strong>Paid:</strong> $" . number_format($total_paid, 2) . "</div><hr align='right' style='width:100px;'/><div style='text-align:right'><strong>Balance:</strong> $" . number_format($balance, 2) . "</div>";
                } else {
                    $returnme .= "<div style='text-align:center'>No Invoices</div>";
                }
            }
            $returnme .= "</div>";
        }
    } else {
        $returnme .= "<div>No Accounts</div>";
    }
    $returnme .= '</div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_info($return = false, $pid = null, $aid = null, $chid = null, $cid = null, $actid = null, $recover = null, $employeeid = null) {
    global $CFG, $MYVARS;
    $activepid  = get_pid();
    $pid        = $pid !== null ? $pid : (empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"]);
    $aid        = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $chid       = $chid !== null ? $chid : (empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"]);
    $cid        = $cid !== null ? $cid : (empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"]);
    $actid      = $actid !== null ? $actid : (empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"]);
    $recover    = $recover ? $recover : (empty($MYVARS->GET["recover"]) ? false : $MYVARS->GET["recover"]);
    $employeeid = $employeeid !== null ? $employeeid : (empty($MYVARS->GET["employeeid"]) ? false : $MYVARS->GET["employeeid"]);

    $returnme = "";

    if ($pid) { //Program enrollment
        //Program Children
        if ($children = get_db_result("SELECT * FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE pid='$pid') AND deleted=0 ORDER BY last,first")) {
            $returnme .= '<div style="display:table-cell;font-weight: bold;font-size: 110%;padding-left: 10px;">Children:</div><div id="children" class="scroll-pane infobox fill_height">';
            while ($child = fetch_row($children)) {
                $identifier = time() . "child_" . $child["chid"];
                $enrolled   = is_enrolled($pid, $child["chid"]);

                $action             = '$.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_admin_children_form\', chid: \'' . $child["chid"] . '\' },
                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                          });';
                $enroll_action      = $enrolled ? 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to unenroll \'+$(\'a#a-' . $child["chid"] . '\').attr(\'data\')+\'?\', \'Yes\', \'No\', function(){ $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'toggle_enrollment\',pid:\'' . $pid . '\',chid: \'' . $child["chid"] . '\' },
                          success: function(data) {
                            $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'get_info\', pid: \'' . $pid . '\' },
                                success: function(data) {
                                    $(\'#info_div\').html(data);
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'get_action_buttons\', pid: \'' . $pid . '\' },
                                        success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                    });
                                }
                            });
                          }
                          });},function(){});' : 'CreateDialog(\'add_edit_enrollment_' . $identifier . '\',200,400)';
                $edit_enroll_action = $enrolled ? 'CreateDialog(\'add_edit_enrollment_' . $identifier . '\',200,400)' : '';

                //Checked In info
                $checked_in = ($activepid == $pid) && $enrolled && is_checked_in($child["chid"]) ? get_icon('status_online') : (($activepid == $pid) && $enrolled ? get_icon('status_offline') : "");

                //More Info Button
                $moreinfo = ($activepid == $pid) && $enrolled ? ' <a style="padding-left: 5px;" href="javascript: void(0);" onclick="' . $action . ' $(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not($(\'#admin_menu_children\')).toggleClass(\'selected_button\',false);"><span class="inline-button ui-corner-all">' . get_icon('magnifier_zoom_in') . '</span></a>' : '';

                //Enrollment Button
                $enroll_button = $enrolled ? ' <a href="javascript: void(0);" onclick="' . $edit_enroll_action . '"><span class="inline-button ui-corner-all">' . get_icon('report_edit') . ' Edit Enrollment</span></a> <a id="a-' . $child["chid"] . '" data="' . $child["first"] . ' ' . $child["last"] . '" href="javascript: void(0);" onclick="' . $enroll_action . '"><span class="caution inline-button ui-corner-all">' . get_icon('report_delete') . ' Unenroll</span></a>' : ' <a id="a-' . $child["chid"] . '" data="' . $child["first"] . ' ' . $child["last"] . '" href="javascript: void(0);" onclick="' . $enroll_action . '"><span class="inline-button ui-corner-all">' . get_icon('user_add') . ' Enroll</span></a>';
                $returnme .= $enrolled ? get_form("add_edit_enrollment", array(
                    "eid" => "$enrolled",
                    "callback" => "programs",
                    "aid" => $child["aid"],
                    "pid" => $pid,
                    "chid" => $child["chid"]
                ), $identifier) : get_form("add_edit_enrollment", array(
                    "callback" => "accounts",
                    "aid" => $aid,
                    "pid" => $pid,
                    "chid" => $child["chid"]
                ), $identifier);

                //Edit Child Button
                $returnme .= get_form("add_edit_child", array(
                    "pid" => $pid,
                    "callback" => "programs",
                    "child" => $child
                ), $identifier);
                $edit_button = ' <a href="javascript: void(0);" onclick="CreateDialog(\'add_edit_child_' . $identifier . '\',300,400)"><span class="inline-button ui-corner-all">' . get_icon('wrench') . ' Edit</span></a>';

                $notifications = get_notifications($pid, $child["chid"], false, true) ? 'style="background: darkred;"' : '';
                $returnme .= '<div class="ui-corner-all list_box" ' . $notifications . '><div class="list_box_item_full">' . get_children_button($child["chid"], "", "top: 5px;float:none;height:50px;width:50px;", false, true, false) . '<div class="list_title" style="width:98%;">' . $checked_in . ' ' . $child["first"] . ' ' . $child["last"];
                $returnme .= $recover ? '</div>' : '<br /><span class="list_links">' . $moreinfo . $edit_button . $enroll_button . '</span></div>';
                $returnme .= '</div></div>';
            }
            $returnme .= '</div><div style="clear:both;"></div>';
        }
    } elseif ($aid) { //Account info
        $deleted = $recover ? "1" : "0";
        //Account Children
        if ($children = get_db_result("SELECT * FROM children WHERE aid='$aid' AND deleted='$deleted' ORDER BY last,first")) {
            $returnme .= '<div style="display:table-cell;font-weight: bold;font-size: 110%;padding-left: 10px;">Children:</div><div id="children" class="scroll-pane infobox">';
            while ($child = fetch_row($children)) {
                $identifier = time() . "child_" . $child["chid"];
                $enrolled   = is_enrolled($activepid, $child["chid"]);

                $action             = '$.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_admin_children_form\', chid: \'' . $child["chid"] . '\' },
                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                          });';
                $enroll_action      = $enrolled ? 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to unenroll \'+$(\'a#a-' . $child["chid"] . '\').attr(\'data\')+\'?\', \'Yes\', \'No\', function(){ $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'toggle_enrollment\',pid:\'' . $activepid . '\',chid: \'' . $child["chid"] . '\' },
                          success: function(data) {
                            $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'get_info\', aid: \'' . $child["aid"] . '\' },
                                success: function(data) {
                                    $(\'#info_div\').html(data);
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'get_action_buttons\', aid: \'' . $child["aid"] . '\' },
                                        success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                    });
                                }
                            });
                          }
                          });},function(){});' : 'CreateDialog(\'add_edit_enrollment_' . $identifier . '\',200,400)';
                $edit_enroll_action = $enrolled ? 'CreateDialog(\'add_edit_enrollment_' . $identifier . '\',200,400)' : '';
                $recover_text       = $recover ? "activate" : "delete";
                $delete_action      = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to ' . $recover_text . ' \'+$(\'a#a-' . $child["chid"] . '\').attr(\'data\')+\'?\', \'Yes\', \'No\', function(){ $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'delete_child\', chid: \'' . $child["chid"] . '\' },
                          success: function(data) {
                            $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'get_admin_accounts_form\', aid: \'' . $child["aid"] . '\' },
                                success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });
                          }
                          });},function(){});';
                //Checked In info
                $checked_in         = $activepid && $enrolled && is_checked_in($child["chid"]) ? get_icon('status_online') : ($activepid && $enrolled && empty($recover) ? get_icon('status_offline') : "");

                //More Info Button
                $moreinfo = $activepid && $enrolled ? ' <a style="padding-left:5px;" href="javascript: void(0);" onclick="' . $action . ' $(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not($(\'#admin_menu_children\')).toggleClass(\'selected_button\',false);"><span class="inline-button ui-corner-all">' . get_icon('magnifier_zoom_in') . '</span></a>' : '';

                //Enrollment Button
                $enroll_button = $activepid && $enrolled ? ' <a href="javascript: void(0);" onclick="' . $edit_enroll_action . '"><span class="inline-button ui-corner-all">' . get_icon('report_edit') . ' Edit Enrollment</span></a> <a id="a-' . $child["chid"] . '" data="' . $child["first"] . ' ' . $child["last"] . '" href="javascript: void(0);" onclick="' . $enroll_action . '"><span class="caution inline-button ui-corner-all">' . get_icon('report_delete') . ' Unenroll</span></a>' : ($activepid ? ' <a id="a-' . $child["chid"] . '" data="' . $child["first"] . ' ' . $child["last"] . '" href="javascript: void(0);" onclick="' . $enroll_action . '"><span class="inline-button ui-corner-all">' . get_icon('user_add') . ' Enroll</span></a>' : '');
                $returnme .= $enrolled ? get_form("add_edit_enrollment", array(
                    "eid" => "$enrolled",
                    "callback" => "accounts",
                    "aid" => $aid,
                    "pid" => $activepid,
                    "chid" => $child["chid"]
                ), $identifier) : get_form("add_edit_enrollment", array(
                    "callback" => "accounts",
                    "aid" => $aid,
                    "pid" => $activepid,
                    "chid" => $child["chid"]
                ), $identifier);

                //Delete Child Button
                if ($recover) {
                    $delete_button = $activepid ? ' <a id="a-' . $child["chid"] . '" data="' . $child["first"] . ' ' . $child["last"] . '" href="javascript: void(0);" onclick="' . $delete_action . '"><span class="inline-button ui-corner-all">' . get_icon('add') . ' Activate</span></a>' : "";
                } else {
                    $delete_button = $activepid ? ' <a id="a-' . $child["chid"] . '" data="' . $child["first"] . ' ' . $child["last"] . '" href="javascript: void(0);" onclick="' . $delete_action . '"><span class="caution inline-button ui-corner-all">' . get_icon('bin_closed') . ' Delete</span></a>' : "";
                }

                //Edit Child Button
                $returnme .= get_form("add_edit_child", array(
                    "aid" => $aid,
                    "child" => $child
                ), $identifier);
                $edit_button = ' <a href="javascript: void(0);" onclick="CreateDialog(\'add_edit_child_' . $identifier . '\',300,400)"><span class="inline-button ui-corner-all">' . get_icon('wrench') . ' Edit</span></a>';

                $notifications = get_notifications($activepid, $child["chid"], $aid, true) ? 'style="background: darkred;"' : '';
                $returnme .= '<div class="ui-corner-all list_box" ' . $notifications . '><div class="list_box_item_full">' . get_children_button($child["chid"], "", "top: 5px;float:none;height:50px;width:50px;", false, true, false) . '<div class="list_title" style="width:98%;">' . $checked_in . ' ' . $child["first"] . ' ' . $child["last"];
                $returnme .= $recover ? '<br /><span class="list_links">' . $delete_button . '</span></div>' : '<br /><span class="list_links">' . $moreinfo . $edit_button . $enroll_button . $delete_button . '</span></div>';
                $returnme .= '</div></div>';
            }
            $returnme .= '</div><div style="clear:both;"></div>';
        }

        //Account Contacts
        if ($contacts = get_db_result("SELECT * FROM contacts WHERE aid='$aid' AND deleted='$deleted' ORDER BY emergency,last,first")) {
            $returnme .= '<div style="display:table-cell;font-weight: bold;font-size: 110%;padding-left: 10px;">Contacts:</div><div id="contacts" class="scroll-pane infobox fill_height">';
            while ($contact = fetch_row($contacts)) {
                $action        = '$.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_admin_contacts_form\', cid: \'' . $contact["cid"] . '\' },
                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                          });';
                $recover_text  = $recover ? "activate" : "delete";
                $delete_action = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to ' . $recover_text . ' \'+$(\'a#a-' . $contact["cid"] . '\').attr(\'data\')+\'?\', \'Yes\', \'No\', function(){ $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'delete_contact\', cid: \'' . $contact["cid"] . '\' },
                          success: function(data) {
                            $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'get_admin_accounts_form\', aid: \'' . $contact["aid"] . '\' },
                                success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                            });
                          }
                          });},function(){});';

                //Delete Contact Button
                if ($recover) {
                    $delete_button = $activepid ? ' <a id="a-' . $contact["cid"] . '" data="' . $contact["first"] . ' ' . $contact["last"] . '" href="javascript: void(0);" onclick="' . $delete_action . '"><span class="inline-button ui-corner-all">' . get_icon('add') . ' Activate</span></a>' : "";
                } else {
                    $delete_button = $activepid ? ' <a id="a-' . $contact["cid"] . '" data="' . $contact["first"] . ' ' . $contact["last"] . '" href="javascript: void(0);" onclick="' . $delete_action . '"><span class="caution inline-button ui-corner-all">' . get_icon('bin_closed') . ' Delete</span></a>' : "";
                }

                $primary   = empty($contact["primary_address"]) ? "" : "primary";
                $emergency = empty($contact["emergency"]) ? "" : "emergency";

                //Edit Contact Form
                $identifier = time() . "contact_" . $contact["cid"];
                $returnme .= get_form("add_edit_contact", array(
                    "contact" => $contact
                ), $identifier);
                $returnme .= '<div class="ui-corner-all list_box ' . $primary . ' ' . $emergency . '"><div class="list_title">' . $contact["first"] . ' ' . $contact["last"];
                $moreinfo = '<a style="padding-left: 5px;" href="javascript: void(0);" onclick="' . $action . ' $(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not($(\'#admin_menu_contacts\')).toggleClass(\'selected_button\',false);"><span class="inline-button ui-corner-all">' . get_icon('magnifier_zoom_in') . '</span></a>';
                $returnme .= $recover ? '<br /><span class="list_links">' . $delete_button . '</span></div>' : '<br /><span class="list_links">' . $moreinfo . ' <a href="javascript: void(0);" onclick="CreateDialog(\'add_edit_contact_' . $identifier . '\',520,400)"><span class="inline-button ui-corner-all">' . get_icon('wrench') . ' Edit</span></a> ' . $delete_button . '</span></div>';
                $returnme .= '</div><div style="clear:both;"></div>';
            }
            $returnme .= '</div><div style="clear:both;"></div>';
        }

        //Billing
        //later

    } elseif ($chid) { //Children
        $returnme .= '<div style="text-align:center;">' . get_children_button($chid, "", "width:100px;height:100px;", "", true) . '</div>';
        $docs_selected = $notes_selected = $activity_selected = $reports_selected = "";
        $tabkey        = empty($MYVARS->GET["values"]) ? false : array_search('tab', $MYVARS->GET["values"]);
        $tab           = $tabkey === false && empty($MYVARS->GET["values"][$tabkey]["value"]) ? (empty($MYVARS->GET["tab"]) ? '' : $MYVARS->GET["tab"]) : $MYVARS->GET["values"][$tabkey]["value"];
        if (!empty($tab)) {
            if ($tab == "documents") {
                $info          = get_documents_list(true, false, $chid);
                $docs_selected = "selected_button";
            } elseif ($tab == "notes") {
                $info           = get_notes_list(true, false, $chid);
                $notes_selected = "selected_button";
            } elseif ($tab == "activity") {
                $info              = get_activity_list(true, false, $chid);
                $activity_selected = "selected_button";
            } elseif ($tab == "reports") {
                $info             = get_reports_list(true, false, false, $chid);
                $reports_selected = "selected_button";
            } else {
                $info          = get_documents_list(true, false, $chid);
                $docs_selected = "selected_button";
            }
        } else {
            $info          = get_documents_list(true, false, $chid);
            $docs_selected = "selected_button";
        }

        $returnme .= '<div class="info_tabbar">
                        <button class="subselect_buttons ' . $docs_selected . '" id="documents" onclick="$(\'.subselect_buttons\').toggleClass(\'selected_button\',true); $(\'.subselect_buttons\').not(this).toggleClass(\'selected_button\',false);
                          $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_documents_list\', chid: \'' . $chid . '\' },
                          success: function(data) { $(\'#subselect_div\').html(data); refresh_all(); }
                          });">Documents</button>
                        <button class="subselect_buttons ' . $notes_selected . '" id="notes" onclick="$(\'.subselect_buttons\').toggleClass(\'selected_button\',true); $(\'.subselect_buttons\').not(this).toggleClass(\'selected_button\',false);
                          $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_notes_list\', chid: \'' . $chid . '\' },
                          success: function(data) { $(\'#subselect_div\').html(data); refresh_all(); }
                          });">Notes</button>
                        <button class="subselect_buttons ' . $activity_selected . '" id="activity" onclick="$(\'.subselect_buttons\').toggleClass(\'selected_button\',true); $(\'.subselect_buttons\').not(this).toggleClass(\'selected_button\',false);
                          $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_activity_list\', chid: \'' . $chid . '\' },
                          success: function(data) { $(\'#subselect_div\').html(data); refresh_all(); }
                          });">Activity</button>
                        <button class="subselect_buttons ' . $reports_selected . '" id="reports" onclick="$(\'.subselect_buttons\').toggleClass(\'selected_button\',true); $(\'.subselect_buttons\').not(this).toggleClass(\'selected_button\',false);
                          $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_reports_list\', chid: \'' . $chid . '\' },
                          success: function(data) { $(\'#subselect_div\').html(data); refresh_all(); }
                          });">Reports</button>
                      </div>';

        $returnme .= '<div id="subselect_div" style="width: 100%;" class="scroll-pane infobox fill_height">' . $info . '</div>';
    } elseif ($cid) { //Contacts
        $docs_selected = $notes_selected = $activity_selected = $reports_selected = "";
        $tabkey        = empty($MYVARS->GET["values"]) ? false : array_search('tab', $MYVARS->GET["values"]);
        $tab           = $tabkey === false && empty($MYVARS->GET["values"][$tabkey]["value"]) ? (empty($MYVARS->GET["tab"]) ? '' : $MYVARS->GET["tab"]) : $MYVARS->GET["values"][$tabkey]["value"];
        if (!empty($tab)) {
            if ($tab == "activity") {
                $info              = get_activity_list(true, false, false, $cid);
                $activity_selected = "selected_button";
            } elseif ($tab == "reports") {
                $info             = get_reports_list(true, false, false, false, $cid);
                $reports_selected = "selected_button";
            } else {
                $info              = get_activity_list(true, false, false, $cid);
                $activity_selected = "selected_button";
            }
        } else {
            $info              = get_activity_list(true, false, false, $cid);
            $activity_selected = "selected_button";
        }

        $returnme .= '<div style="text-align:center;white-space: nowrap;">
                          <button class="subselect_buttons ' . $activity_selected . '" id="activity" onclick="$(\'.subselect_buttons\').toggleClass(\'selected_button\',true); $(\'.subselect_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_activity_list\', cid: \'' . $cid . '\' },
                          success: function(data) { $(\'#subselect_div\').html(data); refresh_all(); }
                          });">Activity</button>
                          <button class="subselect_buttons ' . $reports_selected . '" id="reports" onclick="$(\'.subselect_buttons\').toggleClass(\'selected_button\',true); $(\'.subselect_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_reports_list\', cid: \'' . $cid . '\' },
                          success: function(data) { $(\'#subselect_div\').html(data); refresh_all(); }
                          });">Reports</button>
                      </div>';

        $returnme .= '<div id="subselect_div" style="" class="scroll-pane infobox fill_height">' . $info . '</div>';
    } elseif ($employeeid) { //Employees
        $activity_selected = $reports_selected = "";
        $tabkey            = empty($MYVARS->GET["values"]) ? false : array_search('tab', $MYVARS->GET["values"]);
        $tab               = $tabkey === false && empty($MYVARS->GET["values"][$tabkey]["value"]) ? (empty($MYVARS->GET["tab"]) ? '' : $MYVARS->GET["tab"]) : $MYVARS->GET["values"][$tabkey]["value"];
        if (!empty($tab)) {
            if ($tab == "activity") {
                $info              = get_activity_list(true, false, false, false, false, $employeeid);
                $activity_selected = "selected_button";
            } elseif ($tab == "reports") {
                $info             = get_reports_list(true, false, false, false, false, $employeeid);
                $reports_selected = "selected_button";
            } else {
                $info              = get_activity_list(true, false, false, false, false, $employeeid);
                $activity_selected = "selected_button";
            }
        } else {
            $info              = get_activity_list(true, false, false, false, false, $employeeid);
            $activity_selected = "selected_button";
        }

        $returnme .= '<div style="text-align:center;white-space: nowrap;">
                        <button class="subselect_buttons ' . $activity_selected . '" id="activity" onclick="$(\'.subselect_buttons\').toggleClass(\'selected_button\',true); $(\'.subselect_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_activity_list\', employeeid: \'' . $employeeid . '\' },
                          success: function(data) { $(\'#subselect_div\').html(data); refresh_all(); }
                          });">Activity</button>
                          <button class="subselect_buttons ' . $reports_selected . '" id="reports" onclick="$(\'.subselect_buttons\').toggleClass(\'selected_button\',true); $(\'.subselect_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_reports_list\', employeeid: \'' . $employeeid . '\' },
                          success: function(data) { $(\'#subselect_div\').html(data); refresh_all(); }
                          });">Reports</button>
                      </div>';

        $returnme .= '<div id="subselect_div" style="" class="scroll-pane infobox fill_height">' . $info . '</div>';
    }

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function delete_tag() {
    global $CFG, $MYVARS;
    $tagtype = empty($MYVARS->GET["tagtype"]) ? false : $MYVARS->GET["tagtype"];
    $tag     = empty($MYVARS->GET["tag"]) ? false : $MYVARS->GET["tag"];

    if (!empty($tagtype) && !empty($tag)) {
        execute_db_sql("DELETE FROM $tagtype" . "_tags WHERE tag='$tag'");
    }
}

function delete_payment() {
    global $CFG, $MYVARS;
    $payid = empty($MYVARS->GET["payid"]) ? false : $MYVARS->GET["payid"];

    if (!empty($payid)) {
        execute_db_sql("DELETE FROM billing_payments WHERE payid='$payid'");
    }
}

function delete_note() {
    global $CFG, $MYVARS;
    $aid   = empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"];
    $chid  = empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"];
    $cid   = empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"];
    $actid = empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"];
    $nid   = empty($MYVARS->GET["nid"]) ? false : $MYVARS->GET["nid"];

    if (!empty($nid)) {
        execute_db_sql("DELETE FROM notes WHERE nid='$nid'");
        get_notes_list(false, $aid, $chid, $cid, $actid);
    }
}

function delete_activity() {
    global $CFG, $MYVARS;
    $aid   = empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"];
    $chid  = empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"];
    $cid   = empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"];
    $actid = empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"];
    $nid   = empty($MYVARS->GET["nid"]) ? false : $MYVARS->GET["nid"];

    if (!empty($actid)) {
        execute_db_sql("DELETE FROM notes WHERE actid='$actid'");
        execute_db_sql("DELETE FROM activity WHERE actid='$actid'");
        get_admin_children_form(false, $chid);
    }
}

function delete_employee_activity() {
    global $CFG, $MYVARS;
    $employeeid = empty($MYVARS->GET["employeeid"]) ? false : $MYVARS->GET["employeeid"];
    $actid      = empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"];

    if (!empty($actid)) {
        execute_db_sql("DELETE FROM notes WHERE employeeid='$employeeid' AND actid='$actid'");
        execute_db_sql("DELETE FROM employee_activity WHERE actid='$actid'");
        get_admin_employees_form(false, $employeeid);
    }
}

function delete_document() {
    global $CFG, $MYVARS;
    $aid   = empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"];
    $chid  = empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"];
    $cid   = empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"];
    $actid = empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"];
    $did   = empty($MYVARS->GET["did"]) ? false : $MYVARS->GET["did"];

    if (!empty($did)) {
        $existing = get_db_row("SELECT * FROM documents WHERE did='$did'");
        if (!empty($existing["aid"])) {
            $folder = "accounts/" . $existing["aid"];
        } elseif (!empty($existing["chid"])) {
            $folder = "children/" . $existing["chid"];
        } elseif (!empty($existing["cid"])) {
            $folder = "contacts/" . $existing["cid"];
        } elseif (!empty($existing["actid"])) {
            $folder = "activities/" . $existing["actid"];
        }

        delete_file($CFG->docroot . "/files/$folder/" . $existing["filename"]);
        execute_db_sql("DELETE FROM documents WHERE did='$did'");

        get_documents_list(false, $aid, $chid, $cid, $actid);
    }
}

function get_documents_list($return = false, $aid = null, $chid = null, $cid = null, $actid = null) {
    global $CFG, $MYVARS;
    $aid   = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $chid  = $chid !== null ? $chid : (empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"]);
    $cid   = $cid !== null ? $cid : (empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"]);
    $actid = $actid !== null ? $actid : (empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"]);

    $returnme = "";
    if (!empty($chid)) {
        $type = "chid";
        $SQL  = "SELECT * FROM documents WHERE chid='$chid' AND tag != 'avatar' ORDER BY timelog DESC";
    } elseif (!empty($cid)) {
        $type = "cid";
        $SQL  = "SELECT * FROM documents WHERE cid='$cid' AND tag != 'avatar' ORDER BY timelog DESC";
    } elseif (!empty($aid)) {
        $type = "aid";
        $SQL  = "SELECT * FROM documents WHERE aid='$aid' AND tag != 'avatar' ORDER BY timelog DESC";
    } elseif (!empty($actid)) {
        $type = "actid";
        $SQL  = "SELECT * FROM documents WHERE actid='$actid' AND tag != 'avatar' ORDER BY timelog DESC";
    }

    if ($documents = get_db_result($SQL)) {
        //View All button
        $returnme .= '
                <div class="document_list_item ui-corner-all" style="text-align:center">
                    <a href="ajax/fileviewer.php?chid=' . $chid . '" class="nyroModal"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View All</span></a>
                </div>';
        while ($document = fetch_row($documents)) {
            $delete_action = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to delete this \'+$(\'a#a-' . $document["did"] . '\').attr(\'data\')+\' document?\', \'Yes\', \'No\',
            function(){ $.ajax({
                    type: \'POST\',
                    url: \'ajax/ajax.php\',
                    data: { action: \'delete_document\', ' . $type . ': \'' . $$type . '\',did: \'' . $document["did"] . '\' },
                    success: function(data) {
                            $(\'#subselect_div\').html(data); refresh_all();
                        }
                });
            },
            function(){});';

            $identifier = time() . "documents_" . $document["did"];
            $tag        = get_db_row("SELECT * FROM documents_tags WHERE tag='" . $document["tag"] . "'");
            $returnme .= get_form("attach_doc", array(
                "did" => $document["did"],
                "chid" => $chid,
                "aid" => $aid,
                "cid" => $cid,
                "actid" => $actid,
                "display" => "subselect_div",
                "callback" => "get_documents_list",
                "param1" => "$type",
                "param1value" => $$type
            ), $identifier);
            $returnme .= '
                <div class="last_update ui-corner-all">Last Update: ' . date('F j, Y g:i a', display_time($document["timelog"])) . '</div><div class="document_list_item ui-corner-all">
                    <div style="margin-top:10px;">
                        <span class="tag ui-corner-all" style="color:' . $tag["textcolor"] . ';background-color:' . $tag["color"] . '">' . $tag["title"] . '</span> ' . $document["description"] . '<br />
                        <span class="list_links"><a href="ajax/fileviewer.php?did=' . $document["did"] . '" class="nyroModal"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Document</span></a> <a href="javascript: void(0);" onclick="CreateDialog(\'attach_doc_' . $identifier . '\',300,400)"><span class="inline-button ui-corner-all">' . get_icon('table_edit') . ' Update Document</span></a> <a id="a-' . $document["did"] . '" data="' . $document["tag"] . '" href="javascript: void(0);" onclick="' . $delete_action . '"><span class="inline-button ui-corner-all">' . get_icon('bin_closed') . ' Delete Document</span></a></span>
                    </div>
                </div>';
        }
    } else {
        $returnme .= '
                <div class="document_list_item ui-corner-all" style="text-align:center">
                None
                </div>';
    }

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_notes_list($return = false, $aid = null, $chid = null, $cid = null, $actid = null) {
    global $CFG, $MYVARS;
    $aid   = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $chid  = $chid !== null ? $chid : (empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"]);
    $cid   = $cid !== null ? $cid : (empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"]);
    $actid = $actid !== null ? $actid : (empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"]);

    $returnme = "";
    if (!empty($aid)) {
        $type = "aid";
        $id   = $aid;
    } elseif (!empty($chid)) {
        $type = "chid";
        $id   = $chid;
    } elseif (!empty($cid)) {
        $type = "cid";
        $id   = $cid;
    } elseif (!empty($actid)) {
        $type = "actid";
        $id   = $actid;
    }

    $SQL = "SELECT * FROM notes WHERE $type='$id' AND tag IN (SELECT tag FROM notes_tags) ORDER BY timelog DESC";
    if ($notes = get_db_result($SQL)) {
        //View All button
        $returnme .= '
                <div class="document_list_item ui-corner-all" style="text-align:center">
                    <a href="ajax/reports.php?report=allnotes&type=' . $type . '&id=' . $id . '" class="nyroModal"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View All</span></a>
                </div>';
        while ($note = fetch_row($notes)) {
            $delete_action = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to delete this \'+$(\'a#a-' . $note["nid"] . '\').attr(\'data\')+\' note?\', \'Yes\', \'No\',
            function(){ $.ajax({
                    type: \'POST\',
                    url: \'ajax/ajax.php\',
                    data: { action: \'delete_note\', ' . $type . ': \'' . $$type . '\',nid: \'' . $note["nid"] . '\' },
                    success: function(data) {
                            $(\'#subselect_div\').html(data); refresh_all();
                        }
                });
            },
            function(){});';

            $identifier = time() . "notes_" . $note["nid"];
            $tag        = get_tag(array(
                "type" => "notes",
                "tag" => $note["tag"]
            ));
            $returnme .= get_form("attach_note", array(
                "nid" => $note["nid"],
                "chid" => $chid,
                "aid" => $aid,
                "cid" => $cid,
                "actid" => $actid,
                "display" => "subselect_div",
                "callback" => "children",
                "param1" => "$type",
                "param1value" => $$type
            ), $identifier);
            $returnme .= '
                <div class="last_update ui-corner-all">Last Update: ' . date('F j, Y g:i a', display_time($note["timelog"])) . '</div><div class="document_list_item ui-corner-all">
                    <div style="margin-top:10px;">
                        <span class="tag ui-corner-all" style="color:' . $tag["textcolor"] . ';background-color:' . $tag["color"] . '">' . $tag["title"] . '</span> ' . $note["note"] . '<br />
                        <span class="list_links"><a href="javascript: void(0);" onclick="CreateDialog(\'attach_note_' . $identifier . '\',360,400)"><span class="inline-button ui-corner-all">' . get_icon('table_edit') . ' Update Note</span></a> <a id="a-' . $note["nid"] . '" data="' . $note["tag"] . '" href="javascript: void(0);" onclick="' . $delete_action . '"><span class="inline-button ui-corner-all">' . get_icon('bin_closed') . ' Delete Note</span></a></span>
                    </div>
                </div>';
        }
    } else {
        $returnme .= '
                <div class="document_list_item ui-corner-all" style="text-align:center">
                None
                </div>';
    }

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_reports_list($return = false, $pid = null, $aid = null, $chid = null, $cid = null, $actid = null, $employeeid = null) {
    global $CFG, $MYVARS;
    $activepid  = get_pid();
    $pid        = $pid !== null ? $pid : (empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"]);
    $aid        = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $chid       = $chid !== null ? $chid : (empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"]);
    $cid        = $cid !== null ? $cid : (empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"]);
    $actid      = $actid !== null ? $actid : (empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"]);
    $employeeid = $employeeid !== null ? $employeeid : (empty($MYVARS->GET["employeeid"]) ? false : $MYVARS->GET["employeeid"]);

    $returnme = "";

    if (!empty($pid)) {
        $type = "pid";
        $id   = $pid;
    } elseif (!empty($aid)) {
        $type = "aid";
        $id   = $aid;
    } elseif (!empty($chid)) {
        $type = "chid";
        $id   = $chid;
    } elseif (!empty($cid)) {
        $type = "cid";
        $id   = $cid;
    } elseif (!empty($employeeid)) {
        $type = "employeeid";
        $id   = $employeeid;
    } elseif (!empty($actid)) {
        $type = "actid";
        $id   = $actid;
    }

    $pid = !empty($pid) ? $pid : $activepid; //if pid isn't set, set it to active pid

    $tags_form     = make_select("tag", get_db_result("SELECT tag,title FROM notes_tags n ORDER BY tag"), "tag", "title", "", false, "", true);
    $att_tags_form = make_select("att_tag", get_db_result("SELECT tag,title,2 as sorttype FROM notes_required r WHERE pid='$pid' UNION SELECT tag,title,1 as sorttype FROM events_tags e ORDER BY sorttype,tag"), "tag", "title", "", false, "", true);
    $reports       = "";
    switch ($type) {
        case "pid":
            //Simple Child List
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'child_list\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' List of Children</span></a><div class="report-cubes-container"></div>';
            //Child attendance between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'program_per_child_attendance\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Per Child Attendance Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            //Per Account attendance between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'program_per_account_attendance\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Per Account Attendance Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            //Activities between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'activity\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Activities Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            //Daily Activity Tag Count Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#att_tag\').val().length && $(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'activity_tag\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Daily Activity Tag Count Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            //Daily Attendance Breakdown 30 min
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'attendance_throughout_day\'); $(\'#extra\').val(\'30\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Daily Attendance 30min Breakdown</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            //Daily Attendance Breakdown 15 min
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'attendance_throughout_day\'); $(\'#extra\').val(\'15\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Daily Attendance 15min Breakdown</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            //Meal Status Count Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'meal_status_count\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Meal Status Count Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            //Notes between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'allnotes\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Notes Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: pink"></div><div class="cube" style="background-color: lightblue"></div></div>';
            //Week expected attendance
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'weekly_expected_attendance\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Expected attendance vs Actual attendance</span></a><div class="report-cubes-container"></div>';
            //Account Balances
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'program_per_account_bill\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View All Account Balances</span></a><div class="report-cubes-container"></div>';
            //Program Cash Flow
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'program_per_program_cash_flow\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Program Cash Flow</span></a><div class="report-cubes-container"></div>';
            //Program Payments
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'payments_between\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Program Payments</span></a><div class="report-cubes-container"></div>';
            break;
        case "aid":
            //Activities between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'activity\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Activities Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            //Daily Activity Tag Count Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#att_tag\').val().length && $(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'activity_tag\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Daily Activity Tag Count Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            //Invoice Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'invoice_between\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Invoice Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            //Notes between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'allnotes\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Notes Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: pink"></div><div class="cube" style="background-color: lightblue"></div></div>';
            //Account Cash Flow
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'program_per_program_cash_flow\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Account Cash Flow</span></a><div class="report-cubes-container"></div>';
            //Account Payments
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'payments_between\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Account Payments - FOR TAXES</span></a><div class="report-cubes-container"></div>';
            break;
        case "chid":
            //Child Activities Between Dates
            $reports .= '<br /><br /><a onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'activity\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Activities Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            //Daily Activity Tag Count Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#att_tag\').val().length && $(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'activity_tag\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Daily Activity Tag Count Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            //Child Notes Between Dates
            $reports .= '<br /><br /><a onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'allnotes\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Notes Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: pink"></div><div class="cube" style="background-color: lightblue"></div></div>';
            break;
        case "cid":
            //Daily Activity Tag Count Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if($(\'#att_tag\').val().length && $(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'activity_tag\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' Daily Activity Tag Count Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            //Contact Activities Between Dates
            $reports .= '<br /><br /><a onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'activity\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Activities Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            break;
        case "employeeid":
            //Employee Activities Between Dates
            $reports .= '<br /><br /><a onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'activity\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Activities Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            //Employee Pay Between Dates
            $reports .= '<br /><br /><a onclick="if($(\'#from\').val().length && $(\'#to\').val().length){ $(\'#report\').val(\'employee_paid\'); $(\'#myValidForm\').submit(); }"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View Pay Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            break;
        case "actid":
            break;
    }

    //Activity from / to
    $returnme .= '<div class="scroll-pane document_list_item ui-corner-all fill_height" style="text-align:center">
                        <form id="myValidForm" method="get" action="ajax/reports.php" onsubmit="return false;">
                            <input type="hidden" name="report" id="report" value="" />
                            <input type="hidden" name="id" id="id" value="' . $id . '" />
                            <input type="hidden" name="type" id="type" value="' . $type . '" />
                            <input type="hidden" name="actid" id="actid" value="' . $actid . '" />
                            <input type="hidden" name="extra" id="extra" value="" />
                            <div class="ui-corner-all" style="margin:2px;padding:5px;background-color:lightblue;"><label for="from">From</label><input type="text" id="from" name="from"/><label for="to">to</label><input type="text" id="to" name="to"/></div>
                            <div class="ui-corner-all" style="margin:2px;padding:5px;background-color:pink;"><label for="tag">Only notes with the tag: </label>' . $tags_form . '</div>
                            <div class="ui-corner-all" style="margin:2px;padding:5px;background-color:lightgreen;"><label for="tag">Only activites with the tag: </label>' . $att_tags_form . '</div>
        ';
    $returnme .= $reports . '</form>
<script>
    $(function() {
        var dates = $( "#from, #to" ).datepicker({
            changeMonth: true,
            numberOfMonths: 1,
            onSelect: function( selectedDate ) {
                var option = this.id == "from" ? "minDate" : "maxDate",
                    instance = $( this ).data( "datepicker" ),
                    date = $.datepicker.parseDate(
                        instance.settings.dateFormat ||
                        $.datepicker._defaults.dateFormat,
                        selectedDate, instance.settings );
                dates.not( this ).datepicker( "option", option, date );
            }
        });
    });
$(function() {
  var validForm = $("#myValidForm").submit(function(e) {
      validForm.nyroModal().nmCall();
  });
});
</script>
            </div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_activity_list($return = false, $aid = null, $chid = null, $cid = null, $actid = null, $employeeid = null) {
    global $CFG, $MYVARS;
    $aid        = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $chid       = $chid !== null ? $chid : (empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"]);
    $cid        = $cid !== null ? $cid : (empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"]);
    $actid      = $actid !== null ? $actid : (empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"]);
    $employeeid = $employeeid !== null ? $employeeid : (empty($MYVARS->GET["employeeid"]) ? false : $MYVARS->GET["employeeid"]);
    $month      = empty($MYVARS->GET["month"]) ? date('n') : $MYVARS->GET["month"];
    $year       = empty($MYVARS->GET["year"]) ? date('Y') : $MYVARS->GET["year"];

    $returnme = "";
    if (!empty($chid)) {
        $type = "chid";
        $id   = $chid;
    } elseif (!empty($cid)) {
        $type = "cid";
        $id   = $cid;
    } elseif (!empty($aid)) {
        $type = "aid";
        $id   = $aid;
    } elseif (!empty($employeeid)) {
        $type = "employeeid";
        $id   = $employeeid;
    } elseif (!empty($actid)) {
        $type = "actid";
        $id   = $actid;
    }

    $prevmonth = ($month - 1) == 0 ? 12 : $month - 1;
    $prevyear  = ($month - 1) == 0 ? $year - 1 : $year;
    $nextmonth = ($month + 1) == 13 ? 1 : $month + 1;
    $nextyear  = ($month + 1) == 13 ? $year + 1 : $year;
    //View All button
    $returnme .= '
            <div class="document_list_item ui-corner-all" style="text-align:center">
                <a href="ajax/reports.php?month1=' . $month . '&year1=' . $year . '&report=activity&type=' . $type . '&id=' . $id . '" class="nyroModal"><span class="inline-button ui-corner-all">' . get_icon('magnifier') . ' View All ' . date('F', mktime(0, 0, 0, $month, 1, $year)) . '</span></a>
            </div>';
    $returnme .= '<div class="document_list_item ui-corner-all" style="text-align:center;"><table class="fill_width" cellpadding="0" cellspacing="0"><tr><td style="text-align: left;"><a href="javascript: void(0);" onclick="
    $.ajax({
        type: \'POST\',
        url: \'ajax/ajax.php\',
        data: { action: \'get_activity_list\',' . $type . ': \'' . $$type . '\',month:\'' . $prevmonth . '\',year:\'' . $prevyear . '\' },
        success: function(data) {
                $(\'#subselect_div\').hide(\'fade\');
                $(\'#subselect_div\').html(data);
                $(\'#subselect_div\').show(\'fade\');
                refresh_all();
            }
    });">' . date('F Y', mktime(0, 0, 0, $prevmonth, 1, $prevyear)) . '</a></td><td colspan="5" style="text-align:center;font-size:130%;font-weight:bold;">' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . '</td><td style="text-align:right"><a href="javascript: void(0);" onclick="
    $.ajax({
        type: \'POST\',
        url: \'ajax/ajax.php\',
        data: { action: \'get_activity_list\',' . $type . ': \'' . $$type . '\',month:\'' . $nextmonth . '\',year:\'' . $nextyear . '\' },
        success: function(data) {
                $(\'#subselect_div\').hide(\'fade\',function(){});
                $(\'#subselect_div\').html(data);
                $(\'#subselect_div\').show(\'fade\');
                refresh_all();
            }
    });">' . date('F Y', mktime(0, 0, 0, $nextmonth, 1, $nextyear)) . '</a></td></tr><tr><td colspan="7" style="height:10px;"></td></tr></table>';
    $returnme .= draw_calendar($month, $year, array(
        "type" => "activity",
        "$type" => $$type,
        "form" => "update_activity"
    ));
    $returnme .= "</div>";

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_admin_children_form($return = false, $chid = false, $recover = false) {
    global $MYVARS;
    $chid     = !empty($chid) ? $chid : (empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"]);
    $returnme = '<div class="container_list scroll-pane ui-corner-all fill_height">';
    $returnme .= '
            <div class="document_list_item ui-corner-all" style="text-align:center">
                <span><strong>Enrolled Children</strong></span>
            </div>';
    if ($children = get_db_result("SELECT * FROM children WHERE deleted='0' AND chid IN (SELECT chid FROM enrollments WHERE pid IN (SELECT pid FROM programs WHERE active=1)) ORDER BY last,first")) {
        while ($child = fetch_row($children)) {
            $chid           = empty($chid) ? $child["chid"] : $chid;
            $selected_class = $chid && $chid == $child["chid"] ? "selected_button" : "";
            $checked_in     = $recover ? '' : (is_checked_in($child["chid"]) ? get_icon('status_online') : get_icon('status_offline'));
            $notifications  = get_notifications(get_pid(), $child["chid"], false, true) ? 'style="background: darkred;"' : '';
            $returnme .= '<div class="ui-corner-all list_box" ' . $notifications . '><div class="list_box_item_full"><button class="list_buttons ' . $selected_class . '" style="float:none;" onclick="$(\'.list_buttons\').toggleClass(\'selected_button\',true); $(\'.list_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'get_info\', chid: \'' . $child["chid"] . '\' },
                            success: function(data) {
                                $(\'#info_div\').html(data);
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_action_buttons\', chid: \'' . $child["chid"] . '\' },
                                    success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                });
                            }
                        });

                      ">Select</button><span class="list_title">' . $checked_in . ' ' . $child["last"] . ", " . $child["first"] . '</span></div></div>';
        }
    } else {
        $returnme .= '<div class="ui-corner-all list_box"><div class="list_box_item_full"><span class="list_title">None Enrolled</span></div></div>';
    }

    $returnme .= '</div>';
    $returnme .= '<div class="container_actions ui-corner-all" id="actions_div">' . get_action_buttons(true, false, false, $chid, false, false, $recover) . '</div>';
    $returnme .= '<div class="container_info ui-corner-all fill_height" id="info_div">' . get_info(true, false, false, $chid, false, false, $recover) . '</div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_admin_billing_form($return = false, $pid = false, $aid = false) {
    global $MYVARS;
    $pid      = $pid ? $pid : (empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"]);
    $aid      = $aid ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    //$selected_class = $pid && !$aid ? "selected_button" : "" ;
    $program  = get_db_row("SELECT * FROM programs WHERE pid='$pid'");
    $returnme = '<div class="container_list scroll-pane ui-corner-all">';
    $returnme .= '<div class="ui-corner-all list_box"><div class="list_box_item_full"><button class="list_buttons" style="float:none;" onclick="$(\'.list_buttons\').toggleClass(\'selected_button\',true); $(\'.list_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'view_invoices\', pid: \'' . $pid . '\' },
                            success: function(data) {
                                $(\'#info_div\').html(data);
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_billing_buttons\', pid: \'' . $pid . '\' },
                                    success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                });
                            }
                        });

                      ">Select</button><span class="list_title">' . $program["name"] . '</span></div></div>';
    if ($accounts = get_db_result("SELECT * FROM accounts WHERE deleted = '0' AND admin= '0' AND aid IN (SELECT aid FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE pid='$pid')) ORDER BY name")) {
        $i = 0;
        while ($account = fetch_row($accounts)) {
            $kid_count       = get_db_count("SELECT * FROM children WHERE aid='" . $account["aid"] . "' AND deleted='0'");
            $selected_class  = $aid && $aid == $account["aid"] || ($pid && !$aid && $i == 0) ? "selected_button" : "";
            $aid             = $selected_class == "selected_button" ? $account["aid"] : $aid;
            $account_balance = account_balance($pid, $account["aid"], true);
            $balanceclass    = $account_balance <= 0 ? "balance_good" : "balance_bad";
            $returnme .= '<div class="ui-corner-all list_box"><div class="list_box_item_left"><button class="list_buttons ' . $selected_class . '" style="float:none;" onclick="$(\'.list_buttons\').toggleClass(\'selected_button\',true); $(\'.list_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'view_invoices\', aid: \'' . $account["aid"] . '\',pid: \'' . $pid . '\' },
                            success: function(data) {
                                $(\'#info_div\').html(data);
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_billing_buttons\', aid: \'' . $account["aid"] . '\',pid: \'' . $pid . '\' },
                                    success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                });
                            }
                        });

                      ">Select</button><span class="list_title">' . $account["name"] . '</span></div><div class="list_box_item_right"><div style="width:100px;text-align:center;background:none;display:inline-block;color:lightGrey;text-shadow: black 1px 1px 3px;">Children: ' . $kid_count . '<br /><span class="' . $balanceclass . '">Balance: $' . $account_balance . '</span></div></div></div>';
            $i++;
        }
    }

    $returnme .= '</div>';
    $returnme .= '<div class="container_actions ui-corner-all" id="actions_div">' . get_billing_buttons(true, $pid, $aid) . '</div>';
    $returnme .= '<div class="container_info ui-corner-all fill_height" id="info_div">' . view_invoices(true, $pid, $aid) . '</div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_admin_contacts_form($return = false, $cid = false, $recover = false) {
    global $MYVARS;
    $cid      = $cid ? $cid : (empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"]);
    $returnme = '<div class="container_list scroll-pane ui-corner-all">';
    $returnme .= '
        <div class="document_list_item ui-corner-all" style="text-align:center">
            <span><strong>Active Contacts</strong></span>
        </div>';
    if ($contacts = get_db_result("SELECT * FROM contacts WHERE deleted='0' AND aid IN (SELECT aid FROM enrollments WHERE pid='" . get_pid() . "') ORDER BY last,first")) {
        while ($contact = fetch_row($contacts)) {
            $cid            = empty($cid) ? $contact["cid"] : $cid;
            $selected_class = $cid && $cid == $contact["cid"] ? "selected_button" : "";
            $returnme .= '<div class="ui-corner-all list_box"><div class="list_box_item_full"><button class="list_buttons ' . $selected_class . '" style="float:none;" onclick="$(\'.list_buttons\').toggleClass(\'selected_button\',true); $(\'.list_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'get_info\', cid: \'' . $contact["cid"] . '\' },
                            success: function(data) {
                                $(\'#info_div\').html(data);
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_action_buttons\', cid: \'' . $contact["cid"] . '\' },
                                    success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                });
                            }
                        });

                      ">Select</button><span class="list_title">' . $contact["last"] . ", " . $contact["first"] . '</span></div></div>';
        }
    } else {
        $returnme .= '<div class="ui-corner-all list_box"><div class="list_box_item_full"><span class="list_title">None Active</span></div></div>';
    }

    $returnme .= '</div>';
    $returnme .= '<div class="container_actions ui-corner-all" id="actions_div">' . get_action_buttons(true, false, false, false, $cid, false, $recover) . '</div>';
    $returnme .= '<div class="container_info ui-corner-all fill_height" id="info_div">' . get_info(true, false, false, false, $cid, false, $recover) . '</div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_admin_employees_form($return = false, $employeeid = false, $recover = false) {
    global $MYVARS;
    $employeeid = $employeeid ? $employeeid : (empty($MYVARS->GET["employeeid"]) ? false : $MYVARS->GET["employeeid"]);
    $recover    = $recover ? $recover : (empty($MYVARS->GET["recover"]) ? false : $MYVARS->GET["recover"]);
    $returnme   = '<div class="container_list scroll-pane ui-corner-all"><div class="ui-corner-all list_box"><div class="list_box_item_left">';

    if (!$recover) {
        $returnme .= get_form('add_edit_employee') . '<button class="list_buttons" style="float:none;margin:4px;" type="button" onclick="CreateDialog(\'add_edit_employee\',230,315)">Add New Employee</button>';
        if (get_db_row("SELECT employeeid FROM employee WHERE deleted=1")) {
            $returnme .= ' <button class="list_buttons" onclick="$.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'get_admin_employees_form\', employeeid: \'\', recover: \'true\' },
                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                  });">See Deleted</button>';
        }

    } else {
        $returnme .= '<button style="margin:4px;"  onclick="$.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'get_admin_employees_form\', employeeid: \'\'},
                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
              });">See Active</button>';
    }
    $returnme .= '</div><div class="list_box_item_right"></div></div>';
    $returnme .= '';

    $deleted = $recover ? "1" : "0";
    $SQL     = "SELECT * FROM employee WHERE deleted = '$deleted' ORDER BY last,first";

    if ($employees = get_db_result($SQL)) {
        while ($employee = fetch_row($employees)) {
            $employeeid     = empty($employeeid) ? $employee["employeeid"] : $employeeid;
            $selected_class = $employeeid && $employeeid == $employee["employeeid"] ? "selected_button" : "";
            $deleted_param  = $recover ? ',recover: \'true\'' : '';

            $returnme .= '<div class="ui-corner-all list_box"><div class="list_box_item_left"><button class="list_buttons ' . $selected_class . '" style="float:none;" onclick="$(\'.list_buttons\').toggleClass(\'selected_button\',true); $(\'.list_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'get_info\', employeeid: \'' . $employee["employeeid"] . '\'' . $deleted_param . ' },
                            success: function(data) {
                                $(\'#info_div\').html(data);
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_action_buttons\', employeeid: \'' . $employee["employeeid"] . '\'' . $deleted_param . ' },
                                    success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                });
                            }
                        });

                      ">Select</button><span class="list_title">' . $employee["last"] . ', ' . $employee["first"] . '</span></div><div class="list_box_item_right"></div></div>';
        }
    }
    $returnme .= '</div>';
    $returnme .= '<div class="container_actions ui-corner-all" id="actions_div">' . get_action_buttons(true, false, false, false, false, false, $recover, $employeeid) . '</div>';
    $returnme .= '<div class="container_info ui-corner-all fill_height" id="info_div">' . get_info(true, false, false, false, false, false, $recover, $employeeid) . '</div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_admin_tags_form($return = false, $tagtype = false, $tag = false) {
    global $MYVARS;
    $tagtype  = $tagtype ? $tagtype : (empty($MYVARS->GET["tagtype"]) ? false : $MYVARS->GET["tagtype"]);
    $tag      = $tag ? $tag : (empty($MYVARS->GET["tag"]) ? false : $MYVARS->GET["tag"]);
    $returnme = '<div class="container_list scroll-pane ui-corner-all">';
    $tagtypes = array(
        "documents",
        "notes"
    );
    $returnme .= '
        <div class="document_list_item ui-corner-all" style="text-align:center">
            <span><strong>Tag Types</strong></span>
        </div>';
    foreach ($tagtypes as $tagrow) {
        $tagtype        = empty($tagtype) ? $tagrow : $tagtype;
        $selected_class = $tagtype && $tagtype == $tagrow ? "selected_button" : "";
        $returnme .= '<div class="ui-corner-all list_box"><div class="list_box_item_full"><button class="list_buttons ' . $selected_class . '" style="float:none;" onclick="$(\'.list_buttons\').toggleClass(\'selected_button\',true); $(\'.list_buttons\').not(this).toggleClass(\'selected_button\',false);
                    $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'get_tags_info\', tagtype: \'' . $tagrow . '\' },
                            success: function(data) {
                                $(\'#info_div\').html(data);
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_tags_actions\', tagtype: \'' . $tagrow . '\' },
                                    success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                });
                            }
                        });
                  ">Select</button><span class="list_title">' . ucfirst($tagrow) . '</span></div></div>';
    }

    $returnme .= '</div>';
    $returnme .= '<div class="container_actions ui-corner-all" id="actions_div">' . get_tags_actions(true, $tagtype, $tag) . '</div>';
    $returnme .= '<div class="container_info ui-corner-all fill_height" id="info_div">' . get_tags_info(true, $tagtype, $tag) . '</div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}
function get_tags_actions($return = false, $tagtype = null, $tag = null) {
    global $MYVARS;
    $tagtype  = $tagtype ? $tagtype : (empty($MYVARS->GET["tagtype"]) ? false : $MYVARS->GET["tagtype"]);
    $tag      = $tag ? $tag : (empty($MYVARS->GET["tag"]) ? false : $MYVARS->GET["tag"]);
    $returnme = "";

    $identifier = time() . "note_$tagtype";
    if (!empty($tagtype)) {
        $returnme .= get_form("add_edit_tag", array(
            "tagtype" => $tagtype,
            "callback" => "tags"
        ), $identifier);
        $returnme .= '<button title="Add Tag" class="image_button" type="button" onclick="CreateDialog(\'add_edit_tag_' . $identifier . '\',300,400)">' . get_icon('Address-book') . '</button>';
    }

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_tags_info($return = false, $tagtype = null, $tag = null) {
    global $MYVARS;
    $tagtype  = $tagtype ? $tagtype : (empty($MYVARS->GET["tagtype"]) ? false : $MYVARS->GET["tagtype"]);
    $tag      = $tag ? $tag : (empty($MYVARS->GET["tag"]) ? false : $MYVARS->GET["tag"]);
    $returnme = "";
    //Tags
    $SQL      = "SELECT * FROM $tagtype" . "_tags WHERE tag != 'avatar' ORDER BY title";
    if ($tags = get_db_result($SQL)) {
        $returnme .= '<div style="display:table-cell;font-weight: bold;font-size: 110%;padding-left: 10px;">Tags:</div><div id="tags" class="scroll-pane infobox fill_height">';
        while ($tagrow = fetch_row($tags)) {
            $identifier = time() . "note_$tagtype" . "_" . $tagrow["tag"];

            $delete_action = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to delete this tag?\', \'Yes\', \'No\', function(){ $.ajax({
                      type: \'POST\',
                      url: \'ajax/ajax.php\',
                      data: { action: \'delete_tag\', tagtype: \'' . $tagtype . '\',tag: \'' . $tagrow["tag"] . '\' },
                      success: function(data) {
                        $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'get_admin_tags_form\', tagtype: \'' . $tagtype . '\' },
                            success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });
                      }
                      });},function(){});';
            //Edit Tag Button
            $returnme .= get_form("add_edit_tag", array(
                "tagtype" => $tagtype,
                "callback" => "tags",
                "tagrow" => $tagrow
            ), $identifier);
            $edit_button   = ' <a href="javascript: void(0);" onclick="CreateDialog(\'add_edit_tag_' . $identifier . '\',300,400)"><span class="inline-button ui-corner-all">' . get_icon('wrench') . ' Edit</span></a>';
            $delete_button = get_db_row("SELECT * FROM $tagtype WHERE tag='" . $tagrow["tag"] . "'") ? '' : ' <a href="javascript: void(0);" onclick="' . $delete_action . '"><span class="inline-button ui-corner-all">' . get_icon('bin_closed') . ' Delete</span></a>';

            $returnme .= '<div class="ui-corner-all list_box"><div class="list_title" style="padding:5px;display:block;"><span id="tag_template' . $identifier . '" class="tag ui-corner-all" style="color:' . $tagrow["textcolor"] . ';background-color:' . $tagrow["color"] . '">' . $tagrow["title"] . '</span>';
            $returnme .= ' <span class="list_links" style="float:right;">' . $edit_button . $delete_button . '</span></div>';
            $returnme .= '</div><div style="clear:both;"></div>';
        }
        $returnme .= '</div><div style="clear:both;"></div>';
    }

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_admin_enrollment_form($return = false, $pid = false) {
    global $MYVARS;
    $activepid  = get_pid();
    $pid        = $pid ? $pid : (empty($MYVARS->GET["pid"]) ? $activepid : $MYVARS->GET["pid"]);
    $returnme   = '<div class="container_list scroll-pane ui-corner-all"><div class="ui-corner-all list_box">';
    $identifier = time() . "add_program";
    $returnme .= get_form("add_edit_program", array(
        "callback" => "programs"
    ), $identifier);
    $returnme .= '<div class="list_box_item_full" style="text-align:center"><button type="button" class="list_buttons" onclick="CreateDialog(\'add_edit_program_' . $identifier . '\',450,500)">Create Program</button></div>';

    $returnme .= '</div>';

    if ($programs = get_db_result("SELECT * FROM programs WHERE deleted = '0' ORDER BY name")) {
        while ($program = fetch_row($programs)) {
            $selected_class = $pid && $pid == $program["pid"] ? "selected_button" : "";
            $active         = $activepid && $activepid == $program["pid"] ? "<span style='float:right;margin: 10px 4px;color:white;'>[Active]</span>" : "";

            $notifications = get_notifications($program["pid"], false, false, true) ? 'style="background: darkred;"' : '';
            $returnme .= '<div class="ui-corner-all list_box" ' . $notifications . '><div class="list_box_item_left" style="white-space:nowrap;"><button class="list_buttons ' . $selected_class . '" style="float:none;" onclick="$(\'.list_buttons\').toggleClass(\'selected_button\',true); $(\'.list_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'get_info\', pid: \'' . $program["pid"] . '\' },
                            success: function(data) {
                                $(\'#info_div\').html(data);
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_action_buttons\', pid: \'' . $program["pid"] . '\' },
                                    success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                });
                            }
                        });

                      ">Select</button><span class="list_title">' . $program["name"] . '</span></div><div class="list_box_item_right">' . $active . '</div></div>';
        }
    }

    $returnme .= '</div>';
    $returnme .= '<div class="container_actions ui-corner-all" id="actions_div">' . get_action_buttons(true, $pid, false, false, false, false, false) . '</div>';
    $returnme .= '<div class="container_info ui-corner-all fill_height" id="info_div">' . get_info(true, $pid, false, false, false, false, false) . '</div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_admin_accounts_form($return = false, $aid = false, $recover = false) {
    global $MYVARS;
    $aid      = $aid ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $recover  = $recover ? $recover : (empty($MYVARS->GET["recover"]) ? false : $MYVARS->GET["recover"]);
    $pid      = get_pid();
    $returnme = '<div class="container_list scroll-pane ui-corner-all">
                    <div class="ui-corner-all list_box">';

    if (!$recover) {
        $returnme .= '<div class="list_box_item_left">' . get_form('add_edit_account') . '<button class="list_buttons" style="float:none;margin:4px;" type="button" onclick="CreateDialog(\'add_edit_account\',200,315)">Add New Account</button>';
        if (get_db_row("SELECT chid FROM children WHERE deleted=1") || get_db_row("SELECT cid FROM contacts WHERE deleted=1") || get_db_row("SELECT aid FROM accounts WHERE deleted=1")) {
            $returnme .= ' <button class="list_buttons" onclick="$.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'get_admin_accounts_form\', aid: \'\', recover: \'true\' },
                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                  });">See Deleted</button>';
        }

        $returnme .= '</div>
                        <div class="list_box_item_right">
                            <div style="width:100px;text-align:center;background:none;line-height: 17px;vertical-align:top;color:white;text-shadow: black 1px 1px 3px;">
                                Show Enrolled <input type="checkbox" checked onclick="if($(this).prop(\'checked\')){ $(\'.inactiveaccount\').hide(); }else{ $(\'.inactiveaccount\').show(); } $(\'.scroll-pane\').sbscroller(\'refresh\'); smart_scrollbars();" />
                            </div>
                            <div style="width:100px;text-align:center;background:none;display:inline-block;color:white;text-shadow: black 1px 1px 3px;">Enrolled:
                                ' . get_db_count("SELECT * FROM enrollments WHERE pid='$pid' AND deleted='0' AND chid IN (SELECT chid FROM children WHERE deleted='0')") . '
                            </div>
                        </div>';
    } else {
        $returnme .= '<div class="list_box_item_left">
                        <button style="margin:4px;"  onclick="$.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_admin_accounts_form\', aid: \'\'},
                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });">See Active</button>
                      </div>';
    }
    $returnme .= '</div>';

    $deleted = $recover ? "1" : "0";
    if ($deleted) {
        $SQL = "SELECT * FROM accounts WHERE admin=0 AND (aid IN (SELECT aid FROM children WHERE deleted='$deleted') OR aid IN (SELECT aid FROM contacts WHERE deleted='$deleted') OR deleted=1) ORDER BY name";
    } else {
        $SQL = "SELECT * FROM accounts WHERE admin=0 AND deleted = '$deleted' ORDER BY name";
    }
    if ($accounts = get_db_result($SQL)) {
        while ($account = fetch_row($accounts)) {
            $kid_count = get_db_count("SELECT * FROM children WHERE aid='" . $account["aid"] . "' AND deleted='$deleted'");
            $active    = get_db_count("SELECT * FROM enrollments WHERE chid IN (SELECT chid FROM children WHERE aid='" . $account["aid"] . "') AND pid='$pid' AND deleted='$deleted'") ? "activeaccount" : "inactiveaccount";

            $selected_class = '';
            if (empty($aid) && $active == 'activeaccount') {
                $aid            = empty($aid) ? $account["aid"] : $aid;
                $selected_class = $active == 'activeaccount' && !empty($aid) && $aid == $account["aid"] ? "selected_button" : "";
            }

            $deleted_param   = $recover ? ',recover: \'true\'' : '';
            $notifications   = get_notifications($pid, false, $account["aid"], true) ? 'background: darkred;' : '';
            $override        = $recover ? "display:block;" : "";
            $account_balance = account_balance($pid, $account["aid"], true);
            $balanceclass    = $account_balance <= 0 ? "balance_good" : "balance_bad";
            $returnme .= '<div class="ui-corner-all list_box ' . $active . '" style="' . $notifications . $override . '"><div class="list_box_item_left"><button class="list_buttons ' . $selected_class . '" style="float:none;" onclick="$(\'.list_buttons\').toggleClass(\'selected_button\',true); $(\'.list_buttons\').not(this).toggleClass(\'selected_button\',false);
                        $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'get_info\', aid: \'' . $account["aid"] . '\'' . $deleted_param . ' },
                            success: function(data) {
                                $(\'#info_div\').html(data);
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_action_buttons\', aid: \'' . $account["aid"] . '\'' . $deleted_param . ' },
                                    success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                });
                            }
                        });

                      ">Select</button><span class="list_title">' . $account["name"] . '</span></div><div class="list_box_item_right"><div style="width:100px;text-align:center;background:none;display:inline-block;color:lightGrey;text-shadow: black 1px 1px 3px;">Children: ' . $kid_count . '<br /><a class="'.$balanceclass.'" href="javascript: void(0);" onclick="$.ajax({
                                                                                              type: \'POST\',
                                                                                              url: \'ajax/ajax.php\',
                                                                                              data: { action: \'get_admin_billing_form\', aid:\'' . $account["aid"] . '\' ,pid: \'' . $pid . '\' },
                                                                                              success: function(data) { $(\'#admin_display\').hide(\'fade\',null,null,function(){ $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); });  }
                                                                                          });$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not($(\'#admin_menu_billing\')).toggleClass(\'selected_button\',false);">Balance: $' . $account_balance . '</a></div></div></div>';
        }
    }
    $returnme .= '</div>';
    $returnme .= '<div class="container_actions ui-corner-all" id="actions_div">' . get_action_buttons(true, false, $aid, false, false, false, $recover) . '</div>';
    $returnme .= '<div class="container_info ui-corner-all fill_height" id="info_div">' . get_info(true, false, $aid, false, false, false, $recover) . '</div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_contacts_selector($chids, $admin = false) {
    $children = $returnme = "";
    foreach ($chids as $chid) {
        $children .= $children == "" ? "chid='" . $chid["value"] . "'" : " OR chid='" . $chid["value"] . "'";
    }

    $SQL = "SELECT * FROM contacts WHERE aid IN (SELECT aid FROM children WHERE $children AND deleted=0) AND deleted=0 ORDER by primary_address DESC,last,first";
    $returnme .= '<ol class="selectable" id="selectable" style="width:100%">';
    if (!empty($admin)) {
        $returnme .= '<li class="ui-widget-content ui-selected"><span class="contact" style="display:inline-block;width:30px;"><input class="cid" id="cid_admin" name="cid_admin" type="hidden" value="admin" /></span>Admin</li>';
    }
    if ($result = get_db_result($SQL)) {
        $i = 0;
        while ($row = fetch_row($result)) {
            $selected  = $i == 0 && !$admin ? "ui-selected" : "";
            $emergency = empty($row["emergency"]) ? "" : '<span style="float:right;background:0;">' . get_icon('error') . '</span>';
            $primary   = empty($row["primary_address"]) ? "" : '<span style="float:right;background:0;">' . get_icon('star') . '</span>';
            $returnme .= '<li class="ui-widget-content ' . $selected . '"><span class="contact" style="display:inline-block;width:30px;"><input class="cid" id="cid_' . $row["cid"] . '" name="cid_' . $row["cid"] . '" type="hidden" value="' . $row["cid"] . '" /></span>' . $row["first"] . ' ' . $row["last"] . ' - ' . $row["relation"] . $emergency . $primary . '</li>';
            $i++;
        }
    }
    if (!$admin) {
        $returnme .= '<li class="ui-widget-content" id="other_li" rel="$(\'.keyboard\').getkeyboard().reveal();"><span class="contact" style="display:inline-block;width:30px;"></span><span class="contact fill_width" style="display:inline-block;background-color:initial;">Other<input style="width:85% !important;font-size: 18px;margin:0px 0px 0px 25px; background-color:white;" class="cid keyboard fill_width autocapitalizewords" id="cid_other" name="cid_other" type="text" value="" onMousedown="SelectSelectableElements($(\'#selectable\'),$(\'#other_li\'));" /></span></li>';
    }
    $returnme .= '</ol>';
    return $returnme;
}

function copy_program() {
    global $CFG, $MYVARS;
    $pid = empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"];
    if ($pid) {
        //create new program
        $program = get_db_row("SELECT name,timeopen,timeclosed,deleted,active,perday,fulltime,minimumactive,minimuminactive,vacation,multiple_discount,consider_full,bill_by,discount_rule FROM programs WHERE pid='$pid'");
        $newpid  = copy_db_row($program, "programs", 'name=' . $program["name"] . ' COPY');

        //copy enrollments
        if ($enrollments = get_db_result("SELECT pid,chid,days_attending,exempt,deleted FROM enrollments WHERE pid='$pid' AND deleted=0")) {
            while ($enrollment = fetch_row($enrollments)) {
                copy_db_row($enrollment, "enrollments", 'pid=' . $newpid . '');
            }
        }

        execute_db_sql("UPDATE programs SET active=0"); //deactivate all programs
        execute_db_sql("UPDATE programs SET active=1 WHERE pid='$newpid'"); //activate new program
        //get_admin_enrollment_form(false,$newpid);
        echo get_admin_page("pid", $newpid);
    }
}

function activate_program() {
    global $CFG, $MYVARS;
    $pid = empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"];
    if ($pid) {
        execute_db_sql("UPDATE programs SET active=0");
        execute_db_sql("UPDATE programs SET active=1 WHERE pid='$pid'");
        echo get_admin_page("pid", $pid);
        //get_admin_enrollment_form(false,$pid);
    }
}

function deactivate_program() {
    global $CFG, $MYVARS;
    $pid = empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"];
    if ($pid) {
        execute_db_sql("UPDATE programs SET active=0");
        echo get_admin_page("pid", $pid);
        //get_admin_enrollment_form(false,$pid);
    }
}

function delete_program() {
    global $CFG, $MYVARS;
    $pid = empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"];
    if ($pid) {
        execute_db_sql("DELETE FROM programs WHERE pid='$pid'");
        execute_db_sql("DELETE FROM enrollments WHERE pid='$pid'");
        execute_db_sql("DELETE FROM activity WHERE pid='$pid'");
        execute_db_sql("DELETE FROM billing WHERE pid='$pid'");
        execute_db_sql("DELETE FROM billing_payments WHERE pid='$pid'");
        execute_db_sql("DELETE FROM billing_perchild WHERE pid='$pid'");
        execute_db_sql("DELETE FROM events_required_notes WHERE evid IN (SELECT evid FROM events WHERE pid='$pid')");
        execute_db_sql("DELETE FROM events WHERE pid='$pid'");
        execute_db_sql("DELETE FROM notes WHERE pid='$pid'");
        execute_db_sql("DELETE FROM notes_required WHERE pid='$pid'");
        echo get_admin_page("pid");
        //get_admin_enrollment_form(false,$pid);
    }
}

function activate_account() {
    global $CFG, $MYVARS;
    $aid = empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"];
    if ($aid) {
        execute_db_sql("UPDATE accounts SET deleted=0 WHERE aid='$aid'");
        execute_db_sql("UPDATE enrollments SET deleted=0 WHERE chid IN (SELECT children WHERE aid='$aid')");
        execute_db_sql("UPDATE children SET deleted=0 WHERE aid='$aid'");
        execute_db_sql("UPDATE contacts SET deleted=0 WHERE aid='$aid'");
        get_admin_accounts_form(false, $aid);
    }
}

function activate_employee() {
    global $CFG, $MYVARS;
    $employeeid = empty($MYVARS->GET["employeeid"]) ? false : $MYVARS->GET["employeeid"];
    if ($employeeid) {
        execute_db_sql("UPDATE employee SET deleted=0 WHERE employeeid='$employeeid'");
        get_admin_employees_form(false, $employeeid);
    }
}

function delete_employee() {
    global $CFG, $MYVARS;
    $employeeid = empty($MYVARS->GET["employeeid"]) ? false : $MYVARS->GET["employeeid"];
    if ($employeeid) {
        execute_db_sql("UPDATE employee SET deleted=1 WHERE employeeid='$employeeid'");
        get_admin_employees_form(false, $employeeid);
    }
}

function delete_account() {
    global $CFG, $MYVARS;
    $aid = empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"];
    if ($aid) {
        execute_db_sql("UPDATE accounts SET deleted=1 WHERE aid='$aid'");
        execute_db_sql("UPDATE enrollments SET deleted=1 WHERE chid IN (SELECT children WHERE aid='$aid')");
        execute_db_sql("UPDATE children SET deleted=1 WHERE aid='$aid'");
        execute_db_sql("UPDATE contacts SET deleted=1 WHERE aid='$aid'");
        $MYVARS->GET["aid"] = '';
        get_admin_accounts_form();
    }
}

function delete_contact() {
    global $CFG, $MYVARS;
    $cid     = empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"];
    $contact = get_db_row("SELECT * FROM contacts WHERE cid='$cid'");
    $account = get_db_row("SELECT * FROM accounts WHERE aid='" . $contact["aid"] . "'");
    $deleted = empty($contact["deleted"]) ? "1" : "0";
    if ($cid) {
        execute_db_sql("UPDATE contacts SET deleted='$deleted' WHERE cid='$cid'");
        if (!empty($account["deleted"]) && empty($deleted)) {
            execute_db_sql("UPDATE accounts SET deleted='$deleted' WHERE aid='" . $account["aid"] . "'");
        }
    }
}

function delete_child() {
    global $CFG, $MYVARS;
    $chid    = empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"];
    $child   = get_db_row("SELECT * FROM children WHERE chid='$chid'");
    $account = get_db_row("SELECT * FROM accounts WHERE aid='" . $child["aid"] . "'");
    $deleted = empty($child["deleted"]) ? "1" : "0";
    if ($chid) {
        execute_db_sql("UPDATE children SET deleted='$deleted' WHERE chid='$chid'");
        if (!empty($account["deleted"]) && empty($deleted)) {
            execute_db_sql("UPDATE accounts SET deleted='$deleted' WHERE aid='" . $account["aid"] . "'");
        }
    }
}

function toggle_enrollment() {
    global $CFG, $MYVARS;
    $fields         = empty($MYVARS->GET["values"]) ? array() : $MYVARS->GET["values"];
    $days_attending = "";
    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "eid":
            case "aid":
            case "chid":
            case "pid":
            case "exempt":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "M":
            case "T":
            case "W":
            case "Th":
            case "F":
                $days_attending .= empty($days_attending) ? dbescape($field["value"]) : "," . dbescape($field["value"]);
                break;
        }
    }

    $pid      = empty($pid) ? (empty($MYVARS->GET["pid"]) ? get_pid() : $MYVARS->GET["pid"]) : $pid;
    $chid     = empty($chid) ? (empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"]) : $chid;
    $callback = empty($callback) ? false : $callback;
    if (!empty($eid)) {
        execute_db_sql("UPDATE enrollments SET days_attending='$days_attending', exempt='$exempt' WHERE eid='$eid'");
    } elseif ($chid && $pid) {
        if (get_db_row("SELECT * FROM enrollments WHERE pid='$pid' AND chid='$chid'")) {
            execute_db_sql("DELETE FROM enrollments WHERE pid='$pid' AND chid='$chid'");
        } else {
            execute_db_sql("INSERT INTO enrollments (pid,chid,days_attending,exempt) VALUES('$pid','$chid','$days_attending','$exempt')");
        }
    }

    switch ($callback) {
        case "accounts":
            get_admin_accounts_form(false, $aid);
            break;
        case "children":
            get_admin_children_form(false, $chid);
            break;
        case "programs":
            get_admin_enrollment_form(false, $pid);
            break;
    }
}

function toggle_exemption() {
    global $CFG, $MYVARS;
    $id       = $MYVARS->GET["id"];
    $perchild = get_db_row("SELECT * FROM billing_perchild WHERE id='$id'");
    $aid      = get_db_field("aid", "children", "chid='" . $perchild["chid"] . "'");
    if (empty($perchild["exempt"])) {
        execute_db_sql("UPDATE billing_perchild SET exempt='1' WHERE id='$id'");
    } else {
        execute_db_sql("UPDATE billing_perchild SET exempt='0' WHERE id='$id'");
    }

    //Now you must redo the entire week's invoices for that account
    execute_db_sql("DELETE FROM billing WHERE fromdate='" . $perchild["fromdate"] . "' AND pid='" . $perchild["pid"] . "' AND aid='$aid'");
    make_account_invoice($perchild["pid"], $aid, $perchild["fromdate"]);
}

function children_document_link($chid, $tag) {
    global $CFG;
    if ($document = get_db_row("SELECT * FROM documents WHERE chid='$chid' AND tag='$tag'")) {
        return $CFG->wwwroot . "/files/children/$chid/" . $document["filename"];
    }
    return false;
}

function view_required_notes_form($pid = false, $evid = false) {
    global $CFG, $MYVARS;
    $pid  = $pid ? $pid : (empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"]);
    $evid = $evid ? $evid : (empty($MYVARS->GET["evid"]) ? false : $MYVARS->GET["evid"]);

    $notes_list = "";
    if ($events = get_db_result("SELECT * FROM events_required_notes e JOIN notes_required n ON e.rnid = n.rnid WHERE n.deleted=0 AND e.evid='$evid' ORDER BY e.sort")) {
        $notes_list .= '<strong>Required Notes:</strong>
        <button type="button" style="font-size:9px;float:right;" onclick="$.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'add_required_notes_form\',pid:\'' . $pid . '\',evid: \'' . $evid . '\' },
              success: function(data) { $(\'#required_notes_div_pid_' . $pid . '\').html(data); }
            });">Add Required Event Note</button><br /><br /><ul id="sortable">';
        while ($event = fetch_row($events)) {
            $save          = '<button type="button" style="font-size:9px;float:right;" onclick="$.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'save_required_notes\',pid:\'' . $pid . '\',rnid:\'' . $event["rnid"] . '\',evid: \'' . $evid . '\',values: $(\'.fields\',\'li#' . $event["rnid"] . '\').serializeArray() },
              success: function(data) { $(\'#required_notes_div_pid_' . $pid . '\').html(data); }
            });">Save</button>';
            $delete        = '<button type="button" style="font-size:9px;float:right;" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to delete this required note?\', \'Yes\', \'No\', function(){ $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'delete_required_notes\',pid:\'' . $pid . '\',rnid:\'' . $event["rnid"] . '\',evid: \'' . $evid . '\' },
              success: function(data) { $(\'#required_notes_div_pid_' . $pid . '\').html(data); $(\'#sortable\').sortable({
                                update : function () {
                                    var serial = $(\'#sortable\').sortable(\'toArray\');
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'required_notes_sort\',serial: serial,pid:\'' . $pid . '\',evid: \'' . $evid . '\' },
                                    });
                                }
                            }); $(\'#sortable\').disableSelection(); }
            });}, function(){})">Delete</button>';
            $question_type = get_db_row("SELECT nid FROM notes WHERE rnid='" . $event["rnid"] . "'") ? '<input class="fields" type="hidden" name="question_type" id="question_type" value="' . $event["question_type"] . '" />' . $event["question_type"] : make_select_from_array("question_type", get_note_type_array(), "id", "name", "fields", $event["question_type"]);
            $notes_list .= '<li id="' . $event["rnid"] . '" class="ui-state-default"><input class="fields" type="hidden" name="rnid" value="' . $event["rnid"] . '" /><span class="draggable ui-icon ui-icon-arrowthick-2-n-s"></span>&nbsp;&nbsp;Title: <input class="fields" type="text" name="title" id="title" value="' . $event["title"] . '" />&nbsp;&nbsp;Type: ' . $question_type . '<span style="float:right;position: initial;">' . $delete . ' ' . $save . '</span></li>';
        }
        $notes_list .= '</ul>';
    } else {
        $notes_list .= '<strong>Required Notes:</strong>
        <button type="button" style="font-size:9px;float:right;" onclick="$.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'add_required_notes_form\',pid:\'' . $pid . '\',evid: \'' . $evid . '\' },
              success: function(data) { $(\'#required_notes_div_pid_' . $pid . '\').html(data); }
            });">Add Required Event Note</button><br /><br />None';
    }
    echo $notes_list;
}

function save_required_notes() {
    global $CFG, $MYVARS;
    $fields         = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $days_attending = "";
    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "rnid":
            case "question_type":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "title":
                ${$field["name"]} = dbescape($field["value"]);
                $tag   = strtolower(preg_replace("/[^a-zA-Z0-9\s]/", "_", $title));
                break;
        }
    }

    $pid  = empty($pid) ? (empty($MYVARS->GET["pid"]) ? get_pid() : $MYVARS->GET["pid"]) : $pid;
    $evid = empty($evid) ? (empty($MYVARS->GET["evid"]) ? false : $MYVARS->GET["evid"]) : $evid;
    $rnid = empty($rnid) ? (empty($MYVARS->GET["rnid"]) ? false : $MYVARS->GET["rnid"]) : $rnid;

    if (empty($rnid)) { //Add new
        $rnid = execute_db_sql("INSERT INTO notes_required (pid,type,tag,title,question_type,deleted) VALUES('$pid','actid','$tag','$title','$question_type',0)");
        $sort = get_db_count("SELECT * FROM events_required_notes WHERE evid='$evid'");
        $sort++;
        execute_db_sql("INSERT INTO events_required_notes (evid,rnid,sort) VALUES('$evid','$rnid','$sort')");
    } else {
        $oldnote = get_db_row("SELECT * FROM notes_required WHERE rnid='$rnid'");
        execute_db_sql("UPDATE notes_required SET title='$title',tag='$tag' WHERE rnid='$rnid'");
        execute_db_sql("UPDATE notes SET note=REPLACE(note, '" . $oldnote["title"] . ":', '$title:'),tag='$tag' WHERE rnid='$rnid'");
    }

    echo view_required_notes_form($pid, $evid);
}

function add_required_notes_form() {
    global $CFG, $MYVARS;
    $pid  = empty($pid) ? (empty($MYVARS->GET["pid"]) ? get_pid() : $MYVARS->GET["pid"]) : $pid;
    $evid = empty($evid) ? (empty($MYVARS->GET["evid"]) ? false : $MYVARS->GET["evid"]) : $evid;
    echo '<strong>Add Required Event Note:</strong><br /><br />
    <ul id="sortable">
        <li id="addone" class="ui-state-default">&nbsp;&nbsp;Title: <input class="fields" name="title" id="title" type="text" value="" />&nbsp;&nbsp;Type: ' . make_select_from_array("question_type", get_note_type_array(), "id", "name", "fields") . '
            <button type="button" style="font-size:9px;float:right;" onclick="if($(\'#name\',\'li#addone\').val() != \'\'){ $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'save_required_notes\',pid:\'' . $pid . '\',evid: \'' . $evid . '\',values: $(\'.fields\',\'li#addone\').serializeArray() },
              success: function(data) { $(\'#required_notes_div_pid_' . $pid . '\').html(data); $(\'#sortable\').sortable({
                                update : function () {
                                    var serial = $(\'#sortable\').sortable(\'toArray\');
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'required_notes_sort\',serial: serial,pid:\'' . $pid . '\',evid: \'' . $evid . '\' },
                                    });
                                }
                            }); $(\'#sortable\').disableSelection();}
            });}">Save</button></li>
    </ul>';
}

function delete_required_notes() {
    global $CFG, $MYVARS;
    $pid  = empty($pid) ? (empty($MYVARS->GET["pid"]) ? get_pid() : $MYVARS->GET["pid"]) : $pid;
    $evid = empty($evid) ? (empty($MYVARS->GET["evid"]) ? false : $MYVARS->GET["evid"]) : $evid;
    $rnid = empty($rnid) ? (empty($MYVARS->GET["rnid"]) ? false : $MYVARS->GET["rnid"]) : $rnid;

    if (get_db_row("SELECT nid FROM notes WHERE rnid='$rnid'")) { //Been used already
        execute_db_sql("UPDATE notes_required SET deleted='1' WHERE rnid='$rnid'");
    } else {
        execute_db_sql("DELETE FROM notes_required WHERE rnid='$rnid'");
    }

    execute_db_sql("DELETE FROM events_required_notes WHERE rnid='$rnid'");

    required_notes_resort($evid);
    echo view_required_notes_form($pid, $evid);
}

function required_notes_sort() {
    global $CFG, $MYVARS;
    $pid   = $MYVARS->GET["pid"];
    $evid  = $MYVARS->GET["evid"];
    $rnids = $MYVARS->GET["serial"];

    $i = 1;
    foreach ($rnids as $rnid) {
        execute_db_sql("UPDATE events_required_notes SET sort='$i' WHERE rnid='$rnid'");
        $i++;
    }
}

function required_notes_resort($evid) {
    global $CFG, $MYVARS;

    if ($notes = get_db_result("SELECT * FROM events_required_notes WHERE evid='$evid' ORDER BY sort")) {
        $i = 1;
        while ($note = fetch_row($notes)) {
            execute_db_sql("UPDATE events_required_notes SET sort='$i' WHERE rnid='" . $note["rnid"] . "'");
            $i++;
        }
    }
}
?>