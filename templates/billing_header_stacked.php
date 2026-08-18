<table style="width:100%;color: inherit;font: inherit;">
    <tr>
        <td class="area_toggler" style="width: 16px;">
            <?php echo icon('square-caret-right'); ?>
        </td>
        <td>
            Week of <span class="hide_mobile"><?php echo date('F \t\h\e jS, Y', $weekof); ?></span>
            <span class="show_mobile">
                <?php echo date('m/d/Y', $weekof); ?>
            </span>
        </td>
        <td style="width:100%;text-align:right">
            <strong>Bill: </strong>
            $<?php echo number_format($amount, 2); ?>
        </td>
    </tr>
</table>