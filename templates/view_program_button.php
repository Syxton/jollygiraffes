<button title="View Program"
        class="image_button toggle_view"
        style="display:none;"
        type="button"
        onclick="$('.toggle_view').toggle();
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'get_info',
                        pid: '<?php echo $pid; ?>',
                    },
                    success: function(data) {
                        $('#info_div').html(data);
                        refresh_all();
                    }
                });">
    <?php echo get_icon('search'); ?>
</button>