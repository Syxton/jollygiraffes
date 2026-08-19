<?php

$SQL = "SELECT *
        FROM children
        WHERE chid IN (
            SELECT chid
            FROM enrollments
            WHERE pid = '$pid'
        )
        AND deleted = 0
        ORDER BY last, first";
// Program Children
if ($results = get_db_result($SQL)) {
    $children = "";
    while ($child = fetch_row($results)) {
        $identifier = time() . "child_" . $child["chid"];
        $enrolled   = is_enrolled($pid, $child["chid"]);

        // Get relavent child buttons.
        $buttons = "";
        if (!$recover) {
            // View Child Link.
            $view_child = "";
            if (($activepid == $pid) && $enrolled) {
                $view_child = from_template("view_child_link.php", ["chid" => $child["chid"]]);
            }

            // Enroll / Unenroll Action.
            $enroll_action = from_template("action_child_enroll_unenroll.php", [
                "chid" => $child["chid"],
                "pid" => $pid,
                "enrolled" => $enrolled,
                "identifier" => $identifier,
                "tabid" => "pid",
            ]);

            if ($enrolled) {
                // Edit Enrollment Buttons
                $enroll_button = from_template("create_link.php", [
                    "action" => "CreateDialog('add_edit_enrollment_" . $identifier . "', 200, 400)",
                    "button_text" => icon('list-check') . ' Edit Enrollment',
                ]);

                // Enroll / Unenroll Button
                $enroll_button .= from_template("create_link.php", [
                    "id" => "a-" . $child["chid"],
                    "data" => $child["first"] . " " . $child["last"],
                    "action" => $enroll_action,
                    "class" => "caution",
                    "button_text" => icon('ban') . ' Unenroll',
                ]);

                // Edit Enrollment Form params
                $params = [
                    "pid" => $pid,
                    "chid" => $child["chid"],
                    "aid" => $child["aid"],
                    "callback" => "programs",
                    "eid" => (string) $enrolled,
                ];
            } else {
                // Add Enrollment Button
                $enroll_button .= from_template("create_link.php", [
                    "id" => "a-" . $child["chid"],
                    "data" => $child["first"] . " " . $child["last"],
                    "action" => $enroll_action,
                    "button_text" => icon('users') . ' Enroll',
                ]);

                // Add Enrollment Form params
                $params = [
                    "pid" => $pid,
                    "chid" => $child["chid"],
                    "aid" => $child["aid"],
                    "callback" => "accounts",
                ];
            }

            // Add / Edit Enrollment Form
            $children .= get_form("add_edit_enrollment", $params, $identifier);

            // Edit Child Button
            $params = [
                "pid"      => $pid,
                "child"    => $child,
                "callback" => "programs",
            ];
            $children .= get_form("add_edit_child", $params, $identifier);

            $edit_button = from_template("add_edit_child_link.php", [
                "identifier" => $identifier,
                "icon" => "wrench",
                "label" => "Edit",
            ]);

            $buttons = $view_child . $edit_button . $enroll_button;
        }

        // Status Icon
        $status = "";
        if (($activepid == $pid) && $enrolled) {
            if (is_checked_in($child["chid"])) {
                $status = active_icon(true);
            } else {
                $status = active_icon(false);
            }
        }

        $afterbutton = from_template("after_child_button_layout.php", [
            "status" => $status,
            "name" => $child["first"] . ' ' . $child["last"],
            "buttons" => $buttons,
        ]);

        // Child list item.
        $children .= from_template("list_item_layout.php", [
            "item" => from_template("childbutton.php", [
                "chid" => $child["chid"],
                "containerclass" => "list_title",
                "containerstyles" => "display:flex;flex-wrap: nowrap;white-space: normal;",
                "buttonstyles" => "margin: 10px;float:none;height:50px;width:50px;",
                "piconly" => true,
                "includename" => false,
                "afterbutton" => $afterbutton,
                "aftercontent" => get_notifications($pid, $child["chid"], false, true, true),
            ]),
        ]);
    }

    $returnme .= '
        <div style="display:table-cell;font-weight: bold;font-size: 110%;padding: 10px;">
            Children:
        </div>
        <div id="children" class="scroll-pane infobox fill_height">
            ' . $children . '
        </div>
        <div style="clear:both;"></div>';
}
