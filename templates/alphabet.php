<div class="fill_width_middle alphabet_filter" style="margin:0px 10px;padding:5px;white-space:nowrap;">
    <div class="label alphabet_label">
        Last Initial:
    </div>
    <div style="white-space:normal;">
        <button class="keypad_buttons selected_button ui-corner-all"
            onclick="$('.keypad_buttons').toggleClass('selected_button', true);
                    $('.keypad_buttons').not(this).toggleClass('selected_button', false);
                    $('.child_wrapper').show('fade');
                    $('.scroll-pane').sbscroller('refresh');">
            Show All
        </button>
        <?php echo $letters; ?>
    </div>
</div>