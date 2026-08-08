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
 * @var string $userfilespath Filesystem path to the user files folder.
 * @var string $fileserveurl  URL to the authenticated gateway script that streams files after
 *             checking the logged-in session (replaces the old direct $CFG->userfilesurl).
 *
 * IMPORTANT - read before editing:
 * $CFG->docroot is just the folder this config.php sits in. If Status is installed inside a
 * subdirectory of a larger site (e.g. this app lives at /var/www/html/daycare but the real
 * vhost/server root is /var/www/html, or docroot is several levels deep, or another vhost's
 * root overlaps this path), then walking "one directory above docroot" can still land inside
 * a directory the web server serves. There is no reliable way for PHP to detect the true web
 * root on its own, so DO NOT derive this path automatically - set it explicitly below to a
 * real, absolute filesystem path that you have manually confirmed sits outside EVERY
 * web-accessible directory on this server (check your Apache/nginx vhost config, not just
 * this app's folder structure). A safe default on most single-site setups is a sibling of
 * your actual DocumentRoot, e.g. if Apache's DocumentRoot is /var/www/html, use
 * /var/www/status_files (note: NOT /var/www/html/status_files).
 */
$CFG->userfilespath = '/CHANGE/ME/status_files'; // <-- set this to a real absolute path, see above
$CFG->fileserveurl = $CFG->wwwroot . '/files.php';

// Safety net: refuse to run if userfilespath still looks unconfigured, or if it resolves to
// somewhere inside this app's own docroot or inside the server's reported DOCUMENT_ROOT. This
// won't catch every possible misconfiguration (e.g. a second vhost pointed at this path), but
// it catches the two most common mistakes for free. Treat a green light here as a starting
// point, not a guarantee - always verify manually by requesting a known filename from the
// browser (see the deployment notes) after moving files over. Defined inline here because
// config.php is loaded standalone, before any of the lib/ files exist yet.
if (!function_exists('status_verify_files_path_safety')) {
    function status_verify_files_path_safety($CFG) {
        if (empty($CFG->userfilespath) || strpos($CFG->userfilespath, '/CHANGE/ME') !== false) {
            die('Configuration error: $CFG->userfilespath has not been set in config.php. ' .
                'See the comment above it before continuing.');
        }

        $target = realpath($CFG->userfilespath);
        // Not created yet is fine on a fresh install - just skip the containment checks below.
        if ($target === false) {
            return;
        }

        $unsafeRoots = [];
        if (!empty($CFG->docroot)) {
            $unsafeRoots[] = realpath($CFG->docroot);
        }
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $unsafeRoots[] = realpath($_SERVER['DOCUMENT_ROOT']);
        }

        foreach (array_filter($unsafeRoots) as $root) {
            if ($target === $root || strpos($target . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) === 0) {
                die('Configuration error: $CFG->userfilespath (' . $target . ') is inside a ' .
                    'web-accessible directory (' . $root . '). Move it outside every ' .
                    'web-served folder on this server and update config.php. See the comment ' .
                    'above $CFG->userfilespath for guidance.');
            }
        }
    }
}
status_verify_files_path_safety($CFG);

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