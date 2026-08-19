<?php echo $numpads; ?>
<?php echo go_home_button(); ?>
<div class="checkinoutsplit">
    <div class="list_of_children container_main scroll-pane ui-corner-all contact_select_at_checkout layout-flex">
        <?php echo $children; ?>
    </div>
    <div style="display:flex;flex-direction:column;">
        <div class="contact_headers">
            Who is checking them <?php echo $type; ?>?
        </div>
        <div class="list_of_contacts container_main scroll-pane ui-corner-all layout-flex">
            <?php echo $contacts; ?>
        </div>
    </div>
</div>
<div class="bottom center ui-corner-all" style="display: flex;flex-direction: column;align-items: center;">
    <div class="optional_questions">
        <div style="display: flex;justify-content: space-evenly;">
            <?php echo $notes_header; ?>
        </div>
        <div style="display: flex;justify-content: space-evenly;">
            <?php echo $notes; ?>
        </div>
    </div>
    <button class="submit_buttons big_button"
            onclick="if ($('.ui-selected').length) {
                        if ($('.ui-selected #cid_other').length && $('#admin_numpad').length) {
                            if ($('.ui-selected #cid_other').val().length > 0) {
                                <?php
                                if ($qnum) {
                                    echo "
                                    var selected = true;
                                    $('.notes_values').each(function() {
                                        selected = $(this).toggleSwitch({ toggleset: true } ) ? selected : false;
                                    });
                                    if (selected) {
                                        numpad('admin_numpad');
                                    }";
                                } else {
                                    echo "numpad('admin_numpad');";
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
                                echo "var selected = true;
                                $('.notes_values').each(function() {
                                    selected = $(this).toggleSwitch({ toggleset: true } ) ? selected : false;
                                });
                                if (selected) {
                                    numpad('numpad');
                                }";
                            } else {
                                echo "numpad('numpad');";
                            }
                            ?>
                            <?php echo $questions_open; ?>
                            numpad('numpad');
                            <?php echo $questions_closed; ?>
                        }
                    } else {
                        CreateAlert('dialog-confirm', 'You must select a contact.', 'Ok', function() {});
                    }">
        Finish Check <?php echo ucfirst($type); ?>
    </button>
</div>