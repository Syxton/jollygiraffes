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

function employee_timesheet($thisweekpay = false) {
    global $CFG, $MYVARS;

    // Get all active employees
    $SQL = "SELECT * FROM employee WHERE deleted = 0 ORDER BY last,first";
    if ($result = get_db_result($SQL)) {
        $in = $out = "";

        while ($row = fetch_row($result)) {
            $employee_button = '
                <div class="employee_wrapper ui-corner-all">
                    ' . get_employee_button($row["employeeid"]) . '
                </div>';

            $checked_in = is_working($row["employeeid"]);
            if ($checked_in) {
                $in .= $employee_button;
            } else {
                $out .= $employee_button;
            }
        }

        $showpaystub = "";
        if ($thisweekpay) {
            $showpaystub = '<div class="paystub">Pay Stub: $' . $thisweekpay . '</div>';
        }

        $returnme = from_template("employee_signinout_layout.php", [
            "in" => $in,
            "out" => $out,
            "showpaystub" => $showpaystub,
            "home_button" => go_home_button('Exit'),
            "numpad" => get_numpad("", false, "employee", "#display_level", 'employee_numpad'),
        ]);
    }

    echo $returnme;
}

function check_in_out_employee() {
    global $CFG, $MYVARS;
    $employeeid   = $MYVARS->GET["employeeid"];
    $employee     = get_db_row("SELECT * FROM employee WHERE employeeid='$employeeid'");
    $time         = get_timestamp();
    $readabletime = get_date("l, F j, Y \a\\t g:i a", display_time($time));
    $thisweekpay = false;

    if (is_working($employeeid)) { // check out
        $event = get_db_row("SELECT * FROM events WHERE tag='out'");
        $SQL = "INSERT INTO employee_activity (employeeid, evid, tag, timelog)
                VALUES ('$employeeid', '" . $event["evid"] . "', '" . $event["tag"] . "', $time)";
        $actid = execute_db_sql($SQL);
        $note  = $employee["first"] . " " . $employee["last"] . ": Signed out at $readabletime";
        $thisweekpay = get_wages_for_this_week($employeeid);
    } else { // check in
        $event = get_db_row("SELECT * FROM events WHERE tag='in'");
        $SQL = "INSERT INTO employee_activity (employeeid, evid, tag, timelog)
                VALUES ('$employeeid', '" . $event["evid"] . "', '" . $event["tag"] . "', $time)";
        $actid = execute_db_sql($SQL);
        $note  = $employee["first"] . " " . $employee["last"] . ": Signed in at: $readabletime";
    }

    $SQL = "INSERT INTO notes (actid, employeeid, tag, note, data, timelog)
            VALUES ('$actid', '$employeeid', '" . $event["tag"] . "', '$note', 1, $time)";
    execute_db_sql($SQL);

    echo employee_timesheet($thisweekpay);
}

function get_check_in_out_form() {
    global $CFG, $MYVARS;
    $type        = $MYVARS->GET["type"];
    $lastinitial = false;
    $pid         = get_pid();

    $letters = $children = '';

    // Get all active children
    $SQL = "SELECT *
            FROM children
            WHERE deleted = 0
            AND chid IN (
                SELECT chid
                FROM enrollments
                WHERE deleted = 0
                AND pid = '$pid'
            )
            ORDER BY last, first";

    if ($result = get_db_result($SQL)) {
        while ($row = fetch_row($result)) {
            $aid = $row["aid"];
            $chid = $row["chid"];
            $checked_in = is_checked_in($chid);
            if (($type == "in" && !$checked_in) || ($type == "out" && $checked_in)) {
                $letter = strtoupper(substr($row["last"], 0, 1));
                if (!$lastinitial || ($lastinitial != substr($row["last"], 0, 1))) {
                    $letters .= from_template("alphabet_letter.php", [
                        "letter" => $letter,
                    ]);
                }

                // Highlight children with the expected attendance today.
                $expected = "";
                if (is_expected_today($pid, $chid)) {
                    $expected = "expected-today";
                }

                // Create action button for child button.
                $action = from_template("action_selectchild.php", [
                    "chid" => $chid,
                    "aid" => $aid
                ]);

                $children .= from_template("childbutton.php", [
                    "chid" => $chid,
                    "containerclass" => "child_wrapper ui-corner-all " . $expected,
                    "action" => $action,
                ]);

                $lastinitial = substr($row["last"], 0, 1); // store last initial
            }
        }
    }

    echo from_template("inoutform1.php", [
        "home_button" => go_home_button(),
        "alphabet" => from_template("alphabet.php", ["letters" => $letters]),
        "children" => $children,
        "type" => $type
    ]);
}

function check_in_out_form() {
    global $CFG, $MYVARS;
    $type     = $MYVARS->GET["type"];
    $admin    = !empty($MYVARS->GET["admin"]) && $MYVARS->GET["admin"] != "false" ? true : false;
    $chids    = $MYVARS->GET["chid"];
    $aid      = $admin ? 0 : get_db_field("aid", "children", "chid='" . $chids[0]["value"] . "' AND deleted=0");
    $returnme = $notes = $numpads = $questions_open = $questions_closed = "";

    $children = "";
    foreach ($chids as $chid) {
        $children .= from_template("childbutton.php", [
            "chid" => $chid["value"],
            "containerclass" => (empty($notes) ? 'child_wrapper ui-corner-all' : 'break'),
            "buttonstyles" => "float:left;",
            "piconly" => true,
        ]);
    }

    // fill template variables
    $note_header = get_required_notes_header($type);
    $notes    = get_required_notes_forms($type);
    $contacts = get_contacts_selector($chids, $admin);

    // questions validator
    $SQL = "SELECT *
            FROM notes_required n
            JOIN (
                SELECT *
                FROM events_required_notes
                WHERE evid IN (
                    SELECT evid
                    FROM events
                    WHERE tag = '$type'
                    AND (
                        pid = '" . get_pid() . "'
                        OR pid = '0'
                    )
                )
            ) r ON r.rnid = n.rnid
            WHERE n.deleted = 0
            ORDER BY r.sort";
    $qnum = get_db_count($SQL);
    if ($qnum) {
        $questions_open = '
            var selected = true;
            $(\'.notes_values\').each(function() {
                selected = $(this).toggleSwitch({ toggleset: true } ) ? selected : false;
            });
            if (selected) {';
        $questions_closed = '
            } else {
                CreateAlert(\'dialog-confirm\', \'You must answer every question.\', \'Ok\', function() {});
            }';
    }

    // numpads
    if (!$admin) {
        $numpads .= get_numpad($aid, true, $type, '#display_level', 'admin_numpad');
    }
    $numpads .= get_numpad($aid, $admin, $type);

    echo from_template("inoutform2.php", [
        "type" => $type,
        "children" => $children,
        "contacts" => $contacts,
        "notes" => $notes,
        "notes_header" => $note_header,
        "numpads" => $numpads,
        "qnum" => $qnum,
    ]);
}

function check_in_out($chids, $cid, $type, $time = false) {
    global $CFG, $MYVARS;
    $returnme = $notify = "";
    $rnids    = !empty($MYVARS->GET["rnid"]) ? $MYVARS->GET["rnid"] : false;
    $values   = !empty($MYVARS->GET["values"]) ? $MYVARS->GET["values"] : false;

    $pid = get_pid();

    $lastinvoice = get_db_field("MAX(todate)", "billing_perchild", "pid='$pid'");

    if ($lastinvoice < strtotime("previous Saturday")) {
        // no invoices made lately, build them all now
        create_invoices(true, $pid, false);
    }

    $event = get_db_row("SELECT * FROM events WHERE pid='$pid' OR pid='0' AND tag='$type'");
    $time = empty($time) ? get_timestamp() : $time;
    $readabletime = get_date("l, F j, Y \a\\t g:i a", display_time($time));
    $contact = get_contact_name($cid);

    $returnme .= go_home_button();
    $remaining_balance = "";
    if ($type == "out" && $cid != "admin") {
        $aid           = get_db_field("aid", "children", "chid='" . $chids[0]["value"] . "'");
        $balance       = account_balance($pid, $aid); // Previous weeks combined total - paid
        $current_week  = week_balance($pid, $aid); // Current weeks total
        $method        = get_enrollment_method($pid, $aid);
        $exempt        = get_db_field("exempt", "enrollments", "chid='" . $chids[0]["value"] . "' AND pid='$pid'");
        $payahead      = get_db_field("payahead", "programs", "pid='$pid'");
        $float_balance = (float) $balance;
        $float_current = (float) $current_week;
        $combined_balance = $float_balance + $float_current;

        if (!$exempt) {
            if ($method == "enrollment") { // Flat rate based on days they are expected to attend
                if ($combined_balance <= 0) { // They have paid more than they previously owed
                    $message1 = "You are currently paid up. Thanks!";
                    if ($payahead) {
                        $next_week          = week_balance($pid, $aid, true, true); // Next weeks total
                        $combined_balance  += (float) $next_week;
                        $params = [
                            "message1" => $message1,
                            "message2" => "Payment of $" . number_format($combined_balance, 2) . " is due ahead of next weeks services.",
                        ];
                    } else {
                        $params = ["message1" => $message1];
                    }
                } else {
                    $message1 = "Your account has a balance of $" . number_format($combined_balance, 2) . " is due.";
                    if ($payahead) {
                        $next_week          = week_balance($pid, $aid, true, true); // Next weeks total
                        $next_week          = (float) $next_week;
                        $params = [
                            "message1" => $message1,
                            "message2" => "An additional payment of $" . number_format($next_week, 2) . " is due ahead of next weeks services.",
                        ];
                    } else {
                        $params = ["message1" => $message1];
                    }
                }
            } else { // Rate based on actual attendance
                if ($combined_balance <= 0) {
                    $message1 = "You are currently paid up. Thanks!";
                    if ($payahead) {
                        $next_week          = week_balance($pid, $aid, true, true); // Next weeks total
                        $combined_balance  += (float) $next_week;
                        $params = [
                            "message1" => $message1,
                            "message2" => "Payment of $" . number_format($combined_balance, 2) . " is due ahead of next weeks services.",
                        ];
                    } else {
                        $params = ["message1" => $message1];
                    }
                } else {
                    $message1 = "Your account has a balance of $" . number_format($combined_balance, 2) . " is due.";
                    if ($payahead) {
                        $next_week          = week_balance($pid, $aid, true, true); // Next weeks total
                        $next_week          = (float) $next_week;
                        $params = [
                            "message1" => $message1,
                            "message2" => "Payment of $" . number_format($combined_balance, 2) . " is due ahead of next weeks services.",
                        ];
                    } else {
                        $params = [
                            "message1" => $message1,
                            "message2" => "So far this week you owe $" . number_format($float_current, 2) . ".",
                        ];
                    }
                }
            }
            $remaining_balance = from_template("notice_remaining_balance.php", $params);
        }
    }

    $childcount  = count($chids);
    $notes_count = !empty($rnids) && count($rnids) ? count($rnids) / $childcount : false;
    $notified    = [];
    $c           = 1; // Child counter
    $i           = 0; // Note counter
    $content = "";
    foreach ($chids as $chid) {
        // Signed out
        $child = get_db_row("SELECT * FROM children WHERE chid='" . $chid["value"] . "' AND deleted=0");
        $note  = $child["first"] . " " . $child["last"] . ": Checked $type by $contact: $readabletime";

        // birthday flag
        $confetti_start = $confetti_stop = $bday = "";
        if (date("md", $child["birthdate"] + get_offset()) == date("md", get_timestamp())) {
            $confetti_start = 'confetti.start();';
            $bday = '<h1 class="heading" style="font-size:4em">Happy Birthday!</h1>';
        }

        // prevents duplicate entries -- not sure why it is happening
        if (!get_db_row("SELECT timelog FROM activity WHERE timelog='$time' AND chid='" . $chid["value"] . "'") && $actid = execute_db_sql("INSERT INTO activity (pid, aid, chid, cid, evid, tag, timelog) VALUES('$pid', '" . $child["aid"] . "', '" . $chid["value"] . "', '$cid', '" . $event["evid"] . "', '" . $event["tag"] . "', $time) ")) {
            // Record a note with who checked them in
            execute_db_sql("INSERT INTO notes (pid, aid, chid, actid, cid, tag, note, data, timelog) VALUES('" . $pid . "', '" . $child["aid"] . "', '" . $chid["value"] . "', '$actid', '$cid', '" . $event["tag"] . "', '$note', 1, $time) ");
            $req_notes_text = "";
            // If there are notes, record them now
            if (!empty($notes_count)) {
                $req_notes_text .= '<span style="display: inline-block; padding: 4px; margin: 4px;">';
                while ($i < ($notes_count * $c)) {
                    $rnid          = $rnids[$i]["value"];
                    $setting       = $values[$i]["value"];
                    $req_note      = get_db_row("SELECT * FROM notes_required WHERE rnid='$rnid'");
                    $req_note_text = get_note_text($req_note, $setting);
                    $req_notes_text .= $req_note_text . "<br />";
                    execute_db_sql("INSERT INTO notes (pid, aid, chid, actid, cid, rnid, tag, note, data, timelog) VALUES('" . $pid . "', '" . $child["aid"] . "', '" . $chid["value"] . "', '$actid', '$cid', '$rnid', '" . $req_note["tag"] . "', '$req_note_text', '$setting', $time) ");
                    $i++;
                }
                $req_notes_text .= "</span>";
            }
            $c++;

            // Child button.
            $content .= from_template("childbutton.php", [
                "chid" => $chid["value"],
                "containerclass" => "child_wrapper ui-corner-all ",
                "piconly" => true,
                "afterbutton" => $req_notes_text,
            ]);

            if ($type == "out") {
                if (array_search($child["aid"], $notified) === false) {
                    $notify     = get_notifications($pid, false, $child["aid"], true) . $notify; // add account bulletins to the top
                    $notified[] = $child["aid"];
                }
                $notify .= get_notifications($pid, $chid["value"], false, true);
            }
        }
    }

    if ($type == "out") { // Program bulletins
        $notify = get_notifications($pid, false, false, true) . $notify; // add program bulletins to the top
    }

    $wait = empty($notify) ? "6000" : "15000"; // if there are notifications, give them more time to read

    $returnme .= $bday . '<div class="heading" style="margin:0px 10px;"><h1>Checked ' . ucwords($type) . ' on ' . $readabletime . ' by ' . $contact . '</h1>' . $remaining_balance . '</div>
                 <div class="container_main scroll-pane ui-corner-all fill_height_middle">' . $content . '</div>';

    if ($type == "out" && !empty($notify)) {
        $returnme .= '
            <div class="bottom center ui-corner-all">
                <span style="display:inline-flex;font-size:2em;align-items:center;justify-content:center;padding:10px;">
                    ' . icon("circle-exclamation") . '
                    <span style="padding-left: 10px;">
                        <strong>Attention</strong>
                    </span>
                </span>
                ' . $notify . '
            </div>';
    }

    $returnme .= '<script type="text/javascript">
        ' . $confetti_start . '
        var autoback = setTimeout(function() {
            ' . $confetti_stop . '
            location.reload();
        } ,' . $wait . ');
    </script>';
    return $returnme;
}

function get_notifications($pid, $chid = false, $aid = false, $separate = false, $tagonly = false) {
    global $CFG;
    $notify = "";
    if (empty($aid)) { // Get aid from chid
        $aid = get_db_field("aid", "children", "chid='$chid'");
    }

    if (empty($separate)) { // any combine notifications?
        if ($chid) { // child and bulletin material
            $SQL = "SELECT * FROM notes WHERE ((chid='$chid' AND pid='$pid') || (tag='bulletin' AND (aid='$aid' OR pid='$pid'))) AND ((notify=1 AND CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(FROM_UNIXTIME(timelog+" . get_offset($CFG->servertz) . ")))='" . date("Ynj", get_timestamp($CFG->timezone)) . "') OR (notify=2)) ORDER BY timelog";
        } else { // bulletin only
            $SQL = "SELECT * FROM notes WHERE (tag='bulletin' AND (aid='$aid' OR pid='$pid')) AND ((notify=1 AND CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'" . get_date('P', time(), $CFG->servertz) . "','" . get_date('P', time(), $CFG->timezone) . "')))='" . date("Ynj", get_timestamp($CFG->timezone)) . "') OR (notify=2)) ORDER BY timelog";
        }
    } else { // specific context notifications, usually for display purposes
        if (!empty($chid)) { // child notes
            $name = get_name([
                "type" => "chid",
                "id"   => $chid
            ]);
            $SQL  = "SELECT * FROM notes WHERE (chid='$chid' AND pid='$pid') AND ((notify=1 AND CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(FROM_UNIXTIME(timelog+" . get_offset($CFG->servertz) . ")))='" . date("Ynj", get_timestamp($CFG->timezone)) . "') OR (notify=2)) ORDER BY timelog";
        } elseif (!empty($aid)) { // account bulletins
            $name = get_name([
                "type" => "aid",
                "id"   => $aid
            ]);
            $SQL  = "SELECT * FROM notes WHERE (tag='bulletin' AND aid='$aid') AND ((notify=1 AND CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(FROM_UNIXTIME(timelog+" . get_offset($CFG->servertz) . ")))='" . date("Ynj", get_timestamp($CFG->timezone)) . "') OR (notify=2)) ORDER BY timelog";
        } else { // program bulletins
            $name = get_name([
                "type" => "pid",
                "id"   => $pid
            ]);
            $SQL  = "SELECT * FROM notes WHERE (tag='bulletin' AND pid='$pid') AND ((notify=1 AND CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(FROM_UNIXTIME(timelog+" . get_offset($CFG->servertz) . ")))='" . date("Ynj", get_timestamp($CFG->timezone)) . "') OR (notify=2)) ORDER BY timelog";
        }
    }

    if ($notifications = get_db_result($SQL)) {
        while ($notification = fetch_row($notifications)) {
            $tag = get_tag([
                "type" => "notes",
                "tag"  => $notification["tag"]
            ]);

            // Name based on each tag.
            if (!empty($notification["chid"])) {
                $name = get_name([
                    "type" => "chid",
                    "id"   => $notification["chid"]
                ]);
            } elseif (!empty($notification["aid"])) {
                $name = get_name([
                    "type" => "aid",
                    "id"   => $aid
                ]) . " Account";
            } else {
                $name = get_name([
                    "type" => "pid",
                    "id"   => $pid
                ]);
            }

            // save bulletins and compare so duplicates are not shown
            if ($tagonly) {
                $notify .= from_template("notify_tagonly_layout.php", [
                    "tag" => $tag,
                ]);
            } else {
                $notify .= from_template("notify_layout.php", [
                    "tag" => $tag,
                    "name" => $name,
                    "note" => $notification["note"],
                ]);
            }
        }

        if ($tagonly) {
            $notify = '<div class="notify_tagonly">' . $notify . '</div>';
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

    if (empty($type) && $admin && get_db_row("SELECT aid FROM accounts WHERE admin=1 AND password='$password'")) { // admin login
        $returnme = get_admin_page();
    } elseif (((!$admin && $type != "employee") || ($admin && empty($aid)) || ($admin && !empty($aid) && strstr($MYVARS->GET["cid"][0]["name"], "_other"))) && get_db_row("SELECT aid FROM accounts WHERE (aid='$aid' AND deleted=0 AND password='$password') OR (admin=1 AND password='$password')")) { // student check in / out
        if (strstr($MYVARS->GET["cid"][0]["name"], "_other")) { // Make "other" contact
            if (strstr($cid, " ")) { // has space so assume first and last name
                $name  = explode(" ", $cid);
                $first = $name[0];
                $last  = $name[1];
            } else {
                $first = $cid;
                $last  = "";
            }

            if (!$cid = get_db_field("cid", "contacts", "aid='$aid' AND first='$first' AND last='$last'")) {
                $SQL = "INSERT INTO contacts (aid, first, last, relation, home_address, phone1, phone2, phone3, phone4, employer, employer_address, hours, emergency) VALUES('$aid', '$first', '$last', '', '', '', '', '', '', '', '', '', 0)";
                if (!$cid = execute_db_sql($SQL)) { // Fails
                    echo "false";
                    exit();
                }
            }
        }
        $returnme = check_in_out($chids, $cid, $type);
    } elseif (!$admin && $type == "employee" && get_db_row("SELECT employeeid FROM employee WHERE (employeeid='$employeeid' AND deleted=0 AND password='$password') OR 1 = (SELECT admin FROM accounts WHERE admin=1 AND password='$password')")) { // employee sign in / out
        $returnme = check_in_out_employee($employeeid);
    } else { // failed validation
        echo "false";
        exit();
    }
    echo $returnme;
}

function get_admin_page($type = false, $id = false) {
    $activepid = get_pid();

    // checks for software updates
    check_and_run_upgrades();

    // checks employee check in and out status
    closeout_thisweek();

    // run book keeping if needed
    $lastinvoice = get_db_field("MAX(todate)", "billing_perchild", "pid='$activepid'");
    if ($lastinvoice < strtotime("previous Saturday")) {
        // no invoices made lately, build them all now
        create_invoices(true, $activepid, false);
    }

    $programname = get_db_field("name", "programs", "pid='$activepid'");
    $programname = empty($programname) ? "No Active Program" : $programname;
    $account     = get_db_row("SELECT * FROM accounts WHERE admin='1'");

    $enrollment_selected = $account_selected = $contacts_selected = $tag_selected = $employees_selected = $billing_selected = $children_selected = "";
    if (!empty($type)) {
        if ($type == "pid") {
            $form = get_admin_enrollment_form(true, $id);
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

    $active_display = $activepid ? "" : "display:none;";

    $identifier  = time() . "edit_account_" . $account["aid"];
    $returnme = get_form("add_edit_account", ["account" => $account], $identifier) . '
        <span id="activepidname" class="top-center">' . $programname . '</span>
        <button title="Edit Admin" class="topright_button" type="button" onclick="CreateDialog(\'add_edit_account_' . $identifier . '\', 200, 315)">
            Edit Admin
        </button>
        ' . go_home_button('Exit Admin') . '
        ' . from_template("admin_layout.php", [
            "account_selected"    => $account_selected,
            "enrollment_selected" => $enrollment_selected,
            "contacts_selected"   => $contacts_selected,
            "tag_selected"        => $tag_selected,
            "employees_selected"  => $employees_selected,
            "billing_selected"    => $billing_selected,
            "children_selected"   => $children_selected,
            "active"              => $active_display,
            "pid"                 => $activepid,
            "content"             => $form,
        ]);

    return $returnme;
}

function add_edit_program() {
    global $CFG, $MYVARS;
    $fields = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];

    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "pid":
            case "name":
            case "fein":
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
            $SQL = "UPDATE programs SET name='$name',fein='$fein',timeopen='$timeopen',timeclosed='$timeclosed',perday='$perday',fulltime='$fulltime',minimumactive='$minimumactive',minimuminactive='$minimuminactive',vacation='$vacation',multiple_discount='$multiple_discount',consider_full='$consider_full',bill_by='$bill_by',discount_rule='$discount_rule',payahead='$payahead' WHERE pid='$pid'";
        } else {
            $SQL = "INSERT INTO programs (name, fein, timeopen, timeclosed, perday, fulltime, minimumactive, minimuminactive, vacation, multiple_discount, consider_full, bill_by, discount_rule, payahead) VALUES('$name', '$fein', '$timeopen', '$timeclosed', '$perday', '$fulltime', '$minimumactive', '$minimuminactive', '$vacation', '$multiple_discount', '$consider_full', '$bill_by', '$discount_rule', '$payahead')";
        }

        if (execute_db_sql($SQL)) { // Saved successfully
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
                ${$field["name"]} = make_timestamp_from_date($field["value"], $CFG->timezone);
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
        $SQL = "INSERT INTO billing_payments (pid, aid, payment, timelog, note) VALUES('$pid', '0', '$amount', '$timelog', '$note')";

        if (execute_db_sql($SQL)) { // Saved successfully
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
                    ${$field["name"]} = "'" . str_replace("$", "", dbescape($field["value"])) . "'";
                    $overridemade = true;
                }
                break;
            case "consider_full":
            case "bill_by":
            case "payahead":
                if ($field["value"] == "none") {
                    ${$field["name"]} = "NULL";
                } else {
                    ${$field["name"]} = "'" . dbescape($field["value"]) . "'";
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
        $SQL = "INSERT INTO billing_override (pid, aid, perday, fulltime, minimumactive, minimuminactive, vacation, multiple_discount, consider_full, bill_by, discount_rule, payahead) VALUES($pid, $aid, $perday, $fulltime, $minimumactive, $minimuminactive, $vacation, $multiple_discount, $consider_full, $bill_by, $discount_rule, $payahead)";
    }

    if (execute_db_sql($SQL)) { // Saved successfully
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

    $tag = empty($tag) ? (isset($update) ? $update : false) : $tag;
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
            $SQL = "INSERT INTO $tagtype" . "_tags (title, tag, color, textcolor) VALUES('$title', '$tag', '$color', '$textcolor')";
        }

        if (execute_db_sql($SQL)) { // Saved successfully
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
                ${$field["name"]} = make_timestamp_from_date($field["value"], $CFG->timezone);
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
            $SQL = "INSERT INTO billing_payments (pid, aid, payment, timelog, note) VALUES('$pid', '$aid', '$payment', '$timelog', '$note')";
        }

        if (execute_db_sql($SQL)) { // Saved successfully
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
            $SQL = "INSERT INTO accounts (name, password, meal_status, admin) VALUES('$name', '$password', '$meal_status', '0')";
        }

        if (execute_db_sql($SQL)) { // Saved successfully
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
            if ($oldwage = get_db_row("SELECT * FROM employee_wage WHERE employeeid='$employeeid' ORDER BY dategiven DESC LIMIT 1")) { // wage existed
                if ($oldwage["wage"] != $wage) {
                    if ($oldwage["dategiven"] == $time) {
                        execute_db_sql("UPDATE employee_wage SET wage='$wage' WHERE id='" . $oldwage["id"] . "'");
                    } else {
                        execute_db_sql("INSERT INTO employee_wage (employeeid, wage, dategiven) VALUES('$employeeid', '$wage', '$time')");
                    }
                }
            } else { // no wage entered
                execute_db_sql("INSERT INTO employee_wage (employeeid, wage, dategiven) VALUES('$employeeid', '$wage', '$time')");
            }
            $SQL = "UPDATE employee SET first='$first',last='$last',password='$password' WHERE employeeid='$employeeid'";
        } else {
            $SQL = "INSERT INTO employee (first, last, password, deleted) VALUES('$first', '$last', '$password', '0')";
        }

        if ($id = execute_db_sql($SQL)) { // Saved successfully
            if (empty($employeeid)) {
                execute_db_sql("INSERT INTO employee_wage (employeeid, wage, dategiven) VALUES('$id', '$wage', '$time')");
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
                ${$field["name"]} = make_timestamp_from_date($field["value"], $CFG->timezone);
                break;
        }
    }

    $aid       = empty($aid) ? false : $aid;
    $chid      = empty($chid) ? false : $chid;
    $callback  = empty($callback) ? false : $callback;
    $activepid = get_pid();
    // Validation
    if (empty($first) || empty($last) || empty($birthdate)) {
        echo "false";
    } else {
        if ($chid) {
            $pid = empty($pid) ? $activepid : $pid;
            $SQL = "UPDATE children SET first='$first',last='$last',sex='$sex',birthdate='$birthdate',grade='$grade' WHERE chid='$chid'";
            execute_db_sql($SQL);
        } else {
            $SQL = "INSERT INTO children (aid, first, last, sex, birthdate, grade) VALUES('$aid', '$first', '$last', '$sex', '$birthdate', '$grade')";
            if ($chid = execute_db_sql($SQL)) { // Added successfully
                // Enroll them in the active program
                if ($activepid) {
                    $SQL = "INSERT INTO enrollments (pid, chid, days_attending, exempt) VALUES('$activepid', '$chid', 'M, T, W, Th, F', 0)";
                    execute_db_sql($SQL); // Enrolled successfully
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

    // Validation
    if (empty($first) || empty($last)) {
        echo "false";
    } else {
        if (!empty($cid)) {
            $SQL = "UPDATE contacts SET first='$first',last='$last',relation='$relation',primary_address='$primary_address',home_address='$home_address',phone1='$phone1',phone2='$phone2',phone3='$phone3',phone4='$phone4',employer='$employer',employer_address='$employer_address',hours='$hours',emergency='$emergency' WHERE cid='$cid'";
            execute_db_sql($SQL);
        } elseif (!empty($aid)) {
            $SQL = "INSERT INTO contacts (aid, first, last, relation, primary_address, home_address, phone1, phone2, phone3, phone4, employer, employer_address, hours, emergency) VALUES('$aid', '$first', '$last', '$relation', '$primary_address', '$home_address', '$phone1', '$phone2', '$phone3', '$phone4', '$employer', '$employer_address', '$hours', '$emergency')";
            if (!$cid = execute_db_sql($SQL)) { // Fails
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
                $SQL = "INSERT INTO notes (pid, aid, chid, tag, note, timelog, notify) VALUES('$pid', '$aid', '$chid', '$tag', '$note', '$time', '$notify')";
            } elseif ($aid) {
                $SQL = "INSERT INTO notes (pid, aid, tag, note, timelog, notify) VALUES('$pid', '$aid', '$tag', '$note', '$time', '$notify')";
            } elseif ($cid) {
                $aid = get_db_field("aid", "contacts", "cid='$cid'");
                $SQL = "INSERT INTO notes (pid, aid, cid, tag, note, timelog, notify) VALUES('$pid', '$aid', '$cid', '$tag', '$note', '$time', '$notify')";
            } elseif ($actid) {
                $SQL = "INSERT INTO notes (pid, actid, tag, note, timelog, notify) VALUES('$pid', '$actid', '$tag', '$note', '$time', '$notify')";
            }
        }

        if (execute_db_sql($SQL)) { // Saved successfully
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
    $notify   = empty($notify) ? "0" : "2"; // 2 means it is persistant

    if (!empty($aid)) {
        $nid = get_db_row("SELECT * FROM notes WHERE tag='bulletin' AND pid='0' AND aid='$aid'");
    } else {
        $nid = get_db_row("SELECT * FROM notes WHERE tag='bulletin' AND pid='$pid' AND aid='0'");
    }

    if (!empty($nid)) {
        $SQL = "UPDATE notes SET note='$note',notify='$notify' WHERE nid='" . $nid["nid"] . "'";
    } else {
        if ($aid) {
            $SQL = "INSERT INTO notes (pid, aid, tag, note, timelog, notify) VALUES('0', '$aid', 'bulletin', '$note', '$time', '$notify')";
        } else {
            $SQL = "INSERT INTO notes (pid, aid, tag, note, timelog, notify) VALUES('$pid', '0', 'bulletin', '$note', '$time', '$notify')";
        }
    }

    if (execute_db_sql($SQL)) { // Saved successfully
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
                ${$field["name"]} = make_timestamp_from_date($field["value"], $CFG->timezone);
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
        $chids      = [["value" => $chid]];

        check_in_out($chids, $cid, $tag, $timelog);

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
                $SQL = "INSERT INTO notes (pid, aid, chid, tag, note, timelog) VALUES('$pid', '$aid', '$chid', '$tag', '$note', '$time')";
            } elseif ($aid) {
                $SQL = "INSERT INTO notes (pid, aid, tag, note, timelog) VALUES('$pid', '$aid', '$aid', '$tag', '$note', '$time')";
            } elseif ($cid) {
                $aid = get_db_field("aid", "contacts", "cid='$cid'");
                $SQL = "INSERT INTO notes (pid, aid, cid, tag, note, timelog) VALUES('$pid', '$aid', '$cid', '$tag', '$note', '$time')";
            } elseif ($actid) {
                $SQL = "INSERT INTO notes (pid, actid, tag, note, timelog) VALUES('$pid', '$actid', '$tag', '$note', '$time')";
            }
        }

        if (execute_db_sql($SQL)) { // Saved successfully
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
            case "date":
            case "nid":
            case "newtime":
            case "employeeid":
            case "tab":
            case "tag":
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
    $pid = get_pid();

    if (!empty($newtime) && !empty($date)) {
        $newdatetime      = strtotime($newtime, $date) - get_offset();
        $readabletime = get_date("l, F j, Y \a\\t g:i a", $newdatetime);
    } else {
        return "false";
    }

    $activity = get_db_row("SELECT * FROM employee_activity WHERE actid='" . $vars["actid"] . "'");
    $startofday = strtotime(date("j F Y", $newdatetime));
    $endofday = strtotime("+1 day", strtotime(date("j F Y", $newdatetime)));
    $todaysql = "timelog < '" . $endofday . "' AND timelog > '" . $startofday . "'";
    if ($tag == "in") {
        if ($signout = get_db_field("timelog", "employee_activity", "tag='out' AND employeeid='" . $employeeid . "' AND timelog <= '" . $newdatetime . "' AND $todaysql")) {
            echo "false";
            die;
        }
    } else { // out.
        if ($signin = get_db_field("timelog", "employee_activity", "tag='in' AND employeeid='" . $employeeid . "' AND timelog >= '" . $newdatetime . "' AND $todaysql")) {
            echo "false";
            die;
        }
    }

    if (!empty($employeeid) && !empty($actid) && !empty($nid)) { // EDIT
        $employee   = get_db_row("SELECT * FROM employee WHERE employeeid='$employeeid'");
        $note       = get_db_row("SELECT * FROM notes WHERE nid='$nid'");
        $note       = $employee["first"] . " " . $employee["last"] . ": Signed " . $note["tag"] . " at $readabletime";

        $SQL1 = "UPDATE notes SET note='$note' WHERE nid='$nid'";
        $SQL2 = "UPDATE employee_activity SET timelog='$newdatetime' WHERE actid='$actid'";

        if (execute_db_sql($SQL1) && execute_db_sql($SQL2)) { // Saved successfully
            get_admin_employees_form(false, $employeeid);
        } else {
            echo "false";
        }
    } elseif (!empty($employeeid)) { // ADD
        $employee   = get_db_row("SELECT * FROM employee WHERE employeeid='$employeeid'");
        $note       = get_db_row("SELECT * FROM notes WHERE tag='$tag' AND pid='$pid'");
        $note       = $employee["first"] . " " . $employee["last"] . ": Signed " . $tag . " at $readabletime";

        $event      = get_db_row("SELECT * FROM events WHERE tag='$tag'");
        $SQL1       = "INSERT INTO employee_activity (employeeid, evid, tag, timelog) VALUES('$employeeid', '" . $event["evid"] . "', '" . $event["tag"] . "', $newdatetime)";
        $actid      = execute_db_sql($SQL1);
        $SQL2       = "INSERT INTO notes (actid, employeeid, tag, note, data, timelog) VALUES('$actid', '$employeeid', '" . $event["tag"] . "', '$note', 1, $newdatetime)";
        $note       = execute_db_sql($SQL2);

        if ($actid && $note) { // Saved successfully
            get_admin_employees_form(false, $employeeid);
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}

function refresh_hours() {
    global $CFG, $MYVARS;
    $fields  = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $updated = [];
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
            $timecard = get_db_row("SELECT * FROM employee_timecard WHERE id='$id'");
            $endofweek = strtotime("next Sunday", $startofweek);
            $wage = get_wage($employeeid, get_timestamp());
            $hours = hours_worked($employeeid, $timecard["fromdate"], $timecard["todate"]);

            echo json_encode([
                "hours"     => number_format($hours, 2),
                "calculate" => number_format($timecard["wage"] * $hours, 2),
            ]);
        }
    }
}

function save_employee_timecard() {
    global $CFG, $MYVARS;
    $fields  = empty($MYVARS->GET["values"]) ? false : $MYVARS->GET["values"];
    $updated = [];
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
                if ($updated[] = execute_db_sql($SQL)) { // Saved successfully
                    unset($id);
                    unset($hours);
                }
            } else {
                $SQL = "UPDATE employee_timecard SET hours_override='$hours' WHERE id='$id'";
                if ($updated[] = execute_db_sql($SQL)) { // Saved successfully
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
    $updated = [];
    foreach ($fields as $field) {
        switch ($field["name"]) {
            case "employeeid":
            case "id":
            case "callback":
                ${$field["name"]} = dbescape($field["value"]);
                break;
            case "date":
                ${$field["name"]} = make_timestamp_from_date($field["value"], $CFG->timezone);
                break;
            case "wage":
                ${$field["name"]} = dbescape($field["value"]);
                ${$field["name"]} = str_replace("$", "", $wage);
                break;
        }

        if (!empty($date) && !empty($id) && !empty($employeeid) && !empty($wage) && is_numeric($wage) && $wage > 0) {
            $SQL = "UPDATE employee_wage SET wage='$wage',dategiven='$date' WHERE id='$id'";

            if ($updated[] = execute_db_sql($SQL)) { // Saved successfully
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

    $forms = $display = "";

    // Expand button.
    $display .= from_template("view_expand_button.php");

    if ($pid) { // Program actions
        $identifier = "pid_$pid";
        $program    = get_db_row("SELECT * FROM programs WHERE pid='$pid'");
        $display .= from_template("view_program_button.php", ["pid" => $pid]);
        $display .= from_template("view_reports_button.php", ["pid" => $pid]);

        $forms .= get_form("add_edit_expense", [
            "pid"      => $pid,
            "callback" => "programs"
        ], $identifier);

        $display .= from_template("add_edit_expense_button.php", [
            "identifier" => $identifier,
            "icon" => "money-bill-transfer",
            "title" => "Donations/Expenses",
        ]);

        $forms .= get_form("event_editor", [
            "pid"      => $pid,
            "callback" => "programs",
            "program"  => $program
        ], $identifier);

        $display .= from_template("event_editor_button.php", [
            "identifier" => $identifier,
            "icon" => "clock",
            "title" => "Edit Events",
        ]);

        $activebulletin = get_db_row("SELECT * FROM notes WHERE tag='bulletin' AND pid='$pid' AND aid='0' AND notify='2'") ? 'background:orange' : '';
        $forms .= get_form("bulletin", [
            "pid"      => $pid,
            "callback" => "programs"
        ], $identifier);

        $display .= from_template("bulletin_button.php", [
            "identifier" => $identifier,
            "icon" => "map-pin",
            "title" => "Bulletin",
            "style" => $activebulletin,
        ]);

        // Edit Program Details
        $forms .= get_form("add_edit_program", [
            "pid"      => $program["pid"],
            "callback" => "programs",
            "program"  => $program
        ], $identifier);

        $display .= from_template("add_edit_program_button.php", [
            "identifier" => $identifier,
            "icon" => "wrench",
            "title" => "Edit Program",
        ]);

        if ($program["pid"] == $activepid) {
            $display .= from_template("deactivate_program_button.php", ["pid" => $pid]);
        } else {
            $display .= from_template("activate_program_button.php", ["pid" => $pid]);
        }

        // DELETE PROGRAM BUTTON
        $display .= from_template("delete_program_button.php", ["pid" => $pid]);

        // NEW YEAR BUTTON
        $display .= from_template("newyear_program_button.php", ["pid" => $pid]);
    } elseif ($aid) { // Account actions
        $identifier = time() . "_aid_" . $aid;

        // View Account button.
        $display .= from_template("view_account_button.php", ["aid" => $aid]);

        // View Reports tool button.
        $display .= from_template("get_reports_list_button.php", ["aid" => $aid]);

        // Add Child Form
        $forms .= get_form("add_edit_child", [
            "aid" => $aid
        ], $identifier);

        $display .= from_template("add_edit_child_button.php", [
            "identifier" => $identifier,
            "icon" => "child-reaching",
            "title" => "Add Child",
        ]);

        if (!$recover) {
            // Add Contact Form
            $forms .= get_form("add_edit_contact", [
                "aid" => $aid
            ], $identifier);

            $display .= from_template("add_edit_contact_button.php", [
                "identifier" => $identifier,
                "icon" => "person-breastfeeding",
                "title" => "Add Contact",
            ]);
        }

        $forms .= get_form("add_edit_payment", [
            "pid"          => get_pid(),
            "aid"          => $aid,
            "callback"     => "accounts",
            "callbackinfo" => $aid
        ], $identifier);

        $display .= from_template("add_edit_payment_button.php", [
            "identifier" => $identifier,
            "icon" => "money-bill-1-wave",
            "title" => "Make Payment",
        ]);

        $forms .= get_form("bulletin", [
            "aid"      => $aid,
            "callback" => "accounts"
        ], $identifier);
        $activebulletin = get_db_row("SELECT * FROM notes WHERE tag='bulletin' AND pid='0' AND aid='$aid' AND notify='2'") ? 'background:orange' : '';
        $display .= '<button title="Bulletin" style="' . $activebulletin . '" class="image_button" type="button" onclick="CreateDialog(\'bulletin_' . $identifier . '\', 360, 400)">' . icon("map-pin", "2") . '</button>';

        // Edit Account
        $account = get_db_row("SELECT * FROM accounts WHERE aid='$aid' AND deleted='$deleted'");
        $forms .= get_form("add_edit_account", [
            "account"       => $account,
            "recover_param" => $recover_param
        ], $identifier);

        $display .= from_template("add_edit_account_button.php", [
            "identifier" => $identifier,
            "icon" => "wrench",
            "title" => "Edit Account",
        ]);

        if (!$recover) {
            $display .= from_template("deactivate_account_button.php", ["aid" => $aid]);
        } else {
            $display .= from_template("activate_account_button.php", ["aid" => $aid]);
        }

        // Billing
        // later
    } elseif ($chid) { // Children actions
        $identifier = time() . "child_$chid";
        $child      = get_db_row("SELECT * FROM children WHERE chid='$chid'");
        $did        = get_db_field("did", "documents", "tag='avatar' AND chid='$chid'");

        $display .= '<button title="Go to account" class="image_button" type="button" onclick="$.ajax({
                type: \'POST\',
                url: \'ajax/ajax.php\',
                data: { action: \'get_admin_accounts_form\', aid: \'' . $child["aid"] . '\' } ,
                success: function(data) {
                    $(\'#admin_display\').html(data); refresh_all();
                    $(\'.keypad_buttons\').toggleClass(\'selected_button\', true); $(\'.keypad_buttons\').not($(\'#accounts\')).toggleClass(\'selected_button\', false);
                }
            });">' . icon('magnifying-glass', "2") . '</button>';

        $forms .= get_form("avatar", [
            "did"         => $did,
            "chid"        => $chid,
            "callback"    => "get_admin_children_form",
            "param1"      => "chid",
            "param1value" => $child["chid"],
            "child"       => $child
        ], $identifier);
        $display .= '<button title="Edit Picture" class="image_button" type="button" onclick="CreateDialog(\'avatar_' . $identifier . '\', 300, 400)">' . icon('camera', "2") . '</button>';

        $forms .= get_form("attach_doc", [
            "chid"        => $child["chid"],
            "callback"    => "get_admin_children_form",
            "param1"      => "chid",
            "param1value" => $child["chid"],
            "child"       => $child
        ], $identifier);
        $display .= '<button title="Attach Document" class="image_button" type="button" onclick="CreateDialog(\'attach_doc_' . $identifier . '\', 300, 400)">' . icon('paperclip', "2") . '</button>';

        $forms .= get_form("attach_note", [
            "nid"      => "",
            "chid"     => $child["chid"],
            "callback" => "children",
            "child"    => $child
        ], $identifier);
        $display .= '<button title="Attach Note" class="image_button" type="button" onclick="CreateDialog(\'attach_note_' . $identifier . '\', 360, 400)">' . icon('comments', "2") . '</button>';

        $forms .= get_form("add_edit_child", [
            "aid"      => $child["aid"],
            "callback" => "children",
            "child"    => $child
        ], $identifier);
        $display .= '<button title="Edit Child" class="image_button" type="button" onclick="CreateDialog(\'add_edit_child_' . $identifier . '\', 300, 300)">' . icon('user-pen', "2") . '</button>';

        $enroll_action = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to unenroll ' . $child["first"] . ' ' . $child["last"] . '?\', \'Yes\', \'No\', function(){ $.ajax({
            type: \'POST\',
            url: \'ajax/ajax.php\',
            data: { action: \'toggle_enrollment\',pid:\'' . $activepid . '\',chid: \'' . $child["chid"] . '\' } ,
            success: function(data) {
            $.ajax({
                type: \'POST\',
                url: \'ajax/ajax.php\',
                data: { action: \'get_admin_children_form\', chid: \'\' } ,
                success: function(data) {
                    $(\'#admin_display\').html(data); refresh_all();
                }
            } );
            }
            });},function(){});';

        $forms .= get_form("add_edit_enrollment", [
            "eid"      => get_db_field("eid", "enrollments", "chid='" . $child["chid"] . "' AND pid='$activepid'"),
            "refresh"  => true,
            "callback" => "children",
            "aid"      => $child["aid"],
            "pid"      => $activepid,
            "chid"     => $child["chid"]
        ], $identifier);
        $display .= '<button title="Edit Enrollment" class="image_button" type="button" onclick="CreateDialog(\'add_edit_enrollment_' . $identifier . '\', 200, 400)">' . icon('list-check', "2") . '</button>';


        $display .= '<button title="Unenroll" class="image_button" type="button" onclick="' . $enroll_action . '">' . icon('toggle-on', "2") . '</button>';

        $delete_action = from_template("action_child_activation.php", [
            "chid" => $child["chid"],
            "aid"  => $child["aid"],
            "action" => "delete",
            "onsuccess" => "get_admin_children_form",
            "onsuccessdata" => "chid: '',",
        ]);

        // Delete Child Button
        $display .= '
            <button title="Delete Child" class="image_button" type="button" onclick="' . $delete_action . '">
                ' . icon('trash', "2") . '
            </button>';
    } elseif ($cid) { // Contact Buttons
        $identifier = time() . "contact_$cid";
        $contact    = get_db_row("SELECT * FROM contacts WHERE cid='$cid'");
        $display .= '<button title="Go to account" class="image_button" type="button" onclick="$.ajax({
                        type: \'POST\',
                        url: \'ajax/ajax.php\',
                        data: { action: \'get_admin_accounts_form\', aid: \'' . $contact["aid"] . '\' } ,
                        success: function(data) {
                            $(\'#admin_display\').html(data); refresh_all();
                            $(\'.keypad_buttons\').toggleClass(\'selected_button\', true); $(\'.keypad_buttons\').not($(\'#accounts\')).toggleClass(\'selected_button\', false);
                        }
                    });">' . icon('magnifying-glass', "2") . '</button>';
        $forms .= get_form("add_edit_contact", [
            "callback" => "contacts",
            "contact"  => $contact
        ], $identifier);
        $display .= '<button title="Edit Contact" class="image_button" type="button" onclick="CreateDialog(\'add_edit_contact_' . $identifier . '\', 520, 400)">' . icon('user-pen', '2') . '</button>';
        // Delete Contact Button
        $display .= '<button title="Delete Contact" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to delete this contact?\', \'Yes\', \'No\', function(){ $.ajax({
                        type: \'POST\',
                        url: \'ajax/ajax.php\',
                        data: { action: \'toggle_contact_activation\', cid: \'' . $cid . '\' } ,
                        success: function(data) {
                          $.ajax({
                              type: \'POST\',
                              url: \'ajax/ajax.php\',
                              data: { action: \'get_admin_contacts_form\', cid: \'\' } ,
                              success: function(data) {
                                  $(\'#admin_display\').html(data); refresh_all();
                              }
                          } );
                        }
                    });}, function(){})">' . icon('toggle-on', '2') . '</button>';
    } elseif ($employeeid) {
        $identifier = time() . "_employeeid_" . $employeeid;

        $employee = get_db_row("SELECT * FROM employee WHERE employeeid='$employeeid'");

        // Wage History
        $forms .= get_form("edit_employee_wage_history", [
            "employee"      => $employee,
            "recover_param" => $recover_param
        ], $identifier);
        $display .= '<button title="Wage History" class="image_button" type="button" onclick="CreateDialog(\'edit_employee_wage_history_' . $identifier . '\', 400, 500)">' . icon('sack-dollar', "2") . '</button>';

        // Timecards
        $forms .= get_form("edit_employee_timecards", [
            "employee"      => $employee,
            "recover_param" => $recover_param
        ], $identifier);
        $display .= '<button title="Timecards" class="image_button" type="button" onclick="CreateDialog(\'edit_employee_timecards_' . $identifier . '\', 400, 500)">' . icon('stopwatch', "2") . '</button>';

        // Edit Employee
        $forms .= get_form("add_edit_employee", [
            "employee"      => $employee,
            "recover_param" => $recover_param
        ], $identifier);
        $display .= '<button title="Edit Employee" class="image_button" type="button" onclick="CreateDialog(\'add_edit_employee_' . $identifier . '\', 230, 315)">' . icon('wrench', '2') . '</button>';

        if (!$recover) {
            $display .= '<button title="Deactivate Employee" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to deactivate this employee?\', \'Yes\', \'No\', function(){ $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'deactivate_employee\', employeeid: \'' . $employeeid . '\' } ,
                            success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });}, function(){})">' . icon('toggle-on', '2') . '</button>';
        } else {
            $display .= '<button title="Reactivate Employee" class="image_button" type="button" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to reactivate this employee?\', \'Yes\', \'No\', function(){ $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'activate_employee\', employeeid: \'' . $employeeid . '\' } ,
                            success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });}, function(){})">' . icon('toggle-off', '2') . '</button>';
        }
    } elseif ($actid) {
    }

    if ($return) {
        return $forms . $display;
    } else {
        echo $forms . $display;
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
    $returnme .= from_template("view_expand_button.php");

    if (!empty($pid)) {
        // view invoices group
        $returnme .= '<button title="Show Invoices Grouped" class="image_button" type="button" onclick="$.ajax({
            type: \'POST\',
            url: \'ajax/ajax.php\',
            data: { action: \'view_invoices\', aid: \'' . $aid . '\',pid: \'' . $pid . '\' } ,
            success: function(data) { $(\'#info_div\').html(data); refresh_all(); }
        });">' . icon('layer-group', "2") . '</button>';

        // view invoices timeline
        $returnme .= '<button title="Show Invoice Timeline" class="image_button" type="button" onclick="$.ajax({
            type: \'POST\',
            url: \'ajax/ajax.php\',
            data: { action: \'view_invoices\', aid: \'' . $aid . '\',pid: \'' . $pid . '\',orderbytime: true } ,
            success: function(data) { $(\'#info_div\').html(data); refresh_all(); }
        });">' . icon('timeline', "2") . '</button>';
    }

    if (empty($aid)) {
        // print tax papers
        // Activity from / to
        $returnme .= '
            <form style="display:inline" id="myValidForm" method="get"
                  action="ajax/reports.php" onsubmit="return false;">
                <input type="hidden" name="report" id="report" value="all_tax_papers" />
                <input type="hidden" name="id" id="id" value="' . $pid . '" />
                <input type="hidden" name="type" id="type" value="pid" />
                <input type="hidden" name="actid" id="actid" value="" />
                <input type="hidden" name="extra" id="extra" value="" />
                <input type="hidden" id="from" name="from" value="01/01/' . date("Y", strtotime("-1 year")) . '" />
                <input type="hidden" id="to" name="to" value="12/31/' . date("Y", strtotime("-1 year")) . '" />

                <script>
                    $(function() {
                        var dates = $("#from, #to").datepicker({
                            changeMonth: true,
                            numberOfMonths: 1,
                            onSelect: function(selectedDate) {
                                var option = this.id == "from" ? "minDate" : "maxDate",
                                    instance = $(this).data("datepicker"),
                                    date = $.datepicker.parseDate(
                                        instance.settings.dateFormat ||
                                        $.datepicker._defaults.dateFormat,
                                        selectedDate, instance.settings);
                                dates.not(this).datepicker("option", option, date);
                            }
                        });
                    });
                    $(function() {
                        var validForm = $("#myValidForm").submit(function(e) {
                            validForm.nyroModal().nmCall();
                        });
                    });
                </script>

                <button title="Print Tax Papers" class="image_button" type="button" onclick="$(\'#myValidForm\').submit();">
                    ' . icon('print', "2") . '
                </button>
            </form>';
    }

    if ($aid || $pid) {
        $returnme .= get_form("create_invoices", [
            "pid" => $pid,
            "aid" => $aid
        ], $identifier);
        $returnme .= '
            <button title="Calculate Invoices" class="image_button" type="button" onclick="CreateDialog(\'create_invoices_' . $identifier . '\', 200, 400)">
                ' . icon('calculator', "2") . '
            </button>';
    }

    if ($aid && $pid) {
        // print tax papers
        // Activity from / to
        $returnme .= '
            <form style="display:inline" id="myValidForm" method="get"
                  action="ajax/reports.php" onsubmit="return false;">
                <input type="hidden" name="report" id="report" value="all_tax_papers" />
                <input type="hidden" name="id" id="id" value="' . $aid . '" />
                <input type="hidden" name="type" id="type" value="aid" />
                <input type="hidden" name="actid" id="actid" value="" />
                <input type="hidden" name="extra" id="extra" value="" />
                <input type="hidden" id="from" name="from" value="01/01/' . date("Y", strtotime("-1 year")) . '" />
                <input type="hidden" id="to" name="to" value="12/31/' . date("Y", strtotime("-1 year")) . '" />
                <script>
                    $(function() {
                        var dates = $("#from, #to").datepicker({
                            changeMonth: true,
                            numberOfMonths: 1,
                            onSelect: function(selectedDate) {
                                var option = this.id == "from" ? "minDate" : "maxDate",
                                    instance = $(this).data("datepicker"),
                                    date = $.datepicker.parseDate(
                                        instance.settings.dateFormat ||
                                        $.datepicker._defaults.dateFormat,
                                        selectedDate, instance.settings);
                                dates.not(this).datepicker("option", option, date);
                            }
                        } );
                    } );
                $(function() {
                var validForm = $("#myValidForm").submit(function(e) {
                    validForm.nyroModal().nmCall();
                } );
                } );
                </script>
                <button title="Print Tax Papers" class="image_button" type="button" onclick="$(\'#myValidForm\').submit();">
                    ' . icon('print', "2") . '
                </button>
            </form>';

        $override = get_db_row("SELECT * FROM billing_override WHERE pid='$pid' AND aid='$aid'");
        $returnme .= get_form("billing_overrides", [
            "oid"      => get_db_field("oid", "billing_override", "pid='$pid' AND aid='$aid'"),
            "pid"      => $pid,
            "aid"      => $aid,
            "callback" => "billing",
            "override" => $override
        ], $identifier);
        $returnme .= '<button title="Billing Overrides" class="image_button" type="button" onclick="CreateDialog(\'billing_overrides_' . $identifier . '\', 450, 500)">' . icon('vault', "2") . '</button>';
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

function view_invoices($return = false, $pid = null, $aid = null, $print = null, $orderbytime = null, $year = null) {
    global $CFG, $MYVARS;
    $pid         = $pid !== null ? $pid : (empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"]);
    $aid         = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $print       = $print !== null ? $print : (empty($MYVARS->GET["print"]) ? false : $MYVARS->GET["print"]);
    $orderbytime = $orderbytime !== null ? $orderbytime : (empty($MYVARS->GET["orderbytime"]) ? false : $MYVARS->GET["orderbytime"]);
    $year        = $year !== null ? $year : (empty($MYVARS->GET["year"]) ? date("Y") : $MYVARS->GET["year"]);

    $yearsql = $yearsql2 = "";
    $beginning_balance = 0;
    if ($year !== "all") {
        $beginningofyear = make_timestamp_from_date('01/01/' . $year . 'T00:00:00Z');
        $endofyear = make_timestamp_from_date('12/31/' . $year . 'T00:00:00Z');
        $beginning_balance = account_balance($pid, $aid, false, $year);
        $yearsql = "AND fromdate >= '$beginningofyear' AND fromdate <= '$endofyear'";
        $yearsql2 = "AND timelog >= '$beginningofyear' AND timelog <= '$endofyear'";
    }

    $years[] = "all";
    for ($i = date("Y"); $i > date("Y") - 5; $i--) {
        $years[] = $i;
    }

    $yearselector = make_select_from_array(
        "yearselector",
        $years,
        false,
        false,
        $year,
        from_template("invoice_year_selector_action.php", [
            "pid" => $pid,
            "aid" => $aid,
            "orderbytime" => $orderbytime
        ])
    );

    if (empty($aid)) { // All accounts enrolled in program
        $SQL = "SELECT * FROM accounts WHERE deleted = '0' AND admin= '0' AND aid IN (SELECT aid FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE pid='$pid')) ORDER BY name";
    } else { // Only selected account
        $SQL = "SELECT * FROM accounts WHERE aid='$aid'";
    }

    $total_paid = 0;
    $returnme = "<div>No Accounts</div>"; // Default message if no accounts found
    $account_invoices = "";
    if ($accounts = get_db_result($SQL)) {
        while ($account = fetch_row($accounts)) {
            $total_paid     = $total_billed = $total_fee = 0;

            $transactions = "";
            if (empty($orderbytime)) {  // Group all transactions aka Stacked View
                // Fees
                $SQL = "SELECT * FROM billing_payments WHERE pid='$pid' AND aid='" . $account["aid"] . "' AND payment < 0 $yearsql2 ORDER BY timelog,payid";
                if ($payments = get_db_result($SQL)) {
                    $total_fee = abs(get_db_field("SUM(payment)", "billing_payments", "pid='$pid' AND aid='" . $account["aid"] . "' AND payment < 0 $yearsql2"));
                    $total_fee = empty($total_fee) ? "0.00" : $total_fee;
                    $receipts = "";
                    while ($payment = fetch_row($payments)) {
                        $identifier          = time() . "accountpaymentpayid_" . $payment["payid"];
                        $edit_payment_button = get_form("add_edit_payment", [
                            "payment"      => $payment,
                            "pid"          => $pid,
                            "aid"          => $account["aid"],
                            "callback"     => "billing",
                            "callbackinfo" => $aid
                        ], $identifier);

                        $edit_payment_button .= from_template("edit_payment_button.php", ["identifier" => $identifier]);

                        $delete_payment = from_template("delete_payment_button.php", [
                            "payid" => $payment["payid"],
                            "pid"   => $pid,
                            "aid"   => $aid,
                        ]);
                        $paytext = $payment["payment"] >= 0 ? "Payment of " : "Fee of ";
                        $note = empty($payment["note"]) ? "" : '<tr><td><em>Note: ' . $payment["note"] . '</em></td></tr>';

                        $receipts .= from_template("payment_receipt_layout.php", [
                            "editbutton" => $edit_payment_button,
                            "deletebutton" => $delete_payment,
                            "amount" => $payment["payment"],
                            "desc" => $paytext,
                            "time" => $payment["timelog"],
                            "note" => $note,
                        ]);
                    }

                    $transactions .= from_template("billing_flexsection_layout.php", [
                        "class" => "invoice_bills",
                        "style" => "background-color:darkRed;",
                        "header" => from_template("fees_header_stacked.php", ["amount" => $total_fee]),
                        "contents" => $receipts,
                    ]);
                }

                $SQL = "SELECT * FROM billing_payments WHERE pid='$pid' AND aid='" . $account["aid"] . "' AND payment >= 0 $yearsql2 ORDER BY timelog,payid";
                if ($payments = get_db_result($SQL)) {
                    $total_paid = get_db_field("SUM(payment)", "billing_payments", "pid='$pid' AND aid='" . $account["aid"] . "' AND payment >= 0 $yearsql2");
                    $total_paid = empty($total_paid) ? "0.00" : $total_paid;

                    $receipts = "";
                    while ($payment = fetch_row($payments)) {
                        $identifier          = time() . "accountpaymentpayid_" . $payment["payid"];

                        $edit_payment_button = get_form("add_edit_payment", [
                            "payment"      => $payment,
                            "pid"          => $pid,
                            "aid"          => $account["aid"],
                            "callback"     => "billing",
                            "callbackinfo" => $aid
                        ], $identifier);
                        $edit_payment_button .= from_template("edit_payment_button.php", ["identifier" => $identifier]);

                        $delete_payment = from_template("delete_payment_button.php", [
                            "payid" => $payment["payid"],
                            "pid"   => $pid,
                            "aid"   => $aid,
                        ]);

                        $paytext = $payment["payment"] >= 0 ? "Payment of " : "Fee of ";
                        $note = empty($payment["note"]) ? "" : '<tr><td><em>Note: ' . $payment["note"] . '</em></td></tr>';

                        $receipts .= from_template("payment_receipt_layout.php", [
                            "editbutton" => $edit_payment_button,
                            "deletebutton" => $delete_payment,
                            "amount" => $payment["payment"],
                            "desc" => $paytext,
                            "time" => $payment["timelog"],
                            "note" => $note,
                        ]);
                    }

                    $transactions .= from_template("billing_flexsection_layout.php", [
                        "class" => "invoice_payments",
                        "style" => "background-color:darkCyan;",
                        "header" => from_template("payment_header_stacked.php", ["amount" => $total_paid]),
                        "contents" => $receipts,
                    ]);
                }

                $SQL = "SELECT * FROM billing WHERE pid='$pid' AND aid='" . $account["aid"] . "' $yearsql ORDER BY fromdate";
                if ($invoices = get_db_result($SQL)) {
                    while ($invoice = fetch_row($invoices)) {
                        $SQL = "SELECT * FROM billing_perchild WHERE chid IN (SELECT chid FROM children WHERE aid='" . $account["aid"] . "') AND pid='$pid' AND fromdate = '" . $invoice["fromdate"] . "' ORDER BY id";
                        $receipts = "";
                        if ($perchild_invoices = get_db_result($SQL)) {
                            while ($perchild_invoice = fetch_row($perchild_invoices)) {
                                $exempt_button = "";
                                if (!strstr($perchild_invoice["receipt"], "[Exempt]")) { // If not already exempted
                                    $exempt_button = from_template("exempt_button.php", [
                                        "title" => (empty($perchild_invoice["exempt"]) ? "Exempt" : "Recend Exemption"),
                                        "invoiceid" => $perchild_invoice["id"],
                                        "pid" => $pid,
                                        "aid" => $aid,
                                    ]);
                                }

                                $receipts .= from_template("billing_receipt_layout.php", [
                                    "exemptbutton" => $exempt_button,
                                    "desc" => $perchild_invoice["receipt"],
                                ]);
                            }
                        }

                        $transactions .= from_template("billing_flexsection_layout.php", [
                            "class" => "invoice_week",
                            "style" => "",
                            "header" => from_template("billing_header_stacked.php", [
                                "weekof" => $invoice["fromdate"],
                                "amount" => $invoice["owed"],
                            ]),
                            "contents" => $receipts,
                        ]);
                    }
                }
                $total_billed = get_db_field("SUM(owed)", "billing", "pid='$pid' AND aid='" . $account["aid"] . "' $yearsql");
                $total_billed += $total_fee;
                $total_billed = empty($total_billed) ? "0.00" : $total_billed;
            } else { // Order all transactions by date
                $total_fee = abs(get_db_field("SUM(payment)", "billing_payments", "pid='$pid' AND aid='" . $account["aid"] . "' AND payment < 0 $yearsql2"));
                $total_fee = empty($total_fee) ? "0.00" : $total_fee;

                $total_paid = get_db_field("SUM(payment)", "billing_payments", "pid='$pid' AND aid='" . $account["aid"] . "' AND payment >= 0 $yearsql2");
                $total_paid = empty($total_paid) ? "0.00" : $total_paid;

                $SQL = "SELECT id, pid, aid, amount, note, fromdate, bill
                        FROM (
                            SELECT id, pid, aid, owed as amount, receipt as note, fromdate, 1 AS bill
                            FROM billing
                            UNION
                            SELECT payid as id, pid, aid, payment as amount, note, timelog as fromtime, 0 as bill
                            FROM billing_payments
                        ) c
                        WHERE pid='$pid'
                        AND aid='" . $account["aid"] . "'
                        $yearsql
                        ORDER BY fromdate";

                if ($results = get_db_result($SQL)) {
                    while ($result = fetch_row($results)) {
                        if (!$result["bill"]) { // Payment or Fee
                            if ($result["amount"] < 0) { // Fee
                                $result["payment"] = $result["amount"];
                                $result["payid"] = $result["id"];
                                $result["timelog"] = $result["fromdate"];
                                $identifier          = time() . "accountpaymentpayid_" . $result["id"];
                                $edit_payment_button = get_form("add_edit_payment", [
                                    "payment"      => $result,
                                    "pid"          => $pid,
                                    "aid"          => $account["aid"],
                                    "callback"     => "billing",
                                    "callbackinfo" => $aid
                                ], $identifier);
                                $edit_payment_button .= from_template("edit_payment_button.php", ["identifier" => $identifier]);

                                $delete_payment = from_template("delete_payment_button.php", [
                                    "payid" => $result["id"],
                                    "pid"   => $pid,
                                    "aid"   => $aid,
                                ]);

                                $note = empty($result["note"]) ? "" : '<tr><td><em>Note: ' . $result["note"] . '</em></td></tr>';

                                $header = from_template("payment_or_fees_header_timeline.php", [
                                    "type"   => ($result["amount"] >= 0 ? "Payment" : "Fee"),
                                    "amount" => $result["amount"],
                                ]);

                                $content = from_template("payment_receipt_layout.php", [
                                    "editbutton" => $edit_payment_button,
                                    "deletebutton" => $delete_payment,
                                    "amount" => $result["amount"],
                                    "desc" => ($result["amount"] >= 0 ? "Payment of " : "Fee of "),
                                    "time" => $result["timelog"],
                                    "note" => $note,
                                ]);

                                $transactions .= from_template("billing_flexsection_layout.php", [
                                    "class" => "invoice_bills",
                                    "style" => "background-color:darkRed;padding: 5px;color: white;",
                                    "header" => $header,
                                    "contents" => $content,
                                ]);
                            } else { // Payment
                                $identifier          = time() . "accountpaymentpayid_" . $result["id"];
                                $result["payment"] = $result["amount"];
                                $result["payid"] = $result["id"];
                                $result["timelog"] = $result["fromdate"];
                                $edit_payment_button = get_form("add_edit_payment", [
                                    "payment"      => $result,
                                    "pid"          => $pid,
                                    "aid"          => $account["aid"],
                                    "callback"     => "billing",
                                    "callbackinfo" => $aid
                                ], $identifier);

                                $delete_payment = from_template("delete_payment_button.php", [
                                    "payid" => $result["id"],
                                    "pid"   => $pid,
                                    "aid"   => $aid,
                                ]);

                                $note = empty($result["note"]) ? "" : '<tr><td><em>Note: ' . $result["note"] . '</em></td></tr>';

                                $header = from_template("payment_or_fees_header_timeline.php", [
                                    "type"   => ($result["amount"] >= 0 ? "Payment" : "Fee"),
                                    "amount" => $result["amount"],
                                ]);

                                $content = from_template("payment_receipt_layout.php", [
                                    "editbutton" => $edit_payment_button,
                                    "deletebutton" => $delete_payment,
                                    "amount" => $result["amount"],
                                    "desc" => ($result["amount"] >= 0 ? "Payment of " : "Fee of "),
                                    "time" => $result["timelog"],
                                    "note" => $note,
                                ]);

                                $transactions .= from_template("billing_flexsection_layout.php", [
                                    "class" => "invoice_payments",
                                    "style" => "background-color:darkCyan;padding: 5px;color: white;",
                                    "header" => $header,
                                    "contents" => $content,
                                ]);
                            }
                        } else { // Bill
                            $SQL = "SELECT * FROM billing_perchild WHERE chid IN (SELECT chid FROM children WHERE aid='" . $account["aid"] . "') AND pid='$pid' AND fromdate = '" . $result["fromdate"] . "' ORDER BY id";

                            $receipts = "";
                            if ($perchild_invoices = get_db_result($SQL)) {
                                while ($perchild_invoice = fetch_row($perchild_invoices)) {
                                    $exempt_button = "";
                                    if (!strstr($perchild_invoice["receipt"], "[Exempt]")) { // If not already exempted
                                        $exempt_button = from_template("exempt_button.php", [
                                            "title" => (empty($perchild_invoice["exempt"]) ? "Exempt" : "Recend Exemption"),
                                            "invoiceid" => $perchild_invoice["id"],
                                            "pid" => $pid,
                                            "aid" => $aid,
                                        ]);
                                    }

                                    $receipts .= from_template("billing_receipt_layout.php", [
                                        "exemptbutton" => $exempt_button,
                                        "desc" => $perchild_invoice["receipt"],
                                    ]);
                                }
                            }

                            $transactions .= from_template("billing_flexsection_layout.php", [
                                "class" => "invoice_week",
                                "style" => "padding: 5px;color: white;",
                                "header" => from_template("billing_header_stacked.php", [
                                    "weekof" => $result["fromdate"],
                                    "amount" => $result["amount"],
                                ]),
                                "contents" => $receipts,
                            ]);
                        }
                    }
                    $total_billed = get_db_field("SUM(owed)", "billing", "pid='$pid' AND aid='" . $account["aid"] . "' $yearsql");
                    $total_billed += $total_fee;
                    $total_billed = empty($total_billed) ? "0.00" : $total_billed;
                }
            }

            // Add current week charges.
            if (($year == "all" || $year == date("Y")) && $current_week = week_balance($pid, $account["aid"], true)) {
                $transactions .= from_template("billing_flexsection_layout.php", [
                    "class" => "invoice_week",
                    "style" => "padding: 5px;color: white; background: #5767a1;",
                    "header" => from_template("current_header_stacked.php", [
                        "amount" => $current_week,
                    ]),
                    "contents" => "",
                ]);

                $total_billed += (float) $current_week;
            }

            $balance = $total_billed - $total_paid + $beginning_balance;
            $transactions .= '
                <div style="padding: 0px 12px;">
                    <div style="text-align: right;color: darkred;">
                        <strong>Beginning Year Balance:</strong> $' . number_format($beginning_balance, 2) . '
                    </div>
                    <div style="text-align: right;color: darkred;">
                        <strong>Owed:</strong> $' . number_format($total_billed, 2) . '
                    </div>
                    <div style="text-align: right;color: blue;">
                        <strong>Paid:</strong> $' . number_format($total_paid, 2) . '
                    </div>
                    <hr align="right" style="width:100px;" />
                    <div style="text-align: right">
                        <strong>Balance:</strong> $' . number_format($balance, 2) . '
                    </div>
                </div>';

            // Expected next week if prepaid.
            if (get_db_field("payahead", "programs", "pid='$pid'")) { // if prepaid
                $transactions .= from_template("billing_flexsection_layout.php", [
                    "class" => "invoice_week",
                    "style" => "padding: 5px;color: white; background: green;",
                    "header" => from_template("next_header_stacked.php", [
                        "amount" => week_balance($pid, $account["aid"], true, true),
                    ]),
                    "contents" => "",
                ]);
            }

            $identifier = time() . "accountpayment_" . $account["aid"];
            $payfee_button  = get_form("add_edit_payment", [
                "pid"          => $pid,
                "aid"          => $account["aid"],
                "callback"     => "billing",
                "callbackinfo" => $aid
            ], $identifier);
            $payfee_button .= '<a style="font-size: 9px;" href="javascript: void(0);" onclick="CreateDialog(\'add_edit_payment_' . $identifier . '\', 300, 400)"><span class="inline-button ui-corner-all" style="padding: 5px;">' . icon('money-bill') . ' Add Payment/Fee</span></a>';
            $list_invoices_button = '<a style="font-size: 9px;" href="ajax/reports.php?report=invoice&pid=' . $pid . '&aid=' . $account["aid"] . '&time=' . time() . '" class="nyroModal"><span class="inline-button ui-corner-all" style="padding: 5px;">' . icon('list') . ' List Invoices</span></a>';
            $timeline_button = '<a style="font-size: 9px;" href="ajax/reports.php?report=invoicetimeline&pid=' . $pid . '&aid=' . $account["aid"] . '&time=' . time() . '" class="nyroModal"><span class="inline-button ui-corner-all" style="padding: 5px;">' . icon('timeline') . ' Invoice Timeline</span></a>';

            $account_invoices .= '
                <div class="ui-corner-all">
                    <div style="padding: 6px;display:flex;align-items: center;">
                        <span>
                            <strong>Account: ' . $account["name"] . '</strong>
                        </span>
                        ' . $list_invoices_button . $timeline_button . $payfee_button . '
                    </div>
                    ' . $transactions . '
                </div>';
        }
        $returnme = '
            <div class="scroll-pane fill_height">
                <div style="display:table-cell;font-weight: bold;font-size: 110%;padding: 10px; 5px;">
                    Invoices: ' . $yearselector . '
                </div>
                ' . $account_invoices . '
            </div>';
    }

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

    if ($pid) { // Program enrollment
        include("programtab.php");
    } elseif ($aid) { // Account info
        include("accounttab.php");
    } elseif ($chid) { // Children
        include("childrentab.php");
    } elseif ($cid) { // Contacts
        include("contactstab.php");
    } elseif ($employeeid) { // Employees
        include("employeestab.php");
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
        execute_db_sql("DELETE FROM $tagtype WHERE tag='$tag'");
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

function deactivate_employee_activity() {
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
        // View All button
        $returnme .= '
                <div class="document_list_item ui-corner-all" style="text-align:center">
                    <a href="ajax/fileviewer.php?chid=' . $chid . '" class="nyroModal"><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View All</span></a>
                </div>';
        while ($document = fetch_row($documents)) {
            $delete_action = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to delete this \'+$(\'a#a-' . $document["did"] . '\').attr(\'data\')+\' document?\', \'Yes\', \'No\',
            function() { $.ajax({
                    type: \'POST\',
                    url: \'ajax/ajax.php\',
                    data: { action: \'delete_document\', ' . $type . ': \'' . $$type . '\',did: \'' . $document["did"] . '\' } ,
                    success: function(data) {
                            $(\'#subselect_div\').html(data); refresh_all();
                        }
                } );
            } ,
            function(){});';

            $identifier = time() . "documents_" . $document["did"];
            $tag        = get_db_row("SELECT * FROM documents_tags WHERE tag='" . $document["tag"] . "'");
            $returnme .= get_form("attach_doc", [
                "did"         => $document["did"],
                "chid"        => $chid,
                "aid"         => $aid,
                "cid"         => $cid,
                "actid"       => $actid,
                "display"     => "subselect_div",
                "callback"    => "get_documents_list",
                "param1"      => "$type",
                "param1value" => $$type
            ], $identifier);
            $returnme .= '
                <div class="last_update ui-corner-all">
                    Last Update: ' . date('F j, Y g:i a', display_time($document["timelog"])) . '
                </div>
                <div class="document_list_item ui-corner-all">
                    <div style="margin-top:10px;">
                        <span class="tag ui-corner-all" style="color:' . $tag["textcolor"] . ';background-color:' . $tag["color"] . '">
                            ' . $tag["title"] . '
                        </span>
                        <span class="tag-description">
                            ' . $document["description"] . '
                        </span>
                        <span class="list_links"><a href="ajax/fileviewer.php?did=' . $document["did"] . '" class="nyroModal"><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Document</span></a> <a href="javascript: void(0);" onclick="CreateDialog(\'attach_doc_' . $identifier . '\', 300, 400)"><span class="inline-button ui-corner-all">' . icon('pen-to-square') . ' Update Document</span></a> <a id="a-' . $document["did"] . '" data="' . $document["tag"] . '" href="javascript: void(0);" onclick="' . $delete_action . '"><span class="inline-button ui-corner-all">' . icon('trash') . ' Delete Document</span></a></span>
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
        // View All button
        $returnme .= '
            <div class="document_list_item ui-corner-all" style="text-align:center">
                <a href="ajax/reports.php?report=allnotes&type=' . $type . '&id=' . $id . '" class="nyroModal">
                    <span class="inline-button ui-corner-all">
                        ' . icon('magnifying-glass') . ' View All
                    </span>
                </a>
            </div>';
        while ($note = fetch_row($notes)) {
            $delete_action = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to delete this \'+$(\'a#a-' . $note["nid"] . '\').attr(\'data\')+\' note?\', \'Yes\', \'No\',
            function() { $.ajax({
                    type: \'POST\',
                    url: \'ajax/ajax.php\',
                    data: { action: \'delete_note\', ' . $type . ': \'' . $$type . '\',nid: \'' . $note["nid"] . '\' } ,
                    success: function(data) {
                            $(\'#subselect_div\').html(data); refresh_all();
                        }
                } );
            } ,
            function(){});';

            $identifier = time() . "notes_" . $note["nid"];
            $tag        = get_tag([
                "type" => "notes",
                "tag"  => $note["tag"]
            ]);
            $returnme .= get_form("attach_note", [
                "nid"         => $note["nid"],
                "chid"        => $chid,
                "aid"         => $aid,
                "cid"         => $cid,
                "actid"       => $actid,
                "display"     => "subselect_div",
                "callback"    => "children",
                "param1"      => "$type",
                "param1value" => $$type
            ], $identifier);
            $returnme .= '
                <div class="last_update ui-corner-all">
                    Last Update: ' . date('F j, Y g:i a', display_time($note["timelog"])) . '
                </div>
                <div class="document_list_item ui-corner-all">
                    <div style="margin-top:10px;">
                        <span class="tag ui-corner-all" style="color:' . $tag["textcolor"] . ';background-color:' . $tag["color"] . '">
                            ' . $tag["title"] . '
                        </span>
                        <span class="tag-description">
                            ' . $note["note"] . '
                        </span>
                        <span class="list_links"><a href="javascript: void(0);" onclick="CreateDialog(\'attach_note_' . $identifier . '\', 360, 400)"><span class="inline-button ui-corner-all">' . icon('pen-to-square') . ' Update Note</span></a> <a id="a-' . $note["nid"] . '" data="' . $note["tag"] . '" href="javascript: void(0);" onclick="' . $delete_action . '"><span class="inline-button ui-corner-all">' . icon('trash') . ' Delete Note</span></a></span>
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

    $pid = !empty($pid) ? $pid : $activepid; // if pid isn't set, set it to active pid

    $tags_form     = make_select("tag", get_db_result("SELECT tag, title FROM notes_tags n ORDER BY tag"), "tag", "title", "", false, "", true);
    $att_tags_form = make_select("att_tag", get_db_result("SELECT tag, title, 2 as sorttype FROM notes_required r WHERE pid='$pid' UNION SELECT tag, title, 1 as sorttype FROM events_tags e ORDER BY sorttype, tag"), "tag", "title", "", false, "", true);
    $reports       = "";
    switch ($type) {
        case "pid":
            // Simple Child List
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'child_list\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' List of Children</span></a><div class="report-cubes-container"></div>';
            // Child attendance between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'program_per_child_attendance\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Per Child Attendance Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            // Per Account attendance between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'program_per_account_attendance\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Per Account Attendance Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            // Activities between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'activity\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Activities Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            // Daily Activity Tag Count Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#att_tag\').val().length && $(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'activity_tag\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Daily Activity Tag Count Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            // Daily Attendance Breakdown 30 min
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'attendance_throughout_day\'); $(\'#extra\').val(\'30\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Daily Attendance 30min Breakdown</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            // Daily Attendance Breakdown 15 min
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'attendance_throughout_day\'); $(\'#extra\').val(\'15\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Daily Attendance 15min Breakdown</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            // Meal Status Count Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'meal_status_count\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Meal Status Count Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            // Notes between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'allnotes\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Notes Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: pink"></div><div class="cube" style="background-color: lightblue"></div></div>';
            // Week expected attendance
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'weekly_expected_attendance\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Expected attendance vs Actual attendance</span></a><div class="report-cubes-container"></div>';
            // Account Balances
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'program_per_account_bill\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View All Account Balances</span></a><div class="report-cubes-container"></div>';
            // Program Cash Flow
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'program_per_program_cash_flow\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Program Cash Flow</span></a><div class="report-cubes-container"></div>';
            // Program Payments between dates (optional)
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'payments_between\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Program Payments (dates optional)</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            break;
        case "aid":
            // Activities between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'activity\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Activities Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            // Daily Activity Tag Count Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#att_tag\').val().length && $(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'activity_tag\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Daily Activity Tag Count Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            // Invoice Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'invoice_between\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Invoice Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightblue"></div></div>';
            // Notes between dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'allnotes\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Notes Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: pink"></div><div class="cube" style="background-color: lightblue"></div></div>';
            // Account Cash Flow
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'program_per_program_cash_flow\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Account Cash Flow</span></a><div class="report-cubes-container"></div>';
            // Account Payments
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="$(\'#report\').val(\'payments_between\'); $(\'#myValidForm\').submit();"><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Account Payments - FOR TAXES</span></a><div class="report-cubes-container"></div>';
            break;
        case "chid":
            // Child Activities Between Dates
            $reports .= '<br /><br /><a onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'activity\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Activities Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            // Daily Activity Tag Count Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#att_tag\').val().length && $(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'activity_tag\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Daily Activity Tag Count Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            // Child Notes Between Dates
            $reports .= '<br /><br /><a onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'allnotes\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Notes Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: pink"></div><div class="cube" style="background-color: lightblue"></div></div>';
            break;
        case "cid":
            // Daily Activity Tag Count Between Dates
            $reports .= '<br /><br /><a href="javascript: void(0);" onclick="if ($(\'#att_tag\').val().length && $(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'activity_tag\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' Daily Activity Tag Count Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            // Contact Activities Between Dates
            $reports .= '<br /><br /><a onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'activity\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Activities Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            break;
        case "employeeid":
            // Employee Activities Between Dates
            $reports .= '<br /><br /><a onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'activity\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Activities Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            // Employee Pay Between Dates
            $reports .= '<br /><br /><a onclick="if ($(\'#from\').val().length && $(\'#to\').val().length) { $(\'#report\').val(\'employee_paid\'); $(\'#myValidForm\').submit(); } "><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View Pay Between Dates</span></a><div class="report-cubes-container"><div class="cube" style="background-color: lightgreen"></div><div class="cube" style="background-color: lightblue"></div></div>';
            break;
        case "actid":
            break;
    }

    // Activity from / to
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
        var dates = $("#from, #to").datepicker({
            changeMonth: true,
            numberOfMonths: 1,
            onSelect: function(selectedDate) {
                var option = this.id == "from" ? "minDate" : "maxDate",
                    instance = $(this).data("datepicker"),
                    date = $.datepicker.parseDate(
                        instance.settings.dateFormat ||
                        $.datepicker._defaults.dateFormat,
                        selectedDate, instance.settings);
                dates.not(this).datepicker("option", option, date);
            }
        } );
    } );
$(function() {
  var validForm = $("#myValidForm").submit(function(e) {
      validForm.nyroModal().nmCall();
  } );
} );
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
    // View All button
    $returnme .= '
        <div class="document_list_item ui-corner-all" style="text-align:center">
            <a href="ajax/reports.php?month1=' . $month . '&year1=' . $year . '&report=activity&type=' . $type . '&id=' . $id . '" class="nyroModal"><span class="inline-button ui-corner-all">' . icon('magnifying-glass') . ' View All ' . date('F', mktime(0, 0, 0, $month, 1, $year)) . '</span></a>
        </div>
        <div class="document_list_item ui-corner-all" style="text-align:center;">
            <table id="document_list_item" style="width:100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="text-align: left;">
                        <a href="javascript: void(0);"
                            onclick="
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_activity_list\',' . $type . ': \'' . $$type . '\',month:\'' . $prevmonth . '\',year:\'' . $prevyear . '\' } ,
                                    success: function(data) {
                                            $(\'#subselect_div\').hide(\'fade\');
                                            $(\'#subselect_div\').html(data);
                                            $(\'#subselect_div\').show(\'fade\');
                                            refresh_all();
                                        }
                                } );">
                            ' . date('F Y', mktime(0, 0, 0, $prevmonth, 1, $prevyear)) . '
                        </a>
                    </td>
                    <td colspan="5" style="text-align:center;font-size:130%;font-weight:bold;">
                        ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . '
                    </td>
                    <td style="text-align:right">
                        <a href="javascript: void(0);"
                            onclick="
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_activity_list\',' . $type . ': \'' . $$type . '\',month:\'' . $nextmonth . '\',year:\'' . $nextyear . '\' } ,
                                    success: function(data) {
                                            $(\'#subselect_div\').hide(\'fade\', function() {} );
                                            $(\'#subselect_div\').html(data);
                                            $(\'#subselect_div\').show(\'fade\');
                                            refresh_all();
                                        }
                                });">
                            ' . date('F Y', mktime(0, 0, 0, $nextmonth, 1, $nextyear)) . '
                        </a>
                    </td>
                </tr>
                <tr>
                    <td colspan="7" style="height:10px;"></td>
                </tr>
            </table>
            ' . draw_calendar($month, $year, [
                    "type"  => "activity",
                    "$type" => $$type,
                    "form"  => "update_activity"
                ]) . '
        </div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_admin_children_form($return = false, $chid = false, $recover = false) {
    global $MYVARS;
    $chid     = !empty($chid) ? $chid : (empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"]);

    $children_list = "";
    if ($children = get_db_result("SELECT * FROM children WHERE deleted='0' AND chid IN (SELECT chid FROM enrollments WHERE pid = '" . get_pid() . "') ORDER BY last,first")) {
        while ($child = fetch_row($children)) {
            $chid           = empty($chid) ? $child["chid"] : $chid;
            $selected_class = $chid && $chid == $child["chid"] ? "selected_button" : "";
            $checked_in     = $recover ? '' : (is_checked_in($child["chid"]) ? active_icon(true) : active_icon(false));
            $notifications  = get_notifications(get_pid(), $child["chid"], false, true, true);
            $item_text = '
                <span class="list_title leftselector">
                    <span class="hide_mobile" style="padding: 5px;">
                        ' . $checked_in . '
                    </span>
                    <span style="width: 90%;min-width: 220px;max-width: 70%;">
                        ' . $child["last"] . ", " . $child["first"] . '
                    </span>
                </span>' . $notifications;

            $children_list .= from_template("selectable_list_item_layout.php", [
                "item" => $item_text,
                "class" => $selected_class,
                "tabid" => "chid",
                "chid" => $child["chid"],
            ]);
        }
    } else {
        $children_list .= from_template("list_item_layout.php", [
            "item" => '<span class="list_title">None Enrolled</span>',
        ]);
    }

    $header = '
        <div class="document_list_item ui-corner-all" style="text-align:center">
            <span><strong>
                Enrolled Children
            </strong></span>
        </div>';

    $returnme = from_template("admin_main_layout.php", [
        "header" => $header,
        "list" => $children_list,
        "actions" => get_action_buttons(true, false, false, $chid, false, false, $recover),
        "info" => get_info(true, false, false, $chid, false, false, $recover),
    ]);

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

    $account_list = "";
    if ($accounts = get_db_result("SELECT * FROM accounts WHERE deleted = '0' AND admin= '0' AND aid IN (SELECT aid FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE pid='$pid')) ORDER BY name")) {
        $i = 0;
        while ($account = fetch_row($accounts)) {
            $kid_count       = get_db_count("SELECT * FROM children WHERE aid='" . $account["aid"] . "' AND deleted='0'");
            $selected_class  = $aid && $aid == $account["aid"] || ($pid && !$aid && $i == 0) ? "selected_button" : "";
            $aid             = $selected_class == "selected_button" ? $account["aid"] : $aid;
            $account_balance = account_balance($pid, $account["aid"], true);
            $balanceclass    = $account_balance <= 0 ? "balance_good" : "balance_bad";
            $account_list .= '
                <div class="ui-corner-all list_box selectablelist ' . $selected_class . '"
                    onclick="$(this).addClass(\'selected_button\',true); $(\'.list_box\').not(this).removeClass(\'selected_button\',false);
                            $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'view_invoices\', aid: \'' . $account["aid"] . '\',pid: \'' . $pid . '\' } ,
                                success: function(data) {
                                    $(\'#info_div\').html(data);
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'get_billing_buttons\', aid: \'' . $account["aid"] . '\',pid: \'' . $pid . '\' } ,
                                        success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                    } );
                                }
                            });">
                    <div class="list_box_item_left account_name">
                        <span class="list_title">
                            ' . $account["name"] . '
                        </span>
                    </div>
                    <div class="list_box_item_right billing_info">
                        <div class="child_count">
                            Children: ' . $kid_count . '<br />
                            <span class="' . $balanceclass . '">
                                Balance: $' . $account_balance . '
                            </span>
                        </div>
                    </div>
                </div>';
            $i++;
        }
    }

    $program  = get_db_row("SELECT * FROM programs WHERE pid='$pid'");

    $header = '
        <div class="ui-corner-all list_box selectablelist"
                onclick="$(this).addClass(\'selected_button\',true);
                    $(\'.list_box\').not(this).removeClass(\'selected_button\',false);
                    $.ajax({
                        type: \'POST\',
                        url: \'ajax/ajax.php\',
                        data: { action: \'view_invoices\', pid: \'' . $pid . '\' } ,
                        success: function(data) {
                            $(\'#info_div\').html(data);
                            $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'get_billing_buttons\', pid: \'' . $pid . '\' } ,
                                success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                            } );
                        }
                    });">
                <div class="list_box_item_full">
                    <span class="list_title">
                        ' . $program["name"] . '
                    </span>
                </div>
            </div>';

    $returnme = from_template("admin_main_layout.php", [
        "header" => $header,
        "list" => $account_list,
        "actions" => get_billing_buttons(true, $pid, $aid),
        "info" => view_invoices(true, $pid, $aid),
    ]);

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_admin_contacts_form($return = false, $cid = false, $recover = false) {
    global $MYVARS;
    $cid      = $cid ? $cid : (empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"]);

    $SQL = "SELECT *
            FROM contacts c
            INNER JOIN accounts a ON c.aid = a.aid
            WHERE c.deleted = '0'
            AND c.aid IN (
                SELECT b.aid
                FROM children b
                WHERE b.chid IN (
                    SELECT e.chid
                    FROM enrollments e
                    WHERE e.pid = '" . get_pid() . "'
                )
            )
            ORDER BY a.name, c.last, c.first";

    $contact_list = "";
    if ($contacts = get_db_result($SQL)) {
        while ($contact = fetch_row($contacts)) {
            $cid            = empty($cid) ? $contact["cid"] : $cid;
            $selected_class = $cid && $cid == $contact["cid"] ? "selected_button" : "";
            $accountname = $contact["name"];

            $contact_name = "Unknown";
            if (empty($contact["last"]) && !empty($contact["first"])) {
                $contact_name = $contact["first"];
            } elseif (empty($contact["first"]) && !empty($contact["last"])) {
                $contact_name = $contact["last"];
            } elseif (!empty($contact["first"]) && !empty($contact["last"])) {
                $contact_name = $contact["last"] != $accountname ? $contact["first"] . " " . $contact["last"] : $contact["first"];
            }

            $contact_list .= '
                <div class="ui-corner-all list_box selectablelist ' . $selected_class . '"
                    onclick="$(this).addClass(\'selected_button\',true);
                            $(\'.list_box\').not(this).removeClass(\'selected_button\',false);
                            $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'get_info\', cid: \'' . $contact["cid"] . '\' } ,
                                success: function(data) {
                                    $(\'#info_div\').html(data);
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'get_action_buttons\', cid: \'' . $contact["cid"] . '\' } ,
                                        success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                    } );
                                }
                            });">
                    <div class="list_box_item_full">
                        <span class="list_title">
                            [' . $accountname . '] ' . $contact_name . '
                        </span>
                    </div>
                </div>';
        }
    } else {
        $contact_list .= '<div class="ui-corner-all list_box" ><div class="list_box_item_full"><span class="list_title">None Active</span></div></div>';
    }

    $header = '
        <div class="document_list_item ui-corner-all" style="text-align:center">
            <span><strong>Active Contacts</strong></span>
        </div>';

    $returnme = from_template("admin_main_layout.php", [
        "header" => $header,
        "list" => $contact_list,
        "actions" => get_action_buttons(true, false, false, false, $cid, false, $recover),
        "info" => get_info(true, false, false, false, $cid, false, $recover),
    ]);

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
    $deleted = $recover ? "1" : "0";

    $SQL     = "SELECT * FROM employee WHERE deleted = '$deleted' ORDER BY last, first";

    $employee_list = "";
    if ($employees = get_db_result($SQL)) {
        while ($employee = fetch_row($employees)) {
            $employeeid     = empty($employeeid) ? $employee["employeeid"] : $employeeid;
            $selected_class = $employeeid == $employee["employeeid"] ? "selected_button" : "";
            $deleted_param  = $recover ? ',recover: \'true\'' : '';
            $thisweekpay = get_wages_for_this_week($employee["employeeid"]);
            $employee_list .= '<div class="ui-corner-all list_box selectablelist ' . $selected_class . '" onclick="$(this).addClass(\'selected_button\',true); $(\'.list_box\').not(this).removeClass(\'selected_button\',false);
                            $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'get_info\', employeeid: \'' . $employee["employeeid"] . '\'' . $deleted_param . ' } ,
                                success: function(data) {
                                    $(\'#info_div\').html(data);
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'get_action_buttons\', employeeid: \'' . $employee["employeeid"] . '\'' . $deleted_param . ' } ,
                                        success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                    } );
                                }
                            });"><div class="list_box_item_left employee_name" ><span class="list_title">' . $employee["last"] . ', ' . $employee["first"] . '</span></div><div class="list_box_item_right billing_info"><div class="child_count"><span class="hide_mobile">This week: </span>$' . $thisweekpay . '</div></div></div>';
        }
    }

    $header = '
        <div class="ui-corner-all list_box">
            <div class="list_box_item_left employee_name">';
    if (!$recover) {
        $header .= get_form('add_edit_employee') . '
            <button class="list_buttons" style="float:none;margin:4px;" type="button" onclick="CreateDialog(\'add_edit_employee\', 230, 315)">
                Add New Employee
            </button>';

        if (get_db_row("SELECT employeeid FROM employee WHERE deleted=1")) {
            $header .= ' <button class="list_buttons" onclick="$.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'get_admin_employees_form\', employeeid: \'\', recover: \'true\' } ,
                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                  });">See Inactive</button>';
        }
    } else {
        $header .= '<button style="margin:4px;"  onclick="$.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'get_admin_employees_form\', employeeid: \'\'} ,
                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
              });">See Active</button>';
    }
    $header .= '
            </div>
            <div class="list_box_item_right"></div>
        </div>';

    $returnme = from_template("admin_main_layout.php", [
        "header" => $header,
        "list" => $employee_list,
        "actions" => get_action_buttons(true, false, false, false, false, false, $recover, $employeeid),
        "info" => get_info(true, false, false, false, false, false, $recover, $employeeid),
    ]);

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

    $tagtypes = [
        "documents",
        "notes"
    ];

    $tag_list = "";
    foreach ($tagtypes as $tagrow) {
        $tagtype        = empty($tagtype) ? $tagrow : $tagtype;
        $selected_class = $tagtype && $tagtype == $tagrow ? "selected_button" : "";
        $tag_list .= '<div class="ui-corner-all list_box selectablelist ' . $selected_class . '" onclick="$(this).addClass(\'selected_button\',true); $(\'.list_box\').not(this).removeClass(\'selected_button\',false);
                        $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'get_tags_info\', tagtype: \'' . $tagrow . '\' } ,
                                success: function(data) {
                                    $(\'#info_div\').html(data);
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'get_tags_actions\', tagtype: \'' . $tagrow . '\' } ,
                                        success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                    } );
                                }
                            } );
                    "><div class="list_box_item_full"><span class="list_title">' . ucfirst($tagrow) . '</span></div></div>';
    }

    $header = '
        <div class="document_list_item ui-corner-all" style="text-align:center">
            <span><strong>Tag Types</strong></span>
        </div>';

    $returnme = from_template("admin_main_layout.php", [
        "header" => $header,
        "list" => $tag_list,
        "actions" => get_tags_actions(true, $tagtype, $tag),
        "info" => get_tags_info(true, $tagtype, $tag),
    ]);

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
        $returnme .= get_form("add_edit_tag", [
            "tagtype"  => $tagtype,
            "callback" => "tags"
        ], $identifier);
        $returnme .= '<button title="Add Tag" class="image_button" type="button" onclick="CreateDialog(\'add_edit_tag_' . $identifier . '\', 300, 400)">' . icon('tags', '2') . '</button>';
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
    // Tags
    $SQL      = "SELECT * FROM $tagtype" . "_tags WHERE tag != 'avatar' ORDER BY title";
    if ($tags = get_db_result($SQL)) {
        $returnme .= '<div style="display:table-cell;font-weight: bold;font-size: 110%;padding: 10px;">Tags:</div><div id="tags" class="scroll-pane infobox fill_height">';
        while ($tagrow = fetch_row($tags)) {
            $identifier = time() . "note_$tagtype" . "_" . $tagrow["tag"];

            $delete_action = "CreateConfirm('dialog-confirm', 'Are you sure you want to delete this tag?', 'Yes', 'No', function() {
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'delete_tag',
                        tagtype: '$tagtype',
                        tag: '" . $tagrow["tag"] . "'
                    },
                    success: function(data) {
                        $.ajax({
                            type: 'POST',
                            url: 'ajax/ajax.php',
                            data: {
                                action: 'get_admin_tags_form',
                                tagtype: '$tagtype'
                            },
                            success: function(data) {
                                $('#admin_display').html(data);
                                refresh_all();
                            }
                        });
                    }
                });
            },function(){});";

            // Edit Tag Button
            $returnme .= get_form(
                "add_edit_tag",
                [
                    "tagtype"  => $tagtype,
                    "callback" => "tags",
                    "tagrow"   => $tagrow,
                ],
                $identifier
            );
            $edit_button   = ' <a href="javascript: void(0);" onclick="CreateDialog(\'add_edit_tag_' . $identifier . '\', 300, 400)"><span class="inline-button ui-corner-all">' . icon('wrench') . ' Edit</span></a>';
            $delete_button = ' <a href="javascript: void(0);" onclick="' . $delete_action . '"><span class="inline-button ui-corner-all">' . icon('trash') . ' Delete</span></a>';

            $returnme .= '<div class="ui-corner-all list_box"><div class="list_title"><span id="tag_template' . $identifier . '" class="tag ui-corner-all" style="color:' . $tagrow["textcolor"] . ';background-color:' . $tagrow["color"] . '">' . $tagrow["title"] . '</span>';
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

    $enrollments_list = '';
    if ($programs = get_db_result("SELECT * FROM programs WHERE deleted = '0' ORDER BY name")) {
        while ($program = fetch_row($programs)) {
            $selected_class = $pid && $pid == $program["pid"] ? "selected_button" : "";
            $active         = $activepid && $activepid == $program["pid"] ? "<span style='float:right;margin: 10px 4px;color:white;'>[Active]</span>" : "";

            $notifications = get_notifications($program["pid"], false, false, true) ? 'style="background: darkred;"' : '';
            $enrollments_list .= '<div class="ui-corner-all list_box selectablelist ' . $selected_class . '" ' . $notifications . ' onclick="$(this).addClass(\'selected_button\',true); $(\'.list_box\').not(this).removeClass(\'selected_button\',false);
                            $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'get_info\', pid: \'' . $program["pid"] . '\' } ,
                                success: function(data) {
                                    $(\'#info_div\').html(data);
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'get_action_buttons\', pid: \'' . $program["pid"] . '\' } ,
                                        success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                    } );
                                }
                            });"><div class="list_box_item_left program_name" style="white-space:nowrap;"><span class="list_title">' . $program["name"] . '</span></div><div class="list_box_item_right">' . $active . '</div></div>';
        }
    }

    $identifier = time() . "add_program";
    $returnme = get_form("add_edit_program", ["callback" => "programs"], $identifier);

    $header = '
        <div class="ui-corner-all list_box">
            <div class="list_box_item_full" style="text-align:center">
                <button type="button" class="list_buttons" onclick="CreateDialog(\'add_edit_program_' . $identifier . '\', 450, 500)">
                    Create Program
                </button>
            </div>
        </div>';

    $returnme .= from_template("admin_main_layout.php", [
        "header" => $header,
        "list" => $enrollments_list,
        "actions" => get_action_buttons(true, $pid, false, false, false, false, false),
        "info" => get_info(true, $pid, false, false, false, false, false),
    ]);

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

    $deleted = $recover ? "1" : "0";
    if ($deleted) {
        $SQL = "SELECT * FROM accounts WHERE admin=0 AND (aid IN (SELECT aid FROM children WHERE deleted='$deleted') OR aid IN (SELECT aid FROM contacts WHERE deleted='$deleted') OR deleted=1) ORDER BY name";
    } else {
        $SQL = "SELECT * FROM accounts WHERE admin=0 AND deleted = '$deleted' ORDER BY name";
    }

    $accounts_list = '';
    if ($accounts = get_db_result($SQL)) {
        while ($account = fetch_row($accounts)) {
            $kid_count = get_db_count("SELECT * FROM children WHERE aid='" . $account["aid"] . "' AND deleted='$deleted'");
            $active    = get_db_count("SELECT * FROM enrollments WHERE chid IN (SELECT chid FROM children WHERE aid='" . $account["aid"] . "') AND pid='$pid' AND deleted='$deleted'") ? "activeaccount" : "inactiveaccount";

            $selected_class = '';
            if (!empty($aid) && $active == 'activeaccount') {
                $aid            = empty($aid) ? $account["aid"] : $aid;
                $selected_class = $active == 'activeaccount' && !empty($aid) && $aid == $account["aid"] ? "selected_button" : "";
            }

            $deleted_param   = $recover ? ',recover: \'true\'' : '';
            $notifications   = get_notifications($pid, false, $account["aid"], true) ? 'background: darkred;' : '';
            $override        = $recover ? "display:block;" : "";
            $account_balance = account_balance($pid, $account["aid"], true);
            $balanceclass    = $account_balance <= 0 ? "balance_good" : "balance_bad";
            $accounts_list .= '<div class="ui-corner-all list_box selectablelist ' . $selected_class . ' ' . $active . '" style="' . $notifications . $override . '" onclick="$(this).addClass(\'selected_button\',true); $(\'.list_box\').not(this).removeClass(\'selected_button\',false);
                            $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                data: { action: \'get_info\', aid: \'' . $account["aid"] . '\'' . $deleted_param . ' } ,
                                success: function(data) {
                                    $(\'#info_div\').html(data);
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'get_action_buttons\', aid: \'' . $account["aid"] . '\'' . $deleted_param . ' } ,
                                        success: function(data) { $(\'#actions_div\').html(data); refresh_all(); }
                                    } );
                                }
                            } );"><div class="list_box_item_left account_name"><span class="list_title">' . $account["name"] . '</span></div><div class="list_box_item_right billing_info"><div class="child_count">Children: ' . $kid_count . '<br /><a class="' . $balanceclass . '" href="javascript: void(0);" onclick="$.ajax({
                                                                                              type: \'POST\',
                                                                                              url: \'ajax/ajax.php\',
                                                                                              data: { action: \'get_admin_billing_form\', aid:\'' . $account["aid"] . '\' ,pid: \'' . $pid . '\' } ,
                                                                                              success: function(data) { $(\'#admin_display\').hide(\'fade\', null, null, function() { $(\'#admin_display\').html(data); refresh_all(); $(\'#admin_display\').show(\'fade\'); } );  }
                                                                                          });$(\'.keypad_buttons\').toggleClass(\'selected_button\',true); $(\'.keypad_buttons\').not($(\'#admin_menu_billing\')).toggleClass(\'selected_button\',false);">Balance: $' . $account_balance . '</a></div></div></div>';
        }
    }

    $header = '<div class="ui-corner-all list_box">';
    if (!$recover) {
        $header .= '<div class="list_box_item_left main_account_actions">' . get_form('add_edit_account') . '<button class="list_buttons" style="float:none;margin:4px;" type="button" onclick="CreateDialog(\'add_edit_account\', 200, 315)">Add New Account</button>';
        if (get_db_row("SELECT chid FROM children WHERE deleted=1") || get_db_row("SELECT cid FROM contacts WHERE deleted=1") || get_db_row("SELECT aid FROM accounts WHERE deleted=1")) {
            $header .= ' <button class="list_buttons" onclick="$.ajax({
                  type: \'POST\',
                  url: \'ajax/ajax.php\',
                  data: { action: \'get_admin_accounts_form\', aid: \'\', recover: \'true\' } ,
                  success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                  });">See Inactive</button>';
        }

        $header .= '</div>
                        <div class="list_box_item_right enroll_data">
                            <div class="show_enrolled">
                                Show Enrolled <input type="checkbox" checked onclick="if ($(this).prop(\'checked\')) { $(\'.inactiveaccount\').hide(); } else { $(\'.inactiveaccount\').show(); } $(\'.scroll-pane\').sbscroller(\'refresh\'); smart_scrollbars();" />
                            </div>
                            <div class="enrolled_count">Enrolled:
                                ' . get_db_count("SELECT * FROM enrollments WHERE pid='$pid' AND deleted='0' AND chid IN (SELECT chid FROM children WHERE deleted='0')") . '
                            </div>
                        </div>';
    } else {
        $header .= '<div class="list_box_item_left main_account_actions">
                        <button style="margin:4px;"  onclick="$.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          data: { action: \'get_admin_accounts_form\', aid: \'\'} ,
                          success: function(data) { $(\'#admin_display\').html(data); refresh_all(); }
                        });">See Active</button>
                      </div>';
    }
    $header .= '</div>';

    $returnme = from_template("admin_main_layout.php", [
        "header" => $header,
        "list" => $accounts_list,
        "actions" => get_action_buttons(true, false, $aid, false, false, false, $recover),
        "info" => get_info(true, false, $aid, false, false, false, $recover),
    ]);

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

function get_contacts_selector($chids, $admin = false) {
    $children = "";
    foreach ($chids as $chid) {
        $children .= $children == "" ? "chid='" . $chid["value"] . "'" : " OR chid='" . $chid["value"] . "'";
    }
    $SQL = "SELECT * FROM contacts WHERE aid IN (SELECT aid FROM children WHERE $children AND deleted=0) AND deleted=0 ORDER by primary_address DESC,last,first";

    $contacts = "";
    if ($result = get_db_result($SQL)) {
        $i = 0;
        while ($row = fetch_row($result)) {
            $contacts .= from_template("checkinout_contact.php", [
                "contact" => $row,
                "selected" => ($i == 0 && !$admin),
            ]);
            $i++;
        }
    }

    return from_template("checkinout_contact_selector.php", [
        "contacts" => $contacts,
        "admin" => $admin,
    ]);
}

function copy_program() {
    global $CFG, $MYVARS;
    $pid = empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"];
    if ($pid) {
        // create new program
        $program = get_db_row("SELECT name, timeopen, timeclosed, deleted, active, perday, fulltime, minimumactive, minimuminactive, vacation, multiple_discount, consider_full, bill_by, discount_rule FROM programs WHERE pid='$pid'");
        $newpid  = copy_db_row($program, "programs", 'name=' . $program["name"] . ' COPY');

        // copy enrollments
        if ($enrollments = get_db_result("SELECT pid, chid, days_attending, exempt, deleted FROM enrollments WHERE pid='$pid' AND deleted=0")) {
            while ($enrollment = fetch_row($enrollments)) {
                copy_db_row($enrollment, "enrollments", 'pid=' . $newpid . '');
            }
        }

        execute_db_sql("UPDATE programs SET active=0"); // deactivate all programs
        execute_db_sql("UPDATE programs SET active=1 WHERE pid='$newpid'"); // activate new program
        // get_admin_enrollment_form(false, $newpid);
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
        // get_admin_enrollment_form(false, $pid);
    }
}

function deactivate_program() {
    global $CFG, $MYVARS;
    $pid = empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"];
    if ($pid) {
        execute_db_sql("UPDATE programs SET active=0");
        echo get_admin_page("pid", $pid);
        // get_admin_enrollment_form(false, $pid);
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
        // get_admin_enrollment_form(false, $pid);
    }
}

function activate_account() {
    global $CFG, $MYVARS;
    $aid = empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"];
    if ($aid) {
        execute_db_sql("UPDATE accounts SET deleted=0 WHERE aid='$aid'");
        execute_db_sql("UPDATE enrollments SET deleted=0 WHERE chid IN (SELECT chid FROM children WHERE aid='$aid')");
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
        get_admin_employees_form(false, $employeeid, false);
    }
}

function deactivate_employee() {
    global $CFG, $MYVARS;
    $employeeid = empty($MYVARS->GET["employeeid"]) ? false : $MYVARS->GET["employeeid"];
    if ($employeeid) {
        execute_db_sql("UPDATE employee SET deleted=1 WHERE employeeid='$employeeid'");
        get_admin_employees_form(false, $employeeid, true);
    }
}

function deactivate_account() {
    global $CFG, $MYVARS;
    $aid = empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"];
    if ($aid) {
        execute_db_sql("UPDATE accounts SET deleted=1 WHERE aid='$aid'");
        execute_db_sql("UPDATE enrollments SET deleted=1 WHERE chid IN (SELECT chid FROM children WHERE aid='$aid')");
        execute_db_sql("UPDATE children SET deleted=1 WHERE aid='$aid'");
        execute_db_sql("UPDATE contacts SET deleted=1 WHERE aid='$aid'");
        $MYVARS->GET["aid"] = '';
        get_admin_accounts_form();
    }
}

function toggle_contact_activation() {
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

function toggle_child_activation() {
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
    $fields         = empty($MYVARS->GET["values"]) ? [] : $MYVARS->GET["values"];
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
            execute_db_sql("INSERT INTO enrollments (pid, chid, days_attending, exempt) VALUES('$pid', '$chid', '$days_attending', '$exempt')");
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

    // Now you must redo the entire week's invoices for that account
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
              data: { action: \'add_required_notes_form\',pid:\'' . $pid . '\',evid: \'' . $evid . '\' } ,
              success: function(data) { $(\'#required_notes_div_pid_' . $pid . '\').html(data); }
            });">Add Required Event Note</button><br /><br /><ul id="sortable">';
        while ($event = fetch_row($events)) {
            $save          = '<button type="button" style="font-size:9px;float:right;" onclick="$.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'save_required_notes\',pid:\'' . $pid . '\',rnid:\'' . $event["rnid"] . '\',evid: \'' . $evid . '\',values: $(\'.fields\', \'li#' . $event["rnid"] . '\').serializeArray() } ,
              success: function(data) { $(\'#required_notes_div_pid_' . $pid . '\').html(data); }
            });">Save</button>';
            $delete        = '<button type="button" style="font-size:9px;float:right;" onclick="CreateConfirm(\'dialog-confirm\', \'Are you sure you wish to delete this required note?\', \'Yes\', \'No\', function(){ $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'delete_required_notes\',pid:\'' . $pid . '\',rnid:\'' . $event["rnid"] . '\',evid: \'' . $evid . '\' } ,
              success: function(data) { $(\'#required_notes_div_pid_' . $pid . '\').html(data); $(\'#sortable\').sortable({
                                update : function () {
                                    var serial = $(\'#sortable\').sortable(\'toArray\');
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'required_notes_sort\',serial: serial,pid:\'' . $pid . '\',evid: \'' . $evid . '\' } ,
                                    } );
                                }
                            } ); $(\'#sortable\').disableSelection(); }
            });}, function(){})">Delete</button>';
            $question_type = get_db_row("SELECT nid FROM notes WHERE rnid='" . $event["rnid"] . "'") ? '<input class="fields" type="hidden" name="question_type" id="question_type" value="' . $event["question_type"] . '" />' . $event["question_type"] : make_select_from_object("question_type", get_note_type_array(), "id", "name", "fields", $event["question_type"]);
            $notes_list .= '<li id="' . $event["rnid"] . '" class="ui-state-default"><input class="fields" type="hidden" name="rnid" value="' . $event["rnid"] . '" /><span class="draggable ui-icon ui-icon-arrowthick-2-n-s"></span>&nbsp;&nbsp;Title: <input class="fields" type="text" name="title" id="title" value="' . $event["title"] . '" />&nbsp;&nbsp;Type: ' . $question_type . '<span style="float:right;position: initial;">' . $delete . ' ' . $save . '</span></li>';
        }
        $notes_list .= '</ul>';
    } else {
        $notes_list .= '<strong>Required Notes:</strong>
        <button type="button" style="font-size:9px;float:right;" onclick="$.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'add_required_notes_form\',pid:\'' . $pid . '\',evid: \'' . $evid . '\' } ,
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

    if (empty($rnid)) { // Add new
        $rnid = execute_db_sql("INSERT INTO notes_required (pid, type, tag, title, question_type, deleted) VALUES('$pid', 'actid', '$tag', '$title', '$question_type', 0)");
        $sort = get_db_count("SELECT * FROM events_required_notes WHERE evid='$evid'");
        $sort++;
        execute_db_sql("INSERT INTO events_required_notes (evid, rnid, sort) VALUES('$evid', '$rnid', '$sort')");
    } else {
        $oldnote = get_db_row("SELECT * FROM notes_required WHERE rnid='$rnid'");
        execute_db_sql("UPDATE notes_required SET title='$title', tag='$tag', question_type='$question_type' WHERE rnid='$rnid'");
        execute_db_sql("UPDATE notes SET note=REPLACE(note, '" . $oldnote["title"] . ":', '$title:'), tag='$tag' WHERE rnid='$rnid'");
    }

    echo view_required_notes_form($pid, $evid);
}

function add_required_notes_form() {
    global $CFG, $MYVARS;
    $pid  = empty($pid) ? (empty($MYVARS->GET["pid"]) ? get_pid() : $MYVARS->GET["pid"]) : $pid;
    $evid = empty($evid) ? (empty($MYVARS->GET["evid"]) ? false : $MYVARS->GET["evid"]) : $evid;
    echo '<strong>Add Required Event Note:</strong><br /><br />
    <ul id="sortable">
        <li id="addone" class="ui-state-default">&nbsp;&nbsp;Title: <input class="fields" name="title" id="title" type="text" value="" />&nbsp;&nbsp;Type: ' . make_select_from_object("question_type", get_note_type_array(), "id", "name", "fields") . '
            <button type="button" style="font-size:9px;float:right;" onclick="if($(\'#name\',\'li#addone\').val() != \'\'){ $.ajax({
              type: \'POST\',
              url: \'ajax/ajax.php\',
              data: { action: \'save_required_notes\',pid:\'' . $pid . '\',evid: \'' . $evid . '\',values: $(\'.fields\', \'li#addone\').serializeArray() } ,
              success: function(data) { $(\'#required_notes_div_pid_' . $pid . '\').html(data); $(\'#sortable\').sortable({
                                update : function () {
                                    var serial = $(\'#sortable\').sortable(\'toArray\');
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'required_notes_sort\',serial: serial,pid:\'' . $pid . '\',evid: \'' . $evid . '\' } ,
                                    } );
                                }
                            } ); $(\'#sortable\').disableSelection();}
            });}">Save</button></li>
    </ul>';
}

function delete_required_notes() {
    global $CFG, $MYVARS;
    $pid  = empty($pid) ? (empty($MYVARS->GET["pid"]) ? get_pid() : $MYVARS->GET["pid"]) : $pid;
    $evid = empty($evid) ? (empty($MYVARS->GET["evid"]) ? false : $MYVARS->GET["evid"]) : $evid;
    $rnid = empty($rnid) ? (empty($MYVARS->GET["rnid"]) ? false : $MYVARS->GET["rnid"]) : $rnid;

    if (get_db_row("SELECT nid FROM notes WHERE rnid='$rnid'")) { // Been used already
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
