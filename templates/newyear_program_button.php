<button title="New Year"
        class="image_button"
        type="button"
        onclick="CreateConfirm(
            'dialog-confirm',
            'This will create a new program with the same settings and enrollments as the currently selected program.  Are you sure you wish to do this?',
            'Yes',
            'No',
            function() {
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'copy_program',
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
    <?php echo icon('clone', "2"); ?>
</button>