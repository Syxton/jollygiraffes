<?php
/***************************************************************************
* dblib.php - Database function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 1/17/2011
* Revision: 1.7.4
***************************************************************************/

if(!isset($LIBHEADER)){ include ('header.php'); }
$DBLIB = true;

$conn = mysql_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or senderror("Could not connect to database");
mysql_select_db($CFG->dbname) or senderror("<b>A fatal MySQL error occured</b>.\n<br />\nError: (" . mysql_errno() . ") " . mysql_error());

function reconnect(){
global $CFG;
	$conn = mysql_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass) or senderror("Could not connect to database");
	mysql_select_db($CFG->dbname) or senderror("<b>A fatal MySQL error occured</b>.\n<br />\nError: (" . mysql_errno() . ") " . mysql_error());
	return $conn;
}

function fetch_row($result, $type = MYSQL_ASSOC){
	return mysql_fetch_array($result, $type);
}

function get_db_row($SQL, $resulttype = MYSQL_ASSOC){
global $CFG;
	if($result = get_db_result($SQL)){
		return fetch_row($result, $resulttype);
	}
    return false;
}

function get_db_field($field, $from, $where = false){
global $CFG;
    $where = empty($where) ? "" : "WHERE $where";
	$SQL = "SELECT $field FROM $from $where LIMIT 1";
    
	if($result = get_db_result($SQL)){
		$row = fetch_row($result);
		return $row[$field];
	}
	return false;
}

function get_db_count($SQL){
global $CFG;
	if(strstr($SQL,".")){ //Complex SQL statements
		if($result = get_db_result($SQL)){
			return mysql_num_rows($result);
		} 
        return 0;
	}else{ //Simple SQL can be counted quicker this way
		$SQL = "SELECT COUNT(*) as count " . substr($SQL, strpos($SQL, "FROM"));
		if($row = get_db_row($SQL)){
			return $row["count"];
		}
        return 0;
	}
}

function get_db_result($SQL){
global $CFG, $conn;
	if(!$conn){ $conn = reconnect(); }
	if($result = mysql_query($SQL)){
	   	$select = preg_match('/^SELECT/i',$SQL) ? true : false;
		if($select && mysql_num_rows($result) == 0){ //SELECT STATEMENTS ONLY, RETURN false on EMPTY selects
			return false;
		}
        return $result;
	}
	return false;
}

function copy_db_row($row, $table, $variablechanges){
global $USER, $CFG, $MYVARS;
	$paired = explode(",", $variablechanges);
	$newkey = $newvalue = array();
	$keylist = $valuelist = "";
    $i=0;
	while(isset($paired[$i])){
		$split = explode("=", $paired[$i]);
		$newkey[$i] = $split[0];
		$newvalue[$i] = $split[1];
		$i++;
	}

	$keys = array_keys($row);
    foreach($keys as $key){
		$found = array_search($key, $newkey);
		$keylist .= $keylist == "" ? $key : "," . $key;
		if($found === false){
			$valuelist .= $valuelist == "" ? "'" . $row[$key] . "'" : ",'" . $row[$key] . "'";
		}else{
			$valuelist .= $valuelist == "" ? "'" . $newvalue[$found] . "'" : ",'" . $newvalue[$found] . "'";
		}        
    }
	$SQL = "INSERT INTO $table ($keylist) VALUES($valuelist)";
	return execute_db_sql($SQL);
}

function is_unique($table, $where){
	if(get_db_count("SELECT * FROM $table WHERE $where")){ return true; }
	return false;
}

function even($var){
	return (!($var & 1));
}

function senderror($message){
    $message=preg_replace(array("\r,\t,\n"),"",$message);
    error_log($message);
    die($message);    
}

function prepare_db_var($var){
    return mysql_real_escape_string($var);
}
    
function execute_db_sql($SQL){
global $CFG, $conn;

	$update = preg_match('/^UPDATE/i',$SQL) ? true : false;
	$delete = preg_match('/^DELETE/i',$SQL) ? true : false;

    if($result = get_db_result($SQL)){
    	if($result && $update){ 
    		$id = mysql_affected_rows($conn);
    		if(!$id){ return true; }
    	}elseif($result && $delete){
     		$id = mysql_affected_rows($conn);
    		if(!$id){ return true; }
    	}elseif($result){
    		$id = mysql_insert_id($conn);
    		if(!$id){ return true; }
    	}
    	return $id;        
    } 
    return false;
}

?>