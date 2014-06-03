<?php
if(!isset($CFG)){ include_once('../config.php'); }
include($CFG->dirroot.'/lib/header.php');
$allowed_ext = array(

  // archives
  'zip' => 'application/zip',
  'rar' => 'application/zip',
  'ace' => 'application/zip',
  '7z' => 'application/zip',
    
  // documents
  'pdf' => 'application/pdf',
  'txt' => 'application/msword',
  'doc' => 'application/msword',
  'docx' => 'application/msword',
  'xls' => 'application/vnd.ms-excel',
  'xlsx' => 'application/vnd.ms-excel',
  'ppt' => 'application/vnd.ms-powerpoint',
  'pptx' => 'application/vnd.ms-powerpoint',
  'csv' => 'application/vnd.ms-excel',
  
  // executables
  'exe' => 'application/octet-stream',

  // images
  'gif' => 'image/gif',
  'png' => 'image/png',
  'jpg' => 'image/jpeg',
  'jpeg' => 'image/jpeg',

  // audio
  'mp3' => 'audio/mpeg',
  'wav' => 'audio/x-wav',

  // video
  'mpeg' => 'video/mpeg',
  'mpg' => 'video/mpeg',
  'mpe' => 'video/mpeg',
  'mov' => 'video/quicktime',
  'avi' => 'video/x-msvideo'
);

if(!empty($_GET['file'])){
    $file = $_GET['file'];
    $file = str_replace("\\","/",$file);
    
    $path_parts = pathinfo($file);
    if(empty($path_parts['filename']) && empty($path_parts['extension'])){ exit; }
    $filename = $path_parts['filename'] . "." . $path_parts['extension'];

    if(!array_key_exists($path_parts['extension'], $allowed_ext)){
        die("Not allowed file type."); 
    }
  
    // get mime type
    if($allowed_ext[$path_parts['extension']] == ''){
        $mtype = '';
        // mime type is not set, get from server settings
        if (function_exists('mime_content_type')){
            $mtype = mime_content_type($file);
        }elseif(function_exists('finfo_file')){
            $finfo = finfo_open(FILEINFO_MIME); // return mime type
            $mtype = finfo_file($finfo, $file);
            finfo_close($finfo);  
        }
        if($mtype == ''){
            $mtype = "application/octet-stream";
        }
    }else{
      // get mime type defined by admin
      $mtype = $allowed_ext[$path_parts['extension']];
    }

    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Type: $mtype");
    header("Content-Disposition: attachment; filename=\"".urldecode($filename)."\"");
    header("Content-Transfer-Encoding: binary");
    ob_clean();
    flush();
    readfile(str_replace(" ","%20",$file));
    exit;
}
?>