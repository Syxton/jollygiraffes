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