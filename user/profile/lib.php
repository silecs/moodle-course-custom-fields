<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Profile field API library file.
 *
 * @package core_user
 * @copyright  2007 onwards Shane Elliot {@link http://pukunui.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/custominfo/lib.php');

/**
 * Base class for the customisable profile fields.
 *
 * @package core_user
 * @copyright  2007 onwards Shane Elliot {@link http://pukunui.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class profile_field_base extends custominfo_field_base {

    protected $objectname = 'user';
    protected $capability = 'moodle/user:update';

    /**
     * Abstract method: Adds the profile field to the moodle form class
     * @abstract The following methods must be overwritten by child classes
     * @param moodleform $mform instance of the moodleform class
     */
    public function edit_field_add($mform) {
        print_error('mustbeoveride', 'debug', '', 'edit_field_add');
    }

    /**
     * Display the data for this field
     * @return string
     */
    public function display_data() {
        $options = new stdClass();
        $options->para = false;
        return format_text($this->data, FORMAT_MOODLE, $options);
    }

    /**
     * Print out the form field in the edit profile page
     * @param moodleform $mform instance of the moodleform class
     * @return bool
     */
    public function edit_field($mform) {
        if ($this->field->visible != PROFILE_VISIBLE_NONE
          or has_capability('moodle/user:update', context_system::instance())) {

            $this->edit_field_add($mform);
            $this->edit_field_set_default($mform);
            $this->edit_field_set_required($mform);
            return true;
        }
        return false;
    }

    /**
     * Tweaks the edit form
     * @param moodleform $mform instance of the moodleform class
     * @return bool
     */
    public function edit_after_data($mform) {
        if ($this->field->visible != PROFILE_VISIBLE_NONE
          or has_capability('moodle/user:update', context_system::instance())) {
            $this->edit_field_set_locked($mform);
            return true;
        }
        return false;
    }

    /**
     * Saves the data coming from form
     * @param stdClass $usernew data coming from the form
     * @return mixed returns data id if success of db insert/update, false on fail, 0 if not permitted
     */
    public function edit_save_data($usernew) {
        global $DB;

        if (!isset($usernew->{$this->inputname})) {
            // Field not present in form, probably locked and invisible - skip it.
            return;
        }

        $data = new stdClass();

        $usernew->{$this->inputname} = $this->edit_save_data_preprocess($usernew->{$this->inputname}, $data);

        $data->objectname = 'user';
        $data->objectid = $usernew->id;
        $data->fieldid = $this->field->id;
        $data->data    = $usernew->{$this->inputname};

        if ($dataid = $DB->get_field('custom_info_data', 'id', array('objectid' => $data->objectid, 'fieldid' => $data->fieldid))) {
            $data->id = $dataid;
            $DB->update_record('custom_info_data', $data);
        } else {
            $DB->insert_record('custom_info_data', $data);
        }
    }

    /**
     * Validate the form field from profile page
     *
     * @param stdClass $usernew
     * @return  string  contains error message otherwise null
     */
    public function edit_validate_field($usernew) {
        global $DB;

        $errors = array();
        // Get input value.
        if (isset($usernew->{$this->inputname})) {
            if (is_array($usernew->{$this->inputname}) && isset($usernew->{$this->inputname}['text'])) {
                $value = $usernew->{$this->inputname}['text'];
            } else {
                $value = $usernew->{$this->inputname};
            }
        } else {
            $value = '';
        }

        // Check for uniqueness of data if required.
        if ($this->is_unique() && (($value !== '') || $this->is_required())) {
            $data = $DB->get_records_sql('
                    SELECT id, objectid
                      FROM {custom_info_data}
                     WHERE fieldid = ?
                       AND ' . $DB->sql_compare_text('data', 255) . ' = ' . $DB->sql_compare_text('?', 255),
                    array($this->field->id, $value));
            if ($data) {
                $existing = false;
                foreach ($data as $v) {
                    if ($v->objectid == $usernew->id) {
                        $existing = true;
                        break;
                    }
                }
                if (!$existing) {
                    $errors[$this->inputname] = get_string('valuealreadyused');
                }
            }
        }
        return $errors;
    }

    /**
     * Sets the default data for the field in the form object
     * @param  moodleform $mform instance of the moodleform class
     */
    public function edit_field_set_default($mform) {
        if (!empty($default)) {
            $mform->setDefault($this->inputname, $this->field->defaultdata);
        }
    }

    /**
     * Sets the required flag for the field in the form object
     *
     * @param moodleform $mform instance of the moodleform class
     */
    public function edit_field_set_required($mform) {
        global $USER;
        if ($this->is_required() && ($this->userid == $USER->id || isguestuser())) {
            $mform->addRule($this->inputname, get_string('required'), 'required', null, 'client');
        }
    }

    /**
     * HardFreeze the field if locked.
     * @param moodleform $mform instance of the moodleform class
     */
    public function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked() and !has_capability('moodle/user:update', context_system::instance())) {
            $mform->hardFreeze($this->inputname);
            $mform->setConstant($this->inputname, $this->data);
        }
    }

    /**
     * Hook for child classess to process the data before it gets saved in database
     * @param stdClass $data
     * @param stdClass $datarecord The object that will be used to save the record
     * @return  mixed
     */
    public function edit_save_data_preprocess($data, $datarecord) {
        return $data;
    }

    /**
     * Loads a user object with data for this field ready for the edit profile
     * form
     * @param stdClass $user a user object
     */
    public function edit_load_user_data($user) {
        if ($this->data !== null) {
            $user->{$this->inputname} = $this->data;
        }
    }

    /**
     * Check if the field data should be loaded into the user object
     * By default it is, but for field types where the data may be potentially
     * large, the child class should override this and return false
     * @return bool
     */
    public function is_user_object_data() {
        return true;
    }

    /**
     * Accessor method: set the userid for this instance
     * @internal This method should not generally be overwritten by child classes.
     * @param integer $userid id from the user table
     */
    public function set_userid($userid) {
        $this->userid = $userid;
    }

    /**
     * Accessor method: set the fieldid for this instance
     * @internal This method should not generally be overwritten by child classes.
     * @param integer $fieldid id from the user_info_field table
     */
    public function set_fieldid($fieldid) {
        $this->fieldid = $fieldid;
    }

    /**
     * Accessor method: Load the field record and user data associated with the
     * object's fieldid and userid
     * @internal This method should not generally be overwritten by child classes.
     */
    public function load_data() {
        global $DB;

        // Load the field object.
        if (($this->fieldid == 0) or (!($field = $DB->get_record('custom_info_field',
                array('objectname' => 'user', 'id' => $this->fieldid))))) {
            $this->field = null;
            $this->inputname = '';
        } else {
            $this->field = $field;
            $this->inputname = 'profile_field_'.$field->shortname;
        }

        if (!empty($this->field)) {
            $params = array('objectid' => $this->userid, 'fieldid' => $this->fieldid);
            if ($data = $DB->get_record('custom_info_data', $params, 'data, dataformat')) {
                $this->data = $data->data;
                $this->dataformat = $data->dataformat;
            } else {
                $this->data = $this->field->defaultdata;
                $this->dataformat = FORMAT_HTML;
            }
        } else {
            $this->data = null;
        }
    }

    // for compatibility with PHP4 code in sub-classes
    public function profile_field_base($fieldid=0, $objectid=0) {
        parent::__construct($fieldid, $objectid);
    }

    /**
     * Check if the field data is visible to the current user
     * @internal This method should not generally be overwritten by child classes.
     * @return bool
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
                            context_user::instance($this->objectid));
                }
            case CUSTOMINFO_VISIBLE_NONE:
            default:
                return has_capability($this->capability, context_user::instance($this->objectid));
        }
    }
} /// End of class definition


/***** General purpose functions for customisable user profiles *****/

/**
 * Create a new instance of a child class of custominfo_field_base.
 *
 * @TODO This temporary function will migrate into a generic custominfo function.
 *       Then it will use a local implementation of custominfo_field_extension placed in (user|course)/custominfo/locallib.php.
 *
 * @param object $fieldtype  The custominfo field type
 * @param object $fieldid    (opt) The field id
 * @param integer $objectid  (opt) The objectid to fill the field from
 * @return custominfo_field_base
 */
function profile_field_factory($fieldtype, $fieldid=0, $objectid=0) {
    global $CFG;
    require_once($CFG->libdir.'/custominfo/field/'.$fieldtype.'/field.class.php');
    $newfield = 'profile_field_'.$fieldtype;
    if (empty($fieldid)) {
        return (new $newfield());
    } else {
        return (new $newfield($fieldid, $objectid));
    }
}

function profile_load_data($user) {
    global $DB;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            $formfield = profile_field_factory($field->datatype, $field->id, $user->id);
            $formfield->edit_load_object_data($user);
        }
    }
}

/**
 * Print out the customisable categories and fields for a users profile
 *
 * @param moodleform $mform instance of the moodleform class
 * @param int $userid id of user whose profile is being edited.
 */
function profile_definition($mform, $userid = 0) {
    global $CFG, $DB;

    // If user is "admin" fields are displayed regardless.
    $update = has_capability('moodle/user:update', context_system::instance());

    $categories = $DB->get_records('custom_info_category', array('objectname' => 'user'), 'sortorder ASC');
    if ($categories) {
        foreach ($categories as $category) {
            $fields = $DB->get_records('custom_info_field', array('categoryid' => $category->id), 'sortorder ASC');
            if ($fields) {
                // Check first if *any* fields will be displayed.
                $display = false;
                foreach ($fields as $field) {
                    if ($field->visible != CUSTOMINFO_VISIBLE_NONE) {
                        $display = true;
                    }
                }

                // Display the header and the fields.
                if ($display or $update) {
                    $mform->addElement('header', 'category_'.$category->id, format_string($category->name));
                    foreach ($fields as $field) {
                        $formfield = profile_field_factory($field->datatype, $field->id);
                        $formfield->edit_field($mform);
                    }
                }
            }
        }
    }
}

/**
 * Adds profile fields to user edit forms.
 * @param moodleform $mform
 * @param int $userid
 */
function profile_definition_after_data($mform, $userid) {
    global $CFG, $DB;

    $userid = ($userid < 0) ? 0 : (int)$userid;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            $formfield = profile_field_factory($field->datatype, $field->id, $userid);
            $formfield->edit_after_data($mform);
        }
    }
}

/**
 * Validates profile data.
 * @param stdClass $usernew
 * @param array $files
 * @return array
 */
function profile_validation($usernew, $files) {
    global $CFG, $DB;

    $err = array();
    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            $formfield = profile_field_factory($field->datatype, $field->id, $usernew->id);
            $err += $formfield->edit_validate_field($usernew, $files);
        }
    }
    return $err;
}

/**
 * Saves profile data for a user.
 * @param stdClass $usernew
 */
function profile_save_data($usernew) {
    global $CFG, $DB;

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            $formfield = profile_field_factory($field->datatype, $field->id, $usernew->id);
            $formfield->edit_save_data($usernew);
        }
    }
}

/**
 * Display profile fields.
 * @param int $userid
 */
function profile_display_fields($userid) {
    global $CFG, $DB;

    $categories = $DB->get_records('custom_info_category', array('objectname' => 'user'), 'sortorder ASC');
    if ($categories) {
        foreach ($categories as $category) {
            $fields = $DB->get_records('custom_info_field', array('categoryid' => $category->id), 'sortorder ASC');
            if ($fields) {
                foreach ($fields as $field) {
                    $formfield = profile_field_factory($field->datatype, $field->id, $userid);
                    if ($formfield->is_visible() and !$formfield->is_empty()) {
                        echo html_writer::tag('dt', format_string($formfield->field->name));
                        echo html_writer::tag('dd', $formfield->display_data());
                    }
                }
            }
        }
    }
}

/**
 * Adds code snippet to a moodle form object for custom profile fields that
 * should appear on the signup page
 * @param moodleform $mform moodle form object
 */
function profile_signup_fields($mform) {
    global $CFG, $DB;

    // Only retrieve required custom fields (with category information)
    // results are sort by categories, then by fields.
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
            // Check if we change the categories.
            if (!isset($currentcat) || $currentcat != $field->categoryid) {
                 $currentcat = $field->categoryid;
                 $mform->addElement('header', 'category_'.$field->categoryid, format_string($field->categoryname));
            }
            $formfield = profile_field_factory($field->datatype, $field->fieldid);
            $formfield->edit_field($mform);
        }
    }
}

/**
 * Returns an object with the custom profile fields set for the given user
 * @param integer $userid
 * @return stdClass
 */
function profile_user_record($userid) {
    global $CFG, $DB;

    $usercustomfields = new stdClass();

    $fields = $DB->get_records('custom_info_field', array('objectname' => 'user'));
    if ($fields) {
        foreach ($fields as $field) {
            $formfield = profile_field_factory($field->datatype, $field->id, $userid);
            if ($formfield->is_object_data()) {
                $usercustomfields->{$field->shortname} = $formfield->data;
            }
        }
    }

    return $usercustomfields;
}

/**
 * Obtains a list of all available custom profile fields, indexed by id.
 *
 * Some profile fields are not included in the user object data (see
 * profile_user_record function above). Optionally, you can obtain only those
 * fields that are included in the user object.
 *
 * To be clear, this function returns the available fields, and does not
 * return the field values for a particular user.
 *
 * @param bool $onlyinuserobject True if you only want the ones in $USER
 * @return array Array of field objects from database (indexed by id)
 * @since Moodle 2.7.1
 */
function profile_get_custom_fields($onlyinuserobject = false) {
    global $DB, $CFG;

    // Get all the fields.
    $fields = $DB->get_records('user_info_field', null, 'id ASC');

    // If only doing the user object ones, unset the rest.
    if ($onlyinuserobject) {
        foreach ($fields as $id => $field) {
            require_once($CFG->dirroot . '/user/profile/field/' .
                    $field->datatype . '/field.class.php');
            $newfield = 'profile_field_' . $field->datatype;
            $formfield = new $newfield();
            if (!$formfield->is_user_object_data()) {
                unset($fields[$id]);
            }
        }
    }

    return $fields;
}

/**
 * Load custom profile fields into user object
 *
 * Please note originally in 1.9 we were using the custom field names directly,
 * but it was causing unexpected collisions when adding new fields to user table,
 * so instead we now use 'profile_' prefix.
 *
 * @param stdClass $user user object
 */
function profile_load_custom_fields($user) {
    $user->profile = (array)profile_user_record($user->id);
}
