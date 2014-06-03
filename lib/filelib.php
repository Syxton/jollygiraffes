<?php
/***************************************************************************
* filelib.php - File Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 2/3/2012
* Revision: 0.0.5
***************************************************************************/
 
if(!isset($LIBHEADER)) include('header.php');
$FILELIB = true;

function delete_old_files($path, $days = 1){
global $CFG;
	$seconds = $days * (24*60*60);
	$dir    = $CFG->dirroot . $path;
	$files = scandir($dir);
	foreach ($files as $num => $fname){
		if (file_exists("{$dir}{$fname}") && ((time() - filemtime("{$dir}{$fname}")) > $seconds)) {
			$mod_time = filemtime("{$dir}{$fname}");
			if($fname != "..") 
			{	
				if (unlink("{$dir}{$fname}")){$del = $del + 1;}
			}
		}
	}
}

function delete_file($filepath){
    if(file_exists($filepath)){
        unlink($filepath);        
    }
}

function recursive_mkdir($folder){
global $CFG;
$doc = $mkfolder = "";
    if(strstr($folder,$CFG->docroot)){
        $doc = $CFG->docroot . DIRECTORY_SEPARATOR;
        $folder = str_replace($CFG->docroot, "", $folder);    
    }
    
    $folder = preg_split( "/[\\\\\/]/" , $folder );

    for($i=0; isset($folder[$i]); $i++){
        if(!strlen(trim($folder[$i]))){ continue; }
        $mkfolder .= $folder[$i];

        if(!is_dir($doc . $mkfolder)){
            mkdir($doc . "$mkfolder" ,  0777);
            chmod($doc . "$mkfolder", 0777);
        }
        $mkfolder .= DIRECTORY_SEPARATOR;
    }
}

function recursive_delete($folderPath){
global $CFG;
$doc = "";
    if(strstr($folderPath,$CFG->docroot)){
        $doc = $CFG->docroot . DIRECTORY_SEPARATOR;
        $folderPath = str_replace($CFG->docroot, "", $folderPath);    
    }
    
    if(is_dir($doc . $folderPath)){
        foreach(scandir($doc . $folderPath) as $value){
            if($value != "." && $value != ".."){
                $value = $doc . $value;
                if(is_dir($value)){
                    FolderDelete($value);
                }elseif(is_file($value)){
                    @unlink($value);
                }
            }
        }
        return rmdir($doc . $folderPath);
    }else{
        return false;
    }
}

function make_csv($filename,$contents){
    $tempdir = sys_get_temp_dir() == "" ? "/tmp/" : sys_get_temp_dir();
    $tmpfname = tempnam($tempdir, $filename);
    if(file_exists($tmpfname)){	unlink($tmpfname); }
    $handle = fopen($tmpfname, "w");
    foreach($contents as $fields){
        fputcsv($handle, $fields);
    }  
    fclose($handle); 
    rename($tmpfname,$tempdir."/".$filename);  
    return addslashes($tempdir."/".$filename);       
}

function create_file($filename,$contents,$makecsv=false){
    if($makecsv){
        return make_csv($filename,$contents);
    }else{
        $tempdir = sys_get_temp_dir() == "" ? "/tmp/" : sys_get_temp_dir();
        $tmpfname = tempnam($tempdir, $filename);
        if(file_exists($tmpfname)){	unlink($tmpfname); }
        $handle = fopen($tmpfname, "w");
        
        fwrite($handle, stripslashes($contents));
        fclose($handle);
        rename($tmpfname,$tempdir."/".$filename);  
        return addslashes($tempdir."/".$filename);        
    }
}

function get_download_link($filename,$contents,$makecsv=false){
    global $CFG;
    return 'window.open("'.$CFG->wwwroot . '/scripts/download.php?file='.create_file($filename,$contents,$makecsv).'", "download","menubar=yes,toolbar=yes,scrollbars=1,resizable=1,width=600,height=400");';
}

function smart_resize_image($file,
                          $width              = 0, 
                          $height             = 0, 
                          $proportional       = false, 
                          $output             = 'file', 
                          $delete_original    = true, 
                          $use_linux_commands = false,
                          $quality            = 100 ) {
  
    if ( $height <= 0 && $width <= 0 ) return false;
    
    # Setting defaults and meta
    $info                         = getimagesize($file);
    $image                        = '';
    $final_width                  = 0;
    $final_height                 = 0;
    list($width_old, $height_old) = $info;
    
    # Calculating proportionality
    if ($proportional) {
      if      ($width  == 0)  $factor = $height/$height_old;
      elseif  ($height == 0)  $factor = $width/$width_old;
      else                    $factor = min( $width / $width_old, $height / $height_old );
    
      $final_width  = round( $width_old * $factor );
      $final_height = round( $height_old * $factor );
    }
    else {
      $final_width = ( $width <= 0 ) ? $width_old : $width;
      $final_height = ( $height <= 0 ) ? $height_old : $height;
    }
    
    # Loading image to memory according to type
    switch ( $info[2] ) {
      case IMAGETYPE_GIF:   $image = imagecreatefromgif($file);   break;
      case IMAGETYPE_JPEG:  $image = imagecreatefromjpeg($file);  break;
      case IMAGETYPE_PNG:   $image = imagecreatefrompng($file);   break;
      default: return false;
    }
    
    
    # This is the resizing/resampling/transparency-preserving magic
    $image_resized = imagecreatetruecolor( $final_width, $final_height );
    if ( ($info[2] == IMAGETYPE_GIF) || ($info[2] == IMAGETYPE_PNG) ) {
        $transparency = imagecolortransparent($image);
        if ($transparency >= 0) {
            $trnprt_indx = imagecolorat($image, 0, 0);
            $transparent_color  = imagecolorsforindex($image, $trnprt_indx);
            $transparency       = imagecolorallocate($image_resized, $trnprt_indx['red'], $trnprt_indx['green'], $trnprt_indx['blue']);
            imagefill($image_resized, 0, 0, $transparency);
            imagecolortransparent($image_resized, $transparency);
        }elseif ($info[2] == IMAGETYPE_PNG) {
            imagealphablending($image_resized, false);
            $color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);
            imagefill($image_resized, 0, 0, $color);
            imagesavealpha($image_resized, true);
        }
    }
    imagecopyresampled($image_resized, $image, 0, 0, 0, 0, $final_width, $final_height, $width_old, $height_old);
    
    # Taking care of original, if needed
    if ( $delete_original ) {
      if ( $use_linux_commands ) exec('rm '.$file);
      else @unlink($file);
    }
    
    # Preparing a method of providing result
    switch ( strtolower($output) ) {
      case 'browser':
        $mime = image_type_to_mime_type($info[2]);
        header("Content-type: $mime");
        $output = NULL;
      break;
      case 'file':
        $output = $file;
      break;
      case 'return':
        return $image_resized;
      break;
      default:
      break;
    }
    
    # Writing image according to type to the output destination
    switch ( $info[2] ) {
      case IMAGETYPE_GIF:   imagegif($image_resized, $output, $quality);    break;
      case IMAGETYPE_JPEG:  imagejpeg($image_resized, $output, $quality);   break;
      case IMAGETYPE_PNG:   imagepng($image_resized, $output, $quality);    break;
      default: return false;
    }
    
    return true;
}
?>