<button title="Deactivate Program"
        class="image_button"
        type="button"
        onclick="CreateConfirm(
            'dialog-confirm',
            'Are you sure you wish to deactivate this program?',
            'Yes',
            'No',
            function() {
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'deactivate_program',
                        pid: '<?php echo $pid; ?>',
                    },
                    success: function(data) {
                        $('#display_level').html(data);
                        refresh_all();
                        $('.only_when_active').hide();
                    }
                });
            },
            function(){})">
    <?php echo get_icon('no'); ?>
</button>