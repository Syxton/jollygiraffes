<div class="info_tabbar">
    <button class="subselect_buttons <?php echo $activity_selected; ?>" id="activity"
            onclick="$('.subselect_buttons').toggleClass('selected_button', true);
                    $('.subselect_buttons').not(this).toggleClass('selected_button', false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: { action: 'get_activity_list', chid: '<?php echo $chid; ?>' } ,
                        success: function(data) { $('#subselect_div').html(data); refresh_all(); }
                    });">
        Activity
    </button>
    <button class="subselect_buttons <?php echo $docs_selected; ?>" id="documents"
            onclick="$('.subselect_buttons').toggleClass('selected_button', true);
                    $('.subselect_buttons').not(this).toggleClass('selected_button', false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: { action: 'get_documents_list', chid: '<?php echo $chid; ?>' } ,
                        success: function(data) { $('#subselect_div').html(data); refresh_all(); }
                    });">
        Documents
    </button>
    <button class="subselect_buttons <?php echo $notes_selected; ?>" id="notes"
            onclick="$('.subselect_buttons').toggleClass('selected_button', true);
                    $('.subselect_buttons').not(this).toggleClass('selected_button', false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: { action: 'get_notes_list', chid: '<?php echo $chid; ?>' } ,
                        success: function(data) { $('#subselect_div').html(data); refresh_all(); }
                    });">
        Notes
    </button>
    <button class="subselect_buttons <?php echo $reports_selected; ?>" id="reports"
            onclick="$('.subselect_buttons').toggleClass('selected_button',true);
                    $('.subselect_buttons').not(this).toggleClass('selected_button',false);
                    $.ajax({
                        type: 'POST',
                        url: 'ajax/ajax.php',
                        data: { action: 'get_reports_list', chid: '<?php echo $chid; ?>' } ,
                        success: function(data) { $('#subselect_div').html(data); refresh_all(); }
                    });">
        Reports
    </button>
    </div>