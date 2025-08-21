<button style="font-size: 9px;" type="button"
        onclick="$.ajax({
                    type: 'POST',
                    url: 'ajax/ajax.php',
                    data: {
                        action: 'toggle_exemption',
                        id: '<?php echo $invoiceid; ?>',
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
                });">
    <?php echo $title; ?>
</button