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

// Ensure discount columns exist (safe no-op if already present)
if (function_exists('billing_migrate')) {
    billing_migrate();
} elseif (function_exists('get_db_row') && function_exists('execute_db_sql') && function_exists('dbescape')) {
    $__bm_col = function ($table, $column) {
        try {
            return (bool) get_db_row(
                "SELECT column_name FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = '" . dbescape($table) . "'
                   AND column_name = '" . dbescape($column) . "'"
            );
        } catch (Throwable $e) {
            return false;
        }
    };
    try {
        if (!$__bm_col('enrollments', 'discount')) {
            execute_db_sql("ALTER TABLE enrollments ADD COLUMN discount DECIMAL(8,2) NOT NULL DEFAULT '0.00' AFTER exempt");
        }
        if (!$__bm_col('billing_perchild', 'discount')) {
            execute_db_sql("ALTER TABLE billing_perchild ADD COLUMN discount DECIMAL(8,2) NOT NULL DEFAULT '0.00' AFTER exempt");
        }
        if (!$__bm_col('billing_perchild', 'vacation')) {
            execute_db_sql("ALTER TABLE billing_perchild ADD COLUMN vacation TINYINT(1) NOT NULL DEFAULT '0' AFTER discount");
        }
    } catch (Throwable $e) {
        error_log('billing schema ensure failed: ' . $e->getMessage());
    }
    unset($__bm_col);
}


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
 * Compute the balance for a specific billing week.
 *
 * Rate rules:
 * - Vacation flag on the week: charge vacation rate only
 * - Otherwise days × perday, minimumactive floor, fulltime if days >= consider_full
 * - No activity (not vacation): fulltime if bill_by enrollment, else minimuminactive
 * - Labels: Part-time only for per-day path; [Did Not Attend] when absent; [Vacation Rate] when flagged
 *
 * @param int  $pid
 * @param int  $aid
 * @param bool $use_enrollment
 * @param bool $nextweek
 * @return string
 */
function week_balance($pid, $aid, $use_enrollment = true, $nextweek = false) {
    global $CFG;

    $invoiceweek = (date('N') == 7) ? strtotime('today') : strtotime('previous Sunday');
    $endofweek   = strtotime('next Saturday', $invoiceweek);

    $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ['pid' => $pid]);
    if (!$program) {
        return number_format(0, 2);
    }
    if ($overrides = apply_overrides($program, $pid, $aid)) {
        $program = $overrides;
    }

    $charges = [];

    $accounts = get_db_result("SELECT * FROM accounts WHERE aid = ||aid||", ['aid' => $aid]);
    if (!$accounts) {
        return number_format(0, 2);
    }

    while ($account = fetch_row($accounts)) {
        $sql = "SELECT c.*
                FROM children c
                JOIN enrollments e ON e.chid = c.chid AND e.pid = ||pid||
                WHERE c.aid = ||aid||
                  AND c.deleted = 0
                  AND e.deleted = 0
                  AND e.exempt = 0
                ORDER BY c.last, c.first";

        $children = get_db_result($sql, ['aid' => $account['aid'], 'pid' => $pid]);
        if (!$children) {
            continue;
        }

        while ($child = fetch_row($children)) {
            $chid       = $child['chid'];
            $raw_bill   = 0.0;
            $attendance = '';
            $billed_by  = '';
            $day_count  = 0;
            $vacation   = 0;

            $enroll = get_db_row(
                "SELECT exempt, discount, days_attending
                 FROM enrollments
                 WHERE chid = ||chid|| AND pid = ||pid|| AND deleted = 0",
                false,
                ['chid' => $chid, 'pid' => $pid]
            );
            if (!$enroll) {
                continue;
            }
            $exempt   = (int)($enroll['exempt'] ?? 0);
            $discount = (float)($enroll['discount'] ?? 0);

            $perchild = null;
            if (!$nextweek) {
                $perchild = get_db_row(
                    "SELECT * FROM billing_perchild
                     WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
                    false,
                    ['pid' => $pid, 'chid' => $chid, 'fromdate' => $invoiceweek]
                );
                if ($perchild && !empty($perchild['vacation'])) {
                    $vacation = 1;
                }
            }

            if ($nextweek) {
                $days_str       = $enroll['days_attending'] ?? '';
                $days_attending = count(array_filter(explode(',', (string)$days_str)));
                $day_count      = $days_attending;
                $billed_by      = $days_str;

                if ($days_attending === 0) {
                    $raw_bill = 0.0;
                } else {
                    $raw_bill = $days_attending * (float)$program['perday'];
                    if ($program['minimumactive'] > 0 && $raw_bill < $program['minimumactive']) {
                        $raw_bill = (float)$program['minimumactive'];
                    }
                    if ($days_attending >= (int)$program['consider_full']) {
                        $raw_bill = (float)$program['fulltime'];
                    }
                }
            } elseif ($vacation) {
                // Explicit vacation week for this child
                $raw_bill  = (float)$program['vacation'];
                $billed_by = $perchild['days_attending'] ?? ($enroll['days_attending'] ?? '');
                $attendance = '';
            } else {
                if ($use_enrollment && $perchild && !empty($perchild['days_attending'])) {
                    $billed_by = $perchild['days_attending'];
                } elseif ($program['bill_by'] === 'enrollment') {
                    $billed_by = $enroll['days_attending'] ?? '';
                } else {
                    $billed_by = 'attendance';
                }

                $activities = get_db_result(
                    "SELECT * FROM activity
                     WHERE tag = 'in' AND pid = ||pid|| AND chid = ||chid||
                       AND timelog >= ||start|| AND timelog < ||end||
                     ORDER BY timelog",
                    [
                        'pid'   => $pid,
                        'chid'  => $chid,
                        'start' => $invoiceweek,
                        'end'   => $endofweek,
                    ]
                );

                $days_list = [];
                $last_day  = null;
                $bill      = 0.0;

                if ($activities) {
                    while ($activity = fetch_row($activities)) {
                        $day = date('m/d/Y', display_time($activity['timelog']));
                        if ($day !== $last_day) {
                            $bill      += (float)$program['perday'];
                            $day_count++;
                            $days_list[] = date('D', display_time($activity['timelog']));
                            $last_day    = $day;
                        }
                    }
                }

                if ($day_count > 0) {
                    if ($program['minimumactive'] > 0 && $bill < $program['minimumactive']) {
                        $bill = (float)$program['minimumactive'];
                    }
                    if ($day_count >= (int)$program['consider_full']) {
                        $bill = (float)$program['fulltime'];
                    }
                    $attendance = $day_count . ($day_count === 1 ? ' day' : ' days')
                                . ' (' . implode(' ', $days_list) . ')';
                } else {
                    // Did not attend (not vacation): enrollment → fulltime, attendance → minimuminactive
                    if ($program['bill_by'] === 'enrollment') {
                        $bill = (float)$program['fulltime'];
                    } else {
                        $bill = (float)$program['minimuminactive'];
                    }
                    $attendance = '';
                }

                $raw_bill = $bill;

                if ($billed_by === 'attendance' && $day_count > 0) {
                    $billed_by = implode(',', $days_list);
                }
            }

            if ($exempt) {
                $final = 0.0;
            } elseif ($vacation) {
                // Individual discount does not apply to vacation rate
                $final = $raw_bill;
            } else {
                $final = max(0, $raw_bill - $discount);
            }

            $charges[$chid] = [
                'raw'        => $raw_bill,
                'final'      => $final,
                'attendance' => $attendance,
                'billed_by'  => $billed_by,
                'exempt'     => $exempt,
                // Store 0 discount on the row when vacation so receipts stay consistent
                'discount'   => ($vacation ? 0.0 : $discount),
                'perchild'   => $perchild,
                'day_count'  => $day_count,
                'vacation'   => $vacation,
            ];
        }
    }

    if (empty($charges)) {
        return number_format(0, 2);
    }

    $ord = 0;
    foreach ($charges as &$c) {
        $c['_ord'] = $ord++;
    }
    unset($c);
    uasort($charges, function ($a, $b) {
        $cmp = $b['final'] <=> $a['final'];
        return $cmp !== 0 ? $cmp : ($a['_ord'] <=> $b['_ord']);
    });

    $total  = 0.0;
    $first  = true;
    $lastid = '0';

    foreach ($charges as $chid => $info) {
        $bill = $info['final'];

        if (!$first && !$info['exempt'] && $bill > 0) {
            $bill = max(0, $bill - (float)$program['multiple_discount']);
        }
        $first = false;

        if (!$nextweek) {
            $bill = save_child_invoice(
                $program,
                $chid,
                $invoiceweek,
                $endofweek,
                $info['billed_by'],
                $lastid,
                $bill,
                $info['attendance'],
                $info['exempt'],
                $info['discount'],
                true,
                true,
                $info['vacation']
            );
        }

        $total += (float)$bill;
    }

    return number_format($total, 2);
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
 * Persist (or just calculate) invoice data for a child.
 *
 * Rate labels:
 * - [Fulltime Rate] when fulltime amount is charged (days >= consider_full)
 * - [Part-time Rate] only when charging perday × attendance (or minimumactive floor)
 * - [Did Not Attend] when no activity and not on vacation
 * - [Vacation Rate] only when the week is marked vacation for this child
 *
 * @param array  $program
 * @param int    $chid
 * @param int    $invoiceweek
 * @param int    $endofweek
 * @param mixed  $billed_by
 * @param string $lastid
 * @param float  $bill
 * @param string $attendance
 * @param int    $exempt
 * @param float  $discount
 * @param bool   $billonly
 * @param bool   $upsert
 * @param int    $vacation     1 if this week is marked vacation for the child
 * @return float|void
 */
function save_child_invoice(
    $program,
    $chid,
    $invoiceweek,
    $endofweek,
    $billed_by,
    $lastid = '0',
    $bill = 0,
    $attendance = '',
    $exempt = 0,
    $discount = 0.0,
    $billonly = false,
    $upsert = false,
    $vacation = 0
) {
    $bill     = (float)$bill;
    $discount = (float)$discount;
    $exempt   = (int)$exempt;
    $vacation = (int)$vacation;

    if ($exempt) {
        $bill = 0;
    }

    // Individual discount never applies to vacation rate
    if ($vacation) {
        $discount = 0.0;
    }

    $name = get_name(['type' => 'chid', 'id' => $chid]);

    if ($exempt) {
        $receipt = $name . ' - [Exempt] ' .
                   (empty($attendance) ? '[Did Not Attend]' : 'Attended ' . $attendance) .
                   ': $0.00';
    } else {
        $disc_txt = $discount > 0
            ? ' [$' . number_format($discount, 2) . ' Individual Discount]'
            : '';

        if ($vacation) {
            $rate = '[Vacation Rate]';
            if (!empty($attendance)) {
                $rate .= ' Attended ' . $attendance;
            }
        } elseif (empty($attendance)) {
            // No activity, not vacation
            $rate = '[Did Not Attend]' . $disc_txt;
        } else {
            // Attended: fulltime vs part-time (per-day / minimum active only)
            $is_full = abs($bill - (float)$program['fulltime']) < 0.001
                       || abs($bill + $discount - (float)$program['fulltime']) < 0.001;
            $is_min_active = abs($bill - (float)$program['minimumactive']) < 0.001
                       || abs($bill + $discount - (float)$program['minimumactive']) < 0.001;
            if ($is_full) {
                $rate = '[Fulltime Rate]' . $disc_txt . ' Attended ' . $attendance;
            } elseif ($is_min_active) {
                $rate = '[Minimum Active Rate]' . $disc_txt . ' Attended ' . $attendance;
            } else {
                $rate = '[Part-time Rate]' . $disc_txt . ' Attended ' . $attendance;
            }
        }

        $receipt = $name . ' - ' . $rate . ': $' . number_format($bill, 2);
    }

    $insert_vars = [
        'pid'            => $program['pid'],
        'chid'           => $chid,
        'fromdate'       => $invoiceweek,
        'todate'         => $endofweek,
        'bill'           => $bill,
        'receipt'        => $receipt,
        'exempt'         => $exempt,
        'discount'       => $discount,
        'days_attending' => $billed_by,
        'vacation'       => $vacation,
    ];

    $exists = get_db_row(
        "SELECT id, fromdate FROM billing_perchild
         WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
        false,
        ['pid' => $program['pid'], 'chid' => $chid, 'fromdate' => $invoiceweek]
    );

    // Column may not exist yet on very old DBs; migration should have added it
    if ($exists && $upsert) {
        execute_db_sql(
            "UPDATE billing_perchild
             SET todate = ||todate||, bill = ||bill||, receipt = ||receipt||,
                 exempt = ||exempt||, discount = ||discount||, days_attending = ||days_attending||,
                 vacation = ||vacation||
             WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
            $insert_vars
        );
    } elseif (!$exists) {
        execute_db_sql(
            "INSERT INTO billing_perchild
             (pid, chid, fromdate, todate, bill, receipt, exempt, discount, days_attending, vacation)
             VALUES (||pid||, ||chid||, ||fromdate||, ||todate||, ||bill||, ||receipt||, ||exempt||, ||discount||, ||days_attending||, ||vacation||)",
            $insert_vars
        );
    }

    if ($billonly) {
        return $bill;
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
