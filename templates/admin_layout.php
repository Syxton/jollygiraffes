<div class="admin_menu">
    <button class="keypad_buttons <?php echo $account_selected; ?>" id="accounts"
            onclick="$('.keypad_buttons').toggleClass('selected_button', true);
                    $('.keypad_buttons').not(this).toggleClass('selected_button', false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'get_admin_accounts_form',
                            aid: '',
                        },
                        success: function(data) {
                            $('#admin_display').hide('fade', null, null, function() {
                                $('#admin_display').html(data);
                                refresh_all();
                                $('#admin_display').show('fade');
                            });
                        }
                    });">
        Accounts
    </button>
    <button class="keypad_buttons <?php echo $enrollment_selected; ?>" id="admin_menu_programs"
            onclick="$('.keypad_buttons').toggleClass('selected_button',true);
                    $('.keypad_buttons').not(this).toggleClass('selected_button',false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'get_admin_enrollment_form',
                            chid: '',
                        },
                        success: function(data) {
                            $('#admin_display').hide('fade', null, null, function() {
                                $('#admin_display').html(data);
                                refresh_all();
                                $('#admin_display').show('fade');
                                refresh_all();
                            });
                        }
                    });">
        Programs
    </button>
    <button class="keypad_buttons <?php echo $tag_selected; ?>" id="admin_menu_tags"
            onclick="$('.keypad_buttons').toggleClass('selected_button',true);
                    $('.keypad_buttons').not(this).toggleClass('selected_button',false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'get_admin_tags_form',
                            cid: '',
                        },
                        success: function(data) {
                            $('#admin_display').hide('fade', null, null, function() {
                                $('#admin_display').html(data);
                                refresh_all();
                                $('#admin_display').show('fade');
                            });
                        }
                    });">
        Tags
    </button>
    <button class="keypad_buttons <?php echo $children_selected; ?> only_when_active"
            style="<?php echo $active; ?>" id="admin_menu_children"
            onclick="$('.keypad_buttons').toggleClass('selected_button',true);
                    $('.keypad_buttons').not(this).toggleClass('selected_button',false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'get_admin_children_form',
                            chid: '',
                        },
                        success: function(data) {
                            $('#admin_display').hide('fade', null, null, function() {
                                $('#admin_display').html(data);
                                refresh_all();
                                $('#admin_display').show('fade');
                            });
                        }
                    });">
        Children
    </button>
    <button class="keypad_buttons <?php echo $contacts_selected; ?> only_when_active"
            style="<?php echo $active; ?>" id="admin_menu_contacts"
            onclick="$('.keypad_buttons').toggleClass('selected_button',true);
                    $('.keypad_buttons').not(this).toggleClass('selected_button',false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'get_admin_contacts_form',
                            cid: '',
                        },
                        success: function(data) {
                            $('#admin_display').hide('fade', null, null, function() {
                                $('#admin_display').html(data);
                                refresh_all();
                                $('#admin_display').show('fade');
                            });
                        }
                    });">
        Contacts
    </button>
    <button class="keypad_buttons <?php echo $employees_selected; ?> only_when_active"
            style="<?php echo $active; ?>" id="admin_menu_employees"
            onclick="$('.keypad_buttons').toggleClass('selected_button',true);
                    $('.keypad_buttons').not(this).toggleClass('selected_button',false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'get_admin_employees_form',
                            employeeid: '',
                        },
                        success: function(data) {
                            $('#admin_display').hide('fade', null, null, function() {
                                $('#admin_display').html(data);
                                refresh_all();
                                $('#admin_display').show('fade');
                            });
                        }
                    });">
        Employees
    </button>
    <button class="keypad_buttons <?php echo $billing_selected; ?> only_when_active"
            style="<?php echo $active; ?>" id="admin_menu_billing"
            onclick="$('.keypad_buttons').toggleClass('selected_button',true);
                    $('.keypad_buttons').not(this).toggleClass('selected_button',false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: {
                            action: 'get_admin_billing_form',
                            pid: '<?php echo $pid; ?>',
                        },
                        success: function(data) {
                            $('#admin_display').hide('fade', null, null, function() {
                                $('#admin_display').html(data);
                                refresh_all();
                                $('#admin_display').show('fade');
                            });
                        }
                    });">
        Billing
    </button>
</div>
<div id="admin_display" class="admin_display fill_height">
    <?php echo $content; ?>
</div>
