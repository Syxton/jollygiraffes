<?php
/***************************************************************************
* timelib.php - Time Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 10/24/2019
* Revision: 0.3.4
***************************************************************************/

if(!isset($LIBHEADER)) include('header.php');
$TIMELIB = true;

function get_timestamp($timezone = "UTC"){
global $CFG;
    date_default_timezone_set($timezone);
    $time = time();
    date_default_timezone_set("UTC");
    return $time;
}

function seconds_from_midnight($time){
global $CFG;
    $from = mktime(0,0,0);
    $to = strtotime($time);
    return $to - $from;
}

function display_time($time){
    return ($time + get_offset());
}

function get_date($string,$timestamp,$timezone = "UTC"){
global $CFG;
    date_default_timezone_set($timezone);
    $time = date($string,$timestamp);
    date_default_timezone_set("UTC");
    return $time;
}

function get_offset($timezone = false){
global $CFG;
    $timezone = empty($timezone) ? $CFG->timezone : $timezone;
	// Create two timezone objects, one for UTC and one for local timezone
	$LOCAL = new DateTimeZone($timezone);
	$timeLOCAL = new DateTime("now", $LOCAL);
	$timeOffset = timezone_offset_get($LOCAL,$timeLOCAL);
	return $timeOffset;
}

function get_today($timezone = "UTC"){
global $CFG;
    $dateinmytimezone = new DateTime("now", new DateTimeZone($CFG->timezone)); //first argument "must" be a string
    $UTCdate = new DateTime($dateinmytimezone->format("m/d/Y"), new DateTimeZone("UTC"));
    return $UTCdate->getTimestamp();
}

function ago($timestamp){
global $CFG;
    if(!$timestamp){ return "Never"; };
	$minutes = ""; $seconds = "";
	$difference = (get_timestamp()) - $timestamp;
	if($difference == 0){ return "now"; }
	$ago = $difference >= 0 ? "ago" : "";
	$difference = abs($difference);

	if($difference > 31449600){
        $years = floor($difference / 31449600) > 1 ? floor($difference/31449600) . " years" : floor($difference/31449600) . " year";
        $weeks = "";
        $difference = $difference - (floor($difference / 31449600) * 31449600);
	}
	if($difference == 31449600){
		$years = "1 year";
		$difference = 0;
	}
	if($difference > 604800){
        $weeks = floor($difference / 604800) > 1 ? floor($difference/604800) . " weeks" : floor($difference/604800) . " week";
        $days = "";
        $difference = $difference - (floor($difference / 604800) * 604800);
	}
	if($difference == 604800){
		$weeks = "1 week";
		$difference = 0;
	}
	if($difference > 86400){
        $days = floor($difference / 86400) > 1 ? floor($difference/86400) . " days" : floor($difference/86400) . " day";
        $hours = "";
        $difference = $difference - (floor($difference / 86400) * 86400);
	}
	if($difference == 86400){
		$days = "1 day";
		$difference = 0;
	}
	if($difference > 3600){
        $hours = floor($difference / 3600) > 1 ? floor($difference/3600) . " hrs" : floor($difference/3600) . " hr";
        $minutes = "";
        $difference = $difference - (floor($difference / 3600) * 3600);
	}
	if($difference == 3600){
		$hours = "1 hour";
		$difference = 0;
	}
	if($difference > 60){
        $minutes = floor($difference / 60) > 1 ? floor($difference/60) . " mins" : floor($difference/60) . " min";
        $seconds = "";
        $difference = $difference - (floor($difference / 60) * 60);
	}
	if ($difference == 60){
		$minutes = "1 min";
	}else{ $seconds = floor($difference) > 1 ? $difference . " secs" : $difference . " sec"; }

	if($difference == 0){ $seconds = ""; }

	if(isset($years)){ return "$years $weeks $ago";
	}elseif(isset($weeks)){ return "$weeks $days $ago";
	}elseif(isset($days)){ return "$days $hours $ago";
	}elseif(isset($hours)){ return "$hours $minutes $ago";
	}else{ return "$minutes $seconds $ago"; }
}

function get_date_graphic($timestamp = false, $newday = true, $alter = false){
global $CFG;
	$alterfont = $alter ? "font-size:.75em;" : "";
	$timestamp = !empty($timestamp) ? $timestamp : display_time(get_timestamp());
	if($newday){
        return '
		<table style="vertical-align:top;width:78px;height:78px;max-height:89px;">
            <tr>
				<td style="'.$alterfont.'">
				<div style="text-align:center; font-size:.85em;line-height:20px;"><b>'.date('F',$timestamp).'</b></div>
				<div style="text-align:center; font-size:2.2em;line-height:30px;"><b>'.date('jS',$timestamp).'</b></div>
				<div style="text-align:right; font-size:.85em;">'.date('Y',$timestamp).' &nbsp;</div>
				</td>
		 </tr>
		</table>';
	}else{
        return '
		<table style="vertical-align:top;width:78px;height:1px;max-height:89px;">
            <tr>
				<td style="'.$alterfont.'">
				</td>
		 </tr>
		</table>';
	}
}

function convert_time($time){
	date_default_timezone_set('UTC');
	$time = explode(":",$time);
    $time[1] = empty($time[1]) ? "00" : $time[1];
	if($time[0] > 12){
		return ($time[0]-12) . ":" . $time[1] . "pm";
	}else{
		if($time[0] == "00"){ return "12:" . $time[1] . "am"; }
		return $time[0] . ":" . $time[1] . "am";
	}
}

/* draws a calendar */
function draw_calendar($month,$year,$vars=false){

  /* draw table */
  $calendar = '<table cellpadding="0" cellspacing="0" class="calendar fill_width">';

  /* table headings */
  $headings = array('Monday','Tuesday','Wednesday','Thursday','Friday');
  $calendar.= '<tr class="calendar-row">
                    <td class="calendar-day-head" style="max-width:50px;"><span class="abb_full">Sunday</span><span class="abb_half">Sun</span><span class="abb_min">S</span></td>
                    <td class="calendar-day-head"><span class="abb_full">Monday</span><span class="abb_half">Mon</span><span class="abb_min">M</span></td>
                    <td class="calendar-day-head"><span class="abb_full">Tuesday</span><span class="abb_half">Tue</span><span class="abb_min">T</span></td>
                    <td class="calendar-day-head"><span class="abb_full">Wednesday</span><span class="abb_half">Wed</span><span class="abb_min">W</span></td>
                    <td class="calendar-day-head"><span class="abb_full">Thursday</span><span class="abb_half">Thur</span><span class="abb_min">Th</span></td>
                    <td class="calendar-day-head"><span class="abb_full">Friday</span><span class="abb_half">Fri</span><span class="abb_min">F</span></td>
                    <td class="calendar-day-head" style="max-width:50px;"><span class="abb_full">Saturday</span><span class="abb_half">Sat</span><span class="abb_min">S</span></td></tr>';
  /* days and weeks vars now ... */
  $running_day = date('w',mktime(0,0,0,$month,1,$year));
  $days_in_month = date('t',mktime(0,0,0,$month,1,$year));
  $days_in_this_week = 1;
  $day_counter = 0;
  $dates_array = array();

  /* row for week one */
  $calendar.= '<tr class="calendar-row">';

  /* print "blank" days until the first of the current week */
  for($x = 0; $x < $running_day; $x++):
    $calendar.= '<td class="calendar-day-np">&nbsp;</td>';
    $days_in_this_week++;
  endfor;

  /* keep going with days.... */
  for($list_day = 1; $list_day <= $days_in_month; $list_day++):
      /** QUERY THE DATABASE FOR AN ENTRY FOR THIS DAY !!  IF MATCHES FOUND, PRINT THEM !! **/
      $content = $attending = $dayadd = "";
    if (!empty($vars["type"])) {
        $pid = get_pid();
        $activeprogramsql = " n.pid='$pid' AND ";
        $fromtime = strtotime("$month/$list_day/$year") - get_offset();
        $totime = $fromtime + 86400;
        switch ($vars["type"]) {
            case "activity":
                $id = "nid";
                if(!empty($vars["aid"])){
                    $type = "aid";
                    $SQL = "SELECT * FROM notes n JOIN activity a ON a.actid=n.actid JOIN events_tags t ON a.tag = t.tag WHERE $activeprogramsql n.tag NOT IN (SELECT tag FROM notes_required) AND n.aid='".$vars["aid"]."' AND n.timelog >= $fromtime AND n.timelog <= $totime ORDER BY n.timelog";
                }elseif(!empty($vars["chid"])){
                    $type = "chid";
                    $SQL = "SELECT * FROM notes n JOIN activity a ON a.actid=n.actid JOIN events_tags t ON a.tag = t.tag WHERE $activeprogramsql n.tag NOT IN (SELECT tag FROM notes_required) AND n.chid='".$vars["chid"]."' AND n.timelog >= $fromtime AND n.timelog <= $totime ORDER BY n.timelog";
                }elseif(!empty($vars["cid"])){
                    $type = "cid";
                    $SQL = "SELECT * FROM notes n JOIN activity a ON a.actid=n.actid JOIN events_tags t ON a.tag = t.tag WHERE $activeprogramsql n.tag NOT IN (SELECT tag FROM notes_required) AND n.cid='".$vars["cid"]."' AND n.timelog >= $fromtime AND n.timelog <= $totime ORDER BY n.timelog";
                }elseif(!empty($vars["actid"])){
                    $type = "actid";
                    $SQL = "SELECT * FROM notes n JOIN activity a ON a.actid=n.actid JOIN events_tags t ON a.tag = t.tag WHERE $activeprogramsql n.tag NOT IN (SELECT tag FROM notes_required) AND n.actid='".$vars["actid"]."' AND n.timelog >= $fromtime AND n.timelog <= $totime ORDER BY n.timelog";
                }elseif(!empty($vars["employeeid"])){
                    $type = "employeeid";
                    $SQL = "SELECT * FROM notes n JOIN employee_activity a ON a.actid=n.actid JOIN events_tags t ON a.tag = t.tag WHERE n.tag NOT IN (SELECT tag FROM notes_required) AND n.employeeid='".$vars["employeeid"]."' AND n.timelog >= $fromtime AND n.timelog <= $totime ORDER BY n.timelog";
                }

            break;
            case "notes":
                if(!empty($vars["aid"])){
                    $type = "aid";
                    $SQL = "SELECT * FROM notes n JOIN notes_tags t ON a.tag = t.tag WHERE $activeprogramsql n.aid='$aid'";
                }elseif(!empty($vars["chid"])){
                    $type = "chid";
                    $SQL = "SELECT * FROM notes n JOIN notes_tags t ON a.tag = t.tag WHERE $activeprogramsql n.chid='$chid'";
                }elseif(!empty($vars["cid"])){
                    $type = "cid";
                    $SQL = "SELECT * FROM notes n JOIN notes_tags t ON a.tag = t.tag WHERE $activeprogramsql n.cid='$cid'";
                }elseif(!empty($vars["actid"])){
                    $type = "actid";
                    $SQL = "SELECT * FROM notes n JOIN notes_tags t ON a.tag = t.tag WHERE $activeprogramsql n.actid='$actid'";
                }
            break;
        }

        if (!empty($vars["chid"]) ) {
            if($child = get_db_row("SELECT * FROM enrollments WHERE pid='$pid' AND chid='".$vars["chid"]."'")){
                $days_attending = explode(",",$child["days_attending"]);
                $days_array = array("1" => "M", "2" => "T", "3" => "W", "4" => "Th", "5" => "F", "6" => "S", "7" => "Su");
                $attending = in_array($days_array[date("N",strtotime("$month/$list_day/$year"))],$days_attending) ? "attending" : "";
            }
        }

        if ($result = get_db_result($SQL)) {
            $content .= '<div style="height:20px;"></div>';
            while ($row = fetch_row($result)) {
                $aid = !empty($row["aid"]) ? $row["aid"] : "";
                $chid = !empty($row["chid"]) ? $row["chid"] : "";
                $actid = !empty($row["actid"]) ? $row["actid"] : "";
                $cid = !empty($row["cid"]) ? $row["cid"] : "";
                $nid = !empty($row["nid"]) ? $row["nid"] : "";
                $attending = $attending == "" ? "unexpected" : $attending;

               $content .= '
                    <div class="tag ui-corner-all" style="font-size:9px;text-align:center;display:block;color:'.$row["textcolor"].';background-color:'.$row["color"].'">'.$row["title"].' '.date('g:i a',display_time($row["timelog"])).'</div>
                    <div class="" style="margin-right:auto;margin-left:auto;text-align:center">
                        <a style="padding:2px;" class="nyroModal inline-button ui-corner-all" href="ajax/reports.php?report=activity&type='.$type.'&id='.$row[$type].'&actid='.$actid.'">
                            '.get_icon('magnifier').'
                        </a>';

                if ($type == "chid") {
                    $identifier = $vars["type"]."_".$row[$id];
                    $content .= get_form($vars["form"],array("month" => $month, "year" => $year, "aid" => $row["aid"],"chid" => $chid,"actid" => $actid,"cid" => $cid,"nid" => $nid,"callback" => "children"),$identifier);

                    $delete_action = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to delete this \'+$(\'a#a-'.$actid.'\').attr(\'data\')+\' activity?\', \'Yes\', \'No\',
                    function(){
                        $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'delete_'.$vars["type"].'\',aid:\''.$aid.'\',chid:\''.$chid.'\',actid:\''.$actid.'\',cid:\''.$cid.'\',nid:\''.$nid.'\',tab:\''.$vars["type"].'\' },
                            success: function(data) {
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_activity_list\',aid:\''.$aid.'\',chid:\''.$chid.'\',actid:\''.$actid.'\',cid:\''.$cid.'\',nid:\''.$nid.'\',month:\''.$month.'\',year:\''.$year.'\' },
                                    success: function(data) {
                                            $(\'#subselect_div\').hide(\'fade\');
                                            $(\'#subselect_div\').html(data);
                                            $(\'#subselect_div\').show(\'fade\');
                                            refresh_all();
                                        }
                                });
                            }
                        });
                    },function(){});';

                    $content .= '
                        <a style="padding:2px;" class="inline-button ui-corner-all" href="javascript: CreateDialog(\''.$vars["form"].'_'.$identifier.'\',300,400)">
                            '.get_icon('table_edit').'
                        </a>
                        <a style="padding:2px;" class="inline-button ui-corner-all" id="a-'.$actid.'" data="'.$row["title"].'" href="javascript: '.$delete_action.'">
                            '.get_icon('bin_closed').'
                        </a>';
                } else if ($type == "employeeid") {
                    $identifier = $vars["type"]."_".$row[$id];
                    $content .= get_form("add_update_employee_activity",array("day" => $list_day, "month" => $month, "year" => $year, "employeeid" => $row["employeeid"],"actid" => $actid,"nid" => $nid,"callback" => "employee"),$identifier);

                    $delete_action = 'CreateConfirm(\'dialog-confirm\',\'Are you sure you want to delete this \'+$(\'a#a-'.$actid.'\').attr(\'data\')+\' activity?\', \'Yes\', \'No\',
                    function(){
                        $.ajax({
                            type: \'POST\',
                            url: \'ajax/ajax.php\',
                            data: { action: \'delete_employee_activity\',employeeid:\''.$vars["employeeid"].'\',actid:\''.$actid.'\',nid:\''.$nid.'\',tab:\''.$vars["type"].'\' },
                            success: function(data) {
                                $.ajax({
                                    type: \'POST\',
                                    url: \'ajax/ajax.php\',
                                    data: { action: \'get_activity_list\',employeeid:\''.$vars["employeeid"].'\',actid:\''.$actid.'\',nid:\''.$nid.'\',month:\''.$month.'\',year:\''.$year.'\' },
                                    success: function(data) {
                                            $(\'#subselect_div\').hide(\'fade\');
                                            $(\'#subselect_div\').html(data);
                                            $(\'#subselect_div\').show(\'fade\');
                                            refresh_all();
                                        }
                                });
                            }
                        });
                    },function(){});';

                    $content .= '
                        <a style="padding:2px;" class="inline-button ui-corner-all" href="javascript: CreateDialog(\'add_update_employee_activity_'.$identifier.'\',300,400)">
                            '.get_icon('table_edit').'
                        </a>
                        <a style="padding:2px;" class="inline-button ui-corner-all" id="a-'.$actid.'" data="'.$row["title"].'" href="javascript: '.$delete_action.'">
                            '.get_icon('bin_closed').'
                        </a>';
                }

                $content .= '
                    </div><br />';

            }
        }
    }
    $calendar.= '<td class="calendar-day '.$attending.'">';

    if ($type == "chid" && !empty($vars["chid"]) && $days_in_this_week > 1 && $days_in_this_week < 7) {
        $identifier = $list_day."_".$month."_".$year;
        if (empty($aid)) {
            $aid = get_db_field("aid", "children", "chid='" . $vars["chid"] . "'");
        }

        $content .= get_form("add_activity",array("day" => $list_day, "month" => $month, "year" => $year, "aid" => $aid,"chid" => $vars["chid"],"callback" => "children"),$identifier);

        $dayadd = '<div class="day-add">
                        <a style="" href="javascript: CreateDialog(\'add_activity_'.$identifier.'\',300,400)">
                            +
                        </a>
                   </div>';
    } else if ($type == "employeeid" && !empty($vars["employeeid"]) && $days_in_this_week > 1 && $days_in_this_week < 7) { 
        $identifier = $list_day."_".$month."_".$year;
        $content .= get_form("add_update_employee_activity",array("day" => $list_day, "month" => $month, "year" => $year, "employeeid" => $vars["employeeid"],"callback" => "employee"),$identifier);

        $dayadd = '<div class="day-add">
                        <a style="" href="javascript: CreateDialog(\'add_update_employee_activity_'.$identifier.'\',300,400)">
                            +
                        </a>
                   </div>';
    } else {
        $dayadd = "";
    }

    $calendar .= $dayadd;
    /* add in the day number */
    $calendar .= '<div class="show_mobile day-word">'.date("D", strtotime("$month/$list_day/$year")).'</div><div class="day-number">'.$list_day.'</div>';
    $calendar .= $content;
    $calendar .= '</td>';
    if ($running_day == 6):
      $calendar.= '</tr>';
      if (($day_counter+1) != $days_in_month):
        $calendar.= '<tr class="calendar-row">';
      endif;
      $running_day = -1;
      $days_in_this_week = 0;
    endif;
    $days_in_this_week++; $running_day++; $day_counter++;
  endfor;

  /* finish the rest of the days in the week */
  if ($days_in_this_week > 1 && $days_in_this_week < 8):
    for($x = 1; $x <= (8 - $days_in_this_week); $x++):
      $calendar.= '<td class="calendar-day-np">&nbsp;</td>';
    endfor;
  endif;

  /* final row */
  $calendar.= '</tr>';

  /* end the table */
  $calendar.= '</table>';
  $calendar .= '<div style="clear:both;"></div>';
  /* all done, return result */
  return $calendar;
}

function make_timestamp_from_date($date, $timezone = "UTC") {
    global $CFG;
    $timezone = empty($timezone) ? $CFG->timezone : $timezone;
    $LOCAL = new DateTimeZone($timezone);

    if (DateTime::createFromFormat('m/d/Y', $date)) {
        return DateTime::createFromFormat('m/d/Y', $date, $LOCAL)->getTimestamp(); // Noon
    } else if (DateTime::createFromFormat('m-d-Y', $date)) {
        return DateTime::createFromFormat('m-d-Y', $date, $LOCAL)->getTimestamp(); // Noon
    } else if (DateTime::createFromFormat('Y-m-d\TH:i:s', $date)) {
        return DateTime::createFromFormat('Y-m-d\TH:i:s', $date, $LOCAL)->getTimestamp(); // Time is given
    } else if (DateTime::createFromFormat('Y-m-d\TH:i', $date)) {
        return DateTime::createFromFormat('Y-m-d\TH:i', $date, $LOCAL)->getTimestamp(); // Time is given
    } else {
        return strtotime($date); // Noon
    }
}
?>