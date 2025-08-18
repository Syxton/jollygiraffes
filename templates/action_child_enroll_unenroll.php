<?php
if ($enrolled) {
    ?>
    CreateConfirm(
        'dialog-confirm',
        'Are you sure you want to unenroll ' + $('a#a-<?php echo $chid; ?>').attr('data') + '?',
        'Yes',
        'No',
        function() {
            $.ajax({
                type: 'POST',
                url: 'ajax/ajax.php',
                data: {
                    action: 'toggle_enrollment',
                    pid: '<?php echo $pid; ?>',
                    chid: '<?php echo $chid; ?>',
                },
                success: function(data) {
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'get_info',
                            <?php echo $tabid; ?>: '<?php echo $$tabid; ?>',
                        },
                        success: function(data) {
                            $('#info_div').html(data);
                            $.ajax({
                                type: 'POST',
                                url: 'ajax/ajax.php',
                                data: {
                                    action: 'get_action_buttons',
                                    <?php echo $tabid; ?>: '<?php echo $$tabid; ?>',
                                },
                                success: function(data) {
                                    $('#actions_div').html(data);
                                    refresh_all();
                                }
                            });
                        }
                    });
                }
            });
        },
        function(){}
    );
    <?php
} else {
    ?>
    CreateDialog('add_edit_enrollment_<?php echo $identifier; ?>', 200, 400);
    <?php
}
?>