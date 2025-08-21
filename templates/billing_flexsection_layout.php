<div class="ui-corner-all list_box <?php echo $class; ?>" style="<?php echo $style; ?>">
    <div class="flexsection">
        <?php
        if (!empty($contents)) {
            echo '<a href="javascript: void(0);" style="color: white;">';
        }
        ?>
        <?php echo $header; ?>
        <?php
        if (!empty($contents)) {
            echo '</a>';
        }
        ?>
    </div>
    <?php
    if (!empty($contents)) { ?>
    <div class="ui-corner-all" style="padding: 5px;color: black;background-color:lightgray">
    <?php
    }
    echo $contents;

    if (!empty($contents)) { ?>
    </div>
    <?php
    }
    ?>
</div>