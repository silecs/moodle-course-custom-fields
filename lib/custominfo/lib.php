<?php

/// Some constants

define ('CUSTOMINFO_VISIBLE_ALL',     '2'); // visible for all users
define ('CUSTOMINFO_VISIBLE_PRIVATE', '1'); // either it's our own profile or course, or we have moodle/user:update capability
define ('CUSTOMINFO_VISIBLE_NONE',    '0'); // only visible for moodle/user:update (or course) capability

require_once(__DIR__ . '/lib_models.php');
require_once(__DIR__ . '/lib_define.php');
require_once(__DIR__ . '/index_category_form.php');
require_once(__DIR__ . '/index_field_form.php');


/**
 * Base class for the customisable profile fields.
 * It will handle data linked to the fields.
 */
abstract class custominfo_field_base {

    /// These 2 variables are really what we're interested in.
    /// Everything else can be extracted from them
    protected $fieldid;
    protected $objectname;
    protected $objectid;

    public $field;
    public $inputname;
    public $data;
    public $dataformat;

    /* @var string */
    protected $capability;
    /** @var custominfo_field_extension_i */
    protected $extension;

    /**
     * Constructor method.
     * @param string $objectname The model has uses custominfo (e.g. user, course)
     * @param integer $fieldid    id of the profile from the custom_info_field table
     * @param integer $objectid   id of the object whose we are displaying data
     */
    public function __construct($objectname, $fieldid=0, $objectid=0) {
        global $CFG;
        $modelclass = 'custominfo_field_extension_' . $objectname;
        require_once($CFG->dirroot.'/'.$objectname.'/custominfo/locallib.php');
        $this->objectname = $objectname;
        $this->extension = new $modelclass();
        $this->capability = $this->extension->get_capability();
        $this->set_fieldid($fieldid);
        $this->set_objectid($objectid);
        $this->load_data();
    }


    /**
     * Abstract method: Adds the profile field to the moodle form class
     * @param form $form instance of the moodleform class
     */
    abstract public function edit_field_add($mform);


/***** The following methods may be overwritten by child classes *****/

    /**
     * Display the data for this field
     */
    function display_data() {
        $options = new stdClass();
        $options->para = false;
        return format_text($this->data, FORMAT_MOODLE, $options);
    }

    /**
     * Print out the form field in the edit profile page
     * @param   object  $mform instance of the moodleform class
     * $return  boolean
     */
    function edit_field($mform) {
        if ($this->field->visible != CUSTOMINFO_VISIBLE_NONE
                or has_capability($this->capability, get_context_instance(CONTEXT_SYSTEM))) {

            $this->edit_field_add($mform);
            $this->edit_field_set_default($mform);
            $this->edit_field_set_required($mform);
            return true;
        }
        return false;
    }

    /**
     * Tweaks the edit form
     * @param   object $mform  instance of the moodleform class
     * $return  boolean
     */
    function edit_after_data($mform) {
        if ($this->field->visible != CUSTOMINFO_VISIBLE_NONE
                or has_capability($this->capability, get_context_instance(CONTEXT_SYSTEM))) {
            $this->edit_field_set_locked($mform);
            return true;
        }
        return false;
    }

    /**
     * Saves the data coming from form
     * @param   mixed  $new data coming from the form
     * @return  mixed  returns data id if success of db insert/update, false on fail, 0 if not permitted
     */
    function edit_save_data($new) {
        global $DB;

        if (!isset($new->{$this->inputname})) {
            // field not present in form, probably locked and invisible - skip it
            return;
        }

        $data = new stdClass();

        $new->{$this->inputname} = $this->edit_save_data_preprocess($new->{$this->inputname}, $data);

        $data->objectname = $this->objectname;
        $data->objectid = $new->id;
        $data->fieldid = $this->field->id;
        $data->data    = $new->{$this->inputname};

        if ($dataid = $DB->get_field('custom_info_data', 'id', array('objectid' => $data->objectid, 'fieldid' => $data->fieldid))) {
            $data->id = $dataid;
            $DB->update_record('custom_info_data', $data);
        } else {
            $DB->insert_record('custom_info_data', $data);
        }
    }

    /**
     * Validate the form field from profile page
     * @return  string  contains error message otherwise NULL
     **/
    function edit_validate_field($new) {
        global $DB;

        $errors = array();
        /// Check for uniqueness of data if required
        if ($this->is_unique()) {
            $value = (is_array($new->{$this->inputname}) and isset($new->{$this->inputname}['text']))
                ? $new->{$this->inputname}['text']
                : $new->{$this->inputname};
            $data = $DB->get_records_sql('
                    SELECT id, objectid
                      FROM {custom_info_data}
                     WHERE fieldid = ?
                       AND ' . $DB->sql_compare_text('data', 255) . ' = ' . $DB->sql_compare_text('?', 255),
                    array($this->field->id, $value));
            if ($data) {
                $existing = false;
                foreach ($data as $v) {
                    if ($v->objectid == $new->id) {
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
     * @param   object $mform  instance of the moodleform class
     */
    function edit_field_set_default($mform) {
        if (!empty($default)) {
            $mform->setDefault($this->inputname, $this->field->defaultdata);
        }
    }

    /**
     * Sets the required flag for the field in the form object
     * @param   object $mform  instance of the moodleform class
     */
    function edit_field_set_required($mform) {
        if ($this->is_required() and !has_capability($this->capability, get_context_instance(CONTEXT_SYSTEM))) {
            $mform->addRule($this->inputname, get_string('required'), 'required', null, 'client');
        }
    }

    /**
     * HardFreeze the field if locked.
     * @param   object $mform  instance of the moodleform class
     */
    function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked() and !has_capability($this->capability, get_context_instance(CONTEXT_SYSTEM))) {
            $mform->hardFreeze($this->inputname);
            $mform->setConstant($this->inputname, $this->data);
        }
    }

    /**
     * Hook for child classess to process the data before it gets saved in database
     * @param   mixed    $data
     * @param   stdClass $datarecord The object that will be used to save the record
     * @return  mixed
     */
    function edit_save_data_preprocess($data, $datarecord) {
        return $data;
    }

    /**
     * Loads an object with data for this field ready for the edit profile
     * form
     * @param   object $object  an object that is to be modified
     */
    function edit_load_object_data($object) {
        if ($this->data !== NULL) {
            $object->{$this->inputname} = $this->data;
        }
    }

    /**
     * Check if the field data should be loaded into the object
     * By default it is, but for field types where the data may be potentially
     * large, the child class should override this and return false
     * @return boolean
     */
    function is_object_data() {
        return true;
    }


/***** The following methods generally should not be overwritten by child classes *****/

    /**
     * Accessor method: set the fieldid for this instance
     * @param   integer   $fieldid  id from the custom_info_field table
     */
    function set_fieldid($fieldid) {
        $this->fieldid = $fieldid;
    }

    /**
     * Accessor method: set the object id for this instance
     * @param integer $objectid   id from the $objectname table
     */
    function set_objectid($objectid) {
        $this->objectid = $objectid;
    }

    /**
     * Accessor method: Load the field record and the data associated with the object's fieldid and objectid
     */
    function load_data() {
        global $DB;

        /// Load the field object
        if ($this->fieldid == 0 or !($field = $DB->get_record('custom_info_field', array('id' => $this->fieldid)))) {
            $this->field = NULL;
            $this->inputname = '';
        } else {
            $this->field = $field;
            $this->inputname = 'custominfo_field_'.$field->shortname;
        }

        if (!empty($this->field)) {
            if ($data = $DB->get_record('custom_info_data',
                    array('objectid' => $this->objectid, 'fieldid' => $this->fieldid), 'data, dataformat')) {
                $this->data = $data->data;
                $this->dataformat = $data->dataformat;
            } else {
                $this->data = $this->field->defaultdata;
                $this->dataformat = FORMAT_HTML;
            }
        } else {
            $this->data = NULL;
        }
    }

    /**
     * Check if the field data is visible to the current user
     * @return  boolean
     */
    function is_visible() {
        return $this->extension->is_visible($this->field, $this->objectid);
    }

    /**
     * Check if the field data is considered empty
     * return boolean
     */
    function is_empty() {
        return (($this->data != '0') and empty($this->data));
    }

    /**
     * Check if the field is required on the edit profile page
     * @return   boolean
     */
    function is_required() {
        return (boolean)$this->field->required;
    }

    /**
     * Check if the field is locked on the edit profile page
     * @return   boolean
     */
    function is_locked() {
        return (boolean)$this->field->locked;
    }

    /**
     * Check if the field data should be unique
     * @return   boolean
     */
    function is_unique() {
        return (boolean)$this->field->forceunique;
    }

    /**
     * Check if the field should appear on the signup page
     * @return   boolean
     */
    function is_signup_field() {
        return (boolean)$this->field->signup;
    }

} /// End of class definition

/**
 * Create a new instance of a child class of custominfo_field_base.
 *
 * @param string $objectname The model has uses custominfo (user, course)
 * @param object $fieldtype  The custominfo field type
 * @param object $fieldid    (opt) The field id
 * @param integer $objectid  (opt) The objectid to fill the field from
 * @return custominfo_field_base
 */
function custominfo_field_factory($objectname, $fieldtype, $fieldid=0, $objectid=0) {
    global $CFG;
    require_once($CFG->libdir.'/custominfo/field/'.$fieldtype.'/field.class.php');
    $newfield = 'profile_field_'.$fieldtype;
    if (empty($fieldid)) {
        return (new $newfield($objectname));
    } else {
        return (new $newfield($objectname, $fieldid, $objectid));
    }
}

/**
 * Interface that each model (user, course) has to implement in a class custominfo_field_extension_{model}
 * placed in {model}/custominfo/locallib.php
 */
interface custominfo_field_extension_i {
    /**
     * @return string
     */
    public function get_capability();

    /**
     * Check if the field data is visible to the current user
     * @param custominfo_field_base $field
     * @param integer $objectid
     * @return  boolean
     */
    public function is_visible($field, $objectid);
}
