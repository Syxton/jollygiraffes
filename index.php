<?php

/***************************************************************************
* index.php
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/8/2012
* Revision: 1.0.1
***************************************************************************/

if (!isset($CFG)) {
    include_once('config.php');
}

include_once($CFG->dirroot . '/lib/header.php');

// Check if database is installed.
is_installed();

// Start Page
include_once('header.html');

//echo "Server offset is: " . get_date('P',time(),$CFG->servertz);

// Main Layout
echo get_admin_button() . get_employee_timeclock_button() . '
    <div id="dialog-confirm" title="Confirm" style="display:none;">
        <p>
            <span class="ui-icon ui-icon-alert" style="margin-right: auto;margin-left: auto;"></span>
            <label></label>
        </p>
    </div>
    <div id="display_level" class="display_level ui-corner-all">
        <div id="clock" class="light">
            <div class="display">
                <div class="weekdays"></div>
                <div class="ampm"></div>
                <div class="digits"></div>
            </div>
        </div>
    ' . get_home_page() . '
    </div>
    <div class="loadingscreen" style="display:none;"></div>';

// End Page
include_once('footer.html');
