<button class="big_button signinout"
        onclick="$('.employee_button').hide(); $('.kiosk_button').hide();
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'get_check_in_out_form',
                        type: '<?php echo $type; ?>',
                    },
                    success: function(data) {
                        $('#display_level').html(data);
                        refresh_all();
                    }
                });">
    <?php echo $button_text; ?>
</button>