<?php  /// Moodle Configuration File 

unset($CFG);

//Website info
$CFG = new \stdClass;
$CFG->sitename 	= 'Jolly Giraffes';
$CFG->siteemail = 'test@email.com';
$CFG->streetaddress = '1234 Address';
$CFG->fein = '';
$CFG->logo 	= 'logo.png';

/**
 * Database Connection Variables
 * @var string $dbtype Type of database (mysql or mysqli)
 * @var string $dbhost Database host
 * @var string $dbname Database name
 * @var string $dbuser Database user
 * @var string $dbpass Database password
 */
$CFG->dbtype = 'mysqli'; // mysql or mysqli
$CFG->dbhost = 'localhost';
$CFG->dbname = 'jollygiraffes';
$CFG->dbuser = 'root';
$CFG->dbpass = '';

/**
 * Directory Variables
 * @var string $directory Directory path for the CMS
 * @var string $wwwroot Web root URL
 * @var string $docroot Document root directory
 * @var string $dirroot Directory root
 */
$CFG->directory = ''; // Points to http://localhost/xxxx/jollygiraffes
$CFG->wwwroot = '//' . $_SERVER['SERVER_NAME'];
$CFG->wwwroot = !empty($CFG->directory) ? $CFG->wwwroot . '/' . $CFG->directory : $CFG->wwwroot;
$CFG->docroot = dirname(__FILE__);
$CFG->dirroot = $CFG->docroot;

/**
 * Userfile Path Configuration
 * @var string $userfilesfolder Folder for user files
 * @var string $userfilespath Path to the user files folder
 * @var string $userfilesurl URL to access user files
 */
$CFG->userfilesfolder = 'files';
$CFG->userfilespath = $CFG->docroot . '\\' . $CFG->userfilesfolder;
$CFG->userfilesurl = $CFG->wwwroot . '/' . $CFG->userfilesfolder;

/**
 * Google Analytics ID
 * @var string $analytics
 */
$CFG->analytics = '';

//Cookie variables in seconds
$CFG->timezone = "America/Indiana/Indianapolis";
$CFG->servertz = "America/Indiana/Indianapolis";
date_default_timezone_set('UTC');
?>