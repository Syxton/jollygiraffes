<?php
/*** set the content type header ***/
header("Cache-Control: public, max-age=604800"); // 1 week
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 604800) . " GMT");
header("Content-type: text/css");

$background = "";
if (file_exists("background.jpg")) {
    $background = "url('data:image/jpeg;base64," . base64_encode(file_get_contents("background.jpg")) . "')";
}
?>
.main_level {
    background: skyblue <?php echo $background ?>;
    background-size: cover;
}