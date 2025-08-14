<?php

if (!isset($CFG)) {
    include_once('../config.php');
}
include($CFG->dirroot . '/lib/header.php');

$fileName = !empty($_FILES['afile']['name']) ? $_FILES['afile']['name'] : false;
$fileType = !empty($_FILES['afile']['type']) ? $_FILES['afile']['type'] : false;
$fileContent = !empty($_FILES['afile']['tmp_name']) ? file_get_contents($_FILES['afile']['tmp_name']) : false;
$values = $_REQUEST['values'];

$tag = $chid = $description = "";
foreach ($values as $field) {
    switch ($field["name"]) {
        case "tag":
            $tag = dbescape($field["value"]);
            $tag = make_or_get_tag($tag);
            break;
        case "aid":
            $aid = dbescape($field["value"]);
            break;
        case "chid":
            $chid = dbescape($field["value"]);
            break;
        case "cid":
            $cid = dbescape($field["value"]);
            break;
        case "actid":
            $actid = dbescape($field["value"]);
            break;
        case "description":
            $description = dbescape($field["value"]);
            break;
        case "did":
            $did = dbescape($field["value"]);
            break;
        case "callback":
            $callback = dbescape($field["value"]);
            break;
        case "param1":
            $param1 = dbescape($field["value"]);
            break;
        case "param1value":
            $param1value = dbescape($field["value"]);
            break;
    }
}

$aid = empty($aid) ? false : $aid;
$chid = empty($chid) ? false : $chid;
$cid = empty($cid) ? false : $cid;
$actid = empty($actid) ? false : $actid;

if ((empty($aid) && empty($chid) && empty($cid) && empty($actid)) || empty($tag)) {
    echo "false";
    exit;
} else {
    if (!empty($chid)) {
        $folder = "children/$chid";
        recursive_mkdir($CFG->docroot . "/files/children");
    } elseif (!empty($cid)) {
        $folder = "contacts/$cid";
        recursive_mkdir($CFG->docroot . "/files/contacts");
    } elseif (!empty($actid)) {
        $folder = "activities/$actid";
        recursive_mkdir($CFG->docroot . "/files/activities");
    } elseif (!empty($aid)) {
        $folder = "accounts/$aid";
        recursive_mkdir($CFG->docroot . "/files/accounts");
    }
    recursive_mkdir($CFG->docroot . "/files/$folder");
}

// Insert into DB
$time = get_timestamp();
if (!empty($did)) {
    $existing = get_db_row("SELECT * FROM documents WHERE did='$did'");
    $folder = "children/" . $existing["chid"];

    if (empty($fileContent)) {
        execute_db_sql("UPDATE documents SET description='$description',tag='$tag',timelog='$time' WHERE did='$did'");
    } else {
        $path_parts = pathinfo($fileName);
        $newname = "$tag" . "_" . time() . "." . $path_parts["extension"]; //unique name
        $file = $CFG->docroot . "/files/$folder/$newname";

        $existing = get_db_row("SELECT * FROM documents WHERE did='$did'");
        delete_file($CFG->docroot . "/files/$folder/" . $existing["filename"]);
        execute_db_sql("UPDATE documents SET description='$description',filename='$newname',tag='$tag',timelog='$time' WHERE did='$did'");
        // Write the contents back to the file
        file_put_contents($file, $fileContent);
        if ($tag == "avatar") { //resize avatar
            smart_resize_image($file, 150, 150, true, "file", "true", "false", "60");
        }
    }
} else {
    if (empty($fileContent)) {
        echo "false";
        exit;
    }
    $path_parts = pathinfo($fileName);
    $newname = "$tag" . "_" . time() . "." . $path_parts["extension"]; //unique name

    if (!empty($aid)) {
        $SQL = "INSERT INTO documents (aid,tag,filename,description,timelog) VALUES('$aid','$tag','$newname','$description','$time')";
    } elseif (!empty($chid)) {
        $aid = get_db_field("aid", "children", "chid='$chid'");
        $SQL = "INSERT INTO documents (aid,chid,tag,filename,description,timelog) VALUES('$aid','$chid','$tag','$newname','$description','$time')";
    } elseif (!empty($cid)) {
        $aid = get_db_field("aid", "contacts", "cid='$cid'");
        $SQL = "INSERT INTO documents (aid,cid,tag,filename,description,timelog) VALUES('$aid','$cid','$tag','$newname','$description','$time')";
    } elseif (!empty($actid)) {
        $SQL = "INSERT INTO documents (actid,tag,filename,description,timelog) VALUES('$actid','$tag','$newname','$description','$time')";
    }
    execute_db_sql($SQL);

    $file = $CFG->docroot . "/files/$folder/$newname";

    // Write the contents back to the file
    file_put_contents($file, $fileContent);

    if ($tag == "avatar") { //resize avatar
        smart_resize_image($file, 150, 150, true, "file", "true", "false", "60");
    }
}

    $_POST["action"] = $callback;
    $_POST[$param1] = $param1value;
    include($CFG->dirroot . '/ajax/ajax.php');
