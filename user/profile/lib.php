<?php
global $CFG;

require_once($CFG->libdir . '/custominfo/lib.php');

/***** General purpose functions for customisable user profiles *****/

function profile_load_data($user) {
    global $DB;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            $formfield = custominfo_field_factory("user", $field->datatype, $field->id, $user->id);
            $formfield->edit_load_object_data($user);
        }
    }
}

function profile_save_data($usernew) {
    global $CFG, $DB;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            $formfield = custominfo_field_factory("user", $field->datatype, $field->id, $usernew->id);
            $formfield->edit_save_data($usernew);
        }
    }
}

function profile_display_fields($userid) {
    global $CFG, $DB;

    $categories = $DB->get_records('custom_info_category', array('objectname' => 'user'), 'sortorder ASC');
    if ($categories) {
        foreach ($categories as $category) {
            $fields = $DB->get_records('custom_info_field', array('categoryid' => $category->id), 'sortorder ASC');
            if ($fields) {
                foreach ($fields as $field) {
                    $formfield = custominfo_field_factory("user", $field->datatype, $field->id, $userid);
                    if ($formfield->is_visible() and !$formfield->is_empty()) {
                        print_row(format_string($formfield->field->name.':'), $formfield->display_data());
                    }
                }
            }
        }
    }
}

/**
 * Adds code snippet to a moodle form object for custom profile fields that
 * should appear on the signup page
 * @param  object  moodle form object
 */
function profile_signup_fields($mform) {
    global $CFG, $DB;

     //only retrieve required custom fields (with category information)
    //results are sort by categories, then by fields
    $sql = "SELECT f.id as fieldid, c.id as categoryid, c.name as categoryname, f.datatype
                FROM {custom_info_field} f
                JOIN {custom_info_category} c
                ON f.categoryid = c.id
                WHERE ( c.objectname = 'user' AND f.signup = 1 AND f.visible<>0 )
                ORDER BY c.sortorder ASC, f.sortorder ASC";
    $fields = $DB->get_records_sql($sql);
    if ($fields) {
        $currentcat = null;
        foreach ($fields as $field) {
            //check if we change the categories
            if (!isset($currentcat) || $currentcat != $field->categoryid) {
                 $currentcat = $field->categoryid;
                 $mform->addElement('header', 'category_'.$field->categoryid, format_string($field->categoryname));
            }
            $formfield = custominfo_field_factory("user", $field->datatype, $field->fieldid);
            $formfield->edit_field($mform);
        }
    }
}

/**
 * Returns an object with the custom profile fields set for the given user
 * @param  integer  userid
 * @return  object
 */
function profile_user_record($userid) {
    global $CFG, $DB;

    $usercustomfields = new stdClass();

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            $formfield = custominfo_field_factory("user", $field->datatype, $field->id, $userid);
            if ($formfield->is_object_data()) {
                $usercustomfields->{$field->shortname} = $formfield->data;
            }
        }
    }

    return $usercustomfields;
}

/**
 * Load custom profile fields into user object
 *
 * Please note originally in 1.9 we were using the custom field names directly,
 * but it was causing unexpected collisions when adding new fields to user table,
 * so instead we now use 'profile_' prefix.
 *
 * @param object $user user object
 * @return void $user object is modified
 */
function profile_load_custom_fields($user) {
    $user->profile = (array)profile_user_record($user->id);
}
