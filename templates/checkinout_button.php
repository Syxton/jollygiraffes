<button class="big_button signinout bb_middle textfill"
        onclick="$('.employee_button').hide();
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
    <span style="font-size:10px;">
        <?php echo $button_text; ?>
    </span>
</button>