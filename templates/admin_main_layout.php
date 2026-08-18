<!--
    NOTE: container_list / container_actions / container_info are
    siblings here — the flex context (.layout-flex-col) belongs on
    THEIR shared parent, which lives outside this partial. Add
    `layout-flex-col` to whatever wraps this template's output before
    relying on `.fill_height.layout-flex` below. See
    docs/CSS_MIGRATION.md.
-->
<div class="container_list scroll-pane ui-corner-all">
    <?php echo $header; ?>
    <?php echo $list; ?>
</div>
<div class="container_actions ui-corner-all" id="actions_div">
    <?php echo $actions; ?>
</div>
<div class="container_info ui-corner-all fill_height layout-flex" id="info_div">
    <?php echo $info; ?>
</div>