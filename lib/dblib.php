<?php

/***************************************************************************
* dblib.php - Database function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 1/17/2011
* Revision: 1.7.4
***************************************************************************/

if (!isset($LIBHEADER)) {
    include('header.php');
}
$DBLIB = true;

function reconnect(){
    global $CFG;
    if ($CFG->dbtype == "mysqli" && function_exists('mysqli_connect')) {
        //mysqli is installed
        $CFG->dbtype = "mysqli";
        $conn = mysqli_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or senderror("Could not connect to database");
        mysqli_select_db($conn, $CFG->dbname) or senderror("<b>A fatal MySQL error occured</b>.\n<br />Query: " . $SQL . "<br />\nError: (" . mysqli_errno($conn) . ") " . mysqli_error($conn));
    } else {
        $CFG->dbtype = "mysql";
        $conn = mysql_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or senderror("Could not connect to database");
        mysql_select_db($CFG->dbname) or senderror("<b>A fatal MySQL error occured</b>.\n<br />Query: " . $SQL . "<br />\nError: (" . mysql_errno() . ") " . mysql_error());
    }
    return $conn;
}

$conn = reconnect();

if ($CFG->dbtype == "mysqli") {
    require('dblib_mysqli.php');
} else {
    require('dblib_mysql.php');
}

function get_db_row($SQL, $type = false){
    global $CFG;
    $type = get_mysql_array_type($type);
    if ($result = get_db_result($SQL)) {
        return fetch_row($result, $type);
    }
    return false;
}

function get_db_field($field, $from, $where){
    global $CFG;
    $SQL = "SELECT $field FROM $from WHERE $where LIMIT 1";

    if ($result = get_db_result($SQL)) {
        $row = fetch_row($result);
        return $row[$field];
    }
    return false;
}


function copy_db_row($row, $table, $variablechanges){
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

function is_unique($table, $where){
    if (get_db_count("SELECT * FROM $table WHERE $where")) {
        return true;
    }
    return false;
}

function even($var){
    return (!($var & 1));
}

function senderror($message){
    $message = preg_replace(["\r,\t,\n"], "", $message);
    error_log($message);
    die($message);
}
