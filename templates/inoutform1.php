<?php echo $home_button; ?>
<input type="hidden" id="askme" value="1" />
<?php echo $alphabet; ?>
<div style="clear:both;"></div>
<div class="container_main scroll-pane ui-corner-all layout-flex">
    <?php echo $children; ?>
</div>
<div class="bottom center ui-corner-all">
    <button class="submit_buttons big_button" disabled="true"
            onclick="if ($('.checked_pic').length) {
                var account = '';
                $('.checked_pic').each(function(index) {
                    if (account == '' || account == $(this).attr('class').match(/account_[1-9]+/ig).toString()) {
                        account = $(this).attr('class').match(/account_[1-9]+/ig);
                    } else {
                        account = 'false';
                    }
                });
                if (account == 'false') {
                    CreateAlert(
                        'dialog-confirm',
                        'All selected children must be on the same account.',
                        'Ok',
                        function(){}
                    );
                } else {
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'check_in_out_form',
                            type: '<?php echo $type; ?>',
                            chid: $('.checked_pic input.chid').serializeArray(),
                            admin: false
                        },
                        success: function(data) {
                            $('#display_level').html(data);
                            refresh_all();
                        }
                    });
                }
            }" >
    Parent Check <?php echo ucwords($type); ?>
    </button>
</div>