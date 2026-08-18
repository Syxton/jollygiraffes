<?php

/***************************************************************************
* dblib_mysqli.php - Database function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Upgraded: adds opt-in prepared statements, ported from syxtoncms 1.1.1
* Revision: 1.2.0
***************************************************************************/

function get_mysql_array_type($type = "assoc") {
    switch ($type) {
        case "assoc":
            return MYSQLI_ASSOC;
        case "num":
            return MYSQLI_NUM;
        case "both":
            return MYSQLI_BOTH;
        default:
            return MYSQLI_ASSOC;
    }
}

function set_db_report_level($level = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT) {
    mysqli_report($level);
}

function db_goto_row($result, $rownum = 0) {
    mysqli_data_seek($result, $rownum);
}

function fetch_row($result, $type = false) {
    $type = get_mysql_array_type($type);
    return mysqli_fetch_array($result, $type);
}

function get_db_count($SQL, $vars = []) {
    global $CFG;
    if (strstr($SQL, ".")) { //Complex SQL statements
        if ($result = get_db_result($SQL, $vars)) {
            return mysqli_num_rows($result);
        }
        return 0;
    } else { //Simple SQL can be counted quicker this way
        $SQL = "SELECT COUNT(*) as count " . substr($SQL, strpos($SQL, "FROM"));
        if ($row = get_db_row($SQL, false, $vars)) {
            return $row["count"];
        }
        return 0;
    }
}

/**
 * Turns a "||name||" or "'||name||'" templated SQL string into a
 * prepared mysqli statement bound against $vars.
 */
function db_prepare_statement($SQL, $vars) {
    global $conn;
    $pattern = '/([\'\"]?)(\|\|)((?s).*?)(\|\|)([\'\"]?)/i';
    $variables = build_prepared_variables($SQL, $vars, $pattern);

    $SQL = preg_replace($pattern, '?', $variables["sql"]);
    $statement = mysqli_prepare($conn, $SQL);

    if ($statement && !empty($variables["typestring"]) && !empty($variables["data"])) {
        mysqli_stmt_bind_param($statement, $variables["typestring"], ...$variables["data"]);
    }

    return $statement;
}

function get_prepared_result($statement, $select = false) {
    if (!$statement) {
        return false;
    }
    if ($result = mysqli_stmt_execute($statement)) {
        if ($select) {
            $result = mysqli_stmt_get_result($statement);
            if ($result && mysqli_num_rows($result) == 0) {
                return false;
            }
        }
        return $result;
    }
    return false;
}

/**
 * $vars is NEW and optional. Pass an associative array of
 * ["placeholder" => value] together with "||placeholder||" tokens in
 * $SQL to run this as a safe, parameterized prepared statement instead
 * of a plain query. Existing call sites that only pass $SQL behave
 * exactly as before.
 */
function get_db_result($SQL, $vars = []) {
    global $CFG, $conn;
    if (!$conn) {
        $conn = reconnect();
    }

    $select = preg_match('/^\s*SELECT/i', $SQL) ? true : false;

    if (!empty($vars)) {
        $statement = db_prepare_statement($SQL, $vars);
        return get_prepared_result($statement, $select);
    }

    if ($result = mysqli_query($conn, $SQL)) {
        if ($select && mysqli_num_rows($result) == 0) { //SELECT STATEMENTS ONLY, RETURN false on EMPTY selects
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

    if (!empty($vars)) {
        $statement = db_prepare_statement($SQL, $vars);
        $select = preg_match('/^\s*SELECT/i', $SQL) ? true : false;
        $result = get_prepared_result($statement, $select);
        if ($result) {
            if ($update || $delete) {
                $id = mysqli_stmt_affected_rows($statement);
                return $id ?: true;
            }
            $id = mysqli_insert_id($conn);
            return $id ?: true;
        }
        return false;
    }

    if ($result = get_db_result($SQL)) {
        if ($result && $update) {
            $id = mysqli_affected_rows($conn);
            if (!$id) {
                return true;
            }
        } elseif ($result && $delete) {
            $id = mysqli_affected_rows($conn);
            if (!$id) {
                return true;
            }
        } elseif ($result) {
            $id = mysqli_insert_id($conn);
            if (!$id) {
                return true;
            }
        }
        return $id;
    }
    return false;
}

function get_db_error() {
    global $conn;
    return mysqli_error($conn);
}

function get_db_errorno() {
    global $conn;
    return mysqli_errno($conn);
}

function dbescape($str) {
    global $conn;
    return mysqli_real_escape_string($conn, $str);
}

function db_free_result($result) {
    mysqli_free_result($result);
}
