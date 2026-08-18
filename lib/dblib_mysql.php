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

function set_db_report_level($level = null) {
    // No-op: ext/mysql has no equivalent of mysqli_report().
}

function db_goto_row($result, $rownum = 0) {
    mysql_data_seek($result, $rownum);
}

function fetch_row($result, $type = false) {
    $type = get_mysql_array_type($type);
    return mysql_fetch_array($result, $type);
}

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

function get_db_error() {
    return function_exists('mysql_error') ? mysql_error() : '';
}

function get_db_errorno() {
    return function_exists('mysql_errno') ? mysql_errno() : 0;
}

function dbescape($str) {
    global $conn;
    return mysql_real_escape_string($str, $conn);
}

function db_free_result($result) {
    mysql_free_result($result);
}
