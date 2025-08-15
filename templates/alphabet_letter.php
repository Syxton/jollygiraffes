<button class="keypad_buttons ui-corner-all"
        onclick="$('.keypad_buttons').toggleClass('selected_button', true);
                $('.keypad_buttons').not(this).toggleClass('selected_button', false);
                $('.child_wrapper').children().not('.letter_<?php echo $letter; ?>').parent().hide();
                $('.letter_<?php echo $letter; ?>').parent('.child_wrapper').show('fade');
                $('.scroll-pane').sbscroller('refresh');">
    <?php echo strtoupper($letter); ?>
</button>