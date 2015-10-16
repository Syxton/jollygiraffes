<?php
/***************************************************************************
* dblib_mysqli.php - Database function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 12/21/2012
* Revision: 1.1.1
***************************************************************************/

function get_mysql_array_type($type = "assoc"){
    switch($type){
        case "assoc":
            return MYSQLI_ASSOC;
        break;
        case "num":
            return MYSQLI_NUM;
        break;
        case "both":
            return MYSQLI_BOTH;
        break;
        default:
            return MYSQLI_ASSOC;
        break;
    }    
}

function db_goto_row($result, $rownum = 0){
    mysqli_data_seek($result, $rownum);
}

function fetch_row($result, $type = false){
    $type = get_mysql_array_type($type);
	return mysqli_fetch_array($result, $type);
}

function get_db_count($SQL){
global $CFG;
	if(strstr($SQL,".")){ //Complex SQL statements
		if($result = get_db_result($SQL)){
			return mysqli_num_rows($result);
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

	if($result = mysqli_query($conn, $SQL)){
	   	$select = preg_match('/^SELECT/i',$SQL) ? true : false;
		if($select && mysqli_num_rows($result) == 0){ //SELECT STATEMENTS ONLY, RETURN false on EMPTY selects
			return false;
		}
        return $result;
	}
	return false;
}

function execute_db_sql($SQL){
global $CFG, $conn;
	$update = preg_match('/^UPDATE/i',$SQL) ? true : false;
	$delete = preg_match('/^DELETE/i',$SQL) ? true : false;

    if($result = get_db_result($SQL)){
    	if($result && $update){ 
    		$id = mysqli_affected_rows($conn);
    		if(!$id){ return true; }
    	}elseif($result && $delete){
     		$id = mysqli_affected_rows($conn);
    		if(!$id){ return true; }
    	}elseif($result){
    		$id = mysqli_insert_id($conn);
    		if(!$id){ return true; }
    	}
    	return $id;        
    } 
    return false;
}

function dbescape($str){
global $conn;
    return mysqli_real_escape_string($conn,$str);
}

function db_free_result($result){
    mysqli_free_result($result);
}
?>