<?php
/***************************************************************************
* header.php - Lib header
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 7/21/2010
* Revision: 0.1.5
***************************************************************************/
 
$LIBHEADER = true;

if(!isset($DBLIB)){ include_once($CFG->dirroot.'/lib/dblib.php'); }
if(!isset($PAGELIB)){ include_once($CFG->dirroot.'/lib/pagelib.php'); }
if(!isset($BILLINGLIB)){ include_once($CFG->dirroot.'/lib/billinglib.php'); }
if(!isset($FORMLIB)){ include_once($CFG->dirroot.'/lib/formlib.php'); }
if(!isset($ERRORS)){ include_once($CFG->dirroot.'/lib/errors.php'); }
if(!isset($TIMELIB)){ include_once($CFG->dirroot.'/lib/timelib.php'); }
if(!isset($FILELIB)){ include_once($CFG->dirroot.'/lib/filelib.php'); }
if(!isset($HELP)){ include_once($CFG->dirroot.'/lib/help.php'); }
?>