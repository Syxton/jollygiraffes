<?php
/***************************************************************************
* filelib.php - File Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/25/2014
* Revision: 0.0.9
***************************************************************************/

if(!isset($LIBHEADER)) include('header.php');
$BILLINGLIB = true;

function account_balance($pid,$aid){
    $total_paid = get_db_field("SUM(payment)","billing_payments","pid='$pid' AND aid='$aid'");
    $total_paid = empty($total_paid) ? "0.00" : $total_paid;
    $total_allowed = get_db_field("SUM(owed)","billing","pid='$pid' AND aid='$aid'");
    $total_allowed = empty($total_allowed) ? "0.00" : $total_allowed;
    return number_format($total_allowed - $total_paid,2);
}

function current_week_balance($pid,$aid,$enrollment = true){
global $CFG;
    $invoiceweek = date("N") == 7 ? strtotime("Sunday") : strtotime("previous Sunday");
    $program = get_db_row("SELECT * FROM programs WHERE pid='$pid'");
    $SQL = "SELECT * FROM accounts WHERE aid='$aid'";

    if($override = get_db_row("SELECT * FROM billing_override WHERE pid='$pid' AND aid='$aid'")){ //account override is present
        $program["bill_by"] = $override["bill_by"]; //override program settings
        $program["perday"] = $override["perday"]; //override program settings
        $program["fulltime"] = $override["fulltime"]; //override program settings
        $program["minimumactive"] = $override["minimumactive"]; //override program settings
        $program["minimuminactive"] = $override["minimuminactive"]; //override program settings
        $program["vacation"] = $override["vacation"]; //override program settings
        $program["multiple_discount"] = $override["multiple_discount"]; //override program settings
        $program["consider_full"] = $override["consider_full"]; //override program settings
        $program["discount_rule"] = $override["discount_rule"]; //override program settings
    }

    $totalbill = $perchildbill = $childcount = 0;
    $lastid = '0';
    if($accounts = get_db_result($SQL)){
        while($account = fetch_row($accounts)){
            $SQL = "SELECT * FROM children WHERE aid='".$account["aid"]."' AND chid IN (SELECT chid FROM enrollments WHERE pid='$pid') AND chid IN (SELECT chid FROM activity WHERE pid='$pid' AND tag='in') ORDER BY last,first";
            if($children = get_db_result($SQL)){
                while($child = fetch_row($children)){
                    //Child has signed in so he may be billed
                    if($firstin = get_db_field("MIN(timelog)","activity","pid='$pid' AND chid='".$child["chid"]."' AND tag='in'")){
                        //Get nearest Saturday, counting today if Saturday
                        $chid = $child["chid"];
                        $discount = "";
                        $perchild = get_db_row("SELECT * FROM billing_perchild WHERE pid='$pid' AND chid='$chid' AND fromdate = '$invoiceweek'");
                        $enrollment = $enrollment && $perchild ? $perchild["days_attending"] : ($program["bill_by"] == "enrollment" ? get_db_field("days_attending","enrollments","chid='$chid' AND pid='$pid'") : "attendance");
                        $endofweek = strtotime("next Saturday",$invoiceweek);

                        //Create a week's enrollment based on attendance instead of the program enrollment settings
                        if($enrollment == "attendance"){
                            $enrollment = "";
                            if($days_attending = get_db_result("SELECT DAYOFWEEK(CONVERT_TZ(FROM_UNIXTIME(timelog),'".get_date('P',time(),$CFG->servertz)."','".get_date('P',time(),$CFG->timezone)."')) as daynum, CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'".get_date('P',time(),$CFG->servertz)."','".get_date('P',time(),$CFG->timezone)."'))) as order_day FROM activity WHERE tag='in' AND pid='$pid' AND chid='$chid' AND timelog >= $invoiceweek AND timelog < $endofweek GROUP BY order_day ORDER BY order_day")){
                                $days = array("","S","M","T","W","Th","F","Sa");
                                while($attend = fetch_row($days_attending)){
                                    $enrollment .= empty($enrollment) ? $days[$attend["daynum"]] : ','.$days[$attend["daynum"]];
                                }
                            }
                        }

                        if($activities = get_db_result("SELECT * FROM activity WHERE tag='in' AND pid='$pid' AND chid='$chid' AND timelog >= $invoiceweek AND timelog < $endofweek ORDER BY timelog")){
                            $sameday = $bill = $attendance = 0;
                            $days = "";
                            while($activity = fetch_row($activities)){
                                $bill += date("m/d/Y",display_time($activity["timelog"])) == $sameday ? 0 : $program["perday"];
                                $attendance += date("m/d/Y",display_time($activity["timelog"])) == $sameday ? "0" : "1";
                                $days .= date("m/d/Y",display_time($activity["timelog"])) == $sameday ? "" : ($days == "" ? date("D",display_time($activity["timelog"])) : " ".date("D",display_time($activity["timelog"])));
                                $sameday = date("m/d/Y",display_time($activity["timelog"]));
                            }
                            $bill = $program["minimumactive"] > "0" && ($bill < $program["minimumactive"]) ? $program["minimumactive"] : $bill;
                            $attendance .= $attendance > 0 ? ($attendance == 1 ? " day ($days)" : " days ($days)") : " days";

                            if(!$perchild){
                                $perchildbill = save_child_invoice($program,$chid,$invoiceweek,$endofweek,$enrollment,$lastid,$bill,$attendance,"unknown",true);
                            }
                        }else{ //Did not attend, see if there is a minimum.
                            $bill = $program["minimuminactive"] > "0" ? $program["minimuminactive"] : "0";

                            if(!$perchild){
                                $perchildbill = save_child_invoice($program,$chid,$invoiceweek,$endofweek,$enrollment,$lastid,$bill,"","unknown",true);
                            }
                        }
                    }

                    if($perchildbill >= $program["discount_rule"]){
                        $childcount++;
                    }
                    $totalbill += $perchildbill;
                }
            }
        }
    }
    $totalbill = $childcount > 2 ? $totalbill - ($program["multiple_discount"] * ($childcount-1)) : $totalbill;
    return number_format($totalbill,2);
}

function make_account_invoice($pid,$aid,$invoiceweek=false){
    $returnme = "";
    $invoicesql = $invoiceweek ? " AND fromdate = '$invoiceweek' " : "";
    //done with children, total each account now
    $SQL = "SELECT * FROM billing_perchild WHERE pid='$pid' AND chid IN (SELECT chid FROM children WHERE aid='$aid') $invoicesql ORDER BY fromdate";
    if($child_invoices = get_db_result($SQL)){
        $sameweek = $bill = 0;
        $receipt = "";
        while($invoice = fetch_row($child_invoices)){  //Loop through each week
            $fromdate = $invoice["fromdate"];
            $todate = $invoice["todate"];
             //Does this invoice need to be made?
            if($fromdate != $sameweek){ //start of a new week
                if($sameweek !== 0){ //not the first week, so you need to end the last week.
                    $receipt .= '<div><strong>Week Total: $'.number_format($bill,2).'</strong></div>';
                    if(!get_db_row("SELECT * FROM billing WHERE pid='$pid' AND aid='$aid' AND fromdate='$oldfromdate'")){
                        $SQL = "INSERT INTO billing (pid,aid,fromdate,todate,owed,receipt) VALUES ('$pid','$aid','$oldfromdate','$oldtodate','$bill','$receipt')";
                        execute_db_sql($SQL);
                        $returnme .= "<div><strong>Week of " . get_date('F \t\h\e jS Y',$oldfromdate)."</strong><div>".$receipt."</div></div><br />";
                    }
                    $receipt = "";
                }

                //Start new week bill;
                $bill = empty($invoice["exempt"]) ? $invoice["bill"] : 0;

                //Start week
                $receipt .=  empty($invoice["exempt"]) ? "<div>".$invoice["receipt"]."</div>" : "<div>".$invoice["receipt"]." - Exempt $0</div>";
            }else{ //Same week continuing
                //Add to bill
                $bill +=  empty($invoice["exempt"]) ? $invoice["bill"] : 0;
                $receipt .=  empty($invoice["exempt"]) ? "<div>".$invoice["receipt"]."</div>" : "<div>".$invoice["receipt"]." - Exempt $0</div>";
            }
            //Save last week
            $oldfromdate = $fromdate;
            $oldtodate = $todate;
            $sameweek = $fromdate;
        }

        if($sameweek !== 0){ //not the first week, so you need to end the last week.
            $receipt .= '<div><strong>Week Total: $'.number_format($bill,2).'</strong></div>';
            if(!get_db_row("SELECT * FROM billing WHERE pid='$pid' AND aid='$aid' AND fromdate='$oldfromdate'")){
                $SQL = "INSERT INTO billing (pid,aid,fromdate,todate,owed,receipt) VALUES ('$pid','$aid','$oldfromdate','$oldtodate','$bill','$receipt')";
                execute_db_sql($SQL);
                $returnme .= "<div><strong>Week of " . get_date('F \t\h\e jS Y',$oldfromdate)."</strong><div>".$receipt."</div></div><br />";
            }
        }
        $returnme = empty($returnme) ? "" : "<span>" . $returnme . "</span>";
    }

    $returnme = empty($returnme) ? "" : "<br /><strong>".get_name(array("type" => "aid","id" => $aid))."</strong>" . $returnme;
    return $returnme;
}

function save_child_invoice($program,$chid,$invoiceweek,$endofweek,$billed_by,$lastid="0",$bill="",$attendance="",$exempt='unknown',$billonly=false){
    $discount = "";
    $discount_rule = empty($program["discount_rule"]) || $program["discount_rule"] < $program["multiple_discount"] ? "(bill >= ".$program["multiple_discount"]."" : "(bill >= ".$program["discount_rule"].")";

    $exempt = $exempt == "unknown" ? get_db_field("exempt","enrollments","chid='$chid' AND pid='".$program["pid"]."'") : $exempt;
    $days_attending = get_db_field("days_attending","enrollments","chid='$chid' AND pid='".$program["pid"]."'");
    //SQL that finds other children on the account that would qualify this child for a discount
    $otherchildrenthatmatch = "SELECT * FROM billing_perchild WHERE
        0='$exempt' /* this child is not exempt */
        AND pid='".$program["pid"]."' /* must be a record for the active program */
        AND chid IN (SELECT chid FROM enrollments WHERE pid='".$program["pid"]."') /* matched record must be from an enrolled user */
        AND chid IN (SELECT chid FROM children WHERE aid IN (SELECT aid FROM children WHERE chid='$chid')) /* record must be from a child from the same account */
        AND fromdate='$invoiceweek' /* record must match the same week */
        AND id > $lastid /* record must be newer than last record checked */
        AND exempt=0 /* matching child record is also not exempt */
        AND chid!='$chid' /* the record cannot match another record of the same child */
        AND $discount_rule /* record must also meet the discount rules */";

    // $billed_by is either enrollment or days the child attended ex. M,W,Th,F
    if(($billed_by == "enrollment" && count(explode(",",$days_attending)) >= $program["consider_full"]) || ($billed_by != "enrollment" && !empty($attendance) && $attendance[0] >= $program["consider_full"])){ // If enrollment is considered full time
        if(empty($attendance)){
            $bill = $program["vacation"];
            $rate = "Did Not Attend [Vacation Rate]";
        }else{
            $bill = empty($program["fulltime"]) ? $program["perday"] * $program["consider_full"] : $program["fulltime"];
            if($bill >= $program["discount_rule"] && get_db_row($otherchildrenthatmatch)){ //Not the first child on this account this week
                $discount = "[$".number_format($program["multiple_discount"],2)." Multiple Child Discount]";
                $bill = $bill - $program["multiple_discount"];
            }
            $rate = "[Fulltime Rate] $discount Attended $attendance";
        }

        if($exempt == "1"){
            $bill = 0;
            $receipt = get_name(array("type"=>"chid","id"=>$chid))." - [Exempt] Attended $attendance: $".number_format($bill,2);
        }else{
            $receipt = get_name(array("type"=>"chid","id"=>$chid))." - $rate: $".number_format($bill,2);
        }

        if($billonly){ return $bill; }
        $SQL = "INSERT INTO billing_perchild (pid,chid,fromdate,todate,bill,receipt,exempt,days_attending) VALUES('".$program["pid"]."','$chid','$invoiceweek','$endofweek','$bill','$receipt','$exempt','$billed_by')";
        if(!get_db_row("SELECT fromdate FROM billing_perchild WHERE pid=''".$program["pid"]."'' AND chid='$chid' AND fromdate='$invoiceweek'")){
            execute_db_sql($SQL);
        }
    }else{ //enrollment considered part-time
        if(!empty($attendance) && $bill >= $program["discount_rule"] && get_db_row($otherchildrenthatmatch)){ //Not the first child on this account this week
            $discount = "[$".number_format($program["multiple_discount"],2)." Multiple Child Discount]";
            $bill = $bill - $program["multiple_discount"];
        }

        if ($exempt == "1") {
            $bill = 0;
            $receipt = empty($attendance) ? get_name(array("type"=>"chid","id"=>$chid))." - Did Not Attend [Exempt]: $".number_format($bill,2): get_name(array("type"=>"chid","id"=>$chid))." - [Exempt] Attended $attendance: $".number_format($bill,2);
        } else {
            if (empty($attendance)) {
                $minimum = $bill == $program["minimuminactive"] ? "Minimum " : "";
            } else {
                $minimum = $bill == $program["minimumactive"] ? "Minimum " : "";
            }

            $receipt = empty($attendance) ? get_name(array("type"=>"chid","id"=>$chid))." - Did Not Attend [Minimum Rate]: $".number_format($bill,2): get_name(array("type"=>"chid","id"=>$chid))." - [".$minimum."Part-time Rate] $discount Attended $attendance: $".number_format($bill,2);
        }

        if($billonly){ return $bill; }
        $SQL = "INSERT INTO billing_perchild (pid,chid,fromdate,todate,bill,receipt,exempt,days_attending) VALUES('".$program["pid"]."','$chid','$invoiceweek','$endofweek','$bill','$receipt','$exempt','$billed_by')";
        if(!get_db_row("SELECT fromdate FROM billing_perchild WHERE pid=''".$program["pid"]."'' AND chid='$chid' AND fromdate='$invoiceweek'")){
            execute_db_sql($SQL);
        }
    }
}

//Makes Child invoice per week
function make_child_invoice($pid,$chid,$invoiceweek,$refresh=false,$lastid='0',$honor_past_enrollment=true){
global $CFG;
    $discount = ""; $override = false;
    $program = get_db_row("SELECT * FROM programs WHERE pid='$pid'");
    $perchild = get_db_row("SELECT * FROM billing_perchild WHERE pid='$pid' AND chid='$chid' AND fromdate = '$invoiceweek'");
    $endofweek = strtotime("+1 week -1 second",$invoiceweek);

    //check to see if in the past the user was exempt, if no history is found or you don't want to honor the past, just get it from current enrollment settings
    $exempt = $honor_past_enrollment && $perchild ? $perchild["exempt"] : get_db_field("exempt","enrollments","chid='$chid' AND pid='$pid'");

    //you want to remember past settings and there is a history recorded
    if(!empty($honor_past_enrollment) && !empty($perchild)){
        $bill_by = $perchild["days_attending"];  //bill according to the days attended
    }elseif($override = get_db_row("SELECT * FROM billing_override WHERE pid='$pid' AND aid IN (SELECT aid FROM children WHERE chid='$chid')")){ //account override is present
        $bill_by = $override["bill_by"];
        $program["bill_by"] = $override["bill_by"]; //override program settings
        $program["perday"] = $override["perday"]; //override program settings
        $program["fulltime"] = $override["fulltime"]; //override program settings
        $program["minimumactive"] = $override["minimumactive"]; //override program settings
        $program["minimuminactive"] = $override["minimuminactive"]; //override program settings
        $program["vacation"] = $override["vacation"]; //override program settings
        $program["multiple_discount"] = $override["multiple_discount"]; //override program settings
        $program["consider_full"] = $override["consider_full"]; //override program settings
        $program["discount_rule"] = $override["discount_rule"]; //override program settings
    }elseif($program["bill_by"] == "enrollment"){ //there is no history or you don't want to remember the past and the program is now set to enrollment billing
        $bill_by = get_db_field("days_attending","enrollments","chid='$chid' AND pid='$pid'"); //Get the days attending.
    }else{ //only other choice is that there is no history and the program is set to attendance billing.  This will be built next.
        //Create a week's enrollment based on attendance instead of the program enrollment settings
        $bill_by = "";
        if($days_attending = get_db_result("SELECT DAYOFWEEK(CONVERT_TZ(FROM_UNIXTIME(timelog),'".get_date('P',time(),$CFG->servertz)."','".get_date('P',time(),$CFG->timezone)."')) as daynum, CONCAT(YEAR(FROM_UNIXTIME(timelog)),MONTH(FROM_UNIXTIME(timelog)),DAY(CONVERT_TZ(FROM_UNIXTIME(timelog),'".get_date('P',time(),$CFG->servertz)."','".get_date('P',time(),$CFG->timezone)."'))) as order_day FROM activity WHERE tag='in' AND pid='$pid' AND chid='$chid' AND timelog >= $invoiceweek AND timelog < $endofweek GROUP BY order_day ORDER BY order_day")){
            $days = array("","S","M","T","W","Th","F","Sa");
            while($attend = fetch_row($days_attending)){
                $bill_by .= empty($bill_by) ? $days[$attend["daynum"]] : ','.$days[$attend["daynum"]];
            }
        }
    }

    //OLD $bill_by = $honor_past_enrollment && $perchild ? $perchild["days_attending"] : ($program["bill_by"] == "enrollment" ? get_db_field("days_attending","enrollments","chid='$chid' AND pid='$pid'") : "attendance");

    if($activities = get_db_result("SELECT * FROM activity WHERE tag='in' AND pid='$pid' AND chid='$chid' AND timelog >= $invoiceweek AND timelog < $endofweek ORDER BY timelog")){
        $sameday = $bill = $attendance = 0;
        $days = "";
        while($activity = fetch_row($activities)){
            $bill += date("m/d/Y",display_time($activity["timelog"])) == $sameday ? 0 : (!empty($override) ? $override["perday"] : $program["perday"]);
            $attendance += date("m/d/Y",display_time($activity["timelog"])) == $sameday ? "0" : "1";
            $days .= date("m/d/Y",display_time($activity["timelog"])) == $sameday ? "" : ($days == "" ? date("D",display_time($activity["timelog"])) : " ".date("D",display_time($activity["timelog"])));
            $sameday = date("m/d/Y",display_time($activity["timelog"]));
        }

        if ($attendance > 0) {
            $bill = $program["minimumactive"] > "0" && ($bill < $program["minimumactive"]) ? $program["minimumactive"] : $bill;
        } else {
            $bill = $program["minimuminactive"] > "0" && ($bill < $program["minimuminactive"]) ? $program["minimuminactive"] : $bill;
        }
        $attendance .= $attendance > 0 ? ($attendance == 1 ? " day ($days)" : " days ($days)") : " days";

        if($refresh){ execute_db_sql("DELETE FROM billing_perchild WHERE pid='$pid' AND chid='$chid' AND fromdate = '$invoiceweek'"); }

        if(!$perchild){
            save_child_invoice($program,$chid,$invoiceweek,$endofweek,$bill_by,$lastid,$bill,$attendance);
        }elseif($refresh){
            save_child_invoice($program,$chid,$invoiceweek,$endofweek,$bill_by,$lastid,$bill,$attendance,$exempt);
        }
    }else{ //Did not attend, see if there is a minimuminactive rate.
        $bill = $program["minimuminactive"] > "0" ? $program["minimuminactive"] : "0";
        if($refresh){ execute_db_sql("DELETE FROM billing_perchild WHERE pid='$pid' AND chid='$chid' AND fromdate = '$invoiceweek'"); }

        if(!$perchild){
            save_child_invoice($program,$chid,$invoiceweek,$endofweek,$bill_by,$lastid,$bill);
        }elseif($refresh){
            save_child_invoice($program,$chid,$invoiceweek,$endofweek,$bill_by,$lastid,$bill,"",$exempt);
        }
    }
}

function create_invoices($return = false, $pid = null, $aid = null, $refreshall = false, $startweek = "0", $honor_past_enrollment = true){
global $CFG, $MYVARS;
    $pid = $pid !== null ? $pid : (empty($MYVARS->GET["pid"]) ? get_pid() : $MYVARS->GET["pid"]);
    $aid = $aid !== null ? $aid : (empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"]);
    $returnme = "";

    $program = get_db_row("SELECT * FROM programs WHERE pid='$pid'");
    if(empty($aid)){ //All accounts enrolled in program
        if(!empty($refreshall)){
            execute_db_sql("DELETE FROM billing WHERE pid='$pid' AND fromdate >= $startweek");
        }
        $SQL = "SELECT * FROM accounts WHERE deleted = '0' AND admin= '0' AND aid IN (SELECT aid FROM children WHERE chid IN (SELECT chid FROM enrollments WHERE pid='$pid')) ORDER BY name";
    }else{ //Only selected account
        if(!empty($refreshall)){
            execute_db_sql("DELETE FROM billing WHERE pid='$pid' AND aid='$aid' AND fromdate >= $startweek");
        }
        $SQL = "SELECT * FROM accounts WHERE aid='$aid'";
    }

    //Employees section
    if($employees = get_db_result("SELECT * FROM employee")){
        while($employee = fetch_row($employees)){
            if($firstin = get_db_field("MIN(timelog)","employee_activity","employeeid='".$employee["employeeid"]."' AND tag='in'")){
                $firstin = empty($startweek) ? $firstin : ($firstin < $startweek ? $startweek : $firstin);
                if(!empty($firstin)){
                    if(date('N',$firstin) == "7"){ //is already a sunday
                        $firstweek = strtotime(date('m/d/Y',$firstin));
                    }else{
                        $firstweek = strtotime("previous Sunday UTC",$firstin);
                    }

                    $invoiceweek = $firstweek;

                    //Get nearest Saturday, counting today if Saturday
                    $runtill = date("N",get_timestamp($CFG->timezone)) == 6 ? strtotime("today UTC") : strtotime("previous Saturday UTC");
                    //go to the end of that Saturday
                    $runtill = strtotime("+1 day -1 second",$runtill);

                    while($invoiceweek < $runtill){
                        closeout_workdays($employee["employeeid"],$invoiceweek,$refreshall);
                        //Go to next week
                        $invoiceweek = strtotime("+1 week",$invoiceweek);
                    }
                }
            }
        }
    }

    $lastid = !empty($refreshall) ? get_db_field("MAX(id)","billing_perchild","id>0") : '0';
    if($accounts = get_db_result($SQL)){
        while($account = fetch_row($accounts)){
            $SQL = "SELECT * FROM children WHERE aid='".$account["aid"]."' AND chid IN (SELECT chid FROM enrollments WHERE pid='$pid') AND chid IN (SELECT chid FROM activity WHERE pid='$pid' AND tag='in') ORDER BY last,first";
            if($children = get_db_result($SQL)){
                while($child = fetch_row($children)){
                    //Child has signed in so he may be billed
                    if($firstin = get_db_field("MIN(timelog)","activity","pid='$pid' AND chid='".$child["chid"]."' AND tag='in'")){
                        $firstin = empty($startweek) ? $firstin : ($firstin < $startweek ? $startweek : $firstin);
                        if(!empty($firstin)){
                            if(date('N',$firstin) == "7"){ //is already a sunday
                                $firstweek = strtotime(date('m/d/Y',$firstin));
                            }else{
                                $firstweek = strtotime("previous Sunday UTC",$firstin);
                            }

                            $invoiceweek = $firstweek;

                            //Get nearest Saturday, counting today if Saturday
                            $runtill = date("N",get_timestamp($CFG->timezone)) == 6 ? strtotime("today UTC") : strtotime("previous Saturday UTC");
                            //go to the end of that Saturday
                            $runtill = strtotime("+1 day -1 second",$runtill);

                            while($invoiceweek < $runtill){
                                make_child_invoice($pid,$child["chid"],$invoiceweek,$refreshall,$lastid,$honor_past_enrollment);
                                //Go to next week
                                $invoiceweek = strtotime("+1 week",$invoiceweek);
                            }
                        }
                    }
                }
            }
            $returnme .= make_account_invoice($pid,$account["aid"]);
        }
    }

    if($returnme == ""){
        $returnme .= '<div>None</div>';
    }

    $returnme = '<div style="display:table-cell;font-weight: bold;font-size: 120%;padding: 10px;">Invoices Created:</div><div class="scroll-pane fill_height"><div style="padding:10px;">' . $returnme . '</div></div>';

    if($return){
        return $returnme;
    }else{
        echo $returnme;
    }
}

function get_enrollment_method($pid,$aid=false,$chid=false){
    $program = get_db_row("SELECT * FROM programs WHERE pid='$pid'");
    //you want to remember past settings and there is a history recorded
    if(!empty($aid)){
        if($override = get_db_row("SELECT * FROM billing_override WHERE pid='$pid' AND aid='$aid'")){ //account override is present
            $program["bill_by"] = $override["bill_by"];
        }
    }elseif(!empty($chid)){
        if($override = get_db_row("SELECT * FROM billing_override WHERE pid='$pid' AND aid IN (SELECT aid FROM children WHERE chid='$chid')")){ //account override is present
            $program["bill_by"] = $override["bill_by"];
        }
    }
    return $program["bill_by"];
}
?>