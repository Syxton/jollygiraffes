<?php
/***************************************************************************
* index.php
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/8/2012
* Revision: 1.0.1
***************************************************************************/

if(!isset($CFG)){ include_once ('config.php'); }

include_once ($CFG->dirroot . '/lib/header.php');

//Start Page
include ('header.html');

//Main Layout
echo get_admin_button().get_employee_timeclock_button().'
      <div id="display_level" class="display_level ui-corner-all">';
        get_home_page();
echo '</div>';

//echo "Server offset is: " . get_date('P',time(),$CFG->servertz);
echo '<div class="loadingscreen" style="display:none;"></div>';

//End Page
include ('footer.html');

?>