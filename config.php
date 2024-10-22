<?php  /// Moodle Configuration File

unset($CFG);

//Website info
$CFG = new stdClass();
$CFG->sitename 	= '';
$CFG->siteemail = '';
$CFG->streetaddress = '';
$CFG->logo 	= 'logo.png';

//Database connection variables
$CFG->dbtype    = 'mysqli'; //mysql or mysqli
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'jollygiraffes';
$CFG->dbuser    = 'root';
$CFG->dbpass    = '';

//Directory variables
$CFG->directory = '';
$CFG->wwwroot   = 'http://'.$_SERVER['SERVER_NAME'];
$CFG->wwwroot   = $CFG->directory ? $CFG->wwwroot.'/'.$CFG->directory : $CFG->wwwroot;
$CFG->docroot   = dirname(__FILE__);
$CFG->dirroot   = $CFG->docroot;

//Userfile path
$CFG->userfilespath = substr($CFG->docroot,0,strrpos($CFG->docroot,'/'));

//Cookie variables in seconds
$CFG->timezone = 'America/Indiana/Indianapolis';
$CFG->servertz = 'America/Indiana/Indianapolis';
date_default_timezone_set('UTC');

//Google Analytics id
$CFG->analytics = '';
?>