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
        type="text/javascript"
        src="<?php echo $CFG->wwwroot ?>/min/?b=<?php echo $CFG->directory ? $CFG->directory . "/" : ""; ?>scripts/fontawesome&amp;f=fontawesome.min.js,solid.min.js">
    </script>
<link rel="stylesheet" href="css/status.css">
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
        <header class="topbar">
            <div class="topbar-title"><?php echo $sitename; ?></div>
            <button class="link-button" id="parent_logout">Log out</button>
        </header>

        <div class="child-tabs" id="parent_child_tabs"></div>

        <div class="child_avatar" id="avatar"></div>
        <div class="child_name" id="name"></div>

        <div class="day-nav">
            <button class="day-nav-btn" id="day_prev" aria-label="Previous day">&#8249;</button>
            <div class="day-label" id="day_label">Today</div>
            <button class="day-nav-btn" id="day_next" aria-label="Next day">&#8250;</button>
        </div>

        <div class="swipe-area" id="swipe_area">
            <section class="card">
                <h2>Mood Today</h2>
                <div class="mood-timeline" id="mood_timeline"></div>
                <div class="empty-note" id="mood_empty" style="display:none;">No mood updates logged yet.</div>
            </section>

            <section class="card">
                <h2>Today's Tallies</h2>
                <div class="tally-grid" id="tally_grid"></div>
            </section>

            <section class="card">
                <h2>Menu</h2>
                <div class="menu-text" id="menu_text"></div>
                <div class="empty-note" id="menu_empty" style="display:none;">No menu posted for this day.</div>
            </section>

            <section class="card">
                <h2>Notes</h2>
                <div class="notes-list" id="notes_list"></div>
                <div class="empty-note" id="notes_empty" style="display:none;">No notes for this day.</div>
            </section>
        </div>
    </div>

    <!-- ADMIN / STAFF EDIT VIEW -->
    <div id="screen_admin" class="status-screen" style="display:none;">
        <header class="topbar">
            <div class="topbar-title"><?php echo $sitename; ?> - Staff</div>
            <button class="link-button" id="admin_links_btn">Family Links</button>
            <button class="link-button" id="admin_logout">Log out</button>
        </header>

        <select id="admin_child_select" class="child-select"></select>

        <div class="child_avatar" id="avatar"></div>

        <div class="day-label" id="admin_day_label">Today</div>

        <div class="admin-grid">
            <section class="card">
                <h2>Mood</h2>
                <div class="mood-buttons" id="mood_buttons"></div>
                <div class="mood-timeline" id="admin_mood_timeline"></div>
            </section>

            <section class="card">
                <h2>Tallies</h2>
                <div class="tally-grid admin-tally-grid" id="admin_tally_grid"></div>
            </section>

            <section class="card">
                <h2>Menu</h2>
                <textarea id="admin_menu_input" rows="3" placeholder="Today's menu..."></textarea>
                <button class="primary-button" id="save_menu_btn">Save Menu</button>
                <span class="save-status" id="menu_save_status"></span>
            </section>

            <section class="card">
                <h2>Notes</h2>
                <div class="notes-list" id="admin_notes_list"></div>
                <div class="note-form">
                    <select id="note_tag_select"></select>
                    <textarea id="note_text_input" rows="2" placeholder="Write a note..."></textarea>
                    <label class="notify-label">
                        <input type="checkbox" id="note_notify_checkbox">
                        Notify parent at sign-out
                    </label>
                    <button class="primary-button" id="add_note_btn">Add Note</button>
                </div>
            </section>
        </div>
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

<script src="scripts/status.js"></script>
</body>
</html>
