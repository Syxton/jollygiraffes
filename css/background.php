<?php
/*** set the content type header ***/
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