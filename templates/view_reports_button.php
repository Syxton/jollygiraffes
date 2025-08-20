<button title="View Reports"
        class="image_button toggle_view"
        type="button"
        onclick="$('.toggle_view').toggle();
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'get_reports_list',
                        pid: '<?php echo $pid; ?>',
                    },
                    success: function(data) {
                        $('#info_div').html(data);
                        refresh_all();
                    }
                });">
    <?php echo icon('chart-line'); ?>
</button>