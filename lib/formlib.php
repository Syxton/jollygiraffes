<?php
/***************************************************************************
* formlib.php - Form library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/27/2013
* Revision: 0.0.3
***************************************************************************/

if(!isset($LIBHEADER)){ include ('header.php'); }
$FORMLIB = true;

function get_form($formname,$vars=null,$identifier=""){
global $CFG;
    $identifier = $identifier == "" ? "" : "_$identifier";
    $activepid = get_pid();
    $form = "";
    switch ($formname) {
        case "add_edit_program":
            $name = empty($vars["program"]["name"]) ? "" : $vars["program"]["name"];
            $fein = empty($vars["program"]["fein"]) ? "" : $vars["program"]["fein"];
            $timeopen = empty($vars["program"]["timeopen"]) ? "" : $vars["program"]["timeopen"];
            $timeclosed = empty($vars["program"]["timeclosed"]) ? "" : $vars["program"]["timeclosed"];
            $perday = empty($vars["program"]["perday"]) ? "0" : $vars["program"]["perday"];
            $fulltime = empty($vars["program"]["fulltime"]) ? "0" : $vars["program"]["fulltime"];
            $minimumactive = empty($vars["program"]["minimumactive"]) ? "0" : $vars["program"]["minimumactive"];
            $minimuminactive = empty($vars["program"]["minimuminactive"]) ? "0" : $vars["program"]["minimuminactive"];
            $vacation = empty($vars["program"]["vacation"]) ? "0" : $vars["program"]["vacation"];
            $multiple_discount = empty($vars["program"]["multiple_discount"]) ? "0" : $vars["program"]["multiple_discount"];
            $consider_full = empty($vars["program"]["consider_full"]) ? "5" : $vars["program"]["consider_full"];
            $bill_by = empty($vars["program"]["bill_by"]) ? "enrollment" : $vars["program"]["bill_by"];
            $payahead = empty($vars["program"]["payahead"]) ? "0" : $vars["program"]["payahead"];
            $discount_rule = empty($vars["program"]["discount_rule"]) ? "0" : $vars["program"]["discount_rule"];

            $title = empty($vars["pid"]) ? "Add Program" : "Edit Program";

            $days[1] = new stdClass(); $days[2] = new stdClass();
            $days[3] = new stdClass(); $days[4] = new stdClass(); $days[5] = new stdClass();
            $days[6] = new stdClass(); $days[7] = new stdClass(); $days[8] = new stdClass();

            $days[1]->value = "1"; $days[1]->display = "1 day attending";
            $days[2]->value = "2"; $days[2]->display = "2 days attending";
            $days[3]->value = "3"; $days[3]->display = "3 days attending";
            $days[4]->value = "4"; $days[4]->display = "4 days attending";
            $days[5]->value = "5"; $days[5]->display = "5 days attending";
            $days[6]->value = "6"; $days[6]->display = "6 days attending";
            $days[7]->value = "7"; $days[7]->display = "7 days attending";
            $days[8]->value = "8"; $days[8]->display = "Part-time Rate Only";

            $bill_by_array[0] = new stdClass(); $bill_by_array[1] = new stdClass();
            $bill_by_array[0]->value = "enrollment"; $bill_by_array[0]->display = "Enrollment";
            $bill_by_array[1]->value = "attendance"; $bill_by_array[1]->display = "Attendance";
            $payahead_array[0] = new stdClass(); $payahead_array[1] = new stdClass();
            $payahead_array[0]->value = "0"; $payahead_array[0]->display = "No";
            $payahead_array[1]->value = "1"; $payahead_array[1]->display = "Yes";
            $fields = "";
            $pid = empty($vars["pid"]) ? "0" : $vars["pid"];
            $fields .= empty($vars["pid"]) ? "" : '<input type="hidden" name="pid" class="fields pid" value="'.$vars["pid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="programs" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $form = '<div id="add_edit_program'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table style="width:100%;">
                                <tr><td><label for="name">Name</label></td><td><input style="width:100%;" class="fields" type="input" name="name" id="name" value="'.$name.'" /></td></tr>
                                <tr><td><label for="fein">Business ID or FEIN</label></td><td><input style="width:100%;" class="fields" type="input" name="fein" id="fein" value="'.$fein.'" /></td></tr>
                                <tr><td><label for="business_hours">Normal Hours</label></td><td><span style="display:inline-block;width:55px;">From:</span><input class="time fields" name="timeopen" id="timeopen" type="text" value="'.$timeopen.'" /><br /><span style="display:inline-block;width:55px;">To:</span><input class="time fields" name="timeclosed" id="timeclosed" type="text" value="'.$timeclosed.'" /></td></tr>
                                <tr><td><label for="consider_full">Full Week (days)</label></td><td>'.make_select_from_object("consider_full",$days,"value","display","fields",$consider_full).'</td></tr>
                                <tr><td><label for="bill_by">Bill By</label></td><td>'.make_select_from_object("bill_by",$bill_by_array,"value","display","fields",$bill_by).'</td></tr>
                                <tr><td><label for="payahead">Pay Ahead</label></td><td>'.make_select_from_object("payahead",$payahead_array,"value","display","fields",$payahead).'</td></tr>
                                <tr><td><label for="perday">Price Per Day</label></td><td>$<input style="width:125px;" class="fields" type="input" name="perday" id="perday" value="'.$perday.'" /></td></tr>
                                <tr><td><label for="fulltime">Full Week Price</label></td><td>$<input style="width:125px;" class="fields" type="input" name="fulltime" id="fulltime" value="'.$fulltime.'" /></td></tr>
                                <tr><td><label for="minimumactive">Minimum (Active)</label></td><td>$<input style="width:125px;" class="fields" type="input" name="minimumactive" id="minimumactive" value="'.$minimumactive.'" /></td></tr>
                                <tr><td><label for="minimuminactive">Minimum (Inactive)</label></td><td>$<input style="width:125px;" class="fields" type="input" name="minimuminactive" id="minimuminactive" value="'.$minimuminactive.'" /></td></tr>
                                <tr><td><label for="vacation">Vacation Price</label></td><td>$<input style="width:125px;" class="fields" type="input" name="vacation" id="vacation" value="'.$vacation.'" /></td></tr>
                                <tr><td><label for="multiple_discount">Multiple Discount</label></td><td>$<input style="width:125px;" class="fields" type="input" name="multiple_discount" id="multiple_discount" value="'.$multiple_discount.'" /></td></tr>
                                <tr><td><label for="discount_rule">Discount Qualifier</label></td><td>$<input style="width:125px;" class="fields" type="input" name="discount_rule" id="discount_rule" value="'.$discount_rule.'" /></td></tr>
                            </table>
                        <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          timeout: 10000,
                          error: function(x, t, m) {
                            $(button).button(\'option\', \'disabled\', false);
                          },
                          data: { action: \'add_edit_program\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                          success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_edit_program'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); if(\''.$pid.'\' == \''.$activepid.'\'){ $(\'#activepidname\').html(\''.$name.'\'); } }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                          });">Save Program</button>
                          </form>
                    </div>
                    <script type="text/javascript">
                        $(document).ready(function(){
                            $(\'.time\').timepicker({ \'forceRoundTime\': true });
                        });
                    </script>
                    ';
            break;
        case "billing_overrides":
            $perday = empty($vars["override"]["perday"]) && $vars["override"]["perday"] !== "0" ? "" : $vars["override"]["perday"];
            $fulltime = empty($vars["override"]["fulltime"]) && $vars["override"]["fulltime"] !== "0" ? "" : $vars["override"]["fulltime"];
            $minimumactive = empty($vars["override"]["minimumactive"]) && $vars["override"]["minimumactive"] !== "0" ? "" : $vars["override"]["minimumactive"];
            $minimuminactive = empty($vars["override"]["minimuminactive"]) && $vars["override"]["minimuminactive"] !== "0" ? "" : $vars["override"]["minimuminactive"];
            $vacation = empty($vars["override"]["vacation"]) && $vars["override"]["vacation"] !== "0" ? "" : $vars["override"]["vacation"];
            $multiple_discount = empty($vars["override"]["multiple_discount"]) && $vars["override"]["multiple_discount"] !== "0" ? "" : $vars["override"]["multiple_discount"];
            $consider_full = empty($vars["override"]["consider_full"]) ? "" : $vars["override"]["consider_full"];
            $bill_by = empty($vars["override"]["bill_by"]) ? "none" : $vars["override"]["bill_by"];
            $payahead = empty($vars["override"]["payahead"]) ? "none" : $vars["override"]["payahead"];
            $discount_rule = empty($vars["override"]["discount_rule"]) ? "" : $vars["override"]["discount_rule"];

            $title = "Billing Override";
            $days[0] = new stdClass(); $days[1] = new stdClass(); $days[2] = new stdClass();
            $days[3] = new stdClass(); $days[4] = new stdClass(); $days[5] = new stdClass();
            $days[6] = new stdClass(); $days[7] = new stdClass(); $days[8] = new stdClass();
            $days[0]->value = "none"; $days[0]->display = "None";
            $days[1]->value = "1"; $days[1]->display = "1 day attending";
            $days[2]->value = "2"; $days[2]->display = "2 days attending";
            $days[3]->value = "3"; $days[3]->display = "3 days attending";
            $days[4]->value = "4"; $days[4]->display = "4 days attending";
            $days[5]->value = "5"; $days[5]->display = "5 days attending";
            $days[6]->value = "6"; $days[6]->display = "6 days attending";
            $days[7]->value = "7"; $days[7]->display = "7 days attending";
            $days[8]->value = "8"; $days[8]->display = "Part-time Rate Only";


            $bill_by_array[0] = new stdClass(); $bill_by_array[1] = new stdClass(); $bill_by_array[2] = new stdClass();
            $bill_by_array[0]->value = "none"; $bill_by_array[0]->display = "None";
            $bill_by_array[1]->value = "enrollment"; $bill_by_array[1]->display = "Enrollment";
            $bill_by_array[2]->value = "attendance"; $bill_by_array[2]->display = "Attendance";

            $payahead_array[0] = new stdClass(); $payahead_array[1] = new stdClass(); $payahead_array[2] = new stdClass();
            $payahead_array[0]->value = "none"; $payahead_array[0]->display = "None";
            $payahead_array[1]->value = "0"; $payahead_array[1]->display = "No";
            $payahead_array[2]->value = "1"; $payahead_array[2]->display = "Yes";

            $fields = "";
            $pid = empty($vars["pid"]) ? "0" : $vars["pid"];
            $aid = empty($vars["aid"]) ? "0" : $vars["aid"];
            $oid = empty($vars["oid"]) ? "0" : $vars["aid"];
            $fields .= empty($vars["pid"]) ? "" : '<input type="hidden" name="pid" class="fields pid" value="'.$vars["pid"].'" />';
            $fields .= empty($vars["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />';
            $fields .= empty($vars["oid"]) ? "" : '<input type="hidden" name="oid" class="fields oid" value="'.$vars["oid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="billing" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $fields .= '<input type="hidden" name="callbackinfo" class="fields callbackinfo" value="'.$aid.'" />';

            $form = '<div id="billing_overrides'.$identifier.'" title="'.$title.'" style="display:none;">
                		<form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table style="width:100%;">
                                <tr><td><label for="bill_by">Override Type</label></td><td>'.make_select_from_object("bill_by",$bill_by_array,"value","display","fields",$bill_by).'</td></tr>
                                <tr><td><label for="payahead">Pay Ahead</label></td><td>'.make_select_from_object("payahead",$payahead_array,"value","display","fields",$payahead).'</td></tr>
                                <tr><td><label for="consider_full">Full Week (days)</label></td><td>'.make_select_from_object("consider_full",$days,"value","display","fields",$consider_full).'</td></tr>
                                <tr><td><label for="perday">Price Per Day</label></td><td>$<input style="width:125px;" class="fields" type="input" name="perday" id="perday" value="'.$perday.'" /></td></tr>
                                <tr><td><label for="fulltime">Full Week Price</label></td><td>$<input style="width:125px;" class="fields" type="input" name="fulltime" id="fulltime" value="'.$fulltime.'" /></td></tr>
                                <tr><td><label for="minimumactive">Minimum (Active)</label></td><td>$<input style="width:125px;" class="fields" type="input" name="minimumactive" id="minimumactive" value="'.$minimumactive.'" /></td></tr>
                                <tr><td><label for="minimuminactive">Minimum (Inactive)</label></td><td>$<input style="width:125px;" class="fields" type="input" name="minimuminactive" id="minimuminactive" value="'.$minimuminactive.'" /></td></tr>
                                <tr><td><label for="vacation">Vacation Price</label></td><td>$<input style="width:125px;" class="fields" type="input" name="vacation" id="vacation" value="'.$vacation.'" /></td></tr>
                                <tr><td><label for="multiple_discount">Multiple Discount</label></td><td>$<input style="width:125px;" class="fields" type="input" name="multiple_discount" id="multiple_discount" value="'.$multiple_discount.'" /></td></tr>
                                <tr><td><label for="discount_rule">Discount Qualifier</label></td><td>$<input style="width:125px;" class="fields" type="input" name="discount_rule" id="discount_rule" value="'.$discount_rule.'" /></td></tr>
                            </table>
                        <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          timeout: 10000,
                          error: function(x, t, m) {
                            $(button).button(\'option\', \'disabled\', false);
                          },
                          data: { action: \'billing_overrides\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                          success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#billing_overrides'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                          });">Save Override</button>
                          </form>
                    </div>';
            break;
        case "add_edit_payment":
            $payment = empty($vars["payment"]) ? "" : $vars["payment"];
            $amount = empty($payment["payment"]) ? "" : $payment["payment"];
            $timelog = empty($payment["timelog"]) ? date('m/d/Y',display_time(get_timestamp())) : date('m/d/Y',display_time($payment["timelog"]));
            $note = empty($payment["note"]) ? "" : $payment["note"];
            $title = empty($payment["payid"]) ? "Make Payment or Fee" : "Edit Payment or Fee";

            $fields = "";
            $fields .= empty($payment["payid"]) ? "" : '<input type="hidden" name="payid" class="fields payid" value="'.$payment["payid"].'" />';
            $fields .= empty($vars["pid"]) ? "" : '<input type="hidden" name="pid" class="fields pid" value="'.$vars["pid"].'" />';
            $fields .= empty($vars["pid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />';
            $fields .= empty($vars["callbackinfo"]) ? "" : '<input type="hidden" name="callbackinfo" class="fields callbackinfo" value="'.$vars["callbackinfo"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="billing" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $form = '<div id="add_edit_payment'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <div style="text-align:center;color:red">An amount less than 0 is considered a fee.</div>
                            <table style="width:100%;">
                                <tr><td><label for="payment">Amount</label></td><td><input style="width:100%;" class="fields" type="input" name="payment" id="payment" value="'.$amount.'" /></td></tr>
                                <tr><td><label for="timelog">Date</label></td><td><input style="width:100%;" class="fields" type="input" name="timelog" id="timelog" value="'.$timelog.'" /></td></tr>
                                <tr><td><label for="note">Note</label></td><td><textarea style="width:100%;" rows="7" class="fields" type="input" name="note" id="note">'.$note.'</textarea></td></tr>
                            </table>
                        <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          timeout: 10000,
                          error: function(x, t, m) {
                            $(button).button(\'option\', \'disabled\', false);
                          },
                          data: { action: \'add_edit_payment\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                          success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_edit_payment'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                          });">Save Payment</button>
                          </form>
                    </div>';
            break;
        case "add_edit_expense":
            $amount = "";
            $timelog = date('m/d/Y',get_timestamp());
            $note = "";
            $title = "Donations/Expenses";

            $fields = "";
            $fields .= empty($vars["pid"]) ? "" : '<input type="hidden" name="pid" class="fields pid" value="'.$vars["pid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="billing" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $program_expense_form = '';
            if($expenses = get_db_result("SELECT * FROM billing_payments WHERE pid='".$vars["pid"]."' AND aid=0 ORDER BY timelog DESC")){
                    $program_expense_form = '<table style="width:100%;padding-right:5px;">';
                while($expense = fetch_row($expenses)){
                    $program_expense_form .= '<tr class="expense_'.$expense["payid"].'">
                                            <td style="width:110px">
                                                <input type="hidden" class="fields" name="payid" id="payid" value="'.$expense["payid"].'" />
                                                '.get_date('m/d/Y',$expense["timelog"]).'
                                            </td>
                                            <td style="width:100px">
                                                $'.number_format($expense["payment"],2).'
                                            </td>
                                            <td>
                                                '.stripslashes($expense["note"]).'
                                            </td>
                                            <td style="width:15px;">
                                                <a href="javascript: void(0)" onclick="if(confirm(\'Are you sure you want to delete this?\')){
                                                $.ajax({
                                                  type: \'POST\',
                                                  url: \'ajax/ajax.php\',
                                                  timeout: 10000,
                                                  data: { action: \'delete_expense\',payid: '.$expense["payid"].'},
                                                  success: function(data) { $(\'.expense_'.$expense["payid"].'\').hide(); }
                                                  }); }">
                                                    '.get_icon('delete').'
                                                </a>
                                            </td>
                                        </tr>';
                }
                $program_expense_form .= "</table>";
            }else{
               $program_expense_form = "<div style='text-align:center;'><strong>No donations / expenses recorded yet.</strong></div>";
            }

            $form = '<div id="add_edit_expense'.$identifier.'" title="'.$title.'" style="display:none;">
                		<div style="height: 375px;margin-bottom: 10px;">
                            <strong>Program Donation/Expense History</strong><br />
                            <table style="width:100%;padding-right:5px;"><tr>
                                            <td style="width:110px">
                                                Date
                                            </td>
                                            <td style="width:100px">
                                                Amount
                                            </td>
                                            <td>
                                                Note
                                            </td>
                                            <td style="width:15px;">
                                            </td>
                                        </tr></table>
                            <div style="margin-top:5px;height: 330px;overflow-y:scroll;background: #DDFFFF;">
                                '.$program_expense_form.'
                            </div>
                        </div>
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <strong>Add New Donation/Expense</strong>
                            <span style="font-size: 13px;color: blue;float: right;"> Donations are positive and expenses are negative.</span>
                            <table style="width:100%">
                                <tr><td><label for="payment">Amount</label></td><td>$<input style="width:100px;" class="fields" type="input" name="amount" id="amount" value="'.$amount.'" /></td></tr>
                                <tr><td><label for="timelog">Date</label></td><td><input style="width:100px;" class="fields" type="input" name="timelog" id="timelog" value="'.$timelog.'" /></td></tr>
                                <tr><td><label for="note">Note</label></td><td><textarea style="width:100%;" rows="2" class="fields" type="input" name="note" id="note">'.$note.'</textarea></td></tr>
                            </table>
                        <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          timeout: 10000,
                          error: function(x, t, m) {
                            $(button).button(\'option\', \'disabled\', false);
                          },
                          data: { action: \'add_edit_expense\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                          success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_edit_expense'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                          });">Save Donation/Expense</button>
                          </form>
                    </div>';
            break;
        case "add_edit_account":
            $name = empty($vars["account"]["name"]) ? "" : $vars["account"]["name"];
            $password = empty($vars["account"]["password"]) ? "" : $vars["account"]["password"];
            $status = empty($vars["account"]["meal_status"]) ? "paid" : $vars["account"]["meal_status"];
            $title = empty($vars["account"]["aid"]) ? "Add Account" : "Edit Account";

            $meal_status[0] = new stdClass(); $meal_status[1] = new stdClass(); $meal_status[2] = new stdClass();
            $meal_status[0]->value = "paid"; $meal_status[0]->display = "Paid";
            $meal_status[1]->value = "reduced"; $meal_status[1]->display = "Reduced";
            $meal_status[2]->value = "free"; $meal_status[2]->display = "Free";

            $fields = "";
            $fields .= empty($vars["account"]["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["account"]["aid"].'" />';
            $fields .= empty($vars["recover_param"]) ? "" : '<input type="hidden" name="recover" class="fields recover" value="'.$vars["recover_param"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="accounts" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $meal_status_field = empty($vars["account"]["admin"]) ? '<tr><td><label for="meal_status">Meal Status</label></td><td>'.make_select_from_object("meal_status",$meal_status,"value","display","fields",$status).'</td></tr>' : '<input type="hidden" name="meal_status" class="fields meal_status" value="none" />';

            $form = '<div id="add_edit_account'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table>
                                <tr><td><label for="name">Name</label></td><td><input class="fields" type="input" name="name" id="name" value="'.$name.'" /></td></tr>
                                <tr><td><label for="password">Password</label></td><td><input size="4" maxlength="4" class="fields" type="input" name="password" id="password" value="'.$password.'" /></td></tr>
                                '.$meal_status_field.'
                            </table>
                        <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          timeout: 10000,
                          error: function(x, t, m) {
                            $(button).button(\'option\', \'disabled\', false);
                          },
                          data: { action: \'add_edit_account\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                          success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_edit_account'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                          });">Save Account</button>
                          </form>
                    </div>';
            break;
        case "add_edit_employee":
            $first = empty($vars["employee"]["first"]) ? "" : $vars["employee"]["first"];
            $last = empty($vars["employee"]["last"]) ? "" : $vars["employee"]["last"];
            $password = empty($vars["employee"]["password"]) ? "" : $vars["employee"]["password"];
            $wage = empty($vars["employee"]["employeeid"]) ? "$0.00" : (get_wage($vars["employee"]["employeeid"],get_timestamp()) ? "$".get_wage($vars["employee"]["employeeid"],get_timestamp()) : "$0.00");
            $title = empty($vars["employee"]["employeeid"]) ? "Add Employee" : "Edit Employee";

            $fields = "";
            $fields .= empty($vars["employee"]["employeeid"]) ? "" : '<input type="hidden" name="employeeid" class="fields employeeid" value="'.$vars["employee"]["employeeid"].'" />';
            $fields .= empty($vars["recover_param"]) ? "" : '<input type="hidden" name="recover" class="fields recover" value="'.$vars["recover_param"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="employees" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $form = '<div id="add_edit_employee'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table>
                                <tr><td><label for="first">First</label></td><td><input class="fields" type="input" name="first" id="first" value="'.$first.'" /></td></tr>
                                <tr><td><label for="last">Last</label></td><td><input class="fields" type="input" name="last" id="last" value="'.$last.'" /></td></tr>
                                <tr><td><label for="password">Password</label></td><td><input size="4" maxlength="4" class="fields" type="input" name="password" id="password" value="'.$password.'" /></td></tr>
                                <tr><td><label for="wage">Wage</label></td><td><input style="width:100%;" class="fields" type="input" name="wage" id="wage" value="'.$wage.'" /></td></tr>
                            </table>
                        <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          timeout: 10000,
                          error: function(x, t, m) {
                            $(button).button(\'option\', \'disabled\', false);
                          },
                          data: { action: \'add_edit_employee\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                          success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_edit_employee'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                          });">Save</button>
                          </form>
                    </div>';
            break;
        case "edit_employee_timecards":
            $employeeid = $vars["employee"]["employeeid"];
            $fields = "";
            $fields .= empty($vars["employee"]["employeeid"]) ? "" : '<input type="hidden" name="employeeid" class="fields employeeid" value="'.$vars["employee"]["employeeid"].'" />';
            $fields .= empty($vars["recover_param"]) ? "" : '<input type="hidden" name="recover" class="fields recover" value="'.$vars["recover_param"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="employees" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $timecard_history = '';
            if($timecards = get_db_result("SELECT * FROM employee_timecard WHERE employeeid='$employeeid' ORDER BY fromdate DESC LIMIT 52")){
                    $timecard_history = '<tr>
                                            <td style="width:33%;">
                                                Week of
                                            </td>
                                            <td style="width:25%;">
                                                Wage
                                            </td>
                                            <td style="width:25%;">
                                                Hours
                                            </td>
                                            <td>
                                                Pay
                                            </td>
                                        </tr>';
                while($timecard = fetch_row($timecards)){
                    $hours = empty($timecard["hours_override"]) ? $timecard["hours"] : $timecard["hours_override"];
                    $timecard_history .= '<tr class="wage_'.$timecard["id"].'">
                                            <td>
                                                <input type="hidden" class="fields" name="id" id="id" value="'.$timecard["id"].'" />
                                                '.get_date('m/d/Y',$timecard["fromdate"]).'
                                            </td>
                                            <td>
                                                $'.$timecard["wage"].'/hr
                                            </td>
                                            <td>
                                                <input class="fields" name="hours" id="hours" style="width:75px;" type="text" value="'.$hours.'" onchange="var calc = $(this).val() * '.$timecard["wage"].'; $(\'#calculate_'.$timecard["id"].'\').html(calc.toFixed(2))" />
                                            </td>
                                            <td>
                                                $<span id="calculate_'.$timecard["id"].'">'.number_format($timecard["wage"] * $hours,2).'</span>
                                            </td>
                                        </tr>';
                }
            }

            $form = '<div id="edit_employee_timecards'.$identifier.'" title="Pay Stubs" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            <div style="text-align:center">
                                <button type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    timeout: 10000,
                                    error: function(x, t, m) {
                                    $(button).button(\'option\', \'disabled\', false);
                                    },
                                    data: { action: \'save_employee_timecard\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                    success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#edit_employee_timecards'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                                    });">Save
                                </button>
                            </div>
                            '.$fields.'
                            <table style="width:100%;font-size:1em;">
                                '.$timecard_history.'
                            </table>
                        </form>
                    </div>';

        break;
        case "edit_employee_wage_history":
            $employeeid = $vars["employee"]["employeeid"];
            $fields = "";
            $fields .= empty($vars["employee"]["employeeid"]) ? "" : '<input type="hidden" name="employeeid" class="fields employeeid" value="'.$vars["employee"]["employeeid"].'" />';
            $fields .= empty($vars["recover_param"]) ? "" : '<input type="hidden" name="recover" class="fields recover" value="'.$vars["recover_param"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="employees" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $salary_history = '';
            if($salary_entry = get_db_result("SELECT * FROM employee_wage WHERE employeeid='$employeeid' ORDER BY dategiven DESC")){
                while($salary = fetch_row($salary_entry)){
                    $salary_history .= '<tr class="wage_'.$salary["id"].'">
                                            <td style="width:50%;">
                                                <table style="width:100%;">
                                                    <tr>
                                                        <td>
                                                            <label for="date">Date</label>
                                                        </td>
                                                        <td>
                                                            <input type="hidden" class="fields" name="id" id="id" value="'.$salary["id"].'" />
                                                            <input class="fields" name="date" id="date" type="text" value="'.get_date('m/d/Y',$salary["dategiven"]).'" />
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td style="width:50%;">
                                                <table style="width:100%;">
                                                    <tr>
                                                        <td>
                                                            <label for="wage">Wage</label>
                                                        </td>
                                                        <td>
                                                            <input style="width:100px" class="fields" type="input" name="wage" id="wage" value="$'.$salary["wage"].'" />
                                                        </td>
                                                        <td>
                                                            <a href="javascript: void(0)" onclick="if(confirm(\'Are you sure you want to delete this?\')){
                                                            $.ajax({
                                                              type: \'POST\',
                                                              url: \'ajax/ajax.php\',
                                                              timeout: 10000,
                                                              data: { action: \'delete_wage_history\',id: '.$salary["id"].'},
                                                              success: function(data) { $(\'.wage_'.$salary["id"].'\').hide(); }
                                                              }); }">
                                                                '.get_icon('delete').'
                                                            </a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>';
                }
            }

            $form = '<div id="edit_employee_wage_history'.$identifier.'" title="Wage History" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table style="width:100%;">
                                '.$salary_history.'
                            </table>
                        <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          timeout: 10000,
                          error: function(x, t, m) {
                            $(button).button(\'option\', \'disabled\', false);
                          },
                          data: { action: \'save_employee_salary_history\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                          success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#edit_employee_wage_history'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                          });">Save</button>
                          </form>
                    </div>';
            break;
        case "add_edit_child":
            $first = empty($vars["child"]) ? "" : $vars["child"]["first"];
            $last = empty($vars["child"]) ? "" : $vars["child"]["last"];
            $birthdate = empty($vars["child"]) ? "" : date('m/d/Y',$vars["child"]["birthdate"] + get_offset());
            $male = empty($vars["child"]) ? "" : ($vars["child"]["sex"] == "Male" ? "selected" : "");
            $female = empty($vars["child"]) ? "" : ($vars["child"]["sex"] == "Female" ? "selected" : "");
            $grade0 = empty($vars["child"]) ? "" : ($vars["child"]["grade"] == "0" ? "selected" : "");
            $grade1 = empty($vars["child"]) ? "" : ($vars["child"]["grade"] == "1" ? "selected" : "");
            $grade2 = empty($vars["child"]) ? "" : ($vars["child"]["grade"] == "2" ? "selected" : "");
            $grade3 = empty($vars["child"]) ? "" : ($vars["child"]["grade"] == "3" ? "selected" : "");
            $grade4 = empty($vars["child"]) ? "" : ($vars["child"]["grade"] == "4" ? "selected" : "");
            $grade5 = empty($vars["child"]) ? "" : ($vars["child"]["grade"] == "5" ? "selected" : "");
            $grade6 = empty($vars["child"]) ? "" : ($vars["child"]["grade"] == "6" ? "selected" : "");
            $grade7 = empty($vars["child"]) ? "" : ($vars["child"]["grade"] == "7" ? "selected" : "");
            $grade8 = empty($vars["child"]) ? "" : ($vars["child"]["grade"] == "8" ? "selected" : "");
            $thispid = empty($vars["pid"]) ? get_pid() : $vars["pid"];

            $fields = "";
            $fields .= empty($vars["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />';
            $fields .= empty($vars["pid"]) ? "" : '<input type="hidden" name="pid" class="fields pid" value="'.$vars["pid"].'" />';
            $fields .= empty($vars["child"]["chid"]) ? "" : '<input type="hidden" name="chid" class="fields chid" value="'.$vars["child"]["chid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="accounts" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $title = empty($vars["chid"]) ? "Add Child" : "Edit Child";

            $form = '<div id="add_edit_child'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table>
                                <tr><td><label for="first">First</label></td><td><input class="fields autocapitalizefirst" type="input" name="first" id="first" value="'.$first.'" /></td></tr>
                                <tr><td><label for="last">Last</label></td><td><input class="fields autocapitalizefirst" type="input" name="last" id="last" value="'.$last.'" /></td></tr>
                                <tr><td><label for="sex">Sex</label></td><td><select class="fields" name="sex"><option value="Male" '.$male.'>Male</option><option value="Female" '.$female.'>Female</option></select></td></tr>
                                <tr><td><label for="birthdate">Birthdate</label></td><td><input class="fields" type="input" name="birthdate" id="birthdate" value="'.$birthdate.'" /></td></tr>
                                <tr><td><label for="grade">Grade</label></td><td><select class="fields" name="grade"><option value="7" '.$grade7.'>Infant</option><option value="8" '.$grade8.'>Pre-K</option><option value="0" '.$grade0.'>Kindergarten</option><option value="1" '.$grade1.'>1st</option><option value="2" '.$grade2.'>2nd</option><option value="3" '.$grade3.'>3rd</option><option value="4" '.$grade4.'>4th</option><option value="5" '.$grade5.'>5th</option><option value="6" '.$grade6.'>6th</option></select></td></tr>
                            </table>
                            <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                timeout: 10000,
                                error: function(x, t, m) {
                                $(button).button(\'option\', \'disabled\', false);
                                },
                                data: { action: \'add_edit_child\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_edit_child'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                                });">Save</button>
                        </form>
                    </div>';
            break;
        case "add_edit_contact":
            $first = empty($vars["contact"]) ? "" : $vars["contact"]["first"];
            $last = empty($vars["contact"]) ? "" : $vars["contact"]["last"];
            $relation = empty($vars["contact"]) ? "" : $vars["contact"]["relation"];
            $primary_address = empty($vars["contact"]) ? "" : ($vars["contact"]["primary_address"] == "1" ? "selected" : "");
            $home_address = empty($vars["contact"]) ? "" : $vars["contact"]["home_address"];
            $phone1 = empty($vars["contact"]) ? "" : $vars["contact"]["phone1"];
            $phone2 = empty($vars["contact"]) ? "" : $vars["contact"]["phone2"];
            $phone3 = empty($vars["contact"]) ? "" : $vars["contact"]["phone3"];
            $employer = empty($vars["contact"]) ? "" : $vars["contact"]["employer"];
            $employer_address = empty($vars["contact"]) ? "" : $vars["contact"]["employer_address"];
            $phone4 = empty($vars["contact"]) ? "" : $vars["contact"]["phone4"];
            $hours = empty($vars["contact"]) ? "" : $vars["contact"]["hours"];
            $emergency = empty($vars["contact"]) ? "" : ($vars["contact"]["emergency"] == "1" ? "selected" : "");
            $title = empty($vars["contact"]) ? "Add Contact" : "Edit Contact";

            $fields = "";
            $fields .= empty($vars["contact"]) ? (empty($vars["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />') : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["contact"]["aid"].'" />';
            $fields .= empty($vars["contact"]) ? "" : '<input type="hidden" name="cid" class="fields cid" value="'.$vars["contact"]["cid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="accounts" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $form = '<div id="add_edit_contact'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table>
                                <tr><td><label for="first">First</label></td><td><input class="fields autocapitalizefirst" type="input" name="first" id="first" value="'.$first.'" /></td></tr>
                                <tr><td><label for="last">Last</label></td><td><input class="fields autocapitalizefirst" type="input" name="last" id="last" value="'.$last.'" /></td></tr>
                                <tr><td><label for="relation">Relation</label></td><td><input class="fields autocapitalizefirst" type="input" name="relation" id="relation" value="'.$relation.'" /></td></tr>
                                <tr><td><label for="primary_address">Primary Address</label></td><td><select class="fields" name="primary_address"><option value="0">No</option><option value="1" '.$primary_address.'>Yes</option></select></td></tr>
                                <tr><td><label for="home_address">Home Address</label></td><td><textarea style="height:60px;" class="fields" name="home_address" id="home_address" >'.$home_address.'</textarea></td></tr>
                                <tr><td><label for="phone1">Phone 1</label></td><td><input class="fields" type="input" name="phone1" id="phone1" value="'.$phone1.'" /></td></tr>
                                <tr><td><label for="phone2">Phone 2</label></td><td><input class="fields" type="input" name="phone2" id="phone2" value="'.$phone2.'" /></td></tr>
                                <tr><td><label for="phone3">Phone 3</label></td><td><input class="fields" type="input" name="phone3" id="phone3" value="'.$phone3.'" /></td></tr>
                                <tr><td><label for="employer">Employer</label></td><td><input class="fields" type="input" name="employer" id="employer" value="'.$employer.'" /></td></tr>
                                <tr><td><label for="employer_address">Employer Address</label></td><td><input class="fields" type="input" name="employer_address" id="employer_address" value="'.$employer_address.'" /></td></tr>
                                <tr><td><label for="phone4">Work Phone</label></td><td><input class="fields" type="input" name="phone4" id="phone4" value="'.$phone4.'" /></td></tr>
                                <tr><td><label for="hours">Hours</label></td><td><input class="fields" type="input" name="hours" id="hours" value="'.$hours.'" /></td></tr>
                                <tr><td><label for="emergency">Emergency Contact</label></td><td><select class="fields" name="emergency"><option value="0">No</option><option value="1" '.$emergency.'>Yes</option></select></td></tr>
                            </table>
                        <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            timeout: 10000,
                            error: function(x, t, m) {
                                $(button).button(\'option\', \'disabled\', false);
                                },
                            data: { action: \'add_edit_contact\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                            success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_edit_contact'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                            });">Save Contact</button>
                        </form>
                    </div>';
            break;
        case "avatar":
            $fields = "";
            $fields .= empty($vars["chid"]) ? "" : '<input type="hidden" name="chid" class="fields chid" value="'.$vars["chid"].'" />';
            $fields .= empty($vars["did"]) ? "" : '<input type="hidden" name="did" class="fields did" value="'.$vars["did"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="get_admin_children_form" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $fields .= empty($vars["param1"]) ? "" : '<input type="hidden" name="param1" class="fields param1" value="'.$vars["param1"].'" />';
            $fields .= empty($vars["param1value"]) ? "" : '<input type="hidden" name="param1value" class="fields param1value" value="'.$vars["param1value"].'" />';

            $form = '<div id="avatar'.$identifier.'" title="Change Picture" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <input type="file" class="fields" name="afile" id="afile" accept="image/*"/ value="Upload File" />
                            <input type="hidden" name="tag" class="fields tag" value="avatar" />
                            <button class="bottom-right" type="button" onclick="uploader(\''.$identifier.'\',function(data) { if(data != \'false\'){ $(\'#avatar'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } },$(\'#'.$formname.$identifier.' .fields\').serializeArray())">Save</button>
                            <br /><br />Progress:<br />
                            <div class="progress ui-corner-all" style="display:inline-div;background:red;width:0px;text-align:center;color:grey;">0%</div>
                        </form>
                    </div>';
            break;
        case "attach_doc":
            $fields = "";
            $fields .= empty($vars["tab"]) ? '<input type="hidden" name="tab" class="fields tab" value="documents" />' : '<input type="hidden" name="tab" class="fields tab" value="'.$vars["tab"].'" />';
            $fields .= empty($vars["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />';
            $fields .= empty($vars["chid"]) ? "" : '<input type="hidden" name="chid" class="fields chid" value="'.$vars["chid"].'" />';
            $fields .= empty($vars["cid"]) ? "" : '<input type="hidden" name="cid" class="fields cid" value="'.$vars["cid"].'" />';
            $fields .= empty($vars["actid"]) ? "" : '<input type="hidden" name="actid" class="fields actid" value="'.$vars["actid"].'" />';
            $fields .= empty($vars["did"]) ? "" : '<input type="hidden" name="did" class="fields did" value="'.$vars["did"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="get_admin_children_form" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $fields .= empty($vars["param1"]) ? "" : '<input type="hidden" name="param1" class="fields param1" value="'.$vars["param1"].'" />';
            $fields .= empty($vars["param1value"]) ? "" : '<input type="hidden" name="param1value" class="fields param1value" value="'.$vars["param1value"].'" />';

            $display = empty($vars["display"]) ? "admin_display" : $vars["display"];
            $title = empty($vars["did"]) ? "Attach Document" : "Update Document";
            if(!empty($vars["did"])){
                $doc = get_db_row("SELECT * FROM documents WHERE did='".$vars["did"]."'");
            }
            $selected = empty($doc) ? false : $doc["tag"];
            $description = empty($doc) ? "" : $doc["description"];

            $form = '<div id="attach_doc'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <input class="fields" type="file" name="afile" id="afile" accept="image/*"/ value="Upload File" />
                            <table style="width:100%">
                                <tr><td><label for="tag">Tag</label></td><td><input type="text" name="tag" class="fields tag tags_editor" value="'.$selected.'" /><input name="tags_list" type="hidden" class="tags_list" value="';
                                if($tags = get_db_result("SELECT * FROM documents_tags WHERE tag != 'avatar' ORDER BY tag")){
                                    $i = 0;
                                    while($tag = fetch_row($tags)){
                                        $form .= $i == 0 ? $tag["title"] : ",".$tag["title"];
                                        $i++;
                                    }
                                }
                                $form .= '" /></td></tr>
                                <tr><td style="vertical-align: top;"><label for="description">Description</label></td><td><textarea class="fields" style="width:100%;height:80px;" name="description">'.$description.'</textarea></td></tr>
                            </table>
                            <button class="bottom-right" type="button" onclick="uploader(\'a'.$identifier.'\',function(data) { if(data != \'false\'){ $(\'#attach_doc'.$identifier.'\').dialog(\'close\'); $(\'#'.$display.'\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } },$(\'#'.$formname.$identifier.' .fields\').serializeArray())">Save</button>
                            <br /><br />Progress:<br />
                            <div class="progress ui-corner-all" style="display:inline-div;background:red;width:0px;text-align:center;color:grey;">0%</div>
                        </form>
                    </div>';
            break;
        case "attach_note":
            $fields = "";
            $fields .= empty($vars["tab"]) ? '<input type="hidden" name="tab" class="fields tab" value="notes" />' : '<input type="hidden" name="tab" class="fields tab" value="'.$vars["tab"].'" />';
            $fields .= empty($vars["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />';
            $fields .= empty($vars["chid"]) ? "" : '<input type="hidden" name="chid" class="fields chid" value="'.$vars["chid"].'" />';
            $fields .= empty($vars["cid"]) ? "" : '<input type="hidden" name="cid" class="fields cid" value="'.$vars["cid"].'" />';
            $fields .= empty($vars["actid"]) ? "" : '<input type="hidden" name="actid" class="fields actid" value="'.$vars["actid"].'" />';
            $fields .= empty($vars["nid"]) ? "" : '<input type="hidden" name="nid" class="fields nid" value="'.$vars["nid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="children" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $title = empty($vars["nid"]) ? "Attach Note" : "Edit Note";

            if(!empty($vars["nid"])){
                $note = get_db_row("SELECT * FROM notes WHERE nid='".$vars["nid"]."'");
            }

            $notify_array[0] = new stdClass(); $notify_array[1] = new stdClass();
            $notify_array[0]->value = "1";$notify_array[0]->display = "Yes";$notify_array[1]->value = "0";$notify_array[1]->display = "No";

            $selected = empty($note) ? false : $note["tag"];
            $notify = empty($note) ? "0" : $note["notify"];
            $persistent = $notify == "2" ? "1" : "0";
            $notify = $notify == "2" ? "1" : $notify;

            $note = empty($note) ? "" : $note["note"];

            $form = '<div id="attach_note'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table style="width:100%">
                                <tr><td><label for="tag">Tag</label></td><td><input type="text" name="tag" class="fields tag tags_editor" value="'.$selected.'" /><input name="tags_list" type="hidden" class="tags_list" value="';
                                if($tags = get_db_result("SELECT * FROM notes_tags ORDER BY tag")){
                                    $i = 0;
                                    while($tag = fetch_row($tags)){
                                        $form .= $i == 0 ? $tag["title"] : ",".$tag["title"];
                                        $i++;
                                    }
                                }
                                $form .= '" /></td></tr>
                                <tr><td><label for="notify">Notify Parent</label></td><td>'.make_select_from_object("notify",$notify_array,"value","display","fields",$notify).'</td></tr>
                                <tr><td><label for="persistent">Persistent</label></td><td>'.make_select_from_object("persistent",$notify_array,"value","display","fields",$persistent).'</td></tr>
                                <tr><td style="vertical-align: top;"><label for="note">Note</label></td><td><textarea class="fields" style="width:100%;height:160px;" name="note">'.$note.'</textarea></td></tr>
                            </table>
                            <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                timeout: 10000,
                                error: function(x, t, m) {
                                $(button).button(\'option\', \'disabled\', false);
                                },
                                data: { action: \'add_edit_note\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#attach_note'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                                });">Save</button>
                        </form>
                    </div>';
            break;
        case "bulletin":
            $fields = "";
            $fields .= empty($vars["pid"]) ? "" : '<input type="hidden" name="pid" class="fields pid" value="'.$vars["pid"].'" />';
            $fields .= empty($vars["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="program" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $title = empty($vars["aid"]) ? "Program Bulletin" : "Account Bulletin";

            if(!empty($vars["aid"])){
                $note = get_db_row("SELECT * FROM notes WHERE tag='bulletin' AND pid='0' AND aid='".$vars["aid"]."'");
            }else{
                $note = get_db_row("SELECT * FROM notes WHERE tag='bulletin' AND pid='".$vars["pid"]."' AND aid='0'");
            }

            $notify_array[0] = new stdClass(); $notify_array[1] = new stdClass();
            $notify_array[0]->value = "1";$notify_array[0]->display = "Yes";$notify_array[1]->value = "0";$notify_array[1]->display = "No";

            $notify = empty($note) ? "0" : $note["notify"];
            $notify = $notify == "2" ? "1" : $notify;

            $note = empty($note) ? "" : $note["note"];

            $form = '<div id="bulletin'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table style="width:100%">
                                <tr><td><label for="notify">Notify Parents</label></td><td>'.make_select_from_object("notify",$notify_array,"value","display","fields",$notify).'</td></tr>
                                <tr><td style="vertical-align: top;"><label for="note">Bulletin</label></td><td><textarea class="fields" style="width:100%;height:160px;" name="note">'.$note.'</textarea></td></tr>
                            </table>
                            <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                timeout: 10000,
                                error: function(x, t, m) {
                                $(button).button(\'option\', \'disabled\', false);
                                },
                                data: { action: \'add_edit_bulletin\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#bulletin'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                                });">Save</button>
                        </form>
                    </div>';
            break;
        case "update_activity":
            $fields = '<input type="hidden" name="tab" class="fields tab" value="activity" />';
            $fields .= empty($vars["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />';
            $fields .= empty($vars["chid"]) ? "" : '<input type="hidden" name="chid" class="fields chid" value="'.$vars["chid"].'" />';
            $fields .= empty($vars["cid"]) ? "" : '<input type="hidden" name="cid" class="fields cid" value="'.$vars["cid"].'" />';
            $fields .= empty($vars["actid"]) ? "" : '<input type="hidden" name="actid" class="fields actid" value="'.$vars["actid"].'" />';
            $fields .= empty($vars["nid"]) ? "" : '<input type="hidden" name="nid" class="fields nid" value="'.$vars["nid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="children" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $title = empty($vars["nid"]) ? "Add Activity" : "Edit Activity";

            if(!empty($vars["nid"])){
                $note = get_db_row("SELECT * FROM notes WHERE nid='".$vars["nid"]."'");
            }

            $fields .= empty($note["tag"]) ? "" : '<input type="hidden" name="tag" class="fields tag" value="'.$note["tag"].'" />';
            $note = empty($note) ? "" : $note["note"];

            $form = '<div id="update_activity'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table style="width:100%">
                                <tr><td style="vertical-align: top;"><label for="note">Note</label></td><td><textarea class="fields" style="width:100%;height:160px;" name="note">'.$note.'</textarea></td></tr>
                            </table>
                            <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                timeout: 10000,
                                error: function(x, t, m) {
                                $(button).button(\'option\', \'disabled\', false);
                                },
                                data: { action: \'add_edit_notes\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#update_activity'.$identifier.'\').dialog(\'close\');
                                            $.ajax({
                                            type: \'POST\',
                                            url: \'ajax/ajax.php\',
                                            data: { action: \'get_activity_list\',chid:\''.$vars["chid"].'\',month:\''.$vars["month"].'\',year:\''.$vars["year"].'\' },
                                            success: function(data) {
                                                    $(\'#subselect_div\').hide(\'fade\');
                                                    $(\'#subselect_div\').html(data);
                                                    $(\'#subselect_div\').show(\'fade\');
                                                    refresh_all();
                                                }
                                        });
                                    }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                                });">Save</button>
                        </form>
                    </div>';
            break;
        case "add_activity":
            $fields = '<input type="hidden" name="tab" class="fields tab" value="activity" />';
            $fields .= empty($vars["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />';
            $fields .= empty($vars["chid"]) ? "" : '<input type="hidden" name="chid" class="fields chid" value="'.$vars["chid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="children" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $fields .= '<input type="hidden" name="tag" class="fields tag" value="" />';

            $datetime = new DateTime($vars["year"] . "-" . $vars["month"] . "-" . $vars["day"]);
            $form = '<div id="add_activity'.$identifier.'" title="Add Activity" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table style="width:100%">
                                <tr><td style="vertical-align: top;"><label for="note">Date/Time</label></td><td>
                                <input type="datetime-local" class="fields" id="timelog" name="timelog" value="'.$datetime->format('Y-m-d\T08:00:00').'" /></td></tr>
                            </table>
                            <button class="bottom-left" type="button" onclick="$(\'input[name=tag\').val(\'in\'); var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                timeout: 10000,
                                error: function(x, t, m) {
                                $(button).button(\'option\', \'disabled\', false);
                                },
                                data: { action: \'add_activity\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_activity'.$identifier.'\').dialog(\'close\');
                                            $.ajax({
                                            type: \'POST\',
                                            url: \'ajax/ajax.php\',
                                            data: { action: \'get_activity_list\',chid:\''.$vars["chid"].'\',month:\''.$vars["month"].'\',year:\''.$vars["year"].'\' },
                                            success: function(data) {
                                                    $(\'#subselect_div\').hide(\'fade\');
                                                    $(\'#subselect_div\').html(data);
                                                    $(\'#subselect_div\').show(\'fade\');
                                                    refresh_all();
                                                }
                                        });
                                    }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                                });">Check In</button>
                                <button class="bottom-right" type="button" onclick="$(\'input[name=tag\').val(\'out\'); var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                timeout: 10000,
                                error: function(x, t, m) {
                                    $(button).button(\'option\', \'disabled\', false);
                                },
                                data: { action: \'add_activity\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_activity'.$identifier.'\').dialog(\'close\');
                                                $.ajax({
                                                type: \'POST\',
                                                url: \'ajax/ajax.php\',
                                                data: { action: \'get_activity_list\',chid:\''.$vars["chid"].'\',month:\''.$vars["month"].'\',year:\''.$vars["year"].'\' },
                                                success: function(data) {
                                                        $(\'#subselect_div\').hide(\'fade\');
                                                        $(\'#subselect_div\').html(data);
                                                        $(\'#subselect_div\').show(\'fade\');
                                                        refresh_all();
                                                    }
                                            });
                                        }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                                });">Check Out</button>
                        </form>
                    </div>';
            break;
        case "update_employee_activity":
            $fields = '<input type="hidden" name="tab" class="fields tab" value="activity" />';
            $fields .= empty($vars["employeeid"]) ? "" : '<input type="hidden" name="employeeid" class="fields employeeid" value="'.$vars["employeeid"].'" />';
            $fields .= empty($vars["actid"]) ? "" : '<input type="hidden" name="actid" class="fields actid" value="'.$vars["actid"].'" />';
            $fields .= empty($vars["nid"]) ? "" : '<input type="hidden" name="nid" class="fields nid" value="'.$vars["nid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="employee" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $title = empty($vars["nid"]) ? "Add Activity" : "Edit Activity";

            if(!empty($vars["actid"])){
                $activity = get_db_row("SELECT * FROM employee_activity WHERE actid='".$vars["actid"]."'");
            }

            $fields .= empty($note["tag"]) ? "" : '<input type="hidden" name="tag" class="fields tag" value="'.$note["tag"].'" />';
            $note = empty($note) ? "" : $note["note"];

            $form = '<div id="update_employee_activity'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table style="width:100%">
                                <tr><td style="vertical-align: top;"><input class="fields" type="hidden" id="oldtime" name="oldtime" value="'.$activity["timelog"].'" /><label for="newtime">Time</label></td><td><textarea class="fields" style="width:100%;height:160px;" name="newtime">'.get_date("g:i a",$activity["timelog"],$CFG->timezone).'</textarea></td></tr>
                            </table>
                            <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                timeout: 10000,
                                error: function(x, t, m) {
                                $(button).button(\'option\', \'disabled\', false);
                                },
                                data: { action: \'add_edit_employee_activity\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#update_employee_activity'.$identifier.'\').dialog(\'close\');
                                            $.ajax({
                                            type: \'POST\',
                                            url: \'ajax/ajax.php\',
                                            data: { action: \'get_activity_list\',employeeid:\''.$vars["employeeid"].'\',month:\''.$vars["month"].'\',year:\''.$vars["year"].'\' },
                                            success: function(data) {
                                                    $(\'#subselect_div\').hide(\'fade\');
                                                    $(\'#subselect_div\').html(data);
                                                    $(\'#subselect_div\').show(\'fade\');
                                                    refresh_all();
                                                }
                                        });
                                    }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                                });">Save</button>
                        </form>
                    </div>';
            break;
        case "add_update_employee_activity":
            $fields = '<input type="hidden" name="tab" class="fields tab" value="activity" />';
            $fields .= empty($vars["employeeid"]) ? "" : '<input type="hidden" name="employeeid" class="fields employeeid" value="'.$vars["employeeid"].'" />';
            $fields .= empty($vars["actid"]) ? "" : '<input type="hidden" name="actid" class="fields actid" value="'.$vars["actid"].'" />';
            $fields .= empty($vars["nid"]) ? "" : '<input type="hidden" name="nid" class="fields nid" value="'.$vars["nid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="employee" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $title = empty($vars["nid"]) ? "Add Activity" : "Edit Activity";
            $date = strtotime($vars["month"] . "/" . $vars["day"] . "/" . $vars["year"]);
            $fields .= '<input type="hidden" name="date" class="fields" value="' . $date . '" />';
            $value = "00:00";
            $tagselector = "";
            if (!empty($vars["actid"])) {
                $activity = get_db_row("SELECT * FROM employee_activity WHERE actid='".$vars["actid"]."'");
                $value = date("H:i", $activity["timelog"] + get_offset());
                if ($activity["tag"] == "in") {
                    $endofday = strtotime("+1 day", strtotime(date("j F Y" , $activity["timelog"])));
                    $min = "00:01";
                    if ($next = get_db_field("timelog", "employee_activity", "tag='out' AND employeeid='" . $activity["employeeid"] . "' AND timelog > '" . $activity["timelog"] . "' AND timelog < '" . $endofday . "'")) {
                        $max = get_date("H:i", $next, $CFG->timezone);
                    } else {
                        $max = "23:59";
                    }
                } else { // tag is "out"
                    $startofday = strtotime(date("j F Y" , $activity["timelog"]));
                    $max = "23:59";
                    if ($prev = get_db_field("timelog", "employee_activity", "tag='in' AND employeeid='" . $activity["employeeid"] . "' AND timelog < '" . $activity["timelog"] . "' AND timelog > '" . $startofday . "'")) {
                        $min = get_date("H:i", $prev, $CFG->timezone);
                    } else {
                        $min = "00:01";
                    }
                }
            } else { // Adding new employee sign in.
                $min = "00:01";
                $max = "23:59";
                // tag type select.
                $tagselector = '<table style="width:100%">
                                    <tr>
                                        <td style="vertical-align: top;">
                                            <label for="tag">Type</label>
                                        </td>
                                        <td>
                                            <select class="fields" name="tag">
                                                <option value="in">In</option>
                                                <option value="out">Out</option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>';
            }

            
            $fields .= empty($vars["actid"]) ? $tagselector : '<input type="hidden" name="tag" class="fields tag" value="'.$activity["tag"].'" />';
            
            $form = '<div id="'.$formname.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table style="width:100%">
                                <tr>
                                    <td style="vertical-align: top;">
                                        <label for="newtime">Time</label>
                                    </td>
                                    <td>
                                        <input class="fields" name="newtime" type="time" min="' . $min . '" max="' . $max . '" value="' . $value . '" required>
                                    </td>
                                </tr>
                            </table>
                            <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                timeout: 10000,
                                error: function(x, t, m) {
                                $(button).button(\'option\', \'disabled\', false);
                                },
                                data: { action: \'add_edit_employee_activity\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_update_employee_activity'.$identifier.'\').dialog(\'close\');
                                            $.ajax({
                                            type: \'POST\',
                                            url: \'ajax/ajax.php\',
                                            data: { action: \'get_activity_list\',employeeid:\''.$vars["employeeid"].'\',day:\''.$vars["day"].'\',month:\''.$vars["month"].'\',year:\''.$vars["year"].'\' },
                                            success: function(data) {
                                                    $(\'#subselect_div\').hide(\'fade\');
                                                    $(\'#subselect_div\').html(data);
                                                    $(\'#subselect_div\').show(\'fade\');
                                                    refresh_all();
                                                }
                                        });
                                    }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                                });">Save</button>
                        </form>
                    </div>';
            break;
        case "add_edit_enrollment":
            $title = empty($vars["pid"]) ? "Enroll Child" : "Edit Enrollment";

            $M = $T = $W = $Th = $F = $exempt = "";
            if(!empty($vars["chid"])){
                $enrollment = get_db_row("SELECT * FROM enrollments WHERE chid='".$vars["chid"]."' AND pid='".$vars["pid"]."'");
                $exempt = $enrollment["exempt"] == "1" ? "selected" : "";
                $days_attending = $enrollment["days_attending"];
                $days_attending = explode(",",$days_attending); $days_possible = array("M","T","W","Th","F");
                foreach($days_possible as $day){
                    $$day = array_search($day,$days_attending) !== false ? "checked" : "";
                }
            }

            $fields = "";
            $fields .= empty($vars["eid"]) ? "" : '<input type="hidden" name="eid" class="fields eid" value="'.$vars["eid"].'" />';
            $fields .= empty($vars["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />';
            $fields .= empty($vars["chid"]) ? "" : '<input type="hidden" name="chid" class="fields chid" value="'.$vars["chid"].'" />';
            $fields .= empty($vars["pid"]) ? "" : '<input type="hidden" name="pid" class="fields pid" value="'.$vars["pid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="accounts" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';
            $refresh = empty($vars["refresh"]) ? '$(\'#admin_display\').html(data); refresh_all();' : '$.ajax({
                        type: \'POST\',
                        url: \'ajax/ajax.php\',
                        data: { action: \'get_admin_children_form\', chid: \''.$vars["chid"].'\' },
                        success: function(data) {
                            $(\'#admin_display\').html(data); refresh_all();
                        }
                    });';
            $form = '<div id="add_edit_enrollment'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table style="width:100%;">
                                <tr><td><label for="days_attending">Days Attending</label></td><td>M:<input class="fields" type="checkbox" name="M" value="M" '.$M.'/> T:<input class="fields" type="checkbox" name="T" value="T" '.$T.'/> W:<input class="fields" type="checkbox" name="W" value="W" '.$W.'/> Th:<input class="fields" type="checkbox" name="Th" value="Th" '.$Th.'/> F:<input class="fields" type="checkbox" name="F" value="F" '.$F.'/></td></tr>
                                <tr><td><label for="exempt">Pay Exempt</label></td><td><select class="fields" name="exempt"><option value="0">No</option><option value="1" '.$exempt.'>Yes</option></select></td></tr>
                            </table>
                            <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true);
                            $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            timeout: 10000,
                            error: function(x, t, m) {
                                $(button).button(\'option\', \'disabled\', false);
                            },
                            data: { action: \'toggle_enrollment\',pid:\''.$vars["pid"].'\',chid: \''.$vars["chid"].'\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray() },
                            success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_edit_enrollment'.$identifier.'\').dialog(\'destroy\').remove(); '.$refresh.'  }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                            });
                            ">Save</button>
                        </form>
                    </div>';
            break;
        case "event_editor":
            $program = $vars["program"];
            $title = "Edit Program Events";
            $events_list = "";
            if($events = get_db_result("SELECT * FROM events e JOIN events_tags t ON t.tag = e.tag WHERE pid='".$program["pid"]."' OR pid='0' ORDER BY sort")){
                $events_list .= '<ul class="ui-corner-all selectable" style="width:100%">';
                while($event = fetch_row($events)){
                    $events_list .= '<li class="ui-corner-all" style="font-size: 17px;padding-left: 45px;" onclick="$.ajax({
                          type: \'POST\',
                          url: \'ajax/ajax.php\',
                          timeout: 10000,
                          data: { action: \'view_required_notes_form\',pid:\''.$program["pid"].'\',evid: \''.$event["evid"].'\' },
                          success: function(data) {
                            $(\'#required_notes_div'.$identifier.'\').html(data);
                            $(\'#sortable\').sortable({
                                update : function () {
                                    var serial = $(\'#sortable\').sortable(\'toArray\');
                                    $.ajax({
                                        type: \'POST\',
                                        url: \'ajax/ajax.php\',
                                        data: { action: \'required_notes_sort\',serial: serial,pid:\''.$program["pid"].'\',evid: \''.$event["evid"].'\' },
                                    });
                                }
                            }); $(\'#sortable\').disableSelection(); }
                        });">'.$event["title"].'</li>';
                }
                $events_list .= '</ul>';
            }

            $fields = "";
            $fields .= empty($vars["pid"]) ? "" : '<input type="hidden" name="pid" class="fields pid" value="'.$vars["pid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="programs" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $form = '<div id="event_editor'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table class="fill_height" style="width:100%;">
                                <tr>
                                    <td style="vertical-align:top;width:30%"><strong>Select Event</strong><br />'.$events_list.'</td>
                                    <td style="vertical-align:top;width:70%"><div class="fill_height" id="required_notes_div'.$identifier.'"></div></td>
                                </tr>
                            </table>
                            <button class="bottom-right" type="button" onclick="$(\'#event_editor'.$identifier.'\').dialog(\'close\');">Close</button>
                        </form>
                    </div>';
            break;
        case "add_edit_tag":
            $tagrow = empty($vars["tagrow"]) ? "" : $vars["tagrow"];
            $tagtitle = empty($vars["tagrow"]) ? "Title" : $tagrow["title"];
            $tag = empty($vars["tagrow"]) ? "" : $tagrow["tag"];
            $color = empty($vars["tagrow"]) ? "silver" : $tagrow["color"];
            $textcolor = empty($vars["tagrow"]) ? "black" : $tagrow["textcolor"];
            $tagtype = empty($vars["tagtype"]) ? "" : $vars["tagtype"];
            $title = empty($vars["tagrow"]) ? "Add Tag" : "Edit Tag";
            $lock = !empty($tag) && get_db_row("SELECT timelog FROM $tagtype WHERE tag='".$tag."'") ? 'disabled="disabled"' : "";

            $fields = "";
            $fields .= empty($tag) ? "" : '<input type="hidden" name="update" class="fields update" value="'.$tag.'" />';
            $fields .= empty($vars["tagtype"]) ? "" : '<input type="hidden" name="tagtype" class="fields tagtype" value="'.$tagtype.'" />';
            $fields .= empty($vars["recover_param"]) ? "" : '<input type="hidden" name="recover" class="fields recover" value="'.$vars["recover_param"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="accounts" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $form = '<div id="add_edit_tag'.$identifier.'" title="'.$title.'" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table>
                                <tr><td><label for="title">Title</label></td><td><input onkeyup="if(this.value.length > 0){ $(\'#tag_template'.$identifier.'\').html(ucwords(this.value)); }else if($(\'#tag\',\'form[name=add_edit_tag_form'.$identifier.']\').val().length > 0){ $(\'#tag_template'.$identifier.'\').html(ucwords($(\'#tag\',\'form[name=add_edit_tag_form'.$identifier.']\').val().replace(/_/gi,\' \'),true)); }else{ $(\'#tag_template'.$identifier.'\').html(\'Preview\'); }" class="fields" type="input" name="title" id="title" value="'.$tagtitle.'" /></td></tr>
                                <tr><td><label for="tag">Tag</label></td><td><input onkeyup="if($(\'#title\',\'form[name=add_edit_tag_form'.$identifier.']\').val().length > 0){ }else if(this.value.length > 0){ $(\'#tag_template'.$identifier.'\').html(ucwords(this.value.replace(/_/gi,\' \'),true)); }else{ $(\'#tag_template'.$identifier.'\').html(\'Preview\'); }" class="fields" type="input" name="tag" id="tag" value="'.$tag.'" '.$lock.' /></td></tr>
                                <tr><td><label for="color">Color</label></td><td><input onkeyup="$(\'#tag_template'.$identifier.'\').css(\'background-color\',$(this).val())" class="fields" type="input" name="color" id="color'.$identifier.'_field" value="'.gethexcolor($color).'" /><input class="colorpicker fields" type="input" id="color'.$identifier.'" value="'.gethexcolor($color).'" /></td></tr>
                                <tr><td><label for="textcolor">Textcolor</label></td><td><input onkeyup="$(\'#tag_template'.$identifier.'\').css(\'color\',$(this).val())" class="fields" type="input" name="textcolor" id="textcolor'.$identifier.'_field" value="'.gethexcolor($textcolor).'" /><input class="colorpicker fields" type="input" id="textcolor'.$identifier.'" value="'.gethexcolor($textcolor).'" /></td></tr>
                            </table><br />
                            <div style="text-align:center">Preview:<br /><span id="tag_template'.$identifier.'" class="tag ui-corner-all" style="color:'.$textcolor.';background-color:'.$color.'">'.$tagtitle.'</span></div>
                            <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                timeout: 10000,
                                error: function(x, t, m) {
                                    $(button).button(\'option\', \'disabled\', false);
                                },
                                data: { action: \'add_edit_tag\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#add_edit_tag'.$identifier.'\').dialog(\'close\'); $(\'#admin_display\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                            });">Save Tag</button>
                        </form>
                    </div>';
            break;
        case "create_invoices":
            $startweek = empty($vars["startweek"]) ? "" : $vars["startweek"];
            $refresh[0] = new stdClass(); $refresh[1] = new stdClass();
            $refresh[0]->value = "1";
            $refresh[0]->display = "Yes";
            $refresh[1]->value = "0";
            $refresh[1]->display = "No";
            $fields = "";
            $fields .= '<input type="hidden" name="pid" class="fields pid" value="'.$activepid.'" />';
            $fields .= empty($vars["aid"]) ? "" : '<input type="hidden" name="aid" class="fields aid" value="'.$vars["aid"].'" />';
            $fields .= empty($vars["callback"]) ? '<input type="hidden" name="callback" class="fields callback" value="billing" />' : '<input type="hidden" name="callback" class="fields callback" value="'.$vars["callback"].'" />';

            $sql = empty($vars["aid"]) ? "" : "AND aid='".$vars["aid"]."'";
            $weeks_sql = "SELECT (DATE_FORMAT(CONVERT_TZ(FROM_UNIXTIME(fromdate-".get_offset()."), @@session.time_zone,'UTC'),'%M %D %Y') ) as display, fromdate FROM billing WHERE pid='$activepid' $sql GROUP BY fromdate ORDER BY fromdate DESC";
            $form = '<div id="create_invoices'.$identifier.'" title="Recreate Invoices" style="display:none;">
                        <form name="'.$formname.'_form'.$identifier.'">
                            '.$fields.'
                            <table>
                                <tr><td><label for="startweek">Start processing from:</label></td><td>'.make_select("startweek",get_db_result($weeks_sql),"fromdate","display","fields",$startweek,"",true,"1","","Beginning").'</td></tr>
                                <tr><td><label for="refresh">Remake Existing Invoices</label></td><td>'.make_select_from_object("refresh",$refresh,"value","display","fields", "1").'</td></tr>
                                <tr><td><label for="enrollment">Remember Past Enrollment Settings</label></td><td>'.make_select_from_object("enrollment",$refresh,"value","display","fields","0").'</td></tr>
                            </table><br />
                            <button class="bottom-right" type="button" onclick="var button = $(this); $(this).button(\'option\', \'disabled\', true); $.ajax({
                                type: \'POST\',
                                url: \'ajax/ajax.php\',
                                timeout: 10000,
                                error: function(x, t, m) {
                                    $(button).button(\'option\', \'disabled\', false);
                                },
                                data: { action: \'ajax_refresh_all_invoices\',values: $(\'#'.$formname.$identifier.' .fields\').serializeArray()},
                                success: function(data) { $(button).button(\'option\', \'disabled\', false); if(data != \'false\'){ $(\'#create_invoices'.$identifier.'\').dialog(\'close\'); $(\'#info_div\').html(data); refresh_all(); }else{ $(\'.ui-dialog\').effect(\'shake\', { times:3 }, 150); } }
                            });">Recreate Invoices</button>
                        </form>
                    </div>';
            break;
    }
    return $form;
}
?>