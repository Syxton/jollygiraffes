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
    if ($tab == "documents") {
        $info = get_documents_list(true, false, $chid);
        $docs_selected = "selected_button";
    } elseif ($tab == "notes") {
        $info = get_notes_list(true, false, $chid);
        $notes_selected = "selected_button";
    } elseif ($tab == "activity") {
        $info = get_activity_list(true, false, $chid);
        $activity_selected = "selected_button";
    } elseif ($tab == "reports") {
        $info = get_reports_list(true, false, false, $chid);
        $reports_selected = "selected_button";
    } else {
        $info = get_activity_list(true, false, $chid);
        $docs_selected = "selected_button";
    }
}

$returnme .= from_template("childbutton.php", [
        "chid" => $chid,
        "containerstyles" => "text-align:center;",
        "buttonstyles" => "width:100px;height:100px;",
        "piconly" => true,
    ]) . from_template("childtabs.php", [
        "chid" => $chid,
        "activity_selected" => $activity_selected,
        "docs_selected" => $docs_selected,
        "notes_selected" => $notes_selected,
        "reports_selected" => $reports_selected,
    ]) . '
    <div id="subselect_div" class="scroll-pane infobox fill_height">
        ' . $info . '
    </div>';
