<?php
$action = ajax_builder($actions);
?>
<button
    title="<?php echo $title; ?>"
    class="image_button"
    type="button"
    onclick="CreateConfirm(
        'dialog-confirm',
        'Are you sure you <?php echo $question; ?>?',
        'Yes',
        'No',
        function() {
            <?php echo $action; ?>
        }, function() {})">
    <?php echo icon($icon, "2"); ?>
</button>