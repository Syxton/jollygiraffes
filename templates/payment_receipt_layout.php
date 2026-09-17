<div>
    <div style="display: flex;gap: 15px;align-items: center;width: 100%">
        <div style="width: 36px">
            <?php echo $editbutton; ?>
        </div>
        <div style="flex: 1;">
            <?php echo $desc; ?>
            $<?php echo number_format(abs($amount), 2); ?>
            was added on <?php echo date('m/d/Y', display_time($time)); ?>
        </div>
        <div style="width: 36px">
            <?php echo $deletebutton; ?>
        </div>
    </div>
</div>