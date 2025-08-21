$.ajax({
    type: 'POST',
    url: 'ajax/ajax.php',
    data: {
        action: 'view_invoices',
        aid: '<?php echo $aid; ?>',
        pid: '<?php echo $pid; ?>',
        orderbytime: '<?php echo $orderbytime; ?>',
        year: this.value,
    },
    success: function(data) {
        $('#info_div').html(data);
        refresh_all();
    }
});