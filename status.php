<?php

/***************************************************************************
* status.php - Daily Status Report
* -------------------------------------------------------------------------
* Parent link:  https://yoursite/status?c=FAMILYCODE  (PIN-protected, read only)
* Admin/staff:  https://yoursite/status               (admin PIN, edit view)
***************************************************************************/

if (!isset($CFG)) {
    include_once('config.php');
}

include_once($CFG->dirroot . '/lib/header.php');
include_once($CFG->dirroot . '/lib/status_lib.php');

is_installed();
status_migrate();
status_start_session();

$code = isset($_GET['c']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['c']) : '';
$sitename = htmlspecialchars($CFG->sitename);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>Daily Status - <?php echo $sitename; ?></title>
    <!-- Font Awesome -->
    <script data-search-pseudo-elements defer
        src="<?php echo $CFG->wwwroot ?>/min/?b=<?php echo $CFG->directory ? $CFG->directory . "/" : ""; ?>scripts/fontawesome&amp;f=fontawesome.min.js,solid.min.js">
    </script>
    <link rel="stylesheet" href="css/status.css?version=2026081405">
    <link rel="shortcut icon" href="favicon.ico" />

    <!-- Favicon icons -->
    <link rel="apple-touch-icon" sizes="57x57" href="<?php echo $CFG->wwwroot ?>/images/icons/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="<?php echo $CFG->wwwroot ?>/images/icons/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="<?php echo $CFG->wwwroot ?>/images/icons/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="<?php echo $CFG->wwwroot ?>/images/icons/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="<?php echo $CFG->wwwroot ?>/images/icons/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="<?php echo $CFG->wwwroot ?>/images/icons/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="<?php echo $CFG->wwwroot ?>/images/icons/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo $CFG->wwwroot ?>/images/icons/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $CFG->wwwroot ?>/images/icons/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="36x36"  href="<?php echo $CFG->wwwroot ?>/images/icons/android-icon-36x36.png">
    <link rel="icon" type="image/png" sizes="48x48"  href="<?php echo $CFG->wwwroot ?>/images/icons/android-icon-48x48.png">
    <link rel="icon" type="image/png" sizes="72x72"  href="<?php echo $CFG->wwwroot ?>/images/icons/android-icon-72x72.png">
    <link rel="icon" type="image/png" sizes="96x96"  href="<?php echo $CFG->wwwroot ?>/images/icons/android-icon-96x96.png">
    <link rel="icon" type="image/png" sizes="144x144"  href="<?php echo $CFG->wwwroot ?>/images/icons/android-icon-144x144.png">
    <link rel="icon" type="image/png" sizes="192x192"  href="<?php echo $CFG->wwwroot ?>/images/icons/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $CFG->wwwroot ?>/images/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo $CFG->wwwroot ?>/images/icons/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $CFG->wwwroot ?>/images/icons/favicon-16x16.png">
    <link rel="manifest" href="<?php echo $CFG->wwwroot ?>/manifest.json">
</head>
<body>

<div id="status_app" data-code="<?php echo htmlspecialchars($code); ?>" data-sitename="<?php echo $sitename; ?>">
    <!-- LOGIN SCREEN -->
    <div id="screen_login" class="status-screen">
        <div class="login-card">
            <div class="login-logo">🦒</div>
            <h1 id="login_title"><?php echo $sitename; ?></h1>
            <p id="login_subtitle" class="muted">Enter your PIN to see today's status.</p>
            <div class="pin-display" id="pin_display"></div>
            <div class="numpad" id="numpad"></div>
            <div class="login-error" id="login_error"></div>
        </div>
    </div>

    <!-- PARENT REPORT VIEW -->
    <div id="screen_parent" class="status-screen" style="display:none;">
        <div class="preview-banner" id="preview_banner" style="display:none;">
            <span><i class="fa-solid fa-person-pregnant"></i>&nbsp;Parent View</span>
            <button type="button" class="preview-exit-btn" id="exit_preview_btn">Exit Preview</button>
        </div>
        <div class="topbar-wrap">
            <header class="topbar">
                <div class="topbar-title">🦒 <?php echo $sitename; ?></div>
                <div class="sticky-child-bar" id="parent_sticky_child_bar">
                    <div class="sticky-child-avatar" id="parent_sticky_avatar"></div>
                    <div class="sticky-child-name" id="parent_sticky_name"></div>
                </div>
                <button class="link-button" id="parent_logout">Log out</button>
            </header>
        </div>

        <div class="child-tabs" id="parent_child_tabs"></div>

        <div class="naptime-notice-text" id="parent_naptime_notice_text" style="display:none;">Shhh... It's naptime (1pm - 3pm).</div>

        <div class="child_avatar" id="avatar"></div>
        <div class="child_name" id="name"></div>

        <div class="day-nav">
            <button class="day-nav-btn" id="day_prev" aria-label="Previous day">&#8249;</button>
            <button type="button" class="day-label" id="day_label">Today</button>
            <button class="day-nav-btn" id="day_next" aria-label="Next day">&#8250;</button>
        </div>

        <div class="swipe-area" id="swipe_area">
            <section class="card" id="parent_notify_card" style="display:none;">
                <h2>📌 For Your Attention</h2>
                <div class="notes-list" id="parent_notify_notes"></div>
            </section>

            <div id="parent_meal_sections"></div>

            <section class="card" id="parent_activities_card" style="display:none;">
                <h2>🎨 Activities</h2>
                <div class="activity-chips" id="parent_activity_chips"></div>
            </section>

            <section class="card" id="parent_nap_rating_card" style="display:none;">
                <h2>😴 Naptime</h2>
                <div class="mood-timeline">
                    <div class="mood-chip" id="parent_nap_rating_chip"></div>
                </div>
            </section>

            <section class="card" id="parent_timeline_card">
                <div class="mood-timeline" id="parent_timeline"></div>
                <div class="empty-note" id="parent_timeline_empty" style="display:none;">Nothing logged yet today.</div>
            </section>
        </div>
    </div>

    <!-- ADMIN / STAFF EDIT VIEW -->
    <div id="screen_admin" class="status-screen" style="display:none;">
        <div class="topbar-wrap">
            <header class="topbar">
                <div class="topbar-title">
                    <span>🦒 <?php echo $sitename; ?> Staff</span>
                    <div class="sticky-child-bar" id="admin_sticky_child_bar">
                        <div class="topbar-child-info">
                            <div class="sticky-child-avatar" id="admin_sticky_avatar"></div>
                            <div class="sticky-child-name" id="admin_sticky_name"></div>
                        </div>
                    </div>
                </div>
                <div>
                    <button class="link-button" id="admin_preview_btn"><i class="fa-solid fa-person-pregnant"></i>&nbsp;Parent View</button>
                    <button class="link-button" id="admin_links_btn">Family Links</button>
                    <button class="link-button" id="admin_logout">Log out</button>
                </div>
            </header>
        </div>

        <select id="admin_child_select" class="child-select"></select>

        <div class="naptime-notice-text" id="admin_naptime_notice_text" style="display:none;">Shhh... It's naptime (1pm - 3pm).</div>
        <div class="avatar-editable" id="avatar_wrap">
            <div class="child_avatar" id="avatar"></div>
            <button type="button" class="avatar-upload-btn" id="avatar_upload_btn" title="Change Photo">
                <i class="fa-solid fa-camera"></i>
            </button>
            <input type="file" id="avatar_upload_input" accept="image/*" style="display:none;">
        </div>

        <div class="day-label" id="admin_day_label">Today</div>

        <div class="admin-grid">
            <section class="card">
                <h2>😎 Mood</h2>
                <div class="mood-buttons" id="mood_buttons"></div>
                <button type="button" class="card-history-toggle" id="mood_card_toggle" style="display:none;"></button>
                <div class="card-history" id="admin_mood_history">
                    <div class="mood-timeline" id="admin_mood_timeline"></div>
                </div>
            </section>

            <section class="card" id="admin_activities_card">
                <h2>🎨 Activities</h2>
                <div class="activity-buttons" id="activity_buttons"></div>
                <button type="button" class="secondary-button" id="activities_copy_toggle">Copy to Kids&hellip;</button>
                <span class="save-status" id="activities_copy_status"></span>
                <div class="menu-copy-panel" id="activities_copy_panel" style="display:none;">
                    <p class="muted menu-copy-hint">Copy today's activities to:</p>
                    <div class="menu-copy-list" id="activities_copy_list"></div>
                    <div class="menu-copy-buttons">
                        <button type="button" class="link-button" id="activities_copy_cancel">Cancel</button>
                        <button type="button" class="primary-button" id="activities_copy_confirm">Copy</button>
                    </div>
                </div>
            </section>

            <section class="card" id="admin_bottle_card" style="display:none;">
                <h2>🍼 Bottles</h2>
                <button type="button" class="primary-button" id="add_bottle_btn">+ Add Bottle</button>
                <button type="button" class="card-history-toggle" id="bottle_card_toggle" style="display:none;"></button>
                <div class="card-history" id="admin_bottle_history">
                    <div class="mood-timeline" id="admin_bottle_timeline"></div>
                </div>
            </section>

            <section class="card">
                <h2>🚽 Potty Time</h2>
                <div class="potty-type-buttons" id="potty_type_buttons"></div>
                <button type="button" class="card-history-toggle" id="potty_card_toggle" style="display:none;"></button>
                <div class="card-history" id="admin_potty_history">
                    <div class="potty-timeline" id="admin_potty_timeline"></div>
                </div>
                <div class="quick-note-buttons" id="quick_note_buttons"></div>
            </section>

            <section class="card" id="admin_naptime_card" style="display:none;">
                <h2>😴 Naptime</h2>
                <div class="naptime-buttons" id="naptime_buttons" style="display:none;"></div>
                <div class="meal-rating-buttons" id="nap_rating_buttons" style="display:none;"></div>
                <button type="button" class="card-history-toggle" id="naptime_card_toggle" style="display:none;"></button>
                <div class="card-history" id="admin_naps_history">
                    <div class="mood-timeline" id="admin_naps_timeline"></div>
                </div>
            </section>

            <section class="card">
                <h2>🚀 Incidents Quick Report</h2>
                <div class="incident-buttons" id="incident_buttons"></div>
                <button type="button" class="card-history-toggle" id="incidents_card_toggle" style="display:none;"></button>
                <div class="card-history" id="admin_incidents_history">
                    <div class="potty-timeline" id="admin_incidents_timeline"></div>
                </div>
            </section>

            <div id="admin_meal_sections"></div>

            <section class="card">
                <h2>📝 Notes</h2>
                <button type="button" class="card-history-toggle" id="notes_card_toggle" style="display:none;"></button>
                <div class="card-history" id="admin_notes_history">
                    <div class="notes-list" id="admin_notes_list"></div>
                </div>
                <div class="note-form">
                    <div class="note-form-editing-label" id="note_editing_label" style="display:none;">Editing note &ndash; <span class="link-button-inline" id="note_editing_cancel">cancel</span></div>
                    <div class="note-form-editing-label" id="note_adding_label">Add new note</div>
                    <select id="note_tag_select"></select>
                    <textarea class="app-textarea" id="note_text_input" rows="2" placeholder="Write a note..."></textarea>
                    <label class="notify-label">
                        <input class="styled-checkbox" type="checkbox" id="note_notify_checkbox">
                        Notify parent at sign-out
                    </label>
                    <label class="notify-label" id="note_persist_label">
                        <input class="styled-checkbox" type="checkbox" id="note_persist_checkbox">
                        Persist (keep notifying until cleared)
                    </label>
                    <button class="primary-button" id="add_note_btn">Add Note</button>
                </div>
            </section>
        </div>
    </div>

    <!-- POTTY TIME entry panel (admin) - populated/shown by JS -->
    <div id="potty_panel_overlay" class="potty-panel-overlay" style="display:none;">
        <div class="potty-panel" id="potty_panel"></div>
    </div>

    <!-- INCIDENT entry panel (admin) - populated/shown by JS -->
    <div id="incident_panel_overlay" class="potty-panel-overlay" style="display:none;">
        <div class="potty-panel" id="incident_panel"></div>
    </div>

    <!-- BOTTLE ounces picker (admin) - populated/shown by JS -->
    <div id="bottle_panel_overlay" class="potty-panel-overlay" style="display:none;">
        <div class="potty-panel" id="bottle_panel"></div>
    </div>

    <!-- ACTIVITY photo panel (admin) - populated/shown by JS -->
    <div id="activity_panel_overlay" class="potty-panel-overlay" style="display:none;">
        <div class="potty-panel" id="activity_panel"></div>
    </div>

    <!-- ATTACHMENT VIEWER (both views) - populated/shown by JS -->
    <div id="attachment_viewer_overlay" class="potty-panel-overlay" style="display:none;">
        <div class="potty-panel" id="attachment_viewer_panel"></div>
    </div>

    <!-- DATE PICKER (parent view) - populated/shown by JS -->
    <div id="date_picker_overlay" class="potty-panel-overlay" style="display:none;">
        <div class="potty-panel" id="date_picker_panel"></div>
    </div>

    <!-- FAMILY LINKS PANEL (admin) -->
    <div id="screen_links" class="status-screen" style="display:none;">
        <header class="topbar">
            <div class="topbar-title">Family Links</div>
            <button class="link-button" id="links_back_btn">Back</button>
        </header>
        <p class="muted" style="padding:0 16px;">Share each family's link so they can view their child's daily status. They'll use their existing account PIN to log in.</p>
        <div id="links_list" class="links-list"></div>
    </div>

</div>

<script src="scripts/status.js?version=2026081405"></script>
</body>
</html>