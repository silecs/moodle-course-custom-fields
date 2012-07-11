<?php
global $CFG;

require_once($CFG->libdir . '/custominfo/lib.php');

/**
 * Base class for the customisable profile fields.
 */
abstract class profile_field_base extends custominfo_field_base {

    protected $objectname = 'user';
    protected $capability = 'moodle/user:update';

    // for compatibility with PHP4 code in sub-classes
    public function profile_field_base($fieldid=0, $objectid=0) {
        parent::__construct($fieldid, $objectid);
    }

    /**
     * Check if the field data is visible to the current user
     * @return  boolean
     */
    public function is_visible() {
        global $USER;

        switch ($this->field->visible) {
            case CUSTOMINFO_VISIBLE_ALL:
                return true;
            case CUSTOMINFO_VISIBLE_PRIVATE:
                if ($this->objectid == $USER->id) {
                    return true;
                } else {
                    return has_capability('moodle/user:viewalldetails',
                            get_context_instance(CONTEXT_USER, $this->objectid));
                }
            case CUSTOMINFO_VISIBLE_NONE:
            default:
                return has_capability($this->capability, get_context_instance(CONTEXT_USER, $this->objectid));
        }
    }
} /// End of class definition


/***** General purpose functions for customisable user profiles *****/

function profile_load_data($user) {
    global $CFG, $DB;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->id, $user->id);
            $formfield->edit_load_object_data($user);
        }
    }
}

/**
 * Print out the customisable categories and fields for a users profile
 * @param  object   instance of the moodleform class
 */
function profile_definition($mform) {
    global $CFG, $DB;

    // if user is "admin" fields are displayed regardless
    $update = has_capability('moodle/user:update', get_context_instance(CONTEXT_SYSTEM));

    $categories = $DB->get_records('custom_info_category', array('objectname' => 'user'), 'sortorder ASC');
    if ($categories) {
        foreach ($categories as $category) {
            $fields = $DB->get_records('custom_info_field', array('categoryid' => $category->id), 'sortorder ASC');
            if ($fields) {
                // check first if *any* fields will be displayed
                $display = false;
                foreach ($fields as $field) {
                    if ($field->visible != CUSTOMINFO_VISIBLE_NONE) {
                        $display = true;
                    }
                }

                // display the header and the fields
                if ($display or $update) {
                    $mform->addElement('header', 'category_'.$category->id, format_string($category->name));
                    foreach ($fields as $field) {
                        require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
                        $newfield = 'profile_field_'.$field->datatype;
                        $formfield = new $newfield($field->id);
                        $formfield->edit_field($mform);
                    }
                }
            }
        }
    }
}

function profile_definition_after_data($mform, $userid) {
    global $CFG, $DB;

    $userid = ($userid < 0) ? 0 : (int)$userid;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->id, $userid);
            $formfield->edit_after_data($mform);
        }
    }
}

function profile_validation($usernew, $files) {
    global $CFG, $DB;

    $err = array();
    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->id, $usernew->id);
            $err += $formfield->edit_validate_field($usernew, $files);
        }
    }
    return $err;
}

function profile_save_data($usernew) {
    global $CFG, $DB;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->id, $usernew->id);
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
                    require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
                    $newfield = 'profile_field_'.$field->datatype;
                    $formfield = new $newfield($field->id, $userid);
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
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->fieldid);
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
            require_once($CFG->libdir.'/custominfo/field/'.$field->datatype.'/field.class.php');
            $newfield = 'profile_field_'.$field->datatype;
            $formfield = new $newfield($field->id, $userid);
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
