<button title="Delete Account"
        class="image_button"
        type="button"
        onclick="CreateConfirm(
            'dialog-confirm',
            'READ CAREFULLY!  Are you sure you wish to delete this account?',
            'Yes',
            'No',
            function() {
                $.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'delete_account',
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
    <?php echo get_icon('x'); ?>
</button>