if ($('.chid_<?php echo $chid; ?>.checked_pic').length > 0) {
    if ($('#askme').val() == 1 &&
        $('.account_<?php echo $aid; ?>.checked_pic').not('.chid_<?php echo $chid; ?>').length > 1) {
        CreateConfirm('dialog-confirm', 'Deselect all other children from this account?', 'Yes', 'No',
            function() {
                $('.account_<?php echo $aid; ?>.checked_pic').toggleClass('checked_pic', false);
                if ($('.checked_pic').length) {
                    $('.submit_buttons').button('enable');
                } else {
                    $('.submit_buttons').button('disable');
                }
            } ,
            function() {
                $('#askme').val('0');
                $('.chid_<?php echo $chid; ?>').toggleClass('checked_pic', false);
                if ($('.checked_pic').length > 0) {
                    $('.submit_buttons').button('enable');
                } else {
                    $('.submit_buttons').button('disable');
                }
            }
        );
    } else {
        $('.chid_<?php echo $chid; ?>').toggleClass('checked_pic', false);
        if ($('.checked_pic').length > 0) {
            $('.submit_buttons').button('enable');
        } else {
            $('.submit_buttons').button('disable');
        }
    }
} else {
    if ($('#askme').val() == 1 &&
        $('.account_<?php echo $aid; ?>').not('.chid_<?php echo $chid; ?>').not('.checked_pic').length > 1) {
        CreateConfirm('dialog-confirm', 'Select all other children from this account?', 'Yes', 'No',
            function() {
                $('.account_<?php echo $aid; ?>').toggleClass('checked_pic', true);
                if ($('.checked_pic').length > 0) {
                    $('.submit_buttons').button('enable');
                } else {
                    $('.submit_buttons').button('disable');
                }
            } ,
            function() {
                $('#askme').val('0');
                $('.chid_<?php echo $chid; ?>').toggleClass('checked_pic', true);
                if ($('.checked_pic').length) {
                    $('.submit_buttons').button('enable');
                } else {
                    $('.submit_buttons').button('disable');
                }
            }
        );
    } else {
        $('.chid_<?php echo $chid; ?>').toggleClass('checked_pic', true);
        $('.submit_buttons').button('enable');
    }
}