<table style="width:100%;color: inherit;font: inherit;">
    <tr>
        <td class="hide_mobile" style="width: 16px;">
            <?php echo get_icon('plusminus'); ?>
        </td>
        <td style="width:50%">
            Week of <span class="hide_mobile"><?php echo date('F \t\h\e jS, Y', $weekof); ?></span>
            <span class="show_mobile">
                <?php echo date('m/d/Y', $weekof); ?>
            </span>
        </td>
        <td style="width:50%;text-align:right">
            <strong>Bill: </strong>
            $<?php echo number_format($amount, 2); ?>
        </td>
    </tr>
</table>