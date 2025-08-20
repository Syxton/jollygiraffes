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
                        action: 'deactivate_account',
                        aid: '<?php echo $aid; ?>',
                    },
                    success: function(data) {
                        $('#admin_display').html(data);
                        refresh_all();
                        $('.only_when_active').show();
                    }
                });
            },
            function(){})">
    <?php echo icon("trash", "2"); ?>
</button>