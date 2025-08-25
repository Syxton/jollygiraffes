<?php
$containerclass = isset($containerclass) ? $containerclass : '';
$containerstyles = isset($containerstyles) ? $containerstyles : '';
?>
<div class="<?php echo $containerclass; ?>" style="<?php echo $containerstyles; ?>">
    <?php
    $buttonclass = isset($buttonclass) ? $buttonclass : '';
    $buttonstyles = isset($buttonstyles) ? $buttonstyles : '';
    $action = isset($action) ? $action : '';
    $piconly = isset($piconly) ? $piconly : false;
    $includename = isset($includename) ? $includename : true;
    $afterbutton = isset($afterbutton) ? $afterbutton : '';
    echo get_children_button($chid, $buttonclass, $buttonstyles, $action, $piconly, $includename);
    echo $afterbutton;
    ?>
</div>
<?php
echo isset($aftercontent) ? $aftercontent : '';