<button title="Delete Program"
        class="image_button"
        type="button"
        onclick="CreateConfirm(
            'dialog-confirm',
            'READ CAREFULLY!  This will delete the program and ALL enrollments and activity associated with it.  Are you sure you wish to do this?',
            'Yes',
            'No',
            function() {
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'delete_program',
                        pid: '<?php echo $pid; ?>',
                    },
                    success: function(data) {
                        $('#display_level').html(data);
                        refresh_all();
                        $('.only_when_active').show();
                    }
                });
            },
            function(){})">
    <?php echo get_icon('x'); ?>
</button>