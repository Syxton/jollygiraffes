<button title="Activate Program"
        class="image_button"
        type="button"
        onclick="CreateConfirm(
            'dialog-confirm',
            'Are you sure you wish to make this the active program?',
            'Yes',
            'No',
            function() {
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'activate_program',
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
    <?php echo get_icon('checkmark'); ?>
</button>