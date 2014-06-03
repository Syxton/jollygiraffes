<?php  /// Moodle Configuration File 

unset($CFG);

//Website info
$CFG = new stdClass();
$CFG->sitename 	= 'Homework & Hangout';
$CFG->siteemail = 'test@email.com';
$CFG->streetaddress = '1234 Address';
$CFG->fein = '';
$CFG->logo 	= 'logo.png';

//Database connection variables
$CFG->dbtype    = 'mysqli'; //mysql or mysqli
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'mydbname';
$CFG->dbuser    = 'mydbuser';
$CFG->dbpass    = 'mydbpassword';

//Directory variables
$CFG->directory = 'mywebsite/folder';
$CFG->wwwroot   = 'http://'.$_SERVER['SERVER_NAME'];
$CFG->wwwroot   = $CFG->directory ? $CFG->wwwroot.'/'.$CFG->directory : $CFG->wwwroot;
$CFG->docroot   = dirname(__FILE__);
$CFG->dirroot   = $CFG->docroot;

//Userfile path
$CFG->userfilespath = substr($CFG->docroot,0,strrpos($CFG->docroot,'/'));

//Cookie variables in seconds
$CFG->timezone = 'America/Indianapolis';
$CFG->servertz = 'America/New_York';
date_default_timezone_set('UTC');
?>