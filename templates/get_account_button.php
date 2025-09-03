<?php
$aid = isset($aid) ? $aid : "";
?>
<button title="Go to account"
        class="image_button"
        type="button"
        onclick="$.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'get_admin_accounts_form',
                        aid: '<?php echo $aid; ?>',
                    },
                    success: function(data) {
                        $('#admin_display').html(data);
                        refresh_all();
                        $('.keypad_buttons').toggleClass('selected_button', true);
                        $('.keypad_buttons').not($('#accounts')).toggleClass('selected_button', false);
                    }
                });">
    <?php echo icon('magnifying-glass', "2"); ?>
</button>