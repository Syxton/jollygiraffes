<?php
    $onsuccess = isset($onsuccess) ? $onsuccess : "get_admin_accounts_form";
    $onsuccessdata = isset($onsuccessdata) ? $onsuccessdata : "aid: '$aid',";
?>
CreateConfirm(
    'dialog-confirm',
    'Are you sure you want to <?php echo $action; ?> ' + $('a#a-<?php echo $chid; ?>').attr('data')+'?',
    'Yes',
    'No',
    function() {
        $.ajax({
            type: 'POST',
            url: 'ajax/ajax.php',
            data: {
                action: 'toggle_child_activation',
                chid: '<?php echo $chid; ?>',
            },
            success: function(data) {
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: '<?php echo $onsuccess; ?>',
                        <?php echo $onsuccessdata; ?>
                    },
                    success: function(data) {
                        $('#admin_display').html(data);
                        refresh_all();
                    }
                });
            }
        });
    },
    function(){}
);