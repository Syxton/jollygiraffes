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
include_once($CFG->dirroot . '/lib/status_lib.php');

// Check if database is installed.
is_installed();

status_migrate();
status_start_session();

// The check-in kiosk now shows child avatars (via files.php), which requires a logged-in
// session. Rather than have staff punch in a PIN for every single action like before, an
// admin unlocks the kiosk once here; that session then stays active for the rest of the day
// (or until someone taps "Lock Kiosk") so parents/staff can use the check-in/out screen
// normally, with photos loading.
if (!status_current_role()) {
    include_once('header.html');
    echo kiosk_lock_screen();
    include_once('footer.html');
    exit;
}

// Start Page
include_once('header.html');

// Main Layout
echo get_admin_button() . get_employee_timeclock_button() . get_kiosk_lock_button() . '
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

// -----------------------------------------------------------------
// Kiosk lock/unlock screen
// -----------------------------------------------------------------
function kiosk_lock_screen() {
    global $CFG;
    return '
    <div id="kiosk_lock" class="display_level ui-corner-all" style="text-align:center;padding-top:60px;">
        <h1>' . htmlspecialchars($CFG->sitename) . '</h1>
        <p style="font-size:1.2em;">Enter the admin PIN to unlock the check-in screen.</p>
        <input size="4" maxlength="4" type="password" inputmode="numeric" disabled
            name="kiosk_password" id="kiosk_password" value="" autocomplete="off"
            class="text ui-widget-content ui-corner-all"
            style="text-align:center;font-size:3em;width:225px;padding:0px 10px;" />
        <div id="kiosk_lock_error" style="color:#c00;min-height:1.5em;margin-top:10px;"></div>
        <div style="margin-top:10px;">
            ' . kiosk_keypad_button(1) . kiosk_keypad_button(2) . kiosk_keypad_button(3) . '
            <div style="clear:both;"></div>
            ' . kiosk_keypad_button(4) . kiosk_keypad_button(5) . kiosk_keypad_button(6) . '
            <div style="clear:both;"></div>
            ' . kiosk_keypad_button(7) . kiosk_keypad_button(8) . kiosk_keypad_button(9) . '
            <div style="clear:both;"></div>
            <button onclick="kioskClearPin();" class="keypad_button_big ui-corner-all">Clear</button>
            ' . kiosk_keypad_button(0) . '
            <div style="clear:both;"></div>
        </div>
    </div>
    <script type="text/javascript">
        function kioskAppendDigit(d) {
            var el = document.getElementById("kiosk_password");
            if (el.value.length < 4) { el.value += d; }
            if (el.value.length === 4) { kioskSubmit(); }
        }
        function kioskClearPin() {
            document.getElementById("kiosk_password").value = "";
            document.getElementById("kiosk_lock_error").textContent = "";
        }
        function kioskSubmit() {
            var pin = document.getElementById("kiosk_password").value;
            $.ajax({
                type: "POST",
                url: "' . $CFG->wwwroot . '/ajax/kiosk_login.php",
                timeout: 10000,
                dataType: "json",
                data: { kiosk_action: "unlock", password: pin },
                success: function(data) {
                    if (data && data.success) {
                        location.reload();
                    } else {
                        kioskClearPin();
                        var err = document.getElementById("kiosk_lock_error");
                        err.textContent = (data && data.message) ? data.message : "Incorrect PIN.";
                        $("#kiosk_lock").effect("shake", { times: 3 }, 150);
                    }
                },
                error: function() {
                    kioskClearPin();
                    document.getElementById("kiosk_lock_error").textContent = "Something went wrong. Try again.";
                }
            });
        }
    </script>';
}

function kiosk_keypad_button($digit) {
    return '<button onclick="kioskAppendDigit(' . (int) $digit . ');" class="keypad_button_big ui-corner-all"><span class="keypad">' . (int) $digit . '</span></button>';
}

function get_kiosk_lock_button() {
    global $CFG;
    return '<div class="bottom-left"><button class="kiosk_button bottomleft_button" onclick="
        $.ajax({
            type: \'POST\',
            url: \'' . $CFG->wwwroot . '/ajax/kiosk_login.php\',
            timeout: 10000,
            dataType: \'json\',
            data: { kiosk_action: \'lock\' },
            complete: function() { location.reload(); }
        });
    ">Lock Kiosk</button></div>';
}

