&nbsp;
<a  id="a-<?php echo $cid; ?>"
    data="<?php echo $name; ?>"
    href="javascript: void(0);"
    onclick="CreateConfirm(
                'dialog-confirm',
                'Are you sure you want to <?php echo $confirm_text; ?> ' + $('a#a-<?php echo $cid; ?>').attr('data') + '?',
                'Yes',
                'No',
                function() {
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'toggle_contact_activation',
                            cid: '<?php echo $cid; ?>',
                        },
                        success: function(data) {
                            $.ajax({
                                type: 'POST',
                                url: 'ajax/ajax.php',
                                data: {
                                    action: 'get_admin_accounts_form',
                                    aid: '<?php echo $aid; ?>',
                                },
                                success: function(data) {
                                    $('#admin_display').html(data);
                                    refresh_all();
                                }
                            });
                        }
                    });
                },
                function(){});">
    <span class="inline-button ui-corner-all <?php echo $caution; ?>">
        <?php echo get_icon($icon) . " $title"; ?>
    </span>
</a>