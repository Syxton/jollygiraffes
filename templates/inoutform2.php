<?php echo $numpads; ?>
<?php echo go_home_button(); ?>
<div class="contact_headers">
    Who is checking them <?php echo $type; ?>?
</div>
<div class="container_main scroll-pane ui-corner-all fill_height_middle contact_select_at_checkout">
    <?php echo $children; ?>
</div>
<div class="container_main scroll-pane ui-corner-all fill_height_middle">
    <?php echo $contacts; ?>
</div>
<div class="bottom center ui-corner-all">
    <div class="optional_questions">
        <div style="display: flex;justify-content: space-evenly;">
            <?php echo $notes_header; ?>
        </div>
        <div style="display: flex;justify-content: space-evenly;">
            <?php echo $notes; ?>
        </div>
    </div>
    <button class="submit_buttons big_button textfill"
            onclick="if ($('.ui-selected').length) {
                        if ($('.ui-selected #cid_other').length && $('#admin_numpad').length) {
                            if ($('.ui-selected #cid_other').val().length > 0) {
                                <?php
                                if ($qnum) {
                                    echo `var selected = true;
                                    $('.notes_values').each(function() {
                                        selected = $(this).toggleSwitch({ toggleset: true } ) ? selected : false;
                                    });
                                    if (selected) {
                                        numpad('admin_numpad');
                                    }`;
                                } else {
                                    echo `numpad('admin_numpad');`;
                                }
                                ?>
                            } else {
                                CreateAlert(
                                    'dialog-confirm',
                                    'You must type a name for this person.',
                                    'Ok',
                                    function() {}
                                );
                            }
                        } else {
                            <?php
                            if ($qnum) {
                                echo `var selected = true;
                                $('.notes_values').each(function() {
                                    selected = $(this).toggleSwitch({ toggleset: true } ) ? selected : false;
                                });
                                if (selected) {
                                    numpad('numpad');
                                }`;
                            } else {
                                echo `numpad('numpad');`;
                            }
                            ?>
                            ' . $questions_open . '
                            numpad('numpad');
                            ' . $questions_closed . '
                        }
                    } else {
                        CreateAlert('dialog-confirm', 'You must select a contact.', 'Ok', function() {});
                    }">
        <span style="font-size:10px;">
            Check <?php echo ucfirst($type); ?>
        </span>
    </button>
</div>