<?php echo $home_button; ?>
<input type="hidden" id="askme" value="1" />
<div id="dialog-confirm" title="Confirm" style="display:none;">
    <p>
        <span class="ui-icon ui-icon-alert" style="margin-right: auto;margin-left: auto;">
        </span>
        <label>
            Check for other children on this account?
        </label>
    </p>
</div>
<?php echo $alphabet; ?>
<div style="clear:both;"></div>
<div class="container_main scroll-pane ui-corner-all fill_height_middle">
    <?php echo $children; ?>
</div>
<?php echo $child_container; ?>
<div class="select_buttons_div">
    <button class="select_buttons"
        onclick="$('.child').toggleClass('checked_pic', true);
                $('.submit_buttons').button('enable');" >
        Select All
    </button>
    <button class="select_buttons"
            onclick="$('.child').toggleClass('checked_pic', false); $('.submit_buttons').button('disable');">
        Deselect All
    </button>
    <button class="submit_buttons select_buttons" disabled="true"
            onclick="if ($('.checked_pic').length) {
                    var account = '';
                    $('.checked_pic').each(function(index) {
                        if (account == '' || account == $(this).attr('class').match(/account_[1-9]+/ig).toString()) {
                            account = $(this).attr('class').match(/account_[1-9]+/ig);
                        } else {
                            account = 'false';
                        }
                    });
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'check_in_out_form',
                            type: '<?php echo $type; ?>',
                            chid: $('.checked_pic input.chid').serializeArray(),
                            admin: true
                        },
                        success: function(data) {
                            $('#display_level').html(data);
                            refresh_all();
                        }
                    });
                }">
        Admin Check <?php echo ucfirst($type); ?>
    </button>
</div>
<div class="bottom center ui-corner-all">
    <button class="submit_buttons big_button textfill" disabled="true"
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
    Next
    </button>
</div>