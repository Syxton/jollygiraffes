<?php

/***************************************************************************
* reports.php - Main backend ajax script.  Usually sends off to feature libraries.
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/29/2014
* Revision: 3.0.3
***************************************************************************/

include('header.php');

if (!empty($_POST["action"])) {
    callfunction();
    exit();
} else {
    $MYVARS->GET = $_GET;
}


$fields = ["pid","aid","chid","cid","actid","from","to","tag","att_tag","report","type","id","month1","year","year1","month2","year2","sort","view"];
foreach ($fields as $field) {
    $$field = empty($MYVARS->GET[$field]) ? false : $MYVARS->GET[$field];
}

// Whitelist dynamic column names used in WHERE $type = ...
$allowed_types = ["pid", "aid", "chid", "cid", "actid", "employeeid"];
if ($type !== false && !in_array($type, $allowed_types, true)) {
    $type = false;
}

$pid = empty($pid) ? get_pid() : clean_var_opt($pid, "int", get_pid());
$id = $id !== false ? clean_var_opt($id, "int", 0) : false;
$aid = $aid !== false ? clean_var_opt($aid, "int", 0) : false;
$chid = $chid !== false ? clean_var_opt($chid, "int", 0) : false;
$cid = $cid !== false ? clean_var_opt($cid, "int", 0) : false;
$actid = $actid !== false ? clean_var_opt($actid, "int", 0) : false;
$tag = $tag !== false ? clean_var_opt($tag, "string", "") : false;
$att_tag = $att_tag !== false ? clean_var_opt($att_tag, "string", "") : false;

$returnme = $script = "";
$sql_vars = []; // bound parameters for the main $SQL query

if (empty($sort)) {
    $returnme .= '<div id="popup_display" style="width:800px;height550px;position: relative;right: 10px;">';
}
$returnme .= '
    <div style="position: fixed;display: block;top: 25px;left: 50%;transform: translate(-50%, -50%);z-index: 100;">
        <button style="display:inline-flex;align-items:center;font-size: 12px;text-shadow: darkGrey 1px 1px 3px;" onclick="$(\'.printthis\').print();">' . icon('print', "2") . ' Print</button>
        <button style="display:inline-flex;align-items:center;font-size: 12px;text-shadow: darkGrey 1px 1px 3px;" onclick="
            $(\'.copied\').remove();
            $(\'body\').append(\'<div class=\\\'copied\\\' style=\\\'background:white;width:\'+$(\'.printthis\').width()+\'px\\\'>\'+$(\'.printthis\').html()+\'</div>\');
            html2canvas($(\'.copied\').last(), {
                onrendered: function(canvas) {
                    $(\'.copied\').last().hide();
                    var link = document.createElement(\'a\');
                    link.href = canvas.toDataURL(\'image/png\').replace(\'image/png\', \'image/octet-stream\');
                    link.download = \'copy.png\';
                    link.click();
                }
            });
            ">' . icon('copy', "2") . ' Copy</button></div><div id="printthis" class="printthis fill_height" style="padding-left:10px;width:785px;">';

$fromnum = strtotime($from);
$tonum = strtotime($to);

if (!empty($year)) {
    $fromyear = $year;
    $toyear = $year;
    $fromyear = $year == "all" ? 2000 : $year;
    $toyear = $year == "all" ? date('Y') : $year;

    $fromnum = mktime(0, 0, 0, 1, 1, $fromyear);
    $tonum = mktime(0, 0, 0, 12, 31, $toyear);
}

if (!empty($fromnum) && !empty($tonum)) {
    $tonum += 86399; //go through end of day selected 86400 seconds is one day...minus 1 second
    $timesql = "AND timelog > ||t_from|| AND timelog < ||t_to||";
    $timesql2 = "AND fromdate > ||t_from|| AND todate < ||t_to||";
    $sql_vars["t_from"] = $fromnum;
    $sql_vars["t_to"] = $tonum;
} elseif (!empty($month1) && !empty($year1)) {
    if (!empty($month2) && !empty($year2)) {
        $timefrom = mktime(0, 0, 0, $month1, 1, $year1);
        $timeto = mktime(0, 0, 0, $month2, cal_days_in_month(CAL_GREGORIAN, $month2, $year2), $year2);
    } else {
        $timefrom = mktime(0, 0, 0, $month1, 1, $year1);
        $timeto = $timefrom + (cal_days_in_month(CAL_GREGORIAN, $month1, $year1) * 86400);
    }
    $timesql = "AND timelog > ||t_from|| AND timelog < ||t_to||";
    $timesql2 = "AND fromdate > ||t_from|| AND todate < ||t_to||";
    $sql_vars["t_from"] = $timefrom;
    $sql_vars["t_to"] = $timeto;
} else {
    $timefrom = $timeto = false;
    $timesql = $timesql2 = "";
}

if (!empty($fromnum) && !empty($tonum)) {
    $fromtostring = date("m/d/Y", $fromnum) . " to " . date("m/d/Y", $tonum);
} elseif (!empty($fromnum)) {
    $fromtostring = "From " . date("m/d/Y", $fromnum) . " to present";
} elseif (!empty($tonum)) {
    $fromtostring = "Everything up to " . date("m/d/Y", $tonum);
} else {
    $fromtostring = "All records";
}


$servertz = get_date('P', time(), $CFG->servertz);
$localtz = get_date('P', time(), $CFG->timezone);
$order_day = "CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'" . $servertz . "','" . $localtz . "'))) as order_day";
$order_by = ' ORDER BY order_day,timelog';
$empty = $returnme;
$name = get_name(["type" => "$type","id" => "$id"]);

switch ($report) {
    case "allnotes":
        if ($tag) {
            $tag_sql = "tag IN (SELECT tag FROM notes_tags WHERE tag = ||tag||)";
            $sql_vars["tag"] = $tag;
        } else {
            $tag_sql = "tag IN (SELECT tag FROM notes_tags)";
        }
        $SQL = "SELECT *,$order_day FROM notes WHERE $type = ||id|| AND $tag_sql $timesql $order_by";
        $sql_vars["id"] = $id;
        break;
    case "activity":
        if ($att_tag) {
            $att_tag_sql = "(tag IN (SELECT tag FROM notes_required WHERE tag = ||att_tag||) OR tag IN (SELECT tag FROM events_tags WHERE tag = ||att_tag||))";
            $sql_vars["att_tag"] = $att_tag;
        } else {
            $att_tag_sql = "tag NOT IN (SELECT tag FROM notes_required)";
        }
        if (!empty($actid) && $type == "employeeid") {
            $SQL = "SELECT *,$order_day FROM notes WHERE actid = ||actid|| AND $type = ||id|| AND actid != 0 AND $att_tag_sql $timesql $order_by";
            $sql_vars["actid"] = $actid;
            $sql_vars["id"] = $id;
        } elseif ($type == "employeeid") {
            $SQL = "SELECT *,$order_day FROM notes WHERE $type = ||id|| AND actid != 0 AND $att_tag_sql $timesql $order_by";
            $sql_vars["id"] = $id;
        } elseif (!empty($actid)) {
            $SQL = "SELECT *,$order_day FROM notes WHERE pid = ||pid|| AND actid = ||actid|| AND $att_tag_sql $timesql $order_by";
            $sql_vars["pid"] = $pid;
            $sql_vars["actid"] = $actid;
        } else {
            $SQL = "SELECT *,$order_day FROM notes WHERE pid = ||pid|| AND $type = ||id|| AND actid != 0 AND $att_tag_sql $timesql $order_by";
            $sql_vars["pid"] = $pid;
            $sql_vars["id"] = $id;
        }
        break;
    case "invoice":
        if (empty($aid)) { //All accounts enrolled in program
            $SQL = "SELECT * FROM accounts WHERE deleted = '0' AND admin= '0' AND aid IN (SELECT aid FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE pid = ||pid||)) ORDER BY name";
            $sql_vars["pid"] = $pid;
        } else { //Only selected account
            $SQL = "SELECT * FROM accounts WHERE aid = ||aid||";
            $sql_vars["aid"] = $aid;
        }
        break;
    case "invoicetimeline":
        if (empty($aid)) { //All accounts enrolled in program
            $SQL = "SELECT * FROM accounts WHERE deleted = '0' AND admin= '0' AND aid IN (SELECT aid FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE pid = ||pid||)) ORDER BY name";
            $sql_vars["pid"] = $pid;
        } else { //Only selected account
            $SQL = "SELECT * FROM accounts WHERE aid = ||aid||";
            $sql_vars["aid"] = $aid;
        }
        break;
    case "employee_paid":
        $SQL = "SELECT * FROM employee WHERE employeeid IN (SELECT employeeid FROM employee_timecard WHERE (hours > 0 OR hours_override > 0) AND fromdate >= ||t_from|| AND todate <= ||t_to||) AND employeeid = ||id||";
        $sql_vars["t_from"] = $fromnum;
        $sql_vars["t_to"] = $tonum;
        $sql_vars["id"] = $id;
        break;
    case "all_tax_papers":
        if ($type == "aid") {
            $SQL = "SELECT * FROM accounts WHERE aid IN (SELECT aid FROM billing_payments WHERE payment > 0 $timesql) AND aid = ||id|| ORDER BY name";
            $sql_vars["id"] = $id;
        } else {
            $SQL = "SELECT * FROM accounts WHERE aid IN (SELECT aid FROM billing_payments WHERE payment > 0 $timesql) ORDER BY name";
        }
        break;
    case "payments_between":
        $SQL = "SELECT * FROM billing_payments WHERE payment > 0 AND $type = ||id|| $timesql ORDER BY timelog ASC";
        $sql_vars["id"] = $id;
        break;
    case "invoice_between":
        break;
    case "meal_status_count":
        $SQL = "SELECT $order_day,a.timelog,b.meal_status FROM activity a JOIN children c ON c.chid = a.chid JOIN accounts b ON b.aid = c.aid WHERE tag='in' AND $type = ||id|| AND a.chid IN (SELECT chid FROM enrollments WHERE $type = ||id||) $timesql GROUP BY order_day,a.chid ORDER BY order_day";
        $sql_vars["id"] = $id;
        break;
    case "program_per_child_attendance":
        $SQL = "SELECT CONCAT(c.chid,'-',CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'" . $servertz . "','" . $localtz . "')))) as ident,a.timelog,c.chid,c.last,c.first FROM activity a JOIN children c ON c.chid = a.chid WHERE tag='in' AND $type = ||id|| AND a.chid IN (SELECT chid FROM enrollments WHERE $type = ||id||) $timesql GROUP BY ident ORDER BY c.last,c.first";
        $sql_vars["id"] = $id;
        break;
    case "program_per_account_attendance":
        $SQL = "SELECT CONCAT(c.chid,'-',CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'" . $servertz . "','" . $localtz . "')))) as ident,c.chid,c.aid FROM activity a JOIN children c ON c.chid = a.chid JOIN accounts d ON c.aid = d.aid WHERE tag='in' AND $type = ||id|| AND a.chid IN (SELECT chid FROM enrollments WHERE $type = ||id||) $timesql GROUP BY ident ORDER BY d.name";
        $sql_vars["id"] = $id;
        break;
    case "child_list":
        $SQL = "SELECT * FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE $type = ||id||) AND deleted=0 ORDER BY last";
        $sql_vars["id"] = $id;
        break;
    case "weekly_expected_attendance":
        $SQL = "SELECT * FROM enrollments WHERE $type = ||id|| AND deleted=0";
        $sql_vars["id"] = $id;
        break;
    case "program_per_account_bill":
        $SQL = "SELECT a.aid FROM accounts a JOIN children c ON c.aid = a.aid WHERE c.chid IN (SELECT chid FROM enrollments WHERE $type = ||id||) GROUP BY a.aid ORDER BY a.name";
        $sql_vars["id"] = $id;
        break;
    case "program_per_program_cash_flow":
        if ($type == "aid") {
            $SQL = "SELECT SUM(owed) as bill,pid,fromdate,todate FROM billing WHERE pid = ||active_pid|| AND $type = ||id|| GROUP BY fromdate ORDER BY fromdate";
            $sql_vars["active_pid"] = get_pid();
        } else {
            $SQL = "SELECT SUM(owed) as bill,pid,fromdate,todate FROM billing WHERE $type = ||id|| GROUP BY fromdate ORDER BY fromdate";
        }
        $sql_vars["id"] = $id;
        break;
    case "activity_tag":
        $att_tag_sql = "(tag IN (SELECT tag FROM notes_required WHERE tag = ||att_tag||) OR tag IN (SELECT tag FROM events_tags WHERE tag = ||att_tag||))";
        $sql_vars["att_tag"] = $att_tag;
        $sql_vars["pid"] = $pid;
        $extra = "";
        if ($type == "chid") {
            $extra = "AND chid = ||id||";
            $sql_vars["id"] = $id;
        } elseif ($type == "cid") {
            $extra = "AND cid = ||id||";
            $sql_vars["id"] = $id;
        } elseif ($type == "aid") {
            $extra = "AND aid = ||id||";
            $sql_vars["id"] = $id;
        }

        if (!empty($actid)) {
            $SQL = "SELECT SUM(data) as data,$type,timelog,tag,$order_day FROM notes WHERE pid = ||pid|| $extra AND actid = ||actid|| AND $att_tag_sql $timesql GROUP BY order_day ORDER BY order_day,timelog,data DESC";
            $sql_vars["actid"] = $actid;
        } else {
            $SQL = "SELECT SUM(data) as data,timelog,tag,$order_day FROM notes WHERE pid = ||pid|| $extra AND actid!='0' AND $att_tag_sql $timesql GROUP BY order_day,$type ORDER BY order_day,timelog,data DESC";
        }
        break;
}

switch ($report) {
    case "allnotes":
    case "activity":
        if ($notes = get_db_result($SQL, $sql_vars)) {
            $returnme .= '<div style="font-size:150%;text-align:center;"><strong>' . $name . '</strong></div>';
            $actids = [];
            while ($note = fetch_row($notes)) {
                $temp = note_entry($note);
                if (count($actids)) {
                    $entry = $temp;
                    $arraycount = count($actids);
                    $arraycount--;
                    $fieldcount = get_next_fieldcount($actids[$arraycount]);
                    $i = 1;
                    while (!empty($entry["field$i" . "name"])) { //Run through each field
                        //if fieldnumber already exists but it doesn't match
                        if (!empty($actids[$arraycount]["field$i" . "name"]) && $entry["field$i" . "name"] != $actids[$arraycount]["field$i" . "name"]) {
                            //Does it exist?
                            if ($y = match_fieldname($actids[$arraycount], $entry["field$i" . "name"])) {
                                $temp["field$y" . "name"] = $entry["field$i" . "name"];
                                $temp["field$y" . "value"] = $entry["field$i" . "value"];
                            } else { //No match found, create new number
                                $temp["field$fieldcount" . "name"] = $entry["field$i" . "name"];
                                $temp["field$fieldcount" . "value"] = $entry["field$i" . "value"];

                                //Go back and fill in all the rows where this field didn't exist
                                $actids = fill_new_fields($actids, $entry["field$i" . "name"], $fieldcount);
                                $fieldcount++;
                            }

                            $temp = fill_skipped_fields($actids[$arraycount], $temp, $fieldcount, $fieldcount);
                        //if fieldnumber doesn't exist
                        } elseif (empty($actids[$arraycount]["field$i" . "name"])) {
                            //is new field needed?
                            if ($y = match_fieldname($actids[$arraycount], $entry["field$i" . "name"])) {
                                $temp["field$y" . "name"] = $entry["field$i" . "name"];
                                $temp["field$y" . "value"] = $entry["field$i" . "value"];
                            } else {
                                $actids = fill_new_fields($actids, $entry["field$i" . "name"], $i);
                            }
                        }
                        $i++;
                    }

                    $fieldcount = get_next_fieldcount($actids[$arraycount]);
                    if ($i <= ($fieldcount - 1) && empty($temp["field" . ($fieldcount - 1) . "name"])) {
                        $temp = fill_new_temp($actids[$arraycount], $temp, $i, ($fieldcount - 1));
                    }
                }

                $actids[] = $temp;
            }

            $returnme .= format_report_data($actids);
            $returnme .= '</div></div>';
        }
//        $returnme .= '</div>';
//        if(empty($MYVARS->GET["sort"])){ $returnme .= '</div>'; }
        break;
    case "invoice":
        if ($accounts = get_db_result($SQL, $sql_vars)) {
            while ($account = fetch_row($accounts)) {
                $totalpaid = $total_owed = $totalfee = 0;
                $returnme .= '
                    <div>
                        <div style="font-size:20px;text-align:center;">
                            <strong>Account: ' . $account["name"] . '</strong>
                        </div>';
                $bv = ["pid" => $pid, "aid" => $account["aid"], "from" => $fromnum, "to" => $tonum];
                $SQL = "SELECT *
                        FROM billing_payments
                        WHERE pid = ||pid||
                        AND aid = ||aid||
                        AND payment < 0
                        AND timelog >= ||from||
                        AND timelog <= ||to||
                        ORDER BY timelog,payid";
                if ($payments = get_db_result($SQL, $bv)) {
                    $totalfee = abs(get_db_field("SUM(payment)", "billing_payments", "pid = ||pid|| AND aid = ||aid|| AND payment < 0 AND timelog >= ||from|| AND timelog <= ||to||", $bv));
                    $totalfee = empty($totalfee) ? "0.00" : $totalfee;

                    $feelist = "";
                    while ($payment = fetch_row($payments)) {
                        $feelist .= '
                            <div style="padding-left: 10px;">
                                <div style="padding: 0px 10px;">
                                    <strong>' . date('m/d/Y', display_time($payment["timelog"])) . '</strong> - Fee of $' . number_format(abs($payment["payment"]), 2) . '
                                </div>
                                <div style="padding: 0px 50px;">
                                    <em>' . $payment["note"] . '</em>
                                </div>
                            </div>
                            <br />';
                    }
                    $returnme .= '
                        <div style="font-size:16px;">
                            <strong>Fees</strong>
                        </div>
                        <div style="padding: 5px;">
                        ' . $feelist . '
                        </div>';
                }

                $bv = ["pid" => $pid, "aid" => $account["aid"], "from" => $fromnum, "to" => $tonum];
                $SQL = "SELECT *
                        FROM billing_payments
                        WHERE pid = ||pid||
                        AND aid = ||aid||
                        AND payment >= 0
                        AND timelog >= ||from||
                        AND timelog <= ||to||
                        ORDER BY timelog,payid";
                if ($payments = get_db_result($SQL, $bv)) {
                    $totalpaid = get_db_field("SUM(payment)", "billing_payments", "pid = ||pid|| AND aid = ||aid|| AND payment >= 0 AND timelog >= ||from|| AND timelog <= ||to||", $bv);
                    $totalpaid = empty($totalpaid) ? "0.00" : $totalpaid;

                    $paymentlist = "";
                    while ($payment = fetch_row($payments)) {
                        $paymentlist .= '
                            <div style="padding-left: 10px;">
                                <div style="padding: 0px 10px;">
                                    <strong>' . date('m/d/Y', display_time($payment["timelog"])) . '</strong> - Payment of $' . number_format($payment["payment"], 2) . '
                                </div>
                                <div style="padding: 0px 50px;">
                                    <em>' . $payment["note"] . '</em>
                                </div>
                            </div>
                            <br />';
                    }
                    $returnme .= '
                        <div style="font-size:16px;">
                            <strong>Payments</strong>
                        </div>
                        <div style="padding: 5px;">
                            ' . $paymentlist . '
                        </div>';
                }
                $bv = ["pid" => $pid, "aid" => $account["aid"], "from" => $fromnum, "to" => $tonum];
                $SQL = "SELECT * FROM billing WHERE pid = ||pid|| AND aid = ||aid|| AND fromdate >= ||from|| AND fromdate <= ||to|| ORDER BY fromdate";
                if ($invoices = get_db_result($SQL, $bv)) {
                    $returnme .= '<div style="font-size:16px;"><strong>Activity</strong></div>';
                    while ($invoice = fetch_row($invoices)) {
                        $returnme .= '
                            <div class="week" style="vertical-align: top;padding: 5px;">
                                <div>
                                    <strong>Week of ' . date('F \t\h\e jS, Y', $invoice["fromdate"]) . '</strong>
                                </div>
                                <div style="padding-left: 30px;">
                                    ' . $invoice["receipt"] . '
                                </div>
                            </div>';
                    }
                    $total_owed = get_db_field("SUM(owed)", "billing", "pid = ||pid|| AND aid = ||aid|| AND fromdate >= ||from|| AND fromdate <= ||to||", $bv);
                    $total_owed += $totalfee;
                    $total_owed = empty($total_owed) ? "0.00" : $total_owed;
                    $balance   = $total_owed - $totalpaid;
                    $returnme .= '
                        <div style="text-align:right;color:darkred;">
                            <strong>Owed:</strong> $' . number_format($total_owed, 2) . '
                        </div>
                        <div style="text-align:right;color:blue;">
                            <strong>Paid:</strong> $' . number_format($totalpaid, 2) . '
                        </div>
                        <hr align="right" style="width:100px;"/>
                        <div style="text-align:right">
                            <strong>Balance:</strong> $' . number_format($balance * -1, 2) . '
                        </div>
                    </div>';
                } else {
                    $returnme .= '<div style="text-align:center">No Invoices</div>';
                }
                $returnme .= '</div>';
            }
            $returnme .= '</div>';
        }
        break;
    case "invoicetimeline":
        if ($accounts = get_db_result($SQL, $sql_vars)) {
            while ($account = fetch_row($accounts)) {
                $totalpaid = $total_owed = $totalfee = $lasttodate = 0;
                $firstrun = true;
                $returnme .= '
                    <div>
                        <div style="font-size:20px;text-align:center;">
                            <strong>Account: ' . $account["name"] . '</strong>
                        </div>';

                $bv = ["pid" => $pid, "aid" => $account["aid"], "from" => $fromnum, "to" => $tonum];
                $SQL = "SELECT *
                        FROM billing
                        WHERE pid = ||pid||
                        AND aid = ||aid||
                        AND fromdate >= ||from||
                        AND fromdate <= ||to||
                        ORDER BY fromdate";
                if ($invoices = get_db_result($SQL, $bv)) {
                    $returnme .= '
                        <div style="font-size:16px;">
                            <strong>Activity</strong>
                        </div>';
                    while ($invoice = fetch_row($invoices)) {
                        if ($firstrun) { // check for payment or Fee prior to an invoice
                            $pv = ["pid" => $pid, "aid" => $account["aid"], "before" => $invoice["fromdate"], "from" => $fromnum, "to" => $tonum];
                            $SQL = "SELECT *
                                    FROM billing_payments
                                    WHERE pid = ||pid||
                                    AND aid = ||aid||
                                    AND timelog < ||before||
                                    AND timelog >= ||from||
                                    AND timelog <= ||to||
                                    ORDER BY timelog,payid";
                            if ($transactions = get_db_result($SQL, $pv)) {
                                $transactionlist = "";
                                while ($transaction = fetch_row($transactions)) {
                                    if ($transaction["payment"] < 0) {
                                        $transactionlist .= '
                                            <div style="padding-left:10px">
                                                <strong>Fee: $' . number_format(abs($transaction["payment"]), 2) . '</strong> on ' . date('F \t\h\e jS, Y', display_time($transaction["timelog"])) . ' <em> Note:' . $transaction["note"] . '</em>
                                            </div>';
                                    } else {
                                        $transactionlist .= '
                                            <div style="padding-left:10px">
                                                <strong>Payment: $' . number_format($transaction["payment"], 2) . '</strong> on ' . date('F \t\h\e jS, Y', display_time($transaction["timelog"])) . ' <em> Note:' . $transaction["note"] . '</em>
                                            </div>';
                                    }
                                }
                                $returnme .= '
                                    <div class="week" style="vertical-align: top;padding: 5px;">
                                        <div>
                                            <strong>Payments/Fees Prior to 1st Invoice</strong>
                                        </div>
                                        <div style="padding-left: 30px;">
                                            ' . $transactionlist . '
                                        </div>
                                    </div>';
                            }
                            $firstrun = false;
                        }

                        // check for payment or Fee
                        $pv = ["pid" => $pid, "aid" => $account["aid"], "from" => $invoice["fromdate"], "to" => $invoice["todate"]];
                        $SQL = "SELECT * FROM billing_payments WHERE pid = ||pid|| AND aid = ||aid|| AND (timelog >= ||from|| AND timelog < ||to||) ORDER BY timelog,payid";
                        $paymentlist = "";
                        if ($transactions = get_db_result($SQL, $pv)) {
                            while ($transaction = fetch_row($transactions)) {
                                if ($transaction["payment"] < 0) {
                                    $paymentlist .= '
                                        <div style="padding-left:10px">
                                            <strong>Fee: $' . number_format(abs($transaction["payment"]), 2) . '</strong> on ' . date('F \t\h\e jS, Y', display_time($transaction["timelog"])) . ' <em> Note:' . $transaction["note"] . '</em>
                                        </div>';
                                } else {
                                    $paymentlist .= '
                                        <div style="padding-left:10px">
                                            <strong>Payment: $' . number_format($transaction["payment"], 2) . '</strong> on ' . date('F \t\h\e jS, Y', display_time($transaction["timelog"])) . ' <em> Note:' . $transaction["note"] . '</em>
                                        </div>';
                                }
                            }
                        }

                        $returnme .= '
                            <div class="week" style="vertical-align: top;padding: 5px;">
                                <div>
                                    <strong>Week of ' . date('F \t\h\e jS, Y', $invoice["fromdate"]) . '</strong>
                                </div>
                                <div style="padding-left: 30px;">
                                    ' . str_replace("Week Total", "Invoice Amount", $invoice["receipt"]) . '
                                    ' . $paymentlist . '
                                </div>
                            </div>';
                        $lasttodate = $invoice["todate"];
                    }

                    // check for payment or Fee after last invoice
                    $pv = ["pid" => $pid, "aid" => $account["aid"], "from" => $lasttodate, "to" => $tonum];
                    $SQL = "SELECT *
                            FROM billing_payments
                            WHERE pid = ||pid||
                            AND aid = ||aid||
                            AND timelog >= ||from||
                            AND timelog <= ||to||
                            ORDER BY timelog,payid";
                    if ($transactions = get_db_result($SQL, $pv)) {
                        $transactionlist = "";
                        while ($transaction = fetch_row($transactions)) {
                            if ($transaction["payment"] < 0) {
                                $transactionlist .= '
                                    <div style="padding-left:10px">
                                        <strong>Fee: $' . number_format(abs($transaction["payment"]), 2) . '</strong> on ' . date('F \t\h\e jS, Y', display_time($transaction["timelog"])) . ' <em> Note:' . $transaction["note"] . '</em>
                                    </div>';
                            } else {
                                $transactionlist .= '
                                    <div style="padding-left:10px">
                                        <strong>Payment: $' . number_format($transaction["payment"], 2) . '</strong> on ' . date('F \t\h\e jS, Y', display_time($transaction["timelog"])) . ' <em> Note:' . $transaction["note"] . '</em>
                                    </div>';
                            }
                        }
                        $returnme .= '
                            <div class="week" style="vertical-align: top;padding: 5px;">
                                <div>
                                    <strong>More Recent Activity</strong>
                                </div>
                                <div style="padding-left: 30px;">
                                    ' . $transactionlist . '
                                </div>
                            </div>';
                    }

                    $totalpaid = get_db_field("SUM(payment)", "billing_payments", "pid = ||pid|| AND aid = ||aid|| AND payment >= 0 AND timelog >= ||from|| AND timelog <= ||to||", $bv);
                    $totalpaid = empty($totalpaid) ? "0.00" : $totalpaid;
                    if ($totalfee = get_db_field("SUM(payment)", "billing_payments", "pid = ||pid|| AND aid = ||aid|| AND payment < 0 AND timelog >= ||from|| AND timelog <= ||to||", $bv)) {
                        $totalfee = abs($totalfee);
                    }
                    $totalfee = empty($totalfee) ? "0.00" : $totalfee;
                    $total_owed = get_db_field("SUM(owed)", "billing", "pid = ||pid|| AND aid = ||aid|| AND fromdate >= ||from|| AND fromdate <= ||to||", $bv);
                    $total_owed += $totalfee;
                    $total_owed = empty($total_owed) ? "0.00" : $total_owed;
                    $balance   = $total_owed - $totalpaid;
                    $returnme .= '
                        <div style="text-align:right;color:darkred;">
                            <strong>Owed:</strong> $' . number_format($total_owed, 2) . '
                        </div>
                        <div style="text-align:right;color:blue;">
                            <strong>Paid:</strong> $' . number_format($totalpaid, 2) . '
                        </div>
                        <hr align="right" style="width:100px;"/>
                        <div style="text-align:right">
                            <strong>Balance:</strong> $' . number_format($balance * -1, 2) . '
                        </div>';
                } else {
                    $returnme .= "<div style='text-align:center'>No Invoices</div>";
                }
                $returnme .= "</div>";
            }
            $returnme .= '</div>';
        }
        break;
    case "employee_paid":
        if ($employees = get_db_result($SQL, $sql_vars)) {
            while ($employee = fetch_row($employees)) {
                $name = get_name(["type" => "employeeid","id" => $employee["employeeid"]]);
                $SQL = "SELECT * FROM employee_timecard WHERE (hours > 0 || hours_override > 0) AND employeeid='" . $employee["employeeid"] . "' AND fromdate >= $fromnum AND todate <= $tonum ORDER BY fromdate ASC";
                $sum = 0;
                if ($payments = get_db_result($SQL, $sql_vars)) {
                    $returnme .= '
                    <div style="float:left;">
                        <div style="font-size:120%;"><strong>Employee: ' . $name . '</strong></div>
                        <div><strong>Dates:</strong> ' . $fromtostring . '</div>
                    ';
                    $names = [];
                    while ($payment = fetch_row($payments)) {
                        $hours = empty($payment["hours_override"]) ? $payment["hours"] : $payment["hours_override"];
                        $pay = $payment["wage"] * $hours;
                        $sum += $pay;
                        $names[] = ["field1name" => "Week of", "field1value" => date('m/d/Y', $payment["fromdate"]), "field2name" => "Hours", "field2value" => '' . number_format($hours, 2), "field3name" => "Wage", "field3value" => "$" . $payment["wage"] . "/hr","field4name" => "Paid", "field4value" => '$' . number_format($pay, 2)];
                    }

                    $returnme .= '<div><strong>Total Paid: $' . number_format($sum, 2) . '</strong></div>
                    </div><br /><br /><br />';
                    $returnme .= format_report_data($names, $employee["employeeid"]);
                    $returnme .= '<div style="page-break-after: always"></div>';
                }
            }
            $returnme .= "</div>";
        }
        break;
    case "all_tax_papers":
        $years = range(date("Y") - 5, date("Y"));
        $selected = date("Y", $fromnum);
        $onchange = "
            $.ajax({
              method: 'GET',
              url: 'ajax/reports.php',
              data: { report: 'all_tax_papers', type: 'aid', id: '$id', from: '01/01/' + this.value, to: '12/31/' + this.value }
            })
              .done(function( msg ) {
                 $('.nyroModalForm').html(msg);
            });
        ";
        echo '<div align="center">Year Selector: ' . make_select_from_array("yearselector", $years, false, false, $selected, $onchange) . '</div>';
        if ($accounts = get_db_result($SQL, $sql_vars)) {
            $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ["pid" => $pid]);
            while ($account = fetch_row($accounts)) {
                $name = get_name(["type" => "aid","id" => $account["aid"]]);
                $bv = array_merge(["aid" => $account["aid"]], $sql_vars);
                $SQL = "SELECT * FROM billing_payments WHERE payment > 0 AND aid = ||aid|| $timesql ORDER BY timelog ASC";
                $sum = 0;
                if ($payments = get_db_result($SQL, $bv)) {
                    $returnme .= '
                    <div style="float:right;">
                        <div style="font-size:120%;">
                            <strong>' . $program['name'] . '</strong>
                        </div>
                        <div>
                            <strong>' . $CFG->streetaddress . '</strong>
                        </div>
                        <div>
                            <strong>Business ID or FEIN:</strong> ' . $program['fein'] . '
                        </div>
                    </div>
                    <div style="float:left;">
                        <div style="font-size:120%;"><strong>Account: ' . $name . '</strong></div>
                        <div><strong>Dates:</strong> ' . $fromtostring . '</div>
                    ';
                    $names = [];
                    while ($payment = fetch_row($payments)) {
                        $sum += $payment["payment"];
                        $names[] = ["field1name" => "Date", "field1value" => date('m/d/Y', $payment["timelog"]), "field2name" => "Amount", "field2value" => '$' . number_format($payment["payment"], 2), "field3name" => "Note", "field3value" => $payment["note"]];
                    }

                    $returnme .= '<div><strong>Total Paid: $' . number_format($sum, 2) . '</strong></div>
                    </div><br /><br /><br />';
                    $returnme .= format_report_data($names, $account["aid"]);
                    $returnme .= '<div style="page-break-after: always"></div>';
                }
            }
            $returnme .= "</div>";
        }
        break;
    case "payments_between":
        $sum = 0;
        if ($payments = get_db_result($SQL, $sql_vars)) {
            $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ["pid" => $pid]);
            $returnme .= '
            <div style="float:right;">
                <div style="font-size:120%;">
                    <strong>' . $program['name'] . '</strong>
                </div>
                <div>
                    <strong>' . $CFG->streetaddress . '</strong>
                </div>
                <div>
                    <strong>FEIN:</strong> ' . $program['fein'] . '
                </div>
            </div>
            <div style="float:left;">
                <div style="font-size:120%;"><strong>Account: ' . $name . '</strong></div>
                <div><strong>Dates:</strong> ' . $fromtostring . '</div>
            ';
            $names = [];
            while ($payment = fetch_row($payments)) {
                $sum += $payment["payment"];
                $names[] = ["field1name" => "Date", "field1value" => date('m/d/Y', display_time($payment["timelog"])), "field2name" => "Amount", "field2value" => '$' . number_format($payment["payment"], 2), "field3name" => "Note", "field3value" => $payment["note"]];
            }

            $returnme .= '<div><strong>Total Paid: $' . number_format($sum, 2) . '</strong></div></div><br /><br /><br />';
            $returnme .= format_report_data($names);
            $returnme .= '</div>';
        }
        break;
    case "invoice_between":
        $aid = $id;
        $totalpaid = $totalowed = 0;
        $returnme .= '<div><div style="font-size:20px;text-align:center;"><strong>Account: ' . get_name(["type" => "aid","id" => $aid]) . '</strong><br />' . $fromtostring . '</div>';
        $sql_vars["pid"] = $pid;
        $sql_vars["aid"] = $aid;
        $SQL = "SELECT * FROM billing_payments WHERE pid = ||pid|| AND aid = ||aid|| $timesql ORDER BY timelog,payid";
        if ($payments = get_db_result($SQL, $sql_vars)) {
            $totalpaid = get_db_field("SUM(payment)", "billing_payments", "pid = ||pid|| AND aid = ||aid|| $timesql", $sql_vars);
            $totalpaid = empty($totalpaid) ? "0.00" : $totalpaid;
            $returnme .= '<div style="font-size:16px;"><strong>Payments</strong></div>
                            <div style="padding: 5px;">';
            while ($payment = fetch_row($payments)) {
                $returnme .= '
                    <div>
                        <div style="padding: 0px 10px;">
                            <strong>' . date('m/d/Y', display_time($payment["timelog"])) . '</strong> - Payment of $' . number_format($payment["payment"], 2) . '
                        </div>
                        <div style="padding: 0px 50px;">
                            <em>' . $payment["note"] . '</em>
                        </div>
                    </div><br />';
            }
            $returnme .= '</div>';
        }
        $sql_vars["pid"] = $pid;
        $sql_vars["aid"] = $aid;
        $SQL = "SELECT * FROM billing WHERE pid = ||pid|| AND aid = ||aid|| $timesql2 ORDER BY fromdate";
        if ($invoices = get_db_result($SQL, $sql_vars)) {
            $returnme .= '<div style="font-size:16px;"><strong>Activity</strong></div>';
            while ($invoice = fetch_row($invoices)) {
                $returnme .= '
                    <div class="week" style="vertical-align: top;padding: 5px;">
                        <div>
                            <strong>Week of ' . date('F \t\h\e jS, Y', $invoice["fromdate"]) . '</strong>
                        </div>
                        <div style="padding-left: 30px;">
                            ' . $invoice["receipt"] . '
                        </div>
                    </div>';
            }
            $totalowed = get_db_field("SUM(owed)", "billing", "pid = ||pid|| AND aid = ||aid|| $timesql2", $sql_vars);
            $totalowed = empty($totalowed) ? "0.00" : $totalowed;
            $balance   = $totalowed - $totalpaid;
            $returnme .= '
                <div style="text-align:right;color:darkred;">
                    <strong>Owed:</strong> $' . number_format($totalowed * -1, 2) . '
                </div>
                <div style="text-align:right;color:blue;">
                    <strong>Paid:</strong> $' . number_format($totalpaid * -1, 2) . '
                </div><hr align="right" style="width:100px;"/>
                <div style="text-align:right">
                    <strong>Balance:</strong> $' . number_format($balance * -1, 2) . '
                </div>';
        } else {
            $returnme .= "<div style='text-align:center'>No Invoices</div>";
        }
        $returnme .= "</div></div>";
        break;
    case "program_per_child_attendance":
        if ($children = get_db_result($SQL, $sql_vars)) {
            $currentdate = false;
            $returnme .= '<div style="font-size:150%;text-align:center;"><strong>' . $name . '</strong><br />' . $fromtostring . '</div>';
            $chids = [];
            $attendance = $current_child = $current = $hours = 0;
            while ($child = fetch_row($children)) {
                if ($current_child != $child["chid"]) { //New Child
                    if ($current_child != 0) { //Not First Child
                        $chids[] = ["field1name" => "Name", "field1value" => $current["last"] . " " . $current["first"], "field2name" => "Attendance", "field2value" => $attendance, "field3name" => "Hours", "field3value" => number_format($hours, 2), "field4name" => "Average", "field4value" => number_format($hours / $attendance, 2)];
                        $attendance = $hours = 0;
                    }
                }
                $current = $child;
                $current_child = $current["chid"];
                $attendance++;
                $hours += hours_attended($current_child, $child["timelog"]);
            } $chids[] = ["field1name" => "Name", "field1value" => $current["last"] . " " . $current["first"], "field2name" => "Attendance", "field2value" => $attendance, "field3name" => "Hours", "field3value" => number_format($hours, 2), "field4name" => "Average", "field4value" => number_format($hours / $attendance, 2)];
            $returnme .= format_report_data($chids);
            $returnme .= '</div>';
        }
        break;
    case "meal_status_count":
        if ($days = get_db_result($SQL, $sql_vars)) {
            $returnme .= '<div style="font-size:150%;text-align:center;"><strong>' . $name . ' Meal Status</strong></div>';
            $activity = [];
            $paid = $reduced = $free = 0;
            $paidtotal = $reducedtotal = $freetotal = 0;
            $order_day = false;
            while ($day = fetch_row($days)) {
                if (!empty($order_day) && $order_day != $day["order_day"]) { //new day and not the first
                    $activity[] = ["field1name" => "Date", "field1value" => date("m/d/Y", display_time($lasttimelog)), "field2name" => "Paid", "field2value" => number_format($paid), "field3name" => "Reduced", "field3value" => number_format($reduced), "field4name" => "Free", "field4value" => number_format($free)];
                    $paid = $reduced = $free = 0; //reset counters
                }
                $paid += $day["meal_status"] == "paid" ? 1 : 0;
                $reduced += $day["meal_status"] == "reduced" ? 1 : 0;
                $free += $day["meal_status"] == "free" ? 1 : 0;
                $paidtotal += $day["meal_status"] == "paid" ? 1 : 0;
                $reducedtotal += $day["meal_status"] == "reduced" ? 1 : 0;
                $freetotal += $day["meal_status"] == "free" ? 1 : 0;
                $order_day = $day["order_day"];
                $lasttimelog = $day["timelog"];
            }
            $activity[] = ["field1name" => "Date", "field1value" => date("m/d/Y", display_time($lasttimelog)), "field2name" => "Paid", "field2value" => number_format($paid), "field3name" => "Reduced", "field3value" => number_format($reduced), "field4name" => "Free", "field4value" => number_format($free)];
            $activity[] = ["footer" => true, "field1name" => "Date", "field1value" => "<strong>Total</strong>", "field2name" => "Paid", "field2value" => number_format($paidtotal), "field3name" => "Reduced",  "field3value" => number_format($reducedtotal), "field4name" => "Free",  "field4value" => number_format($freetotal)];
            $returnme .= format_report_data($activity);
            $returnme .= '</div>';
        }
        break;
    case "attendance_throughout_day":
        $dividedbymin = empty($MYVARS->GET["extra"]) ? 30 : $MYVARS->GET["extra"]; //divide this report into segments (in minutes)

        //GETS all 1st checkin times for all kids in program between dates
        $sql_vars["id"] = $id;
        $SQL = "SELECT $order_day,CONCAT(CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'" . $servertz . "','" . $localtz . "'))),a.chid) as sortby,a.timelog,a.chid FROM activity a JOIN children c ON c.chid = a.chid JOIN accounts b ON b.aid = c.aid WHERE tag='in' AND $type = ||id|| AND a.chid IN (SELECT chid FROM enrollments WHERE $type = ||id||) $timesql GROUP BY sortby ORDER BY timelog";
        $dividedbyseconds = $dividedbymin * 60;
        $segments = 86400 / $dividedbyseconds;

        if ($result = get_db_result($SQL, $sql_vars)) {
            $returnme .= '<div style="font-size:150%;text-align:center;"><strong>Daily Attendance Breakdown</div>';
            $order_day = false;
            $savedata = [];
            $datearray = [];

            //example (timeclosed set to 5:30pm, figure seconds from midnight - timezone offset)
            $endofwork = seconds_from_midnight(get_db_field("timeclosed", "programs", "pid = ||pid||", ["pid" => $pid])) - get_offset($CFG->timezone);

            while ($row = fetch_row($result)) {
                //get beginning of day and end of day timestamps
                $daybegins = mktime(0, 0, 0, get_date("n", $row["timelog"], $CFG->timezone), get_date("j", $row["timelog"], $CFG->timezone), get_date("Y", $row["timelog"], $CFG->timezone));
                $dayends = strtotime("+1 day", $daybegins);
                $signouttime = false;
                if (empty($order_day) || $order_day != $row["order_day"]) { //build array of days represented
                    $datearray[] = $daybegins;
                }

                //what time did they sign out
                $SQL2 = "SELECT MAX(a.timelog) as signedout FROM activity a JOIN children c ON c.chid = a.chid JOIN accounts b ON b.aid = c.aid WHERE a.chid = ||chid|| AND tag = 'out' AND a.timelog > ||daybegins|| AND a.timelog < ||dayends|| AND $type = ||id|| AND a.chid IN (SELECT chid FROM enrollments WHERE $type = ||id||)";
                $signedout = get_db_row($SQL2, false, ["chid" => $row["chid"], "daybegins" => $daybegins, "dayends" => $dayends, "id" => $id]);
                if (!empty($signedout["signedout"])) {
                    $signouttime = $signedout["signedout"];
                }

                //if the signout was after end of work hours, assume checkout at end of work time
                $signouttime = empty($signouttime) || $signouttime > ($daybegins + $endofwork) ? ($daybegins + $endofwork) : $signouttime;

                //loop through possible time slots and save result
                $i = 0;
                $saved = false;
                //echo "Check In: ".$row["timelog"]." Check Out: ".$signouttime." Day Begin: ".$daybegins." Day End: ".$dayends."<br />";
                while ($i < $segments) {
                    $time = $daybegins + ($i * $dividedbyseconds);
                    $timespan = $dividedbyseconds / 2;
                    $beginning = $time - $timespan < $daybegins ? $daybegins : $time - $timespan;
                    $end = $time + $timespan > $dayends ? $dayends : $time + $timespan;
                    //if sign in time was in the timespan of this segment
                    if (($row["timelog"] > $beginning && $row["timelog"] <= $end)) {
                        $savedata[$i][$daybegins] = empty($savedata[$i]) ? 1 : $savedata[$i][$daybegins] + 1;
                    //if child had already signed in and did not signed out in this timespan
                    } elseif ($row["timelog"] < $beginning && $signouttime > $end) {
                        $savedata[$i][$daybegins] = empty($savedata[$i]) ? 1 : $savedata[$i][$daybegins] + 1;
                    }
                    $i++;
                }

                $order_day = $row["order_day"];
            }

            //make sure the stats are in the correct order 3:30 4:00 4:30 etc
            ksort($savedata);
            $activity = [];
            $averages = [];
            $avgvalues = [];
            $y = 0;
            foreach ($datearray as $day) {
                $activity[$y] = ["field1name" => "Date", "field1value" => get_date("m/d/Y", $day)];
                $averages[0] = ["footer" => true, "field1name" => "Date", "field1value" => "<strong>Averages</strong>"];

                $g = 2;
                foreach ($savedata as $timeslot => $dayvalue) {
                    $activity[$y]["field$g" . "name"] = get_date("g:ia", (($timeslot * $dividedbyseconds) + mktime(0, 0, 0)), $CFG->timezone);
                    $averages[0]["field$g" . "name"] = get_date("g:ia", (($timeslot * $dividedbyseconds) + mktime(0, 0, 0)), $CFG->timezone);
                    if (!empty($dayvalue[$day])) {
                        $activity[$y]["field$g" . "value"] = $dayvalue[$day];
                        $avgvalues[$timeslot] = empty($avgvalues[$timeslot]) ? $dayvalue[$day] : $avgvalues[$timeslot] + $dayvalue[$day];
                    } else {
                        $activity[$y]["field$g" . "value"] = 0;
                    }
                    $g++;
                }
                $y++;
            }


            $j = 2;
            ksort($avgvalues);
            foreach ($avgvalues as $val) {
                $averages[0]["field$j" . "value"] = number_format($val / $y, 2);
                $j++;
            }

            $activity[] = $averages[0];

            $returnme .= format_report_data($activity);
            $returnme .= '</div>';
        }

        break;
    case "program_per_account_attendance":
        if ($accounts = get_db_result($SQL, $sql_vars)) {
            $currentdate = false;
            $returnme .= '<div style="font-size:150%;text-align:center;"><strong>' . $name . '</strong><br />' . $fromtostring . '</div>';
            $aids = [];
            $attendance = $current_account = $current = 0;
            while ($account = fetch_row($accounts)) {
                if ($current_account != $account["aid"]) { //New Child
                    if ($current_account != 0) { //Not First Child
                        $aids[] = ["field1name" => "Name", "field1value" => get_name(["type" => "aid","id" => $current["aid"]]), "field2name" => "Attendance", "field2value" => $attendance];
                        $attendance = 0;
                    }
                }
                $current = $account;
                $current_account = $current["aid"];
                $attendance++;
            } $aids[] = ["field1name" => "Name", "field1value" => get_name(["type" => "aid","id" => $current["aid"]]), "field2name" => "Attendance", "field2value" => $attendance];

            $returnme .= format_report_data($aids);
            $returnme .= '</div>';
        }
        break;
    case "weekly_expected_attendance":
        $M = $T = $W = $Th = $F = 0;
        $returnme .= '<div style="font-size:150%;text-align:center;"><strong>Expected Attendance</strong></div>';
        if ($enrollments = get_db_result($SQL, $sql_vars)) {
            while ($e = fetch_row($enrollments)) {
                $days = explode(",", $e["days_attending"]);
                foreach ($days as $day) {
                    if (isset($$day)) {
                        $$day++;
                    }
                }
            }
        }

        $weekdays = ["Monday","Tuesday","Wednesday","Thursday","Friday"];
        $avgM = $avgT = $avgW = $avgTh = $avgF = 0;
        $divM = $divT = $divW = $divTh = $divF = 0;
        foreach ($weekdays as $weekday) {
            $i = 0;
            $timestamp = strtotime("last $weekday");
            while ($i < 4) {  //Do an average of the last 4 weeks
                $endofday = strtotime("+1 day", $timestamp);
                $av = ["id" => $id, "start" => $timestamp, "end" => $endofday];
                $SQL = "SELECT a.* FROM activity a WHERE $type = ||id|| AND (timelog >= ||start|| AND timelog < ||end||) AND tag = 'in' GROUP BY chid";

                switch ($weekday) {
                    case "Monday":
                        if ($addM = get_db_count($SQL, $av)) {
                            $avgM += $addM;
                            $divM++;
                        }
                        break;
                    case "Tuesday":
                        if ($addT = get_db_count($SQL, $av)) {
                            $avgT += $addT;
                            $divT++;
                        }
                        break;
                    case "Wednesday":
                        if ($addW = get_db_count($SQL, $av)) {
                            $avgW += $addW;
                            $divW++;
                        }
                        break;
                    case "Thursday":
                        if ($addTh = get_db_count($SQL, $av)) {
                            $avgTh += $addTh;
                            $divTh++;
                        }
                        break;
                    case "Friday":
                        if ($addF = get_db_count($SQL, $av)) {
                            $avgF += $addF;
                            $divF++;
                        }
                        break;
                }
                $timestamp = strtotime("-1 week", $timestamp);
                $i++;
            }
        }

        $avgM = empty($divM) ? $avgM : ceil($avgM / $divM);
        $avgT = empty($divT) ? $avgT : ceil($avgT / $divT);
        $avgW = empty($divW) ? $avgW : ceil($avgW / $divW);
        $avgTh = empty($divTh) ? $avgTh : ceil($avgTh / $divTh);
        $avgF = empty($divF) ? $avgF : ceil($avgF / $divF);

        $dates = [];
        foreach ($weekdays as $weekday) {
            switch ($weekday) {
                case "Monday":
                    $dates[] = ["field1name" => "Day", "field1value" => $weekday, "field2name" => "Expected", "field2value" => $M, "field3name" => "Avg", "field3value" => "$avgM (Past $divM weeks)"];
                    break;
                case "Tuesday":
                    $dates[] = ["field1name" => "Day", "field1value" => $weekday, "field2name" => "Expected", "field2value" => $Th, "field3name" => "Avg", "field3value" => "$avgT (Past $divT weeks)"];
                    break;
                case "Wednesday":
                    $dates[] = ["field1name" => "Day", "field1value" => $weekday, "field2name" => "Expected", "field2value" => $W, "field3name" => "Avg", "field3value" => "$avgW (Past $divW weeks)"];
                    break;
                case "Thursday":
                    $dates[] = ["field1name" => "Day", "field1value" => $weekday, "field2name" => "Expected", "field2value" => $Th, "field3name" => "Avg", "field3value" => "$avgTh (Past $divTh weeks)" ];
                    break;
                case "Friday":
                    $dates[] = ["field1name" => "Day", "field1value" => $weekday, "field2name" => "Expected", "field2value" => $F, "field3name" => "Avg", "field3value" => "$avgF (Past $divF weeks)" ];
                    break;
            }
        }

        $returnme .= format_report_data($dates);
        $returnme .= '</div>';
        break;
    case "child_list":
        $count = get_db_count($SQL);
        if ($children = get_db_result($SQL, $sql_vars)) {
            $returnme .= '<div style="font-size:150%;text-align:center;"><strong>' . $name . '</strong></div>';
            $returnme .= '<div style="font-size:100%;text-align:center;"><strong>Total: ' . $count . '</strong></div>';
            $names = [];
            while ($child = fetch_row($children)) {
                $names[] = ["field1name" => "First Name", "field1value" => $child["first"], "field2name" => "Last Name", "field2value" => $child["last"], "field3name" => "Grade", "field3value" => grade_convert($child["grade"]), "field4name" => "Sex", "field4value" => $child["sex"]];
            }

            $returnme .= format_report_data($names);
            $returnme .= '</div>';
        }
        break;
    case "program_per_account_bill":
        if ($accounts = get_db_result($SQL, $sql_vars)) {
            $returnme .= '<div style="font-size:150%;text-align:center;"><strong>' . $name . '</strong></div>';
            $balance = [];
            while ($account = fetch_row($accounts)) {
                $balance[] = ["field1name" => "Name", "field1value" => get_name(["type" => "aid","id" => $account["aid"]]), "field2name" => "Balance as of " . date('m/d/Y'), "field2value" => "$" . account_balance($id, $account["aid"]), "field3name" => "Current Week", "field3value" => week_balance($id, $account["aid"])];
            }

            $returnme .= format_report_data($balance);
            $returnme .= '</div>';
        }
        break;
    case "program_per_program_cash_flow":
        if ($weeks = get_db_result($SQL, $sql_vars)) {
            $returnme .= '<div style="font-size:150%;text-align:center;"><strong>' . $name . '</strong></div>';

            $balance = [];
            $totalpaid = $totalowed = $totalwages = $totalexpenses = $totaldonations = 0;
            while ($week = fetch_row($weeks)) {
                $week_vars = ["id" => $id, "wfrom" => $week["fromdate"], "wto" => $week["todate"]];
                $paid = get_db_field("SUM(payment)", "billing_payments", "pid = ||id|| AND aid > 0 AND payment > 0 AND timelog >= ||wfrom|| AND timelog < ||wto||", $week_vars);
                $fee = abs(get_db_field("SUM(payment)", "billing_payments", "pid = ||id|| AND aid > 0 AND payment < 0 AND timelog >= ||wfrom|| AND timelog < ||wto||", $week_vars));
                $donations = get_db_field("SUM(payment)", "billing_payments", "pid = ||id|| AND aid = 0 AND payment > 0 AND timelog >= ||wfrom|| AND timelog < ||wto||", $week_vars);
                $expenses = abs(get_db_field("SUM(payment)", "billing_payments", "pid = ||id|| AND aid = 0 AND payment < 0 AND timelog >= ||wfrom|| AND timelog < ||wto||", $week_vars));
                $wages = get_wages_for_week($week["fromdate"]);

                $totalpaid += $paid;
                $totalowed += $week["bill"] + $fee;
                $totalwages += $wages;
                $totalexpenses += $expenses;
                $totaldonations += $donations;

                $datelink = '<a title="Week Break Down" href="javascript:void(0);" onclick="$.ajax({
                      type: \'POST\',
                      url: \'ajax/reports.php\',
                      data: { action: \'week_breakdown\', pid: \'' . $week["pid"] . '\', fromdate: \'' . $week["fromdate"] . '\' },
                      success: function(data) { $(\'#printthis\').css(\'width\', \'auto\'); $(\'#printthis\').css(\'height\', \'auto\'); $(\'#printthis\').html(data); resize_modal(); }
                      });">' . date('m/d/Y', $week["fromdate"]) . '</a>';

                $balance[] = ["field1name" => "Week of", "field1value" => $datelink, "field2name" => "Invoiced", "field2value" => "$" . number_format($week["bill"], 2),"field3name" => "Amount Paid", "field3value" => "$" . number_format($paid, 2),"field4name" => "Donations", "field4value" => "$" . number_format($donations, 2),"field5name" => "Employee Wages", "field5value" => "$" . number_format($wages, 2),"field6name" => "Expenses", "field6value" => "$" . number_format($expenses, 2), "field7name" => "Expected Balance", "field7value" => "<strong>$" . number_format(($totalowed) + $totaldonations - $totalwages - $totalexpenses, 2) . "</strong>", "field8name" => "Actual Balance", "field8value" => "<strong>$" . number_format(($totalpaid) + $totaldonations - $totalwages - $totalexpenses, 2) . "</strong>"];
            }

            $balance[] = ["footer" => true, "field1name" => "Week of", "field1value" => "<strong>Total</strong>", "field2name" => "Invoiced", "field2value" => "<strong>$" . number_format($totalowed, 2) . "</strong>","field3name" => "Amount Paid", "field3value" => "<strong>$" . number_format($totalpaid, 2) . "</strong>","field4name" => "Donations", "field4value" => "$" . number_format($totaldonations, 2),"field5name" => "Employee Wages", "field5value" => "$" . number_format($totalwages, 2),"field6name" => "Expenses", "field6value" => "$" . number_format($totalexpenses, 2), "field7name" => "Expected Balance", "field7value" => "<strong>$" . number_format(($totalowed) + $totaldonations - $totalwages - $totalexpenses, 2) . "</strong>", "field8name" => "Actual Balance", "field8value" => "<strong>$" . number_format(($totalpaid) + $totaldonations - $totalwages - $totalexpenses, 2) . "</strong>"];
            $returnme .= format_report_data($balance);

            $returnme .= '</div>';
        }
        break;
    case "activity_tag":
        if ($notes = get_db_result($SQL, $sql_vars)) {
            $returnme .= '<div style="font-size:150%;text-align:center;"><strong>' . $name . '</strong></div>';
            $activity = [];
            $total = 0;
            while ($note = fetch_row($notes)) {
                $note["data"] = empty($note["data"]) ? '0 ' : $note["data"];
                $total += $note["data"];
                $activity[] = note_entry($note);
            }
            $activity[] = ["footer" => true, "field1name" => "Date", "field1value" => "<strong>Total</strong>", "field2name" => "Tag", "field2value" => " ", "field3name" => "Value", "field3value" => "<strong>" . number_format($total, 0) . "</strong>"];
            $returnme .= format_report_data($activity);
            $returnme .= '</div>';
        }
        break;
}

if ($empty == $returnme) { //Nothing has changed
    $returnme .= '<div style="text-align:center;font-size:20px;padding:10px;"><span style="color:red"><strong>Sorry!</strong></span><br /><br />There was not enough information available to create this report.</div>';
}
    $returnme .= '<script type="text/javascript">' . $script . ' refresh_all(); </script>';

//PRINT REPORT
echo $returnme;

function week_breakdown(){
    global $CFG,$MYVARS;
    $pid = empty($MYVARS->GET["pid"]) ? false : $MYVARS->GET["pid"];
    $startofweek = empty($MYVARS->GET["fromdate"]) ? false : $MYVARS->GET["fromdate"];
    $endofweek = strtotime("+1 week -1 second", $startofweek);
    $weekdays = ["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"];
    $startofday = $endofday = false;
    $timecards = [];
    $returnme = $expense_report = $donation_report = $payment_report = $timecard_report = "";

    //employee timecards
    $totalwages = 0;
    $full_totalhours = 0;
    if ($employees = get_db_result("SELECT * FROM employee WHERE employeeid IN (SELECT employeeid FROM employee_activity WHERE timelog >= ||start|| AND timelog <= ||end||)", ["start" => $startofweek, "end" => $endofweek])) {
        while ($employee = fetch_row($employees)) {
            $timecard = [];
            $totalhours = 0;
            foreach ($weekdays as $weekday) {
                $in = $out = 0;
                $startofday = empty($startofday) && date("N", $startofweek) == "7" ? $startofweek : strtotime("+1 day", $startofday);
                $endofday = strtotime("+1 day -1 second", $startofday);
                $hoursworked = hours_worked($employee["employeeid"], $startofday, $endofday);
                $totalhours += $hoursworked;

                //Get first in and last out time
                $ev = ["employeeid" => $employee["employeeid"], "start" => $startofday, "end" => $endofday];
                $in = get_db_row("SELECT * FROM employee_activity WHERE employeeid = ||employeeid|| AND tag = 'in' AND timelog >= ||start|| AND timelog <= ||end|| ORDER BY timelog LIMIT 1", false, $ev);
                $out = get_db_row("SELECT * FROM employee_activity WHERE employeeid = ||employeeid|| AND tag = 'out' AND timelog >= ||start|| AND timelog <= ||end|| ORDER BY timelog DESC LIMIT 1", false, $ev);
                $timecard[] = ["in" => $in["timelog"],"out" => $out["timelog"], "hours" => $hoursworked];
            }
            $wage = get_wage($employee["employeeid"], $startofweek);
            $totalwages += ($wage * $totalhours);
            $full_totalhours += $totalhours;
            $timecards[] = ["employeeid" => $employee["employeeid"],"name" => $employee["first"] . " " . $employee["last"],"wage" => $wage, "timecard" => $timecard,"totalhours" => $totalhours];
            $startofday = $endofday = false; //reset week
        }
    }

    if (!empty($timecards)) {
        $timecard_report = '<br /><strong>Timecards</strong><br /><table style="font-size:10px;width:100%"><tr style="font-weight: bold;"><td></td><td style="text-align:center;">Sunday</td><td style="text-align:center;">Monday</td><td style="text-align:center;">Tuesday</td><td style="text-align:center;">Wednesday</td><td style="text-align:center;">Thursday</td><td style="text-align:center;">Friday</td><td style="text-align:center;">Saturday</td><td>Hours</td></tr>';
        foreach ($timecards as $t) {
            $timecard_report .= '<tr><td colspan="9" style="background-color:grey;"></td></tr>';
            $timecard_report .= "<tr><td>" . $t["name"] . "</td>";
            foreach ($t["timecard"] as $day) {
                $timecard_report .= !empty($day["hours"]) ? '<td style="text-align:center;font-size:10px">
                ' . date('g:ia', display_time($day["in"])) . '<br />to<br />' . date('g:ia', display_time($day["out"])) . '<br />
                <strong>' . number_format($day["hours"], 2) . ' hrs</strong></td>' : '<td style="text-align:center"> - </td>';
            }
            $timecard_report .= '<td><strong>' . number_format($t["totalhours"], 2) . ' hrs</strong></td></tr>';
        }
        $timecard_report .= '</table><br />';
    } else {
        $timecard_report = "<br /><strong>No timecards to report</strong><br />";
    }

    $returnme .= $timecard_report;


    //income
    $totalpayment = 0;
    if ($income = get_db_result("SELECT * FROM billing_payments WHERE pid = ||pid|| AND aid > 0 AND payment > 0 AND timelog >= ||start|| AND timelog <= ||end||", ["pid" => $pid, "start" => $startofweek, "end" => $endofweek])) {
        $payment_report = '<br /><strong>Income</strong><br /><table style="font-size:10px;width:100%"><tr style="font-weight: bold;"><td style="width:125px">Date</td><td style="width:125px">Amount</td><td style="width:125px">Account</td><td>Note</td></tr>';
        while ($payment = fetch_row($income)) {
            $totalpayment += $payment["payment"];
            $payment_report .= '<tr><td>' . date('m/d/Y', $payment["timelog"]) . '</td><td>$' . number_format(abs($payment["payment"]), 2) . '</td><td>' . get_name(["type" => "aid","id" => $payment["aid"]]) . '</td><td>' . $payment["note"] . '</td></tr>';
        }
        $payment_report .= '</table><br />';
    } else {
        $payment_report = "<br /><strong>No income to report</strong><br />";
    }

    $returnme .= $payment_report;


    //expenses
    $totalexpense = 0;
    if ($expenses = get_db_result("SELECT * FROM billing_payments WHERE pid = ||pid|| AND aid = 0 AND payment < 0 AND timelog >= ||start|| AND timelog <= ||end||", ["pid" => $pid, "start" => $startofweek, "end" => $endofweek])) {
        $expense_report = '<br /><strong>Week Expenses</strong><br /><table style="font-size:10px;width:100%"><tr style="font-weight: bold;"><td style="width:125px">Date</td><td style="width:125px">Amount</td><td>Note</td></tr>';
        while ($expense = fetch_row($expenses)) {
            $totalexpense += $expense["payment"];
            $expense_report .= '<tr><td>' . date('m/d/Y', $expense["timelog"]) . '</td><td>$' . number_format(abs($expense["payment"]), 2) . '</td><td>' . $expense["note"] . '</td></tr>';
        }
        $expense_report .= '</table><br />';
    } else {
        $expense_report = "<br /><strong>No expenses to report</strong><br />";
    }

    $returnme .= $expense_report;

    //donation
    $totaldonations = 0;
    if ($donations = get_db_result("SELECT * FROM billing_payments WHERE pid = ||pid|| AND aid = 0 AND payment > 0 AND timelog >= ||start|| AND timelog <= ||end||", ["pid" => $pid, "start" => $startofweek, "end" => $endofweek])) {
        $donation_report = '<br /><strong>Week Donations</strong><br /><table style="font-size:10px;width:100%"><tr style="font-weight: bold;"><td style="width:125px">Date</td><td style="width:125px">Amount</td><td>Note</td></tr>';
        while ($donation = fetch_row($donations)) {
            $totaldonations += $donation["payment"];
            $donation_report .= '<tr><td>' . date('m/d/Y', $donation["timelog"]) . '</td><td>$' . number_format(abs($donation["payment"]), 2) . '</td><td>' . $donation["note"] . '</td></tr>';
        }
        $donation_report .= '</table><br />';
    } else {
        $donation_report = "<br /><strong>No donations to report</strong><br />";
    }

    $returnme .= $donation_report;

    //totals
    $returnme .= '<br /><strong>Totals:</strong><br />
    <table style="font-size:10px;width:100%;">
        <tr style="font-weight: bold;">
            <td>Income</td>
            <td>Hours</td>
            <td>Approximate Wages</td>
            <td>Expenses</td>
            <td>Donations</td>
            <td>Gross</td>
        </tr>
        <tr>
            <td>$' . number_format($totalpayment, 2) . '</td>
            <td>' . number_format($full_totalhours, 2) . ' hrs</td>
            <td>$' . number_format($totalwages, 2) . '</td>
            <td>$' . number_format($totalexpense, 2) . '</td>
            <td>$' . number_format($totaldonations, 2) . '</td>
            <td><strong>$' . number_format(($totalpayment + $totaldonations - $totalexpense - $totalwages), 2) . '</strong></td>
        </tr>
    </table>';

    echo '<div><strong>Week of ' . date('F \t\h\e jS, Y', $startofweek) . '</strong></div>' . $returnme;
}

function get_next_fieldcount($array){
    $i = 1;
    while (!empty($array["field$i" . "name"])) {
        $i++;
    }
    return $i;
}

function match_fieldname($array, $fieldname){
    $i = 1;
    while (!empty($array["field$i" . "name"])) {
        if ($array["field$i" . "name"] == $fieldname) {
            return $i;
        }
        $i++;
    }
    return false;
}

function fill_new_fields($array, $fieldname, $fieldcount){
    $o = 0;
    while (!empty($array[$o]["field1name"]) && empty($array[$o]["field$fieldcount" . "name"])) {
        $array[$o]["field$fieldcount" . "name"] = $fieldname;
        $array[$o]["field$fieldcount" . "value"] = " ";
        $o++;
    }
    return $array;
}

function fill_new_temp($match, $array, $start, $fieldcount){
    while ($start <= $fieldcount) {
        $array["field$start" . "name"] = $match["field$start" . "name"];
        $array["field$start" . "value"] = " ";
        $start++;
    }
    return $array;
}

function fill_skipped_fields($match, $array, $max, $fieldcount){
    $i = 1;
    while ($i < $fieldcount) {
        if ((empty($array["field$i" . "name"]) && !empty($match["field$i" . "name"])) || !empty($match["field$i" . "name"]) && $match["field$i" . "name"] != $array["field$i" . "name"]) {
            $array["field$i" . "name"] = $match["field$i" . "name"];
            $array["field$i" . "value"] = " ";
        }

        if ($i == $max) {
            return $array;
        }
        $i++;
    }
    return $array;
}

function note_entry($note){
    $required_notes = $note_name = "";
    $return_array = [];
    $i = 1;
    $return_array = ["field1name" => "Date", "field1value" => date("m/d/Y", display_time($note["timelog"]))];

    if (!empty($note["chid"])) {
        $note_name = get_name(["type" => "chid","id" => $note["chid"]]);
        $i++;
        $return_array += ["field$i" . "name" => "Name", "field$i" . "value" => $note_name];
    } elseif (!empty($note["cid"])) {
        $note_name = get_name(["type" => "cid","id" => $note["cid"]]);
        $i++;
        $return_array += ["field$i" . "name" => "Name", "field$i" . "value" => $req["title"]];
    }

    if (!empty($note["actid"]) && empty($note["rnid"])) {
        $type = "events";
        $setting = get_db_field("title", "events_tags", "tag = ||tag||", ["tag" => $note["tag"]]);
        $i++;
        $return_array += ["field$i" . "name" => "Event", "field$i" . "value" => $setting];
        $SQL = "SELECT * FROM notes_required r JOIN (SELECT * FROM events_required_notes WHERE evid IN (SELECT evid FROM activity WHERE actid = ||actid||)) e ON r.rnid = e.rnid WHERE e.rnid IN (SELECT rnid FROM notes WHERE actid = ||actid||) ORDER BY e.sort";
        if ($requires = get_db_result($SQL, ["actid" => $note["actid"]])) {
            while ($req = fetch_row($requires)) {
                $setting = get_db_field("data", "notes", "actid = ||actid|| AND rnid = ||rnid||", ["actid" => $note["actid"], "rnid" => $req["rnid"]]);
                $setting = empty($setting) ? "No" : "Yes";
                $i++;
                $return_array += ["field$i" . "name" => $req["title"], "field$i" . "value" => $setting];
            }
        }
    } elseif (!empty($note["rnid"])) {
        $setting = get_db_row("SELECT * FROM notes_required WHERE tag = ||tag||", false, ["tag" => $note["tag"]]);
        $value = empty($note["data"]) ? "No" : "Yes";
        $i++;
        $return_array += ["field$i" . "name" => $setting["title"], "field$i" . "value" => $value];
    } else {
        if ($setting = get_db_row("SELECT * FROM notes_required WHERE tag = ||tag||", false, ["tag" => $note["tag"]])) {
        } elseif ($setting = get_db_row("SELECT * FROM events_tags WHERE tag = ||tag||", false, ["tag" => $note["tag"]])) {
        } elseif ($setting = get_db_row("SELECT * FROM notes_tags WHERE tag = ||tag||", false, ["tag" => $note["tag"]])) {
            $note["data"] = $note["note"];
        } else {
            return $return_array;
        }

        $i++;
        $return_array += ["field$i" . "name" => "Tag", "field$i" . "value" => $setting["title"]];
        $i++;
        $return_array += ["field$i" . "name" => "Value", "field$i" . "value" => $note["data"]];
    }

    return $return_array;
}

function format_report_data($dataset, $name = "tablesorter", $sortbycolumn = 0){
    $returnme = '<br /><table id="tablesorter_' . $name . '" class="tablesorter" style="width:100%;">';
    $table = $tfooter = "";
    $titles = [];
    $i = 0;
    foreach ($dataset as $data) {
        $f = 1;
        //$color = $i % 2 ? 'white' : 'whitesmoke';
        if (!empty($data["footer"])) {
            $tfooter .= '<tr>';
        } else {
            $table .= '<tr>';
        }
        while (isset($data["field" . $f . "value"])) {
            if (!empty($data["footer"])) {
                $tfooter .= "<td>" . $data["field" . $f . "value"] . "</td>";
                if (!in_array($data["field" . $f . "name"], $titles)) {
                    $titles[] = $data["field" . $f . "name"];
                }
            } else {
                $table .= "<td>" . $data["field" . $f . "value"] . "</td>";
                if (!in_array($data["field" . $f . "name"], $titles)) {
                    $titles[] = $data["field" . $f . "name"];
                }
            }
            $f++;
        }
        if (!empty($data["footer"])) {
            $tfooter .= '</tr>';
        } else {
            $table .= '</tr>';
        }
        $i++;
    }

    $header = '<thead><tr>';
    foreach ($titles as $title) {
        $header .= '<th style="text-align: left;"><strong>' . $title . '</strong></th>';
    }
    $header .= '</tr></thead>';

    $returnme .= $header . "<tbody>" . $table . "</tbody><tfoot>" . $tfooter . "</tfoot></table>";

    $returnme .= '<script type="text/javascript"> $(document).ready(function(){ $("#tablesorter_' . $name . '").tablesorter({
        ' . $sortbycolumn . ': { sortInitialOrder: \'asc\' },
        widthFixed: true,

        // widget code now contained in the jquery.tablesorter.widgets.js file
        widgets : [\'uitheme\', \'zebra\'],

        widgetOptions : {
          // adding zebra striping, using content and default styles - the ui css removes the background from default
          // even and odd class names included for this demo to allow switching themes
          zebra   : ["ui-widget-content even", "ui-state-default odd"],

          // change default uitheme icons - find the full list of icons here: http://jqueryui.com/themeroller/ (hover over them for their name)
          // default icons: ["ui-icon-arrowthick-2-n-s", "ui-icon-arrowthick-1-s", "ui-icon-arrowthick-1-n"]
          // ["up/down arrow (cssHeaders/unsorted)", "down arrow (cssDesc/descending)", "up arrow (cssAsc/ascending)" ]
          uitheme : ["ui-icon-carat-2-n-s", "ui-icon-carat-1-s", "ui-icon-carat-1-n"]
        }
      }); } ); </script>';

    return $returnme;
}
