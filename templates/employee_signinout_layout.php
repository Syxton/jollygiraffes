<?php echo $numpad; ?>
<?php echo $home_button; ?>
<br />
<input type="hidden" id="selectedemployee" />
<?php echo $showpaystub; ?>
<div style="display: flex;flex: 1;gap: 10px;justify-content: space-between;">
    <div class="buttoncontainer container_list ui-corner-all layout-flex">
        <div class="eventbutton ui-corner-all list_box">
            Sign In
        </div>
        <?php echo $out; ?>
    </div>
    <div class="buttoncontainer container_list ui-corner-all layout-flex">
        <div class="eventbutton ui-corner-all list_box">
            Sign Out
        </div>
        <?php echo $in; ?>
    </div>
</div>