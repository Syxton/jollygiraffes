<button title="Activate Account"
        class="image_button"
        type="button"
        onclick="CreateConfirm(
            'dialog-confirm',
            'Are you sure you wish to activate this account?',
            'Yes',
            'No',
            function() {
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'activate_account',
                        aid: '<?php echo $aid; ?>',
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