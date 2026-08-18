<div class="ui-corner-all list_box selectablelist <?php echo $class; ?>"
    onclick="$(this).addClass('selected_button',true);
            $('.list_box').not(this).removeClass('selected_button', false);
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
            });">
    <div class="list_box_item_full">
        <?php echo $item; ?>
    </div>
</div>