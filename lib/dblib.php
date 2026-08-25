<?php

/**
 * dblib.php - Database function library (Rev 1.8.0).
 * Existing call sites keep the same argument order; new optional $vars
 * enable prepared statements via ||placeholder|| in SQL.
 */

if (!isset($LIBHEADER)) {
    include('header.php');
}
$DBLIB = true;

/**
 *
 * True if the database schema appears to be installed.
 *
 *
 */
function is_installed() {
    global $CFG;
    try {
        // Make sure admin user is created.
        if (!get_db_count("SELECT * FROM accounts WHERE admin = '1'") && !get_db_count("SELECT * FROM version")) {
            // Admin user does not exist, so assume we need to install the db.
            include_once('install.php');
        }
    } catch (\Throwable $e) {
        // Try to catch and print out instructions for common connection errors.
        if (strpos($e->getMessage(), 'Access denied for user') !== false) {
            senderror("Access denied for user '" . $CFG->dbuser . "'@'" . $CFG->dbhost . "'. Please check your database username and password in the configuration file.  Make sure the user has the necessary permissions to write to the database.");
        } elseif (strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), 'doesn\'t exist') !== false) {
            // Admin user does not exist, so assume we need to install the db.
            include_once('install.php');
        } else {
            senderror("Database connection error: " . $e->getMessage());
        }
        return false; // Return false if connection fails
    }
}

/**
 *
 * Close and reopen the database connection.
 *
 *
 */
function reconnect() {
    global $CFG;
    try {
        if ($CFG->dbtype == "mysqli" && function_exists('mysqli_connect')) {
            $CFG->dbtype = "mysqli";
            if (function_exists('set_db_report_level')) {
                set_db_report_level();
            }
            $conn = mysqli_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or senderror("Could not connect to database");
            mysqli_select_db($conn, $CFG->dbname) or senderror("<b>A fatal MySQL error occured</b>.\n<br />\nError: (" . mysqli_errno($conn) . ") " . mysqli_error($conn));
        } else {
            $CFG->dbtype = "mysql";
            $conn = mysql_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or senderror("Could not connect to database");
            mysql_select_db($CFG->dbname) or senderror("<b>A fatal MySQL error occured</b>.\n<br />\nError: (" . mysql_errno() . ") " . mysql_error());
        }
        return $conn;
    } catch (\Throwable $e) {
        // Try to catch and print out instructions for common connection errors.
        if (strpos($e->getMessage(), 'Access denied for user') !== false) {
            senderror("Access denied for user '" . $CFG->dbuser . "'@'" . $CFG->dbhost . "'. Please check your database username and password in the configuration file.  Make sure the user has the necessary permissions to write to the database.");
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            senderror("Unknown database '" . $CFG->dbname . "'. Please check your database name in the configuration file.  If the database does not exist, you need to first create it.");
        } else {
            senderror("Database connection error: " . $e->getMessage());
        }
        return false; // Return false if connection fails
    }
}

// The driver file must load BEFORE reconnect() is called below, since
// reconnect() calls set_db_report_level(), which the driver defines.
if ($CFG->dbtype == "mysqli") {
    require('dblib_mysqli.php');
} else {
    require('dblib_mysql.php');
}

$conn = reconnect();

/**
 *
 * Fetch one row as an associative array, or false if none.
 *
 *
 * @param string $SQL  SQL statement.
 * @param mixed  $type Optional type or mode flag.
 * @param array  $vars Prepared-statement placeholder map.
 */
function get_db_row($SQL, $type = false, $vars = []) {
    global $CFG;
    $type = get_mysql_array_type($type);
    if ($result = get_db_result($SQL, $vars)) {
        return fetch_row($result, $type);
    }
    return false;
}

/**
 *
 * Fetch a single field value from the first matching row.
 *
 *
 * @param mixed $field Field.
 * @param mixed $from  From.
 * @param mixed $where Where.
 * @param array $vars  Prepared-statement placeholder map.
 */
function get_db_field($field, $from, $where, $vars = []) {
    global $CFG;
    $SQL = "SELECT $field FROM $from WHERE $where LIMIT 1";

    if ($result = get_db_result($SQL, $vars)) {
        $row = fetch_row($result);
        return $row[$field];
    }
    return false;
}

/**
 *
 * Copy a row into another table. Returns the new insert id.
 *
 *
 * @param array  $row             Database row.
 * @param string $table           Database table name.
 * @param mixed  $variablechanges Variablechanges.
 */
function copy_db_row($row, $table, $variablechanges) {
    global $USER, $CFG, $MYVARS;
    $paired = explode(",", $variablechanges);
    $newkey = $newvalue = [];
    $keylist = $valuelist = "";
    $i = 0;
    while (isset($paired[$i])) {
        $split = explode("=", $paired[$i]);
        $newkey[$i] = $split[0];
        $newvalue[$i] = $split[1];
        $i++;
    }

    $keys = array_keys($row);
    foreach ($keys as $key) {
        $found = array_search($key, $newkey);
        $keylist .= $keylist == "" ? $key : "," . $key;
        if ($found === false) {
            $valuelist .= $valuelist == "" ? "'" . $row[$key] . "'" : ",'" . $row[$key] . "'";
        } else {
            $valuelist .= $valuelist == "" ? "'" . $newvalue[$found] . "'" : ",'" . $newvalue[$found] . "'";
        }
    }
    $SQL = "INSERT INTO $table ($keylist) VALUES($valuelist)";
    return execute_db_sql($SQL);
}

/**
 *
 * Is unique.
 *
 *
 * @param string $table Database table name.
 * @param mixed  $where Where.
 */
function is_unique($table, $where) {
    if (get_db_count("SELECT * FROM $table WHERE $where")) {
        return true;
    }
    return false;
}

/**
 *
 * Even.
 *
 *
 * @param mixed $var Var.
 */
function even($var) {
    return (!($var & 1));
}

/**
 *
 * Senderror.
 *
 *
 * @param mixed $message Message.
 */
function senderror($message) {
    $message = preg_replace(["\r,\t,\n"], "", $message);
    error_log($message);
    die($message);
}

/**
 *
 * IsMultiArray.
 *
 *
 * @param mixed $a A.
 */
function isMultiArray($a) {
    if (is_array($a) && count($a) > 0) {
        foreach ($a as $value) {
            if (!is_array($value)) {
                return false;
            }
        }
        return true;
    }
    return false;
}

/**
 *
 * Clean param req.
 *
 *
 * @param mixed  $params Params.
 * @param string $key    Quick-note preset key.
 * @param mixed $type   Optional type or mode flag.
 */
function clean_param_req($params, $key, $type) {
    if (isset($params[$key])) {
        return clean_var_req($params[$key], $type, $key);
    } else {
        trigger_error("Missing required variable: $key", E_USER_ERROR);
        return NULL;
    }
}

/**
 *
 * Clean param opt.
 *
 *
 * @param mixed  $params  Params.
 * @param string $key     Quick-note preset key.
 * @param mixed $type    Optional type or mode flag.
 * @param mixed  $default Default.
 */
function clean_param_opt($params, $key, $type, $default) {
    if (isset($params[$key])) {
        return clean_var_opt($params[$key], $type, $default);
    } else {
        return clean_var_opt($default, $type, $default);
    }
}

/**
 *
 * Clean var req.
 *
 *
 * @param mixed  $var  Var.
 * @param mixed  $type Optional type or mode flag.
 * @param string $name Name.
 */
function clean_var_req($var, $type, $name = "") {
    $var = clean_var_opt($var, $type, NULL);
    if (is_null($var)) {
        trigger_error("Missing required variable: $name", E_USER_ERROR);
        throw new Exception("Missing required variable: $name");
    }
    return $var;
}

/**
 *
 * Clean var opt.
 *
 *
 * @param mixed  $var     Var.
 * @param mixed $type    Optional type or mode flag.
 * @param mixed  $default Default.
 */
function clean_var_opt($var, $type, $default) {
    if (is_null($var)) { return $default; }

    switch ($type) {
        case "int":
            if ($var === "0" || $var === 0) { return (int) 0; }
            $var = ltrim((string) $var, "0");
            $var = filter_var($var, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default;
            break;
        case "float":
            if ($var !== "" && (float) $var === 0.0) { return 0.0; }
            $var = ltrim((string) $var, "0");
            $var = filter_var($var, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? $default;
            break;
        case "string":
            $var = !strlen((string) $var) ? $default : (string) $var;
            break;
        case "array":
            $var = empty($var) ? $default : (array) $var;
            break;
        case "object":
            $var = empty($var) ? $default : (object) $var;
            break;
        case "json":
            if (empty($var)) { $var = $default; break; }
            $var = json_decode($var);
            if (json_last_error() !== JSON_ERROR_NONE) { $var = $default; }
            break;
        case "bool":
            $var = filter_var($var, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
            break;
        default:
            return $default;
    }

    return $var;
}

/**
 *
 * Build prepared variables.
 *
 *
 * @param string $SQL     SQL statement.
 * @param array  $vars    Prepared-statement placeholder map.
 * @param mixed  $pattern Pattern.
 */
function build_prepared_variables($SQL, $vars, $pattern) {
    $typestring = "";
    $data = [];
    preg_match_all($pattern, $SQL, $matches);
    foreach ($matches[0] as $match) {
        $variablename = trim($match, "|\"'");
        if (isset($vars[$variablename])) {
            $data[] = $vars[$variablename];
            $typestring .= find_var_type($vars[$variablename]);
        } else {
            if (strpos($variablename, "*") === false) {
                throw new \Exception("No value found for variable: " . $variablename);
            }
            $SQL = str_replace($match, "", $SQL);
        }
    }
    return ["data" => $data, "typestring" => $typestring, "sql" => $SQL];
}

/**
 *
 * Find var type.
 *
 *
 * @param mixed $var Var.
 */
function find_var_type($var) {
    if ($var === null) {
        return 's'; // mysqli binds PHP null as SQL NULL for s/i/d
    }
    switch (gettype($var)) {
        case 'string':  return 's';
        case 'integer': return 'i';
        case 'double':  return 'd';
        default:        return 'b';
    }
}

/**
 *
 * Is select.
 *
 *
 * @param string $SQL SQL statement.
 */
function is_select($SQL) {
    return preg_match('/^\s*(SELECT)/i', trim($SQL)) ? true : false;
}

/**
 *
 * Start db transaction.
 *
 *
 */
function start_db_transaction() {
    global $conn;
    if (function_exists('mysqli_begin_transaction') && $conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }
}

/**
 *
 * Commit db transaction.
 *
 *
 */
function commit_db_transaction() {
    global $conn;
    if (function_exists('mysqli_commit') && $conn instanceof mysqli) {
        mysqli_commit($conn);
    }
}

/**
 *
 * Rollback db transaction.
 *
 *
 */
function rollback_db_transaction() {
    global $conn;
    if (function_exists('mysqli_rollback') && $conn instanceof mysqli) {
        mysqli_rollback($conn);
    }
}
