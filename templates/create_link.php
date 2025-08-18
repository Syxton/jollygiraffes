<?php
    $id = isset($id) ? $id : '';
    $data = isset($data) ? $data : '';
    $action = isset($action) ? $action : '';
    $class = isset($class) ? $class : '';
    $button_text = isset($button_text) ? $button_text : '';
?>
&nbsp;
<a  id="<?php echo $id; ?>"
    data="<?php echo $data; ?>"
    href="javascript: void(0);"
    onclick="<?php echo $action; ?>">
    <span class="<?php echo $class; ?> inline-button ui-corner-all">
        <?php echo $button_text; ?>
    </span>
</a>