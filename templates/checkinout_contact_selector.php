<ol class="selectable" id="selectable" style="width:100%">
    <?php if ($admin) { ?>
        <li class="ui-widget-content ui-selected">
            <span class="contact" style="display:inline-block;width:30px;">
                <input class="cid" id="cid_admin" name="cid_admin" type="hidden" value="admin" />
            </span>
            <span>
                Admin
            </span>
        </li>
    <?php } ?>
    <?php echo $contacts; ?>
    <?php if (!$admin) { ?>
        <li class="ui-widget-content" id="other_li" rel="$('.keyboard').getkeyboard().reveal();">
            <span class="contact fill_width" style="display:inline-block;background-color:initial;">
                Other
                <input class="cid keyboard autocapitalizewords"
                    id="cid_other"
                    name="cid_other"
                    type="text"
                    value=""
                    onMousedown="SelectSelectableElements($('#selectable'),$('#other_li'));" />
            </span>
        </li>
    <?php } ?>
</ol>