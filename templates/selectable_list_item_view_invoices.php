<?php
    $empty = "";
    $class = isset($class) ? $class : '';
    $style = isset($style) ? $style : '';
    $tab1id = isset($tab1id) ? $tab1id : 'empty';
    $tab2id = isset($tab2id) ? $tab2id : 'empty';
    $aid = isset($aid) ? $aid : '';
    $pid = isset($pid) ? $pid : '';
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
                    action: 'view_invoices',
                    <?php echo $tab1id; ?>: '<?php echo $$tab1id; ?>',
                    <?php echo $tab2id; ?>: '<?php echo $$tab2id; ?>',
                    <?php echo $extraparams1; ?>
                },
                success: function(data) {
                    $('#info_div').html(data);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'get_billing_buttons',
                            <?php echo $tab1id; ?>: '<?php echo $$tab1id; ?>',
                            <?php echo $tab2id; ?>: '<?php echo $$tab2id; ?>',
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