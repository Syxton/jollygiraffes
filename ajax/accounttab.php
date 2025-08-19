<?php

$deleted = $recover ? "1" : "0";
// Account Children
$SQL = "SELECT *
        FROM children
        WHERE aid = '$aid'
        AND deleted = '$deleted'
        ORDER BY last, first";
if ($results = get_db_result($SQL)) {
    $children = "";
    while ($child = fetch_row($results)) {
        $identifier = time() . "child_" . $child["chid"];
        $enrolled   = is_enrolled($activepid, $child["chid"]);

        // Delete / Recover Child Button
        $delete_button = "";
        if ($activepid) {
            $recover_action = $recover ? "activate" : "delete";
            $delete_action = from_template("action_child_activation.php", [
                "chid" => $child["chid"],
                "aid"  => $child["aid"],
                "action" => $recover_action,
            ]);

            $delete_button = "";
            if ($recover) {
                $label = "Recover";
                $caution = "";
                $icon = "add";
            } else {
                $label = "Delete";
                $caution = "caution";
                $icon = "bin_closed";
            }

            $delete_button .= from_template("create_link.php", [
                "id" => "a-" . $child["chid"],
                "data" => $child["first"] . " " . $child["last"],
                "action" => $delete_action,
                "class" => "caution",
                "button_text" => get_icon($icon) . ' ' . $label,
            ]);
        }

        $buttons = $delete_button;
        if (!$recover) {
            // View Child Link.
            $view_child = "";
            if ($activepid && $enrolled) {
                $view_child = from_template("view_child_link.php", ["chid" => $child["chid"]]);
            }

            // Edit Child Form.
            $params = [
                "aid"   => $aid,
                "child" => $child,
            ];
            $children .= get_form("add_edit_child", $params, $identifier);

            // Edit Child Button.
            $edit_button = from_template("add_edit_child_link.php", [
                "identifier" => $identifier,
                "icon" => "wrench",
                "label" => "Edit",
            ]);

            $enroll_buttons = "";
            if ($activepid) {
                // Edit / Add Enrollment Form
                $params = [
                    "aid"      => $aid,
                    "pid"      => $activepid,
                    "chid"     => $child["chid"],
                    "callback" => "accounts",
                ];

                if ($enrolled) { // If the child is enrolled, we need to pass the enrollment ID.
                    $params["eid"] = (string) $enrolled;
                }

                $children .= get_form("add_edit_enrollment", $params, $identifier);

                // Enroll / Unenroll Button.
                $enroll_action = from_template("action_child_enroll_unenroll.php", [
                    "chid" => $child["chid"],
                    "pid" => $activepid,
                    "identifier" => $identifier,
                    "enrolled" => $enrolled,
                    "tabid" => "aid",
                    "aid"  => $child["aid"],
                ]);

                if ($enrolled) { // If the child is enrolled, we can edit or unenroll.
                    $enroll_buttons .= from_template("create_link.php", [
                        "action" => from_template("action_child_enroll_unenroll.php", [
                            "enrolled" => false, // Editing and Adding enrollment is the same call.
                            "identifier" => $identifier,
                        ]),
                        "button_text" => get_icon('report_edit') . ' Edit Enrollment',
                    ]);

                    $enroll_buttons .= from_template("create_link.php", [
                        "id" => "a-" . $child["chid"],
                        "data" => $child["first"] . " " . $child["last"],
                        "action" => $enroll_action,
                        "class" => "caution",
                        "button_text" => get_icon('report_delete') . ' Unenroll',
                    ]);
                } else { // If the child is not enrolled, we can enroll them.
                    $enroll_buttons .= from_template("create_link.php", [
                        "id" => "a-" . $child["chid"],
                        "data" => $child["first"] . " " . $child["last"],
                        "action" => $enroll_action,
                        "button_text" => get_icon('user_add') . ' Enroll',
                    ]);
                }
            }

            $buttons = $view_child . $edit_button . $enroll_buttons . $delete_button;
        }

        // Checked In info
        $checked_in = "";
        if ($activepid && $enrolled) {
            if (is_checked_in($child["chid"])) {
                $checked_in = get_icon('status_online');
            } elseif (empty($recover)) {
                $checked_in = get_icon('status_offline');
            }
        }

        // After Button Layout.
        $afterbutton = from_template("after_child_button_layout.php", [
            "status" => $checked_in,
            "name" => $child["first"] . ' ' . $child["last"],
            "buttons" => $buttons,
            "notifications" => get_notifications($activepid, $child["chid"], $aid, true, true),
        ]);

        $children .= from_template("list_item_layout.php", [
            "item" => from_template("childbutton.php", [
                "chid" => $child["chid"],
                "containerclass" => "list_title",
                "containerstyles" => "display:flex;flex-wrap: nowrap;white-space: normal;",
                "buttonstyles" => "margin: 10px;float:none;height:50px;width:50px;",
                "piconly" => true,
                "includename" => false,
                "afterbutton" => $afterbutton,
            ]),
        ]);
    }

    $returnme .= from_template("subsection.php", [
        "title" => "Children:",
        "content" => $children,
        "id" => "children",
    ]);
}

// Account Contacts
$SQL = "SELECT *
        FROM contacts
        WHERE aid = '$aid'
        AND deleted = '$deleted'
        ORDER BY emergency, last, first";
if ($results = get_db_result($SQL)) {
    $contacts = "";
    while ($contact = fetch_row($results)) {
        // Toggle Contact Activation Button
        $activation_button = "";
        if ($activepid) {
            $confirm_text  = $recover ? "activate" : "delete";
            $caution = $recover ? "" : "caution";
            $activation_button = from_template("toggle_contact_activation_link.php", [
                "aid" => $contact["aid"],
                "cid" => $contact["cid"],
                "name" => $contact["first"] . " " . $contact["last"],
                "text" => $confirm_text,
                "caution" => $caution,
                "icon" => $recover ? 'add' : 'bin_closed',
                "title" => $recover ? "Activate" : "Delete",
            ]);
        }

        $buttons = $activation_button;
        if (!$recover) {
            // View contact's button.
            $view_contact = from_template("view_contact_link.php", [
                "cid" => $contact["cid"],
            ]);

            // Edit Contact Button.
            $identifier = time() . "contact_" . $contact["cid"];
            $returnme .= get_form("add_edit_contact", [
                "contact" => $contact
            ], $identifier);

            $edit_button = from_template("add_edit_contact_link.php", [
                "identifier" => $identifier,
            ]);

            $buttons = $view_contact . $edit_button . $activation_button;
        }

        $primary   = empty($contact["primary_address"]) ? "" : "primary";
        $emergency = empty($contact["emergency"]) ? "" : "emergency";

        $contacts .= from_template("contact_list_item.php", [
            "name" => $contact["first"] . " " . $contact["last"],
            "buttons" => $buttons,
            "classes" => $primary . " " . $emergency,
        ]);
    }

    $returnme .= from_template("subsection.php", [
        "title" => "Contacts:",
        "content" => $contacts,
        "id" => "contacts",
    ]);
}
