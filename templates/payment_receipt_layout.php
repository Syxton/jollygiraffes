<div>
    <table style="width:100%;color: inherit;font: inherit;">
        <tr>
            <td style="width: 40px;">
                <?php echo $editbutton; ?>
            </td>
            <td>
                <table style="width: 100%;font-size: 11px;background-color: rgba(255, 255, 255, .4);border: 1px solid silver;">
                    <tr>
                        <td style="font-weight: bold;">
                            <?php echo $desc; ?>
                            $<?php echo number_format(abs($amount), 2); ?>
                            was added on <?php echo date('m/d/Y', display_time($time)); ?>
                        </td>
                    </tr>
                    <?php echo $note; ?>
                </table>
            </td>
            <td style="width: 50px;">
                <?php echo $deletebutton; ?>
            </td>
        </tr>
    </table>
</div>