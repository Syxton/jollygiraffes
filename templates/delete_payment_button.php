<button style="font-size: 9px;" type="button"
        onclick="CreateConfirm(
                    'dialog-confirm',
                    'Are you sure you want to delete this payment?',
                    'Yes',
                    'No',
                    function() {
                        $.ajax({
                            type: 'POST',
                            url: 'ajax/ajax.php',
                            data: {
                                action: 'delete_payment',
                                payid: '<?php echo $payid; ?>',
                            },
                            success: function(data) {
                                $.ajax({
                                    type: 'POST',
                                    url: 'ajax/ajax.php',
                                    data: {
                                        action: 'get_admin_billing_form',
                                        pid: '<?php echo $pid; ?>',
                                        aid: '<?php echo $aid; ?>',
                                    },
                                    success: function(data) {
                                        $('#admin_display').hide('fade', null, null, function() {
                                            $('#admin_display').html(data);
                                            refresh_all();
                                            $('#admin_display').show('fade');
                                        });
                                    }
                                });
                            }
                        });
                    },
                    function(){});">
    Delete
</button>
