<?php

$docs_selected = $notes_selected = $activity_selected = $reports_selected = "";
$tabkey        = empty($MYVARS->GET["values"]) ? false : array_search('tab', $MYVARS->GET["values"]);

// Determine the active tab
if ($tabkey !== false && !empty($MYVARS->GET["values"][$tabkey]["value"])) {
    $tab = $MYVARS->GET["values"][$tabkey]["value"];
} elseif (!empty($MYVARS->GET["tab"])) {
    $tab = $MYVARS->GET["tab"];
} else {
    $tab = "activity";
}

if (!empty($tab)) {
    if ($tab == "activity") {
        $info = get_activity_list(true, false, false, $cid);
        $activity_selected = "selected_button";
    } elseif ($tab == "reports") {
        $info = get_reports_list(true, false, false, false, $cid);
        $reports_selected = "selected_button";
    } else {
        $info = get_activity_list(true, false, false, $cid);
        $activity_selected = "selected_button";
    }
}

$returnme .= from_template("contacttabs.php", [
    "cid" => $cid,
    "activity_selected" => $activity_selected,
    "reports_selected" => $reports_selected,
]);

$returnme .= '
    <div id="subselect_div" class="scroll-pane infobox fill_height">
        ' . $info . '
    </div>';
