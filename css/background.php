<?php
/*** set the content type header ***/
header("Cache-Control: public, max-age=604800"); // 1 week
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 604800) . " GMT");
header("Content-type: text/css");

$background = "";
$filetypes = [ // In order of preference.
    "webp",
    "avif",
    "svg",
    "png",
    "jpg",
    "jpeg",
    "gif",
    "bmp",
];

foreach ($filetypes as $filetype) {
    if (file_exists("background." . $filetype)) {
        $background = "url('background." . $filetype . "')";
        break;
    }
}

?>
.main_level::before {
    content: "";
    background: brown <?php echo $background ?>;
    background-size: cover;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    filter: blur(10px) brightness(0.899);
}