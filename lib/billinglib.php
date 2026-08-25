<?php

/***************************************************************************
* filelib.php - File Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/25/2014
* Revision: 0.0.9
***************************************************************************/

if (!isset($LIBHEADER)) {
    include('header.php');
}
$BILLINGLIB = true;

/**
 *
 * Compute the current balance for a billing account.
 *
 *
 * @param int        $pid             Parent / person id.
 * @param int        $aid             Account id.
 * @param bool|false $running_balance Running balance.
 * @param bool|false $year            Year.
 */
function account_balance($pid, $aid, $running_balance = false, $year = false) {
    $billing_year_sql = $payment_year_sql = "";
    $vars = ["pid" => $pid, "aid" => $aid];
    if (!empty($year)) {
        $beginningofyear = make_timestamp_from_date('01/01/' . $year . 'T00:00:00Z');
        $billing_year_sql = "AND fromdate < ||beginningofyear||";
        $payment_year_sql = "AND timelog < ||beginningofyear||";
        $vars["beginningofyear"] = $beginningofyear;
    }

    $total_paid = get_db_field("SUM(payment)", "billing_payments", "pid = ||pid|| AND aid = ||aid|| $payment_year_sql", $vars);
    $total_paid = empty($total_paid) ? "0.00" : $total_paid;
    $total_owed = get_db_field("SUM(owed)", "billing", "pid = ||pid|| AND aid = ||aid|| $billing_year_sql", $vars);
    $total_owed = empty($total_owed) ? "0.00" : $total_owed;

    if ($running_balance) {
        $running_balance = week_balance($pid, $aid);
        $running_balance = empty($running_balance) ? "0.00" : $running_balance;

        $total_owed += $running_balance;
    }
    return number_format($total_owed - $total_paid, 2);
}

/**
 *
 * Apply billing override rules to computed amounts.
 *
 *
 * @param mixed $program Program.
 * @param int   $pid     Parent / person id.
 * @param int   $aid     Account id.
 */
function apply_overrides($program, $pid, $aid) {
    if ($override = get_db_row("SELECT * FROM billing_override WHERE pid = ||pid|| AND aid = ||aid||", false, ["pid" => $pid, "aid" => $aid])) { // account override is present
        foreach ($program as $key => $value) {
            if (isset($override[$key])) {
                $program[$key] = $override[$key];
            }
        }
        return $program;
    }

    return false;
}

/**
 *
 * Compute the balance for a specific billing week.
 *
 *
 * @param int        $pid        Parent / person id.
 * @param int        $aid        Account id.
 * @param bool|false $enrollment Enrollment.
 * @param bool|false $nextweek   Nextweek.
 */
function week_balance($pid, $aid, $enrollment = true, $nextweek = false) {
    global $CFG;
    $invoiceweek = date("N") == 7 ? strtotime("Sunday") : strtotime("previous Sunday");
    $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ["pid" => $pid]);
    if ($overrides = apply_overrides($program, $pid, $aid)) {
        $program = $overrides;
    }

    $totalbill = $childcount = 0;
    $lastid = '0';
    $SQL = "SELECT * FROM accounts WHERE aid = ||aid||";
    if ($accounts = get_db_result($SQL, ["aid" => $aid])) {
        while ($account = fetch_row($accounts)) {
            $SQL = "SELECT * FROM children WHERE aid = ||aid|| AND chid IN (SELECT chid FROM enrollments WHERE pid = ||pid|| AND exempt = 0) AND chid IN (SELECT chid FROM activity WHERE pid = ||pid|| AND tag = 'in') ORDER BY last, first";
            if ($children = get_db_result($SQL, ["aid" => $account["aid"], "pid" => $pid])) {
                $childcount = 0;
                while ($child = fetch_row($children)) {
                    $perchildbill = 0;
                    $chid = $child["chid"];
                    if ($nextweek) { // Base off of assumptions.
                        $days_attending = count(array_filter(explode(',', get_db_field("days_attending", "enrollments", "chid = ||chid|| AND pid = ||pid||", ["chid" => $chid, "pid" => $pid]))));
                        if ($program["bill_by"] == "enrollment") {
                            if ($program["consider_full"] <= $days_attending) {
                                $perchildbill = $program["fulltime"];
                            } else {
                                $perchildbill = $days_attending * $program["perday"];
                                $perchildbill = $program["minimumactive"] > 0 && ($perchildbill < $program["minimumactive"]) ? $program["minimumactive"] : $perchildbill;
                            }
                        } else { // Assumed attendance based.
                            $perchildbill = $days_attending * $program["perday"];
                            $perchildbill = $program["minimumactive"] > 0 && ($perchildbill < $program["minimumactive"]) ? $program["minimumactive"] : $perchildbill;
                        }
                    } else { // Base off of activity
                        // Child has signed in so he may be billed
                        if ($firstin = get_db_field("MIN(timelog)", "activity", "pid = ||pid|| AND chid = ||chid|| AND tag = 'in'", ["pid" => $pid, "chid" => $child["chid"]])) {
                            // Get nearest Saturday, counting today if Saturday
                            $perchild = get_db_row("SELECT * FROM billing_perchild WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||", false, ["pid" => $pid, "chid" => $chid, "fromdate" => $invoiceweek]);
                            $enrollment = $enrollment && $perchild ? $perchild["days_attending"] : ($program["bill_by"] == "enrollment" ? get_db_field("days_attending", "enrollments", "chid = ||chid|| AND pid = ||pid||", ["chid" => $chid, "pid" => $pid]) : "attendance");
                            $endofweek = strtotime("next Saturday", $invoiceweek);

                            // Create a week's enrollment based on attendance instead of the program enrollment settings
                            if ($enrollment == "attendance") {
                                $enrollment = get_child_week_attendance_list($pid, $chid, $invoiceweek);
                            }

                            if ($activities = get_db_result("SELECT * FROM activity WHERE tag = 'in' AND pid = ||pid|| AND chid = ||chid|| AND timelog >= ||start|| AND timelog < ||end|| ORDER BY timelog", ["pid" => $pid, "chid" => $chid, "start" => $invoiceweek, "end" => $endofweek])) {
                                $sameday = $bill = $attendance = 0;
                                $days = "";
                                while ($activity = fetch_row($activities)) {
                                    $bill += date("m/d/Y", display_time($activity["timelog"])) == $sameday ? 0 : $program["perday"];
                                    $attendance += date("m/d/Y", display_time($activity["timelog"])) == $sameday ? "0" : "1";
                                    $days .= date("m/d/Y", display_time($activity["timelog"])) == $sameday ? "" : ($days == "" ? date("D", display_time($activity["timelog"])) : " " . date("D", display_time($activity["timelog"])));
                                    $sameday = date("m/d/Y", display_time($activity["timelog"]));
                                }
                                // Raises minimum charge if a minimum active is set and the current week bill is too low.
                                $bill = $program["minimumactive"] > 0 && ($bill < $program["minimumactive"]) ? $program["minimumactive"] : $bill;
                                if ($attendance >= $program["consider_full"] || !$program["minimumactive"] > 0) {
                                    $bill = $program["fulltime"];
                                }
                                $attendance .= $attendance > 0 ? ($attendance == 1 ? " day ($days)" : " days ($days)") : " days";
                                if (!$perchild) {
                                    $perchildbill = save_child_invoice($program, $chid, $invoiceweek, $endofweek, $enrollment, $lastid, $bill, $attendance, "unknown", true);
                                }
                            } else { //Did not attend, see if there is a minimum.
                                $bill = $program["minimuminactive"] > 0 ? $program["minimuminactive"] : "0";

                                if (!$perchild) {
                                    $perchildbill = save_child_invoice($program, $chid, $invoiceweek, $endofweek, $enrollment, $lastid, $bill, "", "unknown", true);
                                }
                            }
                        }
                    }

                    if ($perchildbill > 0) { // Only cound chilren that are billed.
                        $childcount++; // Count the children that are billed.
                    }

                    if ($childcount > 1 && $totalbill >= $program["discount_rule"]) { // If more than 1 billed child and the total bill is greater than the discount rule, apply the discount.
                        $totalbill += $perchildbill - $program["multiple_discount"]; // Add the discount to the total bill.
                    } else {
                        $totalbill += $perchildbill; // Add the child's bill to the total bill.
                    }
                }
            }
        }
    }

    return number_format($totalbill, 2);
}

/**
 *
 * Build invoice line items for an account.
 *
 *
 * @param int        $pid         Parent / person id.
 * @param int        $aid         Account id.
 * @param bool|false $invoiceweek Invoiceweek.
 */
function make_account_invoice($pid, $aid, $invoiceweek = false) {
    $returnme = "";
    $vars = ["pid" => $pid, "aid" => $aid];
    $invoicesql = "";
    if ($invoiceweek) {
        $invoicesql = " AND fromdate = ||invoiceweek|| ";
        $vars["invoiceweek"] = $invoiceweek;
    }
    //done with children, total each account now
    $SQL = "SELECT * FROM billing_perchild WHERE pid = ||pid|| AND chid IN (SELECT chid FROM children WHERE aid = ||aid||) $invoicesql ORDER BY fromdate";
    if ($child_invoices = get_db_result($SQL, $vars)) {
        $sameweek = $bill = 0;
        $receipt = "";
        start_db_transaction();
        try {
        while ($invoice = fetch_row($child_invoices)) {  //Loop through each week
            $fromdate = $invoice["fromdate"];
            $todate = $invoice["todate"];
             //Does this invoice need to be made?
            if ($fromdate != $sameweek) { //start of a new week
                if ($sameweek !== 0) { //not the first week, so you need to end the last week.
                    $receipt .= '<div><strong>Week Total: $' . number_format($bill, 2) . '</strong></div>';
                    if (!get_db_row("SELECT * FROM billing WHERE pid = ||pid|| AND aid = ||aid|| AND fromdate = ||fromdate||", false, ["pid" => $pid, "aid" => $aid, "fromdate" => $oldfromdate])) {
                        $SQL = "INSERT INTO billing (pid, aid, fromdate, todate, owed, receipt) VALUES (||pid||, ||aid||, ||fromdate||, ||todate||, ||owed||, ||receipt||)";
                        execute_db_sql($SQL, [
                            "pid" => $pid,
                            "aid" => $aid,
                            "fromdate" => $oldfromdate,
                            "todate" => $oldtodate,
                            "owed" => $bill,
                            "receipt" => $receipt,
                        ]);
                        $returnme .= "<div><strong>Week of " . get_date('F \t\h\e jS Y', $oldfromdate) . "</strong><div>" . $receipt . "</div></div><br />";
                    }
                    $receipt = "";
                }

                //Start new week bill;
                $bill = empty($invoice["exempt"]) ? $invoice["bill"] : 0;

                //Start week
                $receipt .= empty($invoice["exempt"]) ? "<div>" . $invoice["receipt"] . "</div>" : "<div>" . $invoice["receipt"] . " - Exempt $0</div>";
            } else { //Same week continuing
                //Add to bill
                $bill += empty($invoice["exempt"]) ? $invoice["bill"] : 0;
                $receipt .= empty($invoice["exempt"]) ? "<div>" . $invoice["receipt"] . "</div>" : "<div>" . $invoice["receipt"] . " - Exempt $0</div>";
            }
            //Save last week
            $oldfromdate = $fromdate;
            $oldtodate = $todate;
            $sameweek = $fromdate;
        }

        if ($sameweek !== 0) { //not the first week, so you need to end the last week.
            $receipt .= '<div><strong>Week Total: $' . number_format($bill, 2) . '</strong></div>';
            if (!get_db_row("SELECT * FROM billing WHERE pid = ||pid|| AND aid = ||aid|| AND fromdate = ||fromdate||", false, ["pid" => $pid, "aid" => $aid, "fromdate" => $oldfromdate])) {
                $SQL = "INSERT INTO billing (pid, aid, fromdate, todate, owed, receipt) VALUES (||pid||, ||aid||, ||fromdate||, ||todate||, ||owed||, ||receipt||)";
                execute_db_sql($SQL, [
                    "pid" => $pid,
                    "aid" => $aid,
                    "fromdate" => $oldfromdate,
                    "todate" => $oldtodate,
                    "owed" => $bill,
                    "receipt" => $receipt,
                ]);
                $returnme .= "<div><strong>Week of " . get_date('F \t\h\e jS Y', $oldfromdate) . "</strong><div>" . $receipt . "</div></div><br />";
            }
        }
        commit_db_transaction();
        } catch (\Throwable $e) {
            rollback_db_transaction();
            throw $e;
        }
        $returnme = empty($returnme) ? "" : "<span>" . $returnme . "</span>";
    }

    $returnme = empty($returnme) ? "" : "<br /><strong>" . get_name(["type" => "aid","id" => $aid]) . "</strong>" . $returnme;
    return $returnme;
}

/**
 *
 * Persist invoice data for a child.
 *
 *
 * @param mixed      $program     Program.
 * @param int        $chid        Child id.
 * @param mixed      $invoiceweek Invoiceweek.
 * @param mixed      $endofweek   Endofweek.
 * @param mixed      $billed_by   Billed by.
 * @param string     $lastid      Lastid.
 * @param string     $bill        Bill.
 * @param string     $attendance  Attendance.
 * @param string     $exempt      Exempt.
 * @param bool|false $billonly    Billonly.
 */
function save_child_invoice($program, $chid, $invoiceweek, $endofweek, $billed_by, $lastid = "0", $bill = "", $attendance = "", $exempt = 'unknown', $billonly = false) {
    $discount = "";
    $discount_threshold = empty($program["discount_rule"]) || $program["discount_rule"] < $program["multiple_discount"]
        ? $program["multiple_discount"]
        : $program["discount_rule"];
    $exempt = $exempt == "unknown"
        ? get_db_field("exempt", "enrollments", "chid = ||chid|| AND pid = ||pid||", ["chid" => $chid, "pid" => $program["pid"]])
        : $exempt;
    $days_expected = get_db_field("days_attending", "enrollments", "chid = ||chid|| AND pid = ||pid||", ["chid" => $chid, "pid" => $program["pid"]]);

    // Other children on the account that would qualify this child for a discount (prepared).
    $other_vars = [
        "exempt" => $exempt,
        "pid" => $program["pid"],
        "chid" => $chid,
        "fromdate" => $invoiceweek,
        "lastid" => $lastid,
        "threshold" => $discount_threshold,
    ];
    $otherchildrenthatmatch = "
        SELECT *
        FROM billing_perchild
        WHERE 0 = ||exempt||
        AND pid = ||pid||
        AND chid IN (SELECT chid FROM enrollments WHERE pid = ||pid||)
        AND chid IN (SELECT chid FROM children WHERE aid IN (SELECT aid FROM children WHERE chid = ||chid||))
        AND fromdate = ||fromdate||
        AND id > ||lastid||
        AND exempt = 0
        AND chid != ||chid||
        AND bill >= ||threshold||";

    // $billed_by is either enrollment or days the child attended ex. M,W,Th,F
    if ($program["bill_by"] == "enrollment") {
        if (empty($attendance)) { // If we expected attendance but no attendance was recorded.
            $bill = $program["vacation"];
            $rate = "Did Not Attend [Vacation Rate]";
        } else {
            if (empty($bill)) {
                $bill = empty($program["fulltime"]) ? $program["perday"] * $program["consider_full"] : $program["fulltime"];
            }
            if ($bill >= $program["discount_rule"] && get_db_row($otherchildrenthatmatch, false, $other_vars)) { //Not the first child on this account this week
                $discount = "[$" . number_format($program["multiple_discount"], 2) . " Multiple Child Discount]";
                $bill = $bill - $program["multiple_discount"];
            }
            if ($attendance[0] >= $program["consider_full"] || $program["minimumactive"] == 0) {
                $rate = "[Fulltime Rate] $discount Attended $attendance";
            } else {
                $rate = "[Partial Week Rate] $discount Attended $attendance";
            }
        }

        if ($exempt == "1") {
            $bill = 0;
            $receipt = get_name(["type" => "chid","id" => $chid]) . " - [Exempt] Attended $attendance: $" . number_format($bill, 2);
        } else {
            $receipt = get_name(["type" => "chid","id" => $chid]) . " - $rate: $" . number_format($bill, 2);
        }

        if ($billonly) {
            return $bill;
        }
        $insert_vars = [
            "pid" => $program["pid"],
            "chid" => $chid,
            "fromdate" => $invoiceweek,
            "todate" => $endofweek,
            "bill" => $bill,
            "receipt" => $receipt,
            "exempt" => $exempt,
            "days_attending" => $billed_by,
        ];
        if (!get_db_row(
            "SELECT fromdate FROM billing_perchild WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
            false,
            ["pid" => $program["pid"], "chid" => $chid, "fromdate" => $invoiceweek]
        )) {
            execute_db_sql(
                "INSERT INTO billing_perchild (pid, chid, fromdate, todate, bill, receipt, exempt, days_attending) VALUES (||pid||, ||chid||, ||fromdate||, ||todate||, ||bill||, ||receipt||, ||exempt||, ||days_attending||)",
                $insert_vars
            );
        }
    } else { // enrollment considered part-time
        if (!empty($attendance) && $bill >= $program["discount_rule"] && get_db_row($otherchildrenthatmatch, false, $other_vars)) { //Not the first child on this account this week
            $discount = "[$" . number_format($program["multiple_discount"], 2) . " Multiple Child Discount]";
            $bill = $bill - $program["multiple_discount"];
        }

        if ($exempt == "1") {
            $bill = 0;
            $receipt = empty($attendance) ? get_name(["type" => "chid","id" => $chid]) . " - Did Not Attend [Exempt]: $" . number_format($bill, 2) : get_name(["type" => "chid","id" => $chid]) . " - [Exempt] Attended $attendance: $" . number_format($bill, 2);
        } else {
            if (empty($attendance)) {
                $minimum = $bill == $program["minimuminactive"] ? "Minimum " : "";
            } else {
                $minimum = $bill == $program["minimumactive"] ? "Minimum " : "";
            }

            $receipt = empty($attendance) ? get_name(["type" => "chid","id" => $chid]) . " - Did Not Attend [Minimum Rate]: $" . number_format($bill, 2) : get_name(["type" => "chid","id" => $chid]) . " - [" . $minimum . "Part-time Rate] $discount Attended $attendance: $" . number_format($bill, 2);
        }

        if ($billonly) {
            return $bill;
        }
        $insert_vars = [
            "pid" => $program["pid"],
            "chid" => $chid,
            "fromdate" => $invoiceweek,
            "todate" => $endofweek,
            "bill" => $bill,
            "receipt" => $receipt,
            "exempt" => $exempt,
            "days_attending" => $billed_by,
        ];
        if (!get_db_row(
            "SELECT fromdate FROM billing_perchild WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
            false,
            ["pid" => $program["pid"], "chid" => $chid, "fromdate" => $invoiceweek]
        )) {
            execute_db_sql(
                "INSERT INTO billing_perchild (pid, chid, fromdate, todate, bill, receipt, exempt, days_attending) VALUES (||pid||, ||chid||, ||fromdate||, ||todate||, ||bill||, ||receipt||, ||exempt||, ||days_attending||)",
                $insert_vars
            );
        }
    }
}

/**
 *
 * Child week attendance list.
 *
 *
 * @param int   $pid         Parent / person id.
 * @param int   $chid        Child id.
 * @param mixed $invoiceweek Invoiceweek.
 */
function get_child_week_attendance_list($pid, $chid, $invoiceweek) {
    $endofweek = strtotime("+1 week -1 second", $invoiceweek);

    $week = "";
    $SQL = "SELECT *
            FROM activity
            WHERE tag = 'in'
            AND pid = ||pid||
            AND chid = ||chid||
            AND timelog >= ||start||
            AND timelog < ||end||
            ORDER BY timelog ASC";
    // Get days during the selected week in which the child was present.
    if ($days_attending = get_db_result($SQL, ["pid" => $pid, "chid" => $chid, "start" => $invoiceweek, "end" => $endofweek])) {
        // Array of days of the week.
        $days = ["S", "M", "T", "W", "Th", "F", "Sa"];
        $enrolled_days = [];
        while ($attend = fetch_row($days_attending)) {
            $day_of_week = date("w", display_time($attend["timelog"])); // Sunday = 0, Monday = 1, etc.
            $enrolled_days[$day_of_week] = $day_of_week;
        }

        // Create a comma separated list of days in which the child was present.
        $week = implode(',', $enrolled_days);
    }

    return $week;
}

/**
 *
 * Child invoice.
 *
 *
 * @param int        $pid                   Parent / person id.
 * @param int        $chid                  Child id.
 * @param mixed      $invoiceweek           Invoiceweek.
 * @param bool|false $refresh               Refresh.
 * @param string     $lastid                Lastid.
 * @param bool|false $honor_past_enrollment Honor past enrollment.
 */
function make_child_invoice($pid, $chid, $invoiceweek, $refresh = false, $lastid = '0', $honor_past_enrollment = true) {
    global $CFG;
    $discount = "";
    $override = false;
    $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ["pid" => $pid]);
    $aid = get_db_field("aid", "children", "chid = ||chid||", ["chid" => $chid]);
    $perchild = get_db_row(
        "SELECT * FROM billing_perchild WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
        false,
        ["pid" => $pid, "chid" => $chid, "fromdate" => $invoiceweek]
    );
    $endofweek = strtotime("+1 week -1 second", $invoiceweek);

    //check to see if in the past the user was exempt, if no history is found or you don't want to honor the past, just get it from current enrollment settings
    $exempt = $honor_past_enrollment && $perchild
        ? $perchild["exempt"]
        : get_db_field("exempt", "enrollments", "chid = ||chid|| AND pid = ||pid||", ["chid" => $chid, "pid" => $pid]);

    //you want to remember past settings and there is a history recorded
    if (!empty($honor_past_enrollment) && !empty($perchild)) {
        $bill_by = $perchild["days_attending"];  //bill according to the days attended
    } elseif ($overrides = apply_overrides($program, $pid, $aid)) { //account override is present
        $program = $overrides;
        $bill_by = $program["bill_by"];
    } elseif ($program["bill_by"] == "enrollment") { //there is no history or you don't want to remember the past and the program is now set to enrollment billing
        $bill_by = get_db_field("days_attending", "enrollments", "chid = ||chid|| AND pid = ||pid||", ["chid" => $chid, "pid" => $pid]); //Get the days attending.
    } else { //only other choice is that there is no history and the program is set to attendance billing.  This will be built next.
        // Create a week's enrollment based on attendance instead of the program enrollment settings
        $bill_by = get_child_week_attendance_list($pid, $chid, $invoiceweek);
    }

    if ($activities = get_db_result(
        "SELECT * FROM activity WHERE tag = 'in' AND pid = ||pid|| AND chid = ||chid|| AND timelog >= ||start|| AND timelog < ||end|| ORDER BY timelog",
        ["pid" => $pid, "chid" => $chid, "start" => $invoiceweek, "end" => $endofweek]
    )) {
        $sameday = $bill = $attendance = 0;
        $days = "";
        while ($activity = fetch_row($activities)) {
            $bill += date("m/d/Y", display_time($activity["timelog"])) == $sameday ? 0 : $program["perday"];
            $attendance += date("m/d/Y", display_time($activity["timelog"])) == $sameday ? "0" : "1";
            $days .= date("m/d/Y", display_time($activity["timelog"])) == $sameday ? "" : ($days == "" ? date("D", display_time($activity["timelog"])) : " " . date("D", display_time($activity["timelog"])));
            $sameday = date("m/d/Y", display_time($activity["timelog"]));
        }

        if ($attendance > 0) {
            if ($attendance >= $program["consider_full"]) {
                $bill = $program["fulltime"];
            } else {
                $bill = $program["minimumactive"] > 0 && ($bill < $program["minimumactive"]) ? $program["minimumactive"] : $bill;
            }
        } else {
            $bill = $program["minimuminactive"] > 0 && ($bill < $program["minimuminactive"]) ? $program["minimuminactive"] : $bill;
        }

        $attendance .= $attendance > 0 ? ($attendance == 1 ? " day ($days)" : " days ($days)") : " days";

        if ($refresh) {
            execute_db_sql(
                "DELETE FROM billing_perchild WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
                ["pid" => $pid, "chid" => $chid, "fromdate" => $invoiceweek]
            );
        }

        if (!$perchild) {
            save_child_invoice($program, $chid, $invoiceweek, $endofweek, $bill_by, $lastid, $bill, $attendance);
        } elseif ($refresh) {
            save_child_invoice($program, $chid, $invoiceweek, $endofweek, $bill_by, $lastid, $bill, $attendance, $exempt);
        }
    } else { //Did not attend, see if there is a minimuminactive rate.
        $bill = $program["minimuminactive"] > "0" ? $program["minimuminactive"] : "0";
        if ($refresh) {
            execute_db_sql(
                "DELETE FROM billing_perchild WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
                ["pid" => $pid, "chid" => $chid, "fromdate" => $invoiceweek]
            );
        }

        if (!$perchild) {
            save_child_invoice($program, $chid, $invoiceweek, $endofweek, $bill_by, $lastid, $bill);
        } elseif ($refresh) {
            save_child_invoice($program, $chid, $invoiceweek, $endofweek, $bill_by, $lastid, $bill, "", $exempt);
        }
    }
}

/**
 *
 * Create invoices.
 *
 *
 * @param bool|false $return                Return.
 * @param int        $pid                   Parent / person id.
 * @param int        $aid                   Account id.
 * @param bool|false $refreshall            Refreshall.
 * @param string     $startweek             Startweek.
 * @param bool|false $honor_past_enrollment Honor past enrollment.
 */
function create_invoices($return = false, $pid = null, $aid = null, $refreshall = false, $startweek = "0", $honor_past_enrollment = true) {
    global $CFG, $MYVARS;
    $pid = $pid !== null ? $pid : (empty($MYVARS->GET["pid"]) ? get_pid() : $MYVARS->GET["pid"]);
    $aid = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $returnme = "";

    $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ["pid" => $pid]);
    if (empty($aid)) { //All accounts enrolled in program
        if (!empty($refreshall)) {
            execute_db_sql("DELETE FROM billing WHERE pid = ||pid|| AND fromdate >= ||startweek||", ["pid" => $pid, "startweek" => $startweek]);
        }
        $SQL = "SELECT * FROM accounts WHERE deleted = '0' AND admin= '0' AND aid IN (SELECT aid FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE pid = ||pid||)) ORDER BY name";
        $accounts = get_db_result($SQL, ["pid" => $pid]);
    } else { //Only selected account
        if (!empty($refreshall)) {
            execute_db_sql("DELETE FROM billing WHERE pid = ||pid|| AND aid = ||aid|| AND fromdate >= ||startweek||", ["pid" => $pid, "aid" => $aid, "startweek" => $startweek]);
        }
        $SQL = "SELECT * FROM accounts WHERE aid = ||aid||";
        $accounts = get_db_result($SQL, ["aid" => $aid]);
    }

    //Employees section
    if ($employees = get_db_result("SELECT * FROM employee")) {
        while ($employee = fetch_row($employees)) {
            if ($firstin = get_db_field("MIN(timelog)", "employee_activity", "employeeid = ||employeeid|| AND tag = 'in'", ["employeeid" => $employee["employeeid"]])) {
                $firstin = empty($startweek) ? $firstin : ($firstin < $startweek ? $startweek : $firstin);
                if (!empty($firstin)) {
                    if (date('N', $firstin) == "7") { //is already a sunday
                        $firstweek = strtotime(date('m/d/Y', $firstin));
                    } else {
                        $firstweek = strtotime("previous Sunday UTC", $firstin);
                    }

                    $invoiceweek = $firstweek;

                    //Get nearest Saturday, counting today if Saturday
                    $runtill = date("N", get_timestamp($CFG->timezone)) == 6 ? strtotime("today UTC") : strtotime("previous Saturday UTC");
                    //go to the end of that Saturday
                    $runtill = strtotime("+1 day -1 second", $runtill);

                    while ($invoiceweek < $runtill) {
                        closeout_workdays($employee["employeeid"], $invoiceweek, $refreshall);
                        //Go to next week
                        $invoiceweek = strtotime("+1 week", $invoiceweek);
                    }
                }
            }
        }
    }

    $lastid = !empty($refreshall) ? get_db_field("MAX(id)", "billing_perchild", "id>0") : '0';
    $lastid = !$lastid ? '0' : $lastid;

    if ($accounts) {
        while ($account = fetch_row($accounts)) {
            $SQL = "SELECT * FROM children WHERE aid = ||aid|| AND chid IN (SELECT chid FROM enrollments WHERE pid = ||pid||) AND chid IN (SELECT chid FROM activity WHERE pid = ||pid|| AND tag = 'in') ORDER BY last, first";
            if ($children = get_db_result($SQL, ["aid" => $account["aid"], "pid" => $pid])) {
                while ($child = fetch_row($children)) {
                    //Child has signed in so he may be billed
                    if ($firstin = get_db_field("MIN(timelog)", "activity", "pid = ||pid|| AND chid = ||chid|| AND tag = 'in'", ["pid" => $pid, "chid" => $child["chid"]])) {
                        $firstin = empty($startweek) ? $firstin : ($firstin < $startweek ? $startweek : $firstin);
                        if (!empty($firstin)) {
                            if (date('N', $firstin) == "7") { //is already a sunday
                                $firstweek = strtotime(date('m/d/Y', $firstin));
                            } else {
                                $firstweek = strtotime("previous Sunday UTC", $firstin);
                            }

                            $invoiceweek = $firstweek;

                            //Get nearest Saturday, counting today if Saturday
                            $runtill = date("N", get_timestamp($CFG->timezone)) == 6 ? strtotime("today UTC") : strtotime("previous Saturday UTC");
                            //go to the end of that Saturday
                            $runtill = strtotime("+1 day -1 second", $runtill);

                            while ($invoiceweek < $runtill) {
                                make_child_invoice($pid, $child["chid"], $invoiceweek, $refreshall, $lastid, $honor_past_enrollment);
                                //Go to next week
                                $invoiceweek = strtotime("+1 week", $invoiceweek);
                            }
                        }
                    }
                }
            }
            $returnme .= make_account_invoice($pid, $account["aid"]);
        }
    }

    if ($returnme == "") {
        $returnme .= '<div>None</div>';
    }

    $returnme = '<div style="display:table-cell;font-weight: bold;font-size: 120%;padding: 10px;">Invoices Created:</div><div class="scroll-pane fill_height"><div style="padding:10px;">' . $returnme . '</div></div>';

    if ($return) {
        return $returnme;
    } else {
        echo $returnme;
    }
}

/**
 *
 * Enrollment method.
 *
 *
 * @param int $pid  Parent / person id.
 * @param int $aid  Account id.
 * @param int $chid Child id.
 */
function get_enrollment_method($pid, $aid = false, $chid = false) {
    $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ["pid" => $pid]);
    //you want to remember past settings and there is a history recorded
    if (!empty($aid)) {
        if ($override = get_db_row("SELECT * FROM billing_override WHERE pid = ||pid|| AND aid = ||aid||", false, ["pid" => $pid, "aid" => $aid])) { //account override is present
            $program["bill_by"] = $override["bill_by"];
        }
    } elseif (!empty($chid)) {
        if ($override = get_db_row(
            "SELECT * FROM billing_override WHERE pid = ||pid|| AND aid IN (SELECT aid FROM children WHERE chid = ||chid||)",
            false,
            ["pid" => $pid, "chid" => $chid]
        )) { //account override is present
            $program["bill_by"] = $override["bill_by"];
        }
    }
    return $program["bill_by"];
}
