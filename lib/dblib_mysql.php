<?php

/***************************************************************************
* dblib_mysql.php - DEPRECATED legacy database function library
* -------------------------------------------------------------------------
* The ext/mysql extension this file wraps was removed from PHP entirely
* in PHP 7.0 (December 2015). If $CFG->dbtype is ever anything other
* than "mysqli", the app cannot run on any supported PHP version.
*
* RECOMMENDATION: set $CFG->dbtype = "mysqli" in config.php (this should
* already be the default) and delete this file, dblib.php's mysql
* fallback branch, and the dbtype config option entirely. It is kept
* here only as a clearly-labeled compatibility stub so the app fails
* with an actionable message instead of a confusing fatal error.
***************************************************************************/

if (!function_exists('mysql_connect')) {
    trigger_error(
        'config.php has $CFG->dbtype set to "mysql", but the mysql_* ' .
        'extension was removed in PHP 7.0. Set $CFG->dbtype = "mysqli" instead.',
        E_USER_ERROR
    );
}

/**
 *
 * Mysql array type.
 *
 *
 * @param mixed  $type Optional type or mode flag.
 */
function get_mysql_array_type($type = "assoc") {
    switch ($type) {
        case "assoc":
            return MYSQL_ASSOC;
        case "num":
            return MYSQL_NUM;
        case "both":
            return MYSQL_BOTH;
        default:
            return MYSQL_ASSOC;
    }
}

/**
 *
 * Db report level.
 *
 *
 * @param mixed $level Level.
 */
function set_db_report_level($level = null) {
    // No-op: ext/mysql has no equivalent of mysqli_report().
}

/**
 *
 * Db goto row.
 *
 *
 * @param mixed $result Result.
 * @param int   $rownum Rownum.
 */
function db_goto_row($result, $rownum = 0) {
    mysql_data_seek($result, $rownum);
}

/**
 *
 * Fetch the next row from a result resource as an associative array.
 *
 *
 * @param mixed  $result Result.
 * @param mixed $type   Optional type or mode flag.
 */
function fetch_row($result, $type = false) {
    $type = get_mysql_array_type($type);
    return mysql_fetch_array($result, $type);
}

/**
 *
 * Db count.
 *
 *
 * @param string $SQL SQL statement.
 */
function get_db_count($SQL) {
    global $CFG;
    if (strstr($SQL, ".")) {
        if ($result = get_db_result($SQL)) {
            return mysql_num_rows($result);
        }
        return 0;
    } else {
        $SQL = "SELECT COUNT(*) as count " . substr($SQL, strpos($SQL, "FROM"));
        if ($row = get_db_row($SQL)) {
            return $row["count"];
        }
        return 0;
    }
}

/**
 *
 * Run a query and return the result resource (or false).
 *
 *
 * @param string $SQL  SQL statement.
 * @param array  $vars Prepared-statement placeholder map.
 */
function get_db_result($SQL, $vars = []) {
    global $CFG, $conn;
    if (!$conn) {
        $conn = reconnect();
    }
    if (!empty($vars)) {
        trigger_error('Prepared statements are not supported on the legacy mysql driver.', E_USER_WARNING);
    }

    if ($result = mysql_query($SQL)) {
        $select = preg_match('/^\s*SELECT/i', $SQL) ? true : false;
        if ($select && mysql_num_rows($result) == 0) {
            return false;
        }
        return $result;
    }
    return false;
}

/**
 *
 * Execute a non-SELECT statement (INSERT, UPDATE, DELETE, or DDL).
 *
 *
 * @param string $SQL  SQL statement.
 * @param array  $vars Prepared-statement placeholder map.
 */
function execute_db_sql($SQL, $vars = []) {
    global $CFG, $conn;
    $update = preg_match('/^\s*UPDATE/i', $SQL) ? true : false;
    $delete = preg_match('/^\s*DELETE/i', $SQL) ? true : false;

    if ($result = get_db_result($SQL, $vars)) {
        if ($result && $update) {
            $id = mysql_affected_rows($conn);
            if (!$id) {
                return true;
            }
        } elseif ($result && $delete) {
            $id = mysql_affected_rows($conn);
            if (!$id) {
                return true;
            }
        } elseif ($result) {
            $id = mysql_insert_id($conn);
            if (!$id) {
                return true;
            }
        }
        return $id;
    }
    return false;
}

/**
 *
 * Db error.
 *
 *
 */
function get_db_error() {
    return function_exists('mysql_error') ? mysql_error() : '';
}

/**
 *
 * Db errorno.
 *
 *
 */
function get_db_errorno() {
    return function_exists('mysql_errno') ? mysql_errno() : 0;
}

/**
 *
 * Escape a string for safe interpolation into SQL.
 *
 *
 * @param mixed $str Str.
 */
function dbescape($str) {
    global $conn;
    return mysql_real_escape_string($str, $conn);
}

/**
 *
 * Db free result.
 *
 *
 * @param mixed $result Result.
 */
function db_free_result($result) {
    mysql_free_result($result);
}
