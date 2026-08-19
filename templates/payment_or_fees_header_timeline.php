<table style="width:100%;color: inherit;font: inherit;">
    <tr>
        <td class="area_toggler" style="width: 16px;">
            <?php echo icon('square-caret-right'); ?>
        </td>
        <td class="hide_mobile">
            <?php echo date('m/d/Y', display_time($time)); ?>
        </td>
        <td style="width:100px;text-align:right">
            <strong><?php echo $type; ?>: </strong>$<?php echo number_format($amount, 2); ?>
        </td>
    </tr>
</table>