<?php
    $class = isset($class) ? $class : '';
    $style = isset($style) ? $style : '';
    $extraparams1 = isset($extraparams1) ? $extraparams1 : '';
    $extraparams2 = isset($extraparams2) ? $extraparams2 : '';
?>
<div class="ui-corner-all list_box selectablelist <?php echo $class; ?>" style="<?php echo $style; ?>"
    onclick="$(this).addClass('selected_button',true);
            $('.list_box').not(this).removeClass('selected_button', false);
            $.ajax({
                type: 'POST',
                url: 'ajax/ajax.php',
                data: {
                    action: 'get_info',
                    <?php echo $tabid; ?>: '<?php echo $$tabid; ?>',
                    <?php echo $extraparams1; ?>
                },
                success: function(data) {
                    $('#info_div').html(data);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'get_action_buttons',
                            <?php echo $tabid; ?>: '<?php echo $$tabid; ?>',
                            <?php echo $extraparams2; ?>
                        },
                        success: function(data) {
                            $('#actions_div').html(data);
                            refresh_all();
                        }
                    });
                }
            });">
    <?php echo $item; ?>
</div>