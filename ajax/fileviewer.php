<?php

/***************************************************************************
* fileviewer.php - Main backend ajax script.  Usually sends off to feature libraries.
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/13/2012
* Revision: 2.9.6
***************************************************************************/

include('header.php');
include_once($CFG->dirroot . '/lib/status_lib.php');
status_start_session();

$MYVARS->GET = $_GET;

$did = empty($MYVARS->GET["did"]) ? false : $MYVARS->GET["did"];
$aid = empty($MYVARS->GET["aid"]) ? false : $MYVARS->GET["aid"];
$chid = empty($MYVARS->GET["chid"]) ? false : $MYVARS->GET["chid"];
$cid = empty($MYVARS->GET["cid"]) ? false : $MYVARS->GET["cid"];
$actid = empty($MYVARS->GET["actid"]) ? false : $MYVARS->GET["actid"];

// Must be logged in, and only allowed to browse documents for a child/contact/account/activity
// the current session actually has rights to - mirrors the check files.php does per-file.
if (!status_current_role()) {
    exit;
}
if (!empty($did)) {
    $checkDoc = get_db_row("SELECT * FROM documents WHERE did='$did'");
    if (!$checkDoc || !status_can_access_document($checkDoc)) {
        exit;
    }
} elseif (!empty($chid) && !status_can_access_child($chid)) {
    exit;
} elseif (!empty($cid) && !status_can_access_contact($cid)) {
    exit;
} elseif (!empty($aid) && !status_can_access_account($aid)) {
    exit;
}
// Note: $actid (activity attachments) is intentionally viewable by any logged-in session,
// same as status_can_access_document treats it - activities aren't tied to one family.

$document = get_db_row("SELECT * FROM documents WHERE did='$did'");
$returnme = '<div style="left: 47.5%;position: fixed;display: block;top: 0;z-index: 100;"><button onclick="$(\'.printthis\').print();">PRINT</button></div><div id="printthis" class="printthis" style="text-align:center;position:absolute;">';

$returnme .= '<script type="text/javascript">';

if (!empty($did)) {
    $documents = get_db_result("SELECT * FROM documents WHERE did='$did'");
    if (!empty($document["chid"])) {
        $folder = "children/" . $document["chid"];
    } elseif (!empty($document["cid"])) {
        $folder = "contacts/" . $document["cid"];
    } elseif (!empty($document["actid"])) {
        $folder = "activities/" . $document["actid"];
    } elseif (!empty($document["aid"])) {
        $folder = "accounts/" . $document["aid"];
    }
} else {
    if (!empty($chid)) {
        $folder = "children/" . $chid;
        $documents = get_db_result("SELECT * FROM documents WHERE tag!='avatar' AND chid='$chid' ORDER BY timelog DESC");
    } elseif (!empty($cid)) {
        $folder = "contacts/" . $cid;
        $documents = get_db_result("SELECT * FROM documents WHERE tag!='avatar' AND cid='$cid' ORDER BY timelog DESC");
    } elseif (!empty($actid)) {
        $folder = "activities/" . $actid;
        $documents = get_db_result("SELECT * FROM documents WHERE tag!='avatar' AND actid='$actid' ORDER BY timelog DESC");
    } elseif (!empty($aid)) {
        $folder = "accounts/" . $aid;
        $documents = get_db_result("SELECT * FROM documents WHERE tag!='avatar' AND aid='$aid' ORDER BY timelog DESC");
    }
}

if ($documents) {
    while ($document = fetch_row($documents)) {
        $returnme .= '
        $(function () {
          var img = new Image();

          // wrap our new image in jQuery, then:
          $(img)
            // once the image has loaded, execute this code
            .load(function () {
              // set the image hidden by default
              $(this).hide();
              $(this).addClass(\'doc\');
              // with the holding div #loader, apply:
              $(\'#printthis\').append(\'<div id="doc_' . $document["did"] . '" class="image_wrapper ui-corner-all"></div><br /><br />\');
              $(\'#doc_' . $document["did"] . '\').append(this);
              $(\'#doc_' . $document["did"] . '\').append(\'<div class="image_caption ui-corner-all">' . $document["description"] . '</div>\');
              // fade our image in to create a nice effect
              $(this).fadeIn(500,function(){ resize_modal(); });
            })

            // if there was an error loading the image, react accordingly
            .error(function () {
              // notify the user that the image could not be loaded
            })

            // *finally*, set the src attribute of the new image to our image
            .attr(\'src\', \'' . $CFG->fileserveurl . "?did=" . (int) $document["did"] . '\');
        });
        ';
    }
}



$returnme .= 'refresh_all();</script></div>';

echo $returnme;
