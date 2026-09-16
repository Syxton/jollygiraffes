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

require_once __DIR__ . '/billing_math.php';

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
    $total_paid = empty($total_paid) ? 0.0 : (float)$total_paid;
    $total_owed = get_db_field("SUM(owed)", "billing", "pid = ||pid|| AND aid = ||aid|| $billing_year_sql", $vars);
    $total_owed = empty($total_owed) ? 0.0 : (float)$total_owed;

    if ($running_balance) {
        // week_balance returns a raw float; do not number_format until the final return
        $running = week_balance($pid, $aid);
        $total_owed += (float)$running;
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
 * See compute_child_week_bill() for enrollment-vs-attendance billing rules
 * and apply_individual_discount() for how the individual discount and its
 * minimum floors are applied. Vacation weeks charge the vacation rate only
 * (no discount). Labels: [Did Not Attend] when absent, [Vacation Rate] when flagged.
 *
 * Week boundary is Sunday 00:00 through Saturday 23:59:59 (inclusive), matching
 * make_child_invoice() / recalculate_completed_child_invoice().
 *
 * Uses $CFG->timezone (via get_timestamp) so "current week" agrees with create_invoices().
 *
 * Returns a raw float for internal arithmetic. Callers that display the value
 * should number_format() it; do not feed the return value back into arithmetic
 * without casting (previous number_format-with-comma version truncated >= $1,000).
 *
 * @param int  $pid
 * @param int  $aid
 * @param bool $use_enrollment
 * @param bool $nextweek
 * @return float
 */
function week_balance($pid, $aid, $use_enrollment = true, $nextweek = false) {
    global $CFG;

    // Align "today" / week start with create_invoices() timezone handling.
    $now = function_exists('get_timestamp') ? get_timestamp($CFG->timezone) : time();
    $invoiceweek = (date('N', $now) == 7) ? strtotime('today', $now) : strtotime('previous Sunday', $now);
    // Inclusive end-of-week (Saturday 23:59:59), same as make_child_invoice().
    $endofweek   = strtotime('+1 week -1 second', $invoiceweek);

    $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ['pid' => $pid]);
    if (!$program) {
        return 0.0;
    }
    if ($overrides = apply_overrides($program, $pid, $aid)) {
        $program = $overrides;
    }
    $is_enrollment_billing = ($program['bill_by'] === 'enrollment');

    $charges = [];

    $accounts = get_db_result("SELECT * FROM accounts WHERE aid = ||aid||", ['aid' => $aid]);
    if (!$accounts) {
        return 0.0;
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
                    $raw_bill = compute_child_week_bill($program, $days_attending, $is_enrollment_billing);
                }
            } elseif ($vacation) {
                // Explicit vacation week for this child
                $raw_bill  = (float)$program['vacation'];
                $billed_by = $perchild['days_attending'] ?? ($enroll['days_attending'] ?? '');
                $attendance = '';
            } else {
                if ($use_enrollment && $perchild && !empty($perchild['days_attending'])) {
                    $billed_by = $perchild['days_attending'];
                } elseif ($is_enrollment_billing) {
                    $billed_by = $enroll['days_attending'] ?? '';
                } else {
                    $billed_by = 'attendance';
                }

                // Inclusive upper bound (timelog <= end) so Saturday attendance is counted,
                // matching recalculate_completed_child_invoice() / make_child_invoice().
                $activities = get_db_result(
                    "SELECT * FROM activity
                     WHERE tag = 'in' AND pid = ||pid|| AND chid = ||chid||
                       AND timelog >= ||start|| AND timelog <= ||end||
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

                if ($activities) {
                    while ($activity = fetch_row($activities)) {
                        $day = date('m/d/Y', display_time($activity['timelog']));
                        if ($day !== $last_day) {
                            $day_count++;
                            $days_list[] = date('D', display_time($activity['timelog']));
                            $last_day    = $day;
                        }
                    }
                }

                if ($day_count > 0) {
                    $attendance = $day_count . ($day_count === 1 ? ' day' : ' days')
                                . ' (' . implode(' ', $days_list) . ')';
                } else {
                    $attendance = '';
                }

                // See compute_child_week_bill() for the enrollment-vs-attendance rules.
                $raw_bill = compute_child_week_bill($program, $day_count, $is_enrollment_billing);

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
                $final = apply_individual_discount($program, $raw_bill, $discount, $is_enrollment_billing, $day_count);
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
        return 0.0;
    }

    $kids = [];
    foreach ($charges as $chid => $info) {
        $kids[$chid] = [
            'final'          => (float)$info['final'],
            'exempt'         => (int)$info['exempt'],
            'did_not_attend' => empty($info['attendance']) ? 1 : 0,
        ];
    }
    $multiple  = (float)($program['multiple_discount'] ?? 0);
    $threshold = (float)($program['discount_rule'] ?? 0);
    $after     = apply_multi_child_discount($kids, $multiple, $threshold);

    $total = 0.0;
    foreach ($after as $bill) {
        $total += (float)$bill;
    }
    // Return raw float; formatting belongs at display sites / account_balance().
    return round($total, 2);
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
 * Build the human-readable receipt line for one child-week.
 *
 * Rate labels (when not exempt/vacation):
 * - [Fulltime Rate] when the classification amount matches fulltime
 * - [Minimum Active Rate] when it matches minimumactive
 * - [Part-time Rate] otherwise
 * - [Did Not Attend] when attendance is empty and not vacation
 * - [Vacation Rate] when the week is marked vacation
 *
 * $bill is the amount printed on the receipt. $classification_bill (defaults
 * to $bill) is used only for Fulltime / Minimum Active detection so that a
 * later multi-child discount does not change the rate label.
 *
 * Pass $siblings (chid => ['final' => float, 'exempt' => 0|1]) when you need
 * multi-child labeling. build_child_receipt() then calls
 * child_gets_multi_child_discount() using program multiple_discount /
 * discount_rule. Omit $siblings (or pass null) when family context is unknown
 * (e.g. the first-pass per-child save before multi is applied).
 *
 * @param array  $program
 * @param int    $chid
 * @param float  $bill                Amount shown on the receipt
 * @param float  $discount            Individual discount (0 when vacation)
 * @param int    $exempt
 * @param int    $vacation
 * @param string $attendance          e.g. "1 day (Fri)" or "" for none
 * @param float|null $classification_bill  Amount used for rate labels; null = use $bill
 * @param array|null $siblings        Optional family map keyed by chid for multi-child check
 * @return string
 */
function build_child_receipt(
    $program,
    $chid,
    $bill,
    $discount = 0.0,
    $exempt = 0,
    $vacation = 0,
    $attendance = '',
    $classification_bill = null,
    $siblings = null
) {
    $bill     = (float)$bill;
    $discount = (float)$discount;
    $exempt   = (int)$exempt;
    $vacation = (int)$vacation;
    $class_bill = $classification_bill !== null ? (float)$classification_bill : $bill;

    if ($exempt) {
        $bill = 0.0;
    }
    if ($vacation) {
        $discount = 0.0;
    }

    $is_enrollment = (($program['bill_by'] ?? '') === 'enrollment');

    $day_count = 0;
    if (!empty($attendance) && preg_match('/^(\d+)\s+days?\b/', $attendance, $m)) {
        $day_count = (int)$m[1];
    }

    // Effective individual discount (0 when floor blocked it)
    $indiv_applied = 0.0;
    if ($discount > 0 && !$vacation && !$exempt) {
        $raw     = compute_child_week_bill($program, $day_count, $is_enrollment);
        $with    = apply_individual_discount($program, $raw, $discount, $is_enrollment, $day_count);
        $without = apply_individual_discount($program, $raw, 0, $is_enrollment, $day_count);
        if ($with < $without - 0.0005) {
            $indiv_applied = $discount;
        }
    }

    // Multi-child only when it actually reduces a positive pre-multi amount
    $multi_applied = 0.0;
    $multi_amt     = (float)($program['multiple_discount'] ?? 0);
    if (is_array($siblings) && !empty($siblings) && array_key_exists($chid, $siblings)) {
        $threshold = (float)($program['discount_rule'] ?? 0);
        $pre_multi = (float)$siblings[$chid]['final'];
        if ($multi_amt > 0 && $pre_multi > 0.0005
            && empty($siblings[$chid]['did_not_attend'])
            && child_gets_multi_child_discount($chid, $siblings, $multi_amt, $threshold)) {
            $multi_applied = $multi_amt;
        }
    }

    $name = get_name(['type' => 'chid', 'id' => $chid]);
    $header = empty($attendance)
        ? $name . ' - Did Not Attend'
        : $name . ' - Attended ' . $attendance;

    if ($exempt) {
        return $header . "<br />[Exempt] = $0.00";
    }
    if ($vacation) {
        return $header . "<br />[$" . number_format($bill, 2, '.', '') . " Vacation Rate] = $" . number_format($bill, 2, '.', '');
    }

    // Rate label
    if (empty($attendance)) {
        if ($is_enrollment) {
            $rate_label  = 'Fulltime Rate';
            $rate_amount = (float)$program['fulltime'];
        } else {
            $rate_label  = 'Minimum Inactive Rate';
            $rate_amount = (float)$program['minimuminactive'];
        }
    } else {
        $is_full = abs($class_bill - (float)$program['fulltime']) < 0.001
                   || abs($class_bill + $discount - (float)$program['fulltime']) < 0.001;
        $is_min_active = abs($class_bill - (float)$program['minimumactive']) < 0.001
                   || abs($class_bill + $discount - (float)$program['minimumactive']) < 0.001;
        if ($is_full) {
            $rate_label  = 'Fulltime Rate';
            $rate_amount = (float)$program['fulltime'];
        } elseif ($is_min_active) {
            $rate_label  = 'Minimum Active Rate';
            $rate_amount = (float)$program['minimumactive'];
        } else {
            $rate_label  = 'Part-time Rate ' . $day_count . ' x ' . '$' . number_format((float)$program['perday'], 2, '.', '');
            $rate_amount = $indiv_applied > 0 ? ($class_bill + $indiv_applied) : $class_bill;
        }
    }

    // Flex layout so operator, $, decimal points, and labels always align,
    // independent of font metrics or parent CSS.
    // Each row is its own flex row: [op] [$] [amount right-aligned] [label]
    // Outside .printhis, rows stack vertically. Inside .printhis, rows wrap
    // side by side to minimize vertical height.
    // Last detail row (before total) gets an underline under $ + amount only.
    $rows = [];
    $rows[] = ['', $rate_amount, $rate_label, 'receipt_rate'];
    if ($discount > 0 && $indiv_applied > 0) {
        $rows[] = ['-', $indiv_applied, 'Individual Discount', 'receipt_discount'];
    } else if ($discount > 0 && $indiv_applied == 0) {
        $rows[] = ['-', $indiv_applied, 'Individual Discount Dismissed', 'receipt_discount'];
    }
    if ($multi_applied > 0) {
        $rows[] = ['-', $multi_applied, 'Multi-Child Discount', 'receipt_multi'];
    }
    $rows[] = ['', $bill, '', 'receipt_total']; // total — no operator; underline comes from prior row

    $last_detail_idx = count($rows) - 2; // row just above the total

    // Pre-format amounts and find the widest one so the amount column
    // stays a fixed character-width (tabular-nums makes 'ch' == digit width).
    $formatted = array_map(function ($row) {
        return number_format((float)$row[1], 2, '.', '');
    }, $rows);
    $max_amt_len = max(array_map('strlen', $formatted));

    $html = '';
    foreach ($rows as $i => $row) {
        list($op, $amt, $label, $class) = $row;
        $op_cell    = htmlspecialchars($op);
        $num_cell   = $formatted[$i];
        $label_cell = $label !== '' ? '&nbsp;[' . htmlspecialchars($label) . ']' : '';
        $uline = ($i === $last_detail_idx) ? ' ul' : '';

        $html .= '
            <div class="rbd-row ' . ($class ?? '') . '">
                <div class="op">' . $op_cell . '</div>
                <div class="dol' . $uline . '">$</div>
                <div class="amt' . $uline . '" style="flex:0 0 ' . $max_amt_len . 'ch;">' . $num_cell . '</div>
                <div class="lbl">' . $label_cell . '</div>
            </div>';
    }

    $html = $header . '<br /><div class="rbd"> ' . $html . '</div>';
    return $html;
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

    $receipt = build_child_receipt(
        $program,
        $chid,
        $bill,
        $discount,
        $exempt,
        $vacation,
        $attendance
    );

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
    $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ["pid" => $pid]);
    $aid = get_db_field("aid", "children", "chid = ||chid||", ["chid" => $chid]);
    $perchild = get_db_row(
        "SELECT * FROM billing_perchild WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
        false,
        ["pid" => $pid, "chid" => $chid, "fromdate" => $invoiceweek]
    );
    $endofweek = strtotime("+1 week -1 second", $invoiceweek);

    // On refresh, always use current enrollment settings (not historical perchild flags)
    // so attendance changes are reflected. Honor past only when not refreshing.
    $use_history = !empty($honor_past_enrollment) && !empty($perchild) && empty($refresh);

    $exempt = $use_history
        ? $perchild["exempt"]
        : get_db_field("exempt", "enrollments", "chid = ||chid|| AND pid = ||pid||", ["chid" => $chid, "pid" => $pid]);

    $vacation = $use_history ? $perchild["vacation"] : 0;

    $discount = $use_history
        ? (float)($perchild["discount"] ?? 0)
        : (float)get_db_field("discount", "enrollments", "chid = ||chid|| AND pid = ||pid||", ["chid" => $chid, "pid" => $pid]);

    // Apply account overrides first so bill_by mode is correct.
    if ($overrides = apply_overrides($program, $pid, $aid)) {
        $program = $overrides;
    }

    // On refresh (or when not honoring history), always re-derive bill_by from
    // current enrollment or actual attendance — never reuse the stored value.
    if ($use_history) {
        $bill_by = $perchild["days_attending"];
    } elseif (($program["bill_by"] ?? '') === "enrollment") {
        $bill_by = get_db_field("days_attending", "enrollments", "chid = ||chid|| AND pid = ||pid||", ["chid" => $chid, "pid" => $pid]);
    } else {
        $bill_by = get_child_week_attendance_list($pid, $chid, $invoiceweek);
    }
    $is_enrollment_billing = (($program["bill_by"] ?? '') === 'enrollment');

    if ($activities = get_db_result(
        "SELECT * FROM activity WHERE tag = 'in' AND pid = ||pid|| AND chid = ||chid|| AND timelog >= ||start|| AND timelog < ||end|| ORDER BY timelog",
        ["pid" => $pid, "chid" => $chid, "start" => $invoiceweek, "end" => $endofweek]
    )) {
        // Use string sentinel so the first day is not lost to PHP type coercion
        // (date string == 0 is true). Matches recalculate_completed_child_invoice().
        $sameday   = '';
        $day_count = 0;
        $days_list = [];
        while ($activity = fetch_row($activities)) {
            $daykey = date("m/d/Y", display_time($activity["timelog"]));
            if ($daykey !== $sameday) {
                $day_count++;
                $days_list[] = date("D", display_time($activity["timelog"]));
                $sameday = $daykey;
            }
        }

        // See compute_child_week_bill() for the enrollment-vs-attendance rules.
        $bill = compute_child_week_bill($program, $day_count, $is_enrollment_billing);

        // Individual discount: see apply_individual_discount().
        $bill_discount = 0.0;
        if ($vacation) {
            $bill = (float)$program['vacation'];
        } else {
            $bill_discount = $discount;
            $bill = apply_individual_discount($program, $bill, $discount, $is_enrollment_billing, $day_count);
        }
        if ($exempt) {
            $bill = 0.0;
        }

        $attendance = $day_count > 0
            ? ($day_count === 1 ? "1 day (" . implode(' ', $days_list) . ")" : $day_count . " days (" . implode(' ', $days_list) . ")")
            : '';

        // Keep stored days_attending aligned with actual attendance on refresh.
        if (!$is_enrollment_billing && $day_count > 0) {
            $bill_by = get_child_week_attendance_list($pid, $chid, $invoiceweek);
        }

        if ($refresh) {
            execute_db_sql(
                "DELETE FROM billing_perchild WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
                ["pid" => $pid, "chid" => $chid, "fromdate" => $invoiceweek]
            );
        }

        if (!$perchild || $refresh) {
            save_child_invoice($program, $chid, $invoiceweek, $endofweek, $bill_by, $lastid, $bill, $attendance, $exempt, $bill_discount, false, false, $vacation);
        }
    } else { //Did not attend
        $bill = compute_child_week_bill($program, 0, $is_enrollment_billing);

        // See apply_individual_discount() for the discount/floor rules.
        $bill_discount = 0.0;
        if ($vacation) {
            $bill = (float)$program['vacation'];
        } else {
            $bill_discount = $discount;
            $bill = apply_individual_discount($program, $bill, $discount, $is_enrollment_billing, 0);
        }
        if ($exempt) {
            $bill = 0.0;
        }

        // Clear days_attending on refresh when there is no attendance.
        if ($refresh && !$is_enrollment_billing) {
            $bill_by = '';
        }

        if ($refresh) {
            execute_db_sql(
                "DELETE FROM billing_perchild WHERE pid = ||pid|| AND chid = ||chid|| AND fromdate = ||fromdate||",
                ["pid" => $pid, "chid" => $chid, "fromdate" => $invoiceweek]
            );
        }

        if (!$perchild || $refresh) {
            save_child_invoice($program, $chid, $invoiceweek, $endofweek, $bill_by, $lastid, $bill, "", $exempt, $bill_discount, false, false, $vacation);
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
    // Cast GET-sourced ids to int (defense-in-depth; matches pattern used elsewhere).
    $pid = $pid !== null ? (int)$pid : (empty($MYVARS->GET["pid"]) ? get_pid() : (int)$MYVARS->GET["pid"]);
    $aid = $aid !== null ? ($aid === false ? false : (int)$aid) : (empty($MYVARS->GET["aid"]) ? false : (int)$MYVARS->GET["aid"]);
    $returnme = "";

    $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ["pid" => $pid]);
    if (empty($aid)) { //All accounts enrolled in program
        if (!empty($refreshall)) {
            // Clear both account invoices and per-child rows so weeks with
            // removed attendance are not resurrected from stale billing_perchild.
            execute_db_sql("DELETE FROM billing WHERE pid = ||pid|| AND fromdate >= ||startweek||", ["pid" => $pid, "startweek" => $startweek]);
            execute_db_sql("DELETE FROM billing_perchild WHERE pid = ||pid|| AND fromdate >= ||startweek||", ["pid" => $pid, "startweek" => $startweek]);
        }
        $SQL = "SELECT * FROM accounts WHERE deleted = '0' AND admin= '0' AND aid IN (SELECT aid FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE pid = ||pid||)) ORDER BY name";
        $accounts = get_db_result($SQL, ["pid" => $pid]);
    } else { //Only selected account
        if (!empty($refreshall)) {
            execute_db_sql("DELETE FROM billing WHERE pid = ||pid|| AND aid = ||aid|| AND fromdate >= ||startweek||", ["pid" => $pid, "aid" => $aid, "startweek" => $startweek]);
            execute_db_sql(
                "DELETE FROM billing_perchild
                 WHERE pid = ||pid|| AND fromdate >= ||startweek||
                   AND chid IN (SELECT chid FROM children WHERE aid = ||aid||)",
                ["pid" => $pid, "aid" => $aid, "startweek" => $startweek]
            );
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
            // On refresh include all enrolled children (even with no remaining
            // activity) so weeks where attendance was removed still get rewritten.
            // On normal create, only children who have checked in at least once.
            if (!empty($refreshall)) {
                $SQL = "SELECT * FROM children
                        WHERE aid = ||aid|| AND deleted = 0
                          AND chid IN (SELECT chid FROM enrollments WHERE pid = ||pid|| AND deleted = 0)
                        ORDER BY last, first";
            } else {
                $SQL = "SELECT * FROM children
                        WHERE aid = ||aid||
                          AND chid IN (SELECT chid FROM enrollments WHERE pid = ||pid||)
                          AND chid IN (SELECT chid FROM activity WHERE pid = ||pid|| AND tag = 'in')
                        ORDER BY last, first";
            }
            if ($children = get_db_result($SQL, ["aid" => $account["aid"], "pid" => $pid])) {
                while ($child = fetch_row($children)) {
                    // On refresh with a startweek, walk every week from that boundary
                    // so attendance changes (including full removals) are reflected.
                    // Otherwise start from the child's first check-in.
                    if (!empty($refreshall) && !empty($startweek) && $startweek !== "0") {
                        $firstin = $startweek;
                    } else {
                        $firstin = get_db_field(
                            "MIN(timelog)",
                            "activity",
                            "pid = ||pid|| AND chid = ||chid|| AND tag = 'in'",
                            ["pid" => $pid, "chid" => $child["chid"]]
                        );
                        if (empty($firstin)) {
                            continue;
                        }
                        $firstin = empty($startweek) || $startweek === "0"
                            ? $firstin
                            : ($firstin < $startweek ? $startweek : $firstin);
                    }

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
            // Apply multi-child discount to weeks that do not yet have a billing row
            // (raw per-child bills are written first; multi-discount is a second pass).
            // Skip weeks that already have a billing row so re-runs do not double-apply.
            $program_for_account = $program;
            if ($overrides = apply_overrides($program, $pid, $account["aid"])) {
                $program_for_account = $overrides;
            }
            $week_rows = get_db_result(
                "SELECT DISTINCT fromdate FROM billing_perchild
                 WHERE pid = ||pid||
                   AND chid IN (SELECT chid FROM children WHERE aid = ||aid||)
                 ORDER BY fromdate",
                ['pid' => $pid, 'aid' => $account['aid']]
            );
            if ($week_rows) {
                while ($week_row = fetch_row($week_rows)) {
                    $billing_exists = get_db_row(
                        "SELECT id FROM billing
                         WHERE pid = ||pid|| AND aid = ||aid|| AND fromdate = ||fromdate||",
                        false,
                        ['pid' => $pid, 'aid' => $account['aid'], 'fromdate' => $week_row['fromdate']]
                    );
                    if (!$billing_exists) {
                        apply_multiple_child_discount_for_week(
                            $pid,
                            $account['aid'],
                            $week_row['fromdate'],
                            $program_for_account
                        );
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

/**
 * Rebuild bill/receipt for one completed-week billing_perchild row from activity,
 * honoring the row's current exempt and vacation flags. Does not touch other weeks.
 *
 * @param array $program Program row (with overrides already applied if desired)
 * @param array $perchild Existing billing_perchild row
 * @return float Final bill amount written
 */
function recalculate_completed_child_invoice($program, $perchild) {
    $pid         = $perchild['pid'];
    $chid        = $perchild['chid'];
    $invoiceweek = (int)$perchild['fromdate'];
    $endofweek   = (int)$perchild['todate'];
    if ($endofweek <= $invoiceweek) {
        $endofweek = strtotime('+1 week -1 second', $invoiceweek);
    }

    $exempt   = (int)($perchild['exempt'] ?? 0);
    $vacation = (int)($perchild['vacation'] ?? 0);
    $discount = (float)($perchild['discount'] ?? 0);

    // Prefer discount from current enrollment when not vacation
    $enroll = get_db_row(
        "SELECT discount, days_attending FROM enrollments
         WHERE chid = ||chid|| AND pid = ||pid|| AND deleted = 0",
        false,
        ['chid' => $chid, 'pid' => $pid]
    );
    if ($enroll && !$vacation) {
        $discount = (float)($enroll['discount'] ?? 0);
    }
    if ($vacation) {
        $discount = 0.0;
    }

    $bill_by    = $perchild['days_attending'] ?? '';
    $attendance = '';
    $day_count  = 0;
    $days_list  = [];

    $bill = 0.0;
    $activities = get_db_result(
        "SELECT * FROM activity
            WHERE tag = 'in' AND pid = ||pid|| AND chid = ||chid||
            AND timelog >= ||start|| AND timelog <= ||end||
            ORDER BY timelog",
        ['pid' => $pid, 'chid' => $chid, 'start' => $invoiceweek, 'end' => $endofweek]
    );

    $sameday = '';
    if ($activities) {
        while ($activity = fetch_row($activities)) {
            $daykey = date('m/d/Y', display_time($activity['timelog']));
            if ($daykey !== $sameday) {
                $day_count++;
                $days_list[] = date('D', display_time($activity['timelog']));
                $sameday = $daykey;
            }
        }
    }

    $is_enrollment_billing = (($program['bill_by'] ?? '') === 'enrollment');

    if ($day_count > 0) {
        $attendance = $day_count . ($day_count == 1 ? ' day' : ' days')
                    . ' (' . implode(' ', $days_list) . ')';
        // See compute_child_week_bill() for the enrollment-vs-attendance rules.
        $bill = compute_child_week_bill($program, $day_count, $is_enrollment_billing);
        if (($program['bill_by'] ?? '') === 'attendance' || $bill_by === 'attendance' || $bill_by === '') {
            $bill_by = implode(',', $days_list);
        }
    } else {
        // Did not attend
        $bill = compute_child_week_bill($program, 0, $is_enrollment_billing);
        $attendance = '';
    }
    $raw = $bill;
    $bill = apply_individual_discount($program, $bill, $discount, $is_enrollment_billing, $day_count);

    if ($vacation) {
        $bill = (float)$program['vacation'];
        $raw  = $bill;
    }

    if ($exempt) {
        $bill = 0.0;
    }

    // Build receipt via save_child_invoice (upsert)
    $final = save_child_invoice(
        $program,
        $chid,
        $invoiceweek,
        $endofweek,
        $bill_by,
        '0',
        $bill,
        $attendance,
        $exempt,
        $discount,
        true,  // billonly -> returns bill
        true,  // upsert
        $vacation
    );

    return (float)$final;
}

/**
 *
 * Apply the multi-child discount to billing_perchild rows for one account-week.
 * Only applies when the family total (pre-discount) exceeds discount_rule.
 * Highest bill is unchanged; each subsequent non-exempt positive bill is reduced.
 * Receipts are regenerated via build_child_receipt() so the final amount stays
 * consistent with the rate labels (classification uses the pre-multi bill).
 *
 *
 * @param int   $pid
 * @param int   $aid
 * @param int   $fromdate Week start timestamp.
 * @param array $program  Program row with overrides already applied.
 */
function apply_multiple_child_discount_for_week($pid, $aid, $fromdate, $program) {
    $multiple = (float)($program['multiple_discount'] ?? 0);
    if ($multiple <= 0) {
        return;
    }

    $rows = get_db_result(
        "SELECT id, chid, bill, exempt, days_attending FROM billing_perchild
         WHERE pid = ||pid|| AND fromdate = ||fromdate||
           AND chid IN (SELECT chid FROM children WHERE aid = ||aid||)
         ORDER BY id",
        ['pid' => $pid, 'fromdate' => $fromdate, 'aid' => $aid]
    );
    if (!$rows) {
        return;
    }

    $children = [];
    $siblings = [];
    while ($row = fetch_row($rows)) {
        $days = trim((string)($row['days_attending'] ?? ''));
        $did_not_attend = ($days === '' || strtolower($days) === 'attendance') ? 1 : 0;
        $info = [
            'final'          => (float)$row['bill'],
            'exempt'         => (int)($row['exempt'] ?? 0),
            'did_not_attend' => $did_not_attend,
        ];
        $children[$row['id']]        = $info;
        $siblings[(int)$row['chid']] = $info;
    }

    $threshold = (float)($program['discount_rule'] ?? 0);
    $after     = apply_multi_child_discount($children, $multiple, $threshold);

    foreach ($after as $id => $newbill) {
        $oldbill = $children[$id]['final'];
        if (abs($newbill - $oldbill) <= 0.001) {
            continue;
        }

        $pc = get_db_row("SELECT * FROM billing_perchild WHERE id = ||id||", false, ['id' => $id]);
        if (!$pc) {
            continue;
        }

        // Map numeric date("w") codes to day names
        $attendance = '';
        $billed_by  = trim((string)($pc['days_attending'] ?? ''));
        if ($billed_by !== '' && strtolower($billed_by) !== 'attendance') {
            $day_names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $parts = array_values(array_filter(array_map('trim', explode(',', $billed_by))));
            $labels = [];
            foreach ($parts as $p) {
                if ($p === '') {
                    continue;
                }
                if (ctype_digit((string)$p) && isset($day_names[(int)$p])) {
                    $labels[] = $day_names[(int)$p];
                } else {
                    $labels[] = $p;
                }
            }
            $cnt = count($labels);
            if ($cnt > 0) {
                $attendance = $cnt . ($cnt === 1 ? ' day' : ' days')
                            . ' (' . implode(' ', $labels) . ')';
            }
        }

        $receipt = build_child_receipt(
            $program,
            (int)$pc['chid'],
            $newbill,
            (float)($pc['discount'] ?? 0),
            (int)($pc['exempt'] ?? 0),
            (int)($pc['vacation'] ?? 0),
            $attendance,
            $oldbill,
            $siblings
        );

        execute_db_sql(
            "UPDATE billing_perchild SET bill = ||bill||, receipt = ||receipt|| WHERE id = ||id||",
            ['bill' => $newbill, 'receipt' => $receipt, 'id' => $id]
        );
    }
}

/**
 *
 * Recalculate every child on an account for one week, then rebuild the billing row.
 *
 *
 * @param int $pid      Parent / person id.
 * @param int $aid      Account id.
 * @param int $fromdate Week start timestamp.
 */
function rebuild_account_week_invoices($pid, $aid, $fromdate) {
    $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ['pid' => $pid]);
    if (!$program) {
        return;
    }
    if ($overrides = apply_overrides($program, $pid, $aid)) {
        $program = $overrides;
    }

    $rows = get_db_result(
        "SELECT * FROM billing_perchild
         WHERE pid = ||pid|| AND fromdate = ||fromdate||
           AND chid IN (SELECT chid FROM children WHERE aid = ||aid||)
         ORDER BY id",
        ['pid' => $pid, 'fromdate' => $fromdate, 'aid' => $aid]
    );

    if ($rows) {
        while ($row = fetch_row($rows)) {
            recalculate_completed_child_invoice($program, $row);
        }
    }

    apply_multiple_child_discount_for_week($pid, $aid, $fromdate, $program);

    execute_db_sql(
        "DELETE FROM billing WHERE fromdate = ||fromdate|| AND pid = ||pid|| AND aid = ||aid||",
        ['fromdate' => $fromdate, 'pid' => $pid, 'aid' => $aid]
    );
    make_account_invoice($pid, $aid, $fromdate);
}

/**
 *
 * Return the effective enrollment billing method for a program or account.
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