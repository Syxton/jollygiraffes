<?php

/***************************************************************************
* help.php - Help library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/16/2013
* Revision: 0.1.1
***************************************************************************/

unset($HELP);
if (!isset($LIBHEADER)) {
    include('header.php');
}

$HELP = new stdClass();

//PAGE EDITING HINTS
$HELP->input_page_name = "This is the name of the page.  It will be displayed in links and in the page HTML header.  It is search indexed.";
$HELP->input_page_tags = "List of words that would best be used to find your page in a search.";
$HELP->input_page_summary = "This is a quick summary of the purpose of the page.  It is search indexed.";
$HELP->input_page_default_role = "New users will automatically be given this role.  Default: Guest";
$HELP->input_page_opendoor = "Allow people into your page without an invitation.  (logged in users only)";
$HELP->input_page_siteviewable = "Allow people into your page without an invitation.  (All website visitors)";
$HELP->input_page_menulink = "Link to the page in the sites main menu.";
$HELP->input_page_visitors_hide = "Link to the page in the sites main menu.";

//STANDARD INPUT FIELDS HINTS
$HELP->input_username = "Please enter your username/email.";
$HELP->input_email = "This is the email address that will be used to log into the website.";
$HELP->input_password = "Password must be <strong>at least 6 characters long</strong>";
$HELP->input_password2 = "Please enter your password.";
$HELP->input_vpassword = "This must match your previously entered password.</strong>";
$HELP->input_fname = "Please use your full first name, not just an initial.";
$HELP->input_lname = "Please use your full last name, not just an initial.";

//FORUM HINTS
$HELP->new_category = "Please type the name of the new forum category.";

function get_help($help){
    global $CFG, $HELP;
    $lang = explode(":", $help);
    if (isset($lang[2])) {
        include($CFG->dirroot . '/addons/' . $lang[1] . "/" . $lang[2] . "/lang.php");
        return $HELP->$lang[0];
    } elseif (isset($lang[1])) {
        include($CFG->dirroot . '/features/' . $lang[1] . "/lang.php");
        return $HELP->$lang[0];
    } else {
        return $HELP->$help;
    }
}
