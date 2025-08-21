<button title="View Account"
        class="image_button toggle_view"
        style="display:none;"
        type="button"
        onclick="$('.toggle_view').toggle();
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'get_info',
                        aid: '<?php echo $aid; ?>',
                    },
                    success: function(data) {
                        $('#info_div').html(data);
                        refresh_all();
                    }
                });">
    <?php echo icon('magnifying-glass', "2"); ?>
</button>