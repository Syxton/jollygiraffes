<?php
    $leftclass = isset($leftclass) ? $leftclass : '';
    $rightclass = isset($rightclass) ? $rightclass : '';
    $left = isset($left) ? $left : '';
    $right = isset($right) ? $right : '';
?>

<div class="list_box_item_left <?php echo $leftclass; ?>" >
    <?php echo $left; ?>
</div>
<div class="list_box_item_right <?php echo $rightclass; ?>">
    <?php echo $right; ?>
</div>