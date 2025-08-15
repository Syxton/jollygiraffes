$.ajax({
    type: 'POST',
    url: 'ajax/ajax.php',
    data: { action: 'get_admin_children_form', chid: '<?php echo $chid; ?>' } ,
    success: function(data) { $('#admin_display').html(data); refresh_all(); }
});