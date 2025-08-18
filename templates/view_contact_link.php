&nbsp;
<a  href="javascript: void(0);"
    onclick="$.ajax({
                type: 'POST',
                url: 'ajax/ajax.php',
                data: {
                    action: 'get_admin_contacts_form',
                    cid: '<?php echo $cid; ?>',
                },
                success: function(data) {
                    $('#admin_display').html(data);
                    refresh_all();
                }
            });
            $('.keypad_buttons').toggleClass('selected_button', true);
            $('.keypad_buttons').not($('#admin_menu_contacts')).toggleClass('selected_button', false);">
    <span class="inline-button ui-corner-all">
        <?php echo get_icon('magnifier_zoom_in'); ?>
    </span>
</a>