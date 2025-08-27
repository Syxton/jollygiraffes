<li class="ui-widget-content <?php echo ($selected ? "ui-selected" : ""); ?>">
    <span class="contact">
        <input
            class="cid"
            id="cid_<?php echo $contact["cid"]; ?>"
            name="cid_<?php echo $contact["cid"]; ?>"
            type="hidden"
            value="<?php echo $contact["cid"]; ?>" />
        <?php echo $contact["first"]; ?> <?php echo $contact["last"]; ?> - <?php echo $contact["relation"]; ?></span>
    </span>
    <span class="emergency_contact">
        <?php echo empty($contact["emergency"]) ? "" : icon('circle-exclamation'); ?>
    </span>
    <span class="primary_contact">
        <?php echo empty($contact["primary_address"]) ? "" : icon('star'); ?>
    </span>
</li>