<?php
    $orderbytime = isset($orderbytime) ? 'orderbytime: true,' : '';
?>
$.ajax({
    type: 'POST',
    url: 'ajax/ajax.php',
    data: {
        action: 'view_invoices',
        aid: '<?php echo $aid; ?>',
        pid: '<?php echo $pid; ?>',
        <?php echo $orderbytime; ?>
    },
    success: function(data) {
        $('#info_div').html(data);
        refresh_all();
    }
});