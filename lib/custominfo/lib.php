<?php

/// Some constants

define ('CUSTOMINFO_VISIBLE_ALL',     '2'); // visible for all users
define ('CUSTOMINFO_VISIBLE_PRIVATE', '1'); // either it's our own profile or course, or we have moodle/user:update capability
define ('CUSTOMINFO_VISIBLE_NONE',    '0'); // only visible for moodle/user:update (or course) capability

require_once(__DIR__ . '/index_category_form.php');
require_once(__DIR__ . '/index_field_form.php');


/**
 * Base class for the customisable profile fields.
 */
abstract class custominfo_field_base {

    /// These 2 variables are really what we're interested in.
    /// Everything else can be extracted from them
    protected $fieldid;
    protected $objectid;

    public $field;
    public $inputname;
    public $data;
    public $dataformat;

    // must be overriden
    protected $objectname;
    protected $capability;

    /**
     * Constructor method.
     * @param integer $fieldid    id of the profile from the custom_info_field table
     * @param integer $objectid   id of the object whose we are displaying data
     */
    function __construct($fieldid=0, $objectid=0) {
        if (is_null($this->objectname) || is_null($this->capability)) {
            print_error('mustbeoveride', 'debug', '', 'custominfo_field_base');
        }
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
    abstract function is_visible();

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


abstract class custominfo_record {
    protected $objectname;
    protected $id;

    protected $table; // must be overriden
    protected $record;
    protected $form;

    const EDIT_SAVED = 1;
    const EDIT_CANCELLED = 2;
    const EDIT_DISPLAY = 3;

    public function __construct($objectname, $id=null) {
        if (empty($this->table)) {
            print_error('mustbeoveride', 'debug', '', '');
        }
        $this->objectname = $objectname;
        $this->set_id($id);
    }

    /**
     * Accessor method: set the id
     * @param integer $id  id from the table
     */
    function set_id($id) {
        global $DB;
        $this->id = $id;
        if (isset($id)) {
            $this->record = $DB->get_record($this->table, array('id' => $id));
            if (!$this->record || $this->record->objectname != $this->objectname) {
                print_error('invaliditemid');
            }
        }
    }

    /**
     * Accessor method: set the record (and id)
     * @param object $record  record from the table
     */
    function set_record($record) {
        if (empty($record->id) || empty($record->name) || empty($record->objectname) || $record->objectname != $this->objectname) {
            print_error('invaliditemid');
        }
        $this->id = $record->id;
        $this->record = $record;
    }

    /**
     * Accessor method: get the record
     * return object record from the table
     */
    function get_record() {
        return $this->record;
    }
}

class custominfo_category extends custominfo_record {
    protected $table = 'custom_info_category';

    /**
     * Builds a new instance not linked to a specific record.
     * @param string $objectname
     * @return custominfo_record new instance
     */
    public static function type($objectname) {
        return new self($objectname);
    }

    /**
     * Builds a new instance from a ID.
     * @param integer $id ID
     * @return custominfo_record new instance
     */
    public static function findById($id) {
        global $DB;
        $record = $DB->get_record('custom_info_category', array('id' => $id));
        if (!$record) {
            print_error('invalidcategoryid');
        }
        $c = new self($record->objectname);
        $c->set_record($record);
        return $c;
    }

    /**
     * Change the sortorder of the category
     * @param   string  $dir  direction of move: "up" or "down"
     * @return  boolean       success of operation
     */
    public function move($dir) {
        global $DB;

        if (!$this->record) {
            return false;
        }

        /// Count the number of categories
        $categorycount = $DB->count_records('custom_info_category', array('objectname' => $this->objectname));

        /// Calculate the new sortorder
        if (($dir == 'up') and ($this->record->sortorder > 1)) {
            $neworder = $this->record->sortorder - 1;
        } elseif (($dir == 'down') and ($this->record->sortorder < $categorycount)) {
            $neworder = $this->record->sortorder + 1;
        } else {
            return false;
        }

        /// Retrieve the category object that is currently residing in the new position
        $swapcategory = $DB->get_record('custom_info_category',
                array('objectname' => $this->objectname, 'sortorder' => $neworder),'id, sortorder');
        if ($swapcategory) {
            /// Swap the sortorders
            $swapcategory->sortorder = $this->record->sortorder;
            $this->record->sortorder = $neworder;

            /// Update the category records
            $DB->update_record('custom_info_category', $this->record)
                    and $DB->update_record('custom_info_category', $swapcategory);
            return true;
        }

        return false;
    }

    /**
     * Delete a custominfo category
     * @return  boolean   success of operation
     */
    public function delete() {
        global $DB;

        if (!$this->record) {
            print_error('invalidcategoryid');
        }

        $categories = $DB->get_records('custom_info_category', array('objectname' => $this->objectname), 'sortorder ASC');
        if (!$categories) {
            print_error('nocate', 'debug');
        }

        unset($categories[$this->record->id]);

        if (!count($categories)) {
            return; //we can not delete the last category
        }

        /// Does the category contain any fields
        if ($DB->count_records('custom_info_field', array('categoryid' => $this->record->id))) {
            // warning: this legacy code does not select a neighbouring category
            // It selects a category whose id is close to the late sortorder.
            if (array_key_exists($this->record->sortorder-1, $categories)) {
                $newcategory = $categories[$this->record->sortorder-1];
            } else if (array_key_exists($this->record->sortorder+1, $categories)) {
                $newcategory = $categories[$this->record->sortorder+1];
            } else {
                $newcategory = reset($categories); // get first category if sortorder broken
            }

            $fields = $DB->get_records('custom_info_field', array('categoryid' => $this->record->id), 'sortorder ASC');
            if ($fields) {
                $sortorder = $DB->count_records('custom_info_field', array('categoryid' => $newcategory->id)) + 1;
                foreach ($fields as $field) {
                    $f = new stdClass();
                    $f->id = $field->id;
                    $f->sortorder = $sortorder++;
                    $f->categoryid = $newcategory->id;
                    $DB->update_record('custom_info_field', $f);
                }
            }
        }

        /// Finally we get to delete the category
        $DB->delete_records('custom_info_category', array('objectname' => $this->objectname, 'id' => $this->record->id));
        profile_reorder_categories();
        return true;
    }

    /**
     * Retrieve a list of categories as an array(id => name) suitable for use in a form
     * @return   array
     */
    public function list_assoc() {
        global $DB;
        $categories = $DB->get_records_menu(
                'custom_info_category', array('objectname' => $this->objectname), 'sortorder ASC', 'id, name'
        );
        if (!$categories) {
            $categories = array();
        }
        return $categories;
    }

    /**
     * Reorder the profile categories starting at the category
     */
    public function reorder() {
        global $DB;

        $categories = $DB->get_records('custom_info_category', array('objectname' => $this->objectname), 'sortorder ASC');
        if ($categories) {
            $sortorder = 1;
            foreach ($categories as $cat) {
                $c = new stdClass();
                $c->id = $cat->id;
                $c->sortorder = $sortorder++;
                $DB->update_record('custom_info_category', $c);
            }
        }
    }

    /**
     * Insert or update a category with the data submitted from a form.
     * @global object $DB
     * @param object $data (opt, null by default)
     * @return integer A constant among self::EDIT_*
     */
    function edit($data=null) {
        global $DB;
        $form = $this->get_form();
        if ($form->is_cancelled()) {
            return self::EDIT_CANCELLED;
        } else {
            if (empty($data)) {
                $data = $form->get_data();
            }
            if ($data) {
                if (empty($data->id)) {
                    unset($data->id);
                    $data->objectname = $this->objectname;
                    $data->sortorder = $DB->count_records('custom_info_category', array('objectname' => $this->objectname)) + 1;
                    $DB->insert_record('custom_info_category', $data, false);
                } else {
                    $DB->update_record('custom_info_category', $data);
                }
                $this->reorder();
                return self::EDIT_SAVED;
            }
            return self::EDIT_DISPLAY;
        }
    }

    /**
     * Return the form for this record
     * @return category_form
     */
    public function get_form() {
        if (empty($this->form)) {
            $this->form = new category_form();

            if ($this->id && $this->record) {
                $this->form->set_data($this->record);
            }
        }
        return $this->form;
    }
}

class custominfo_field extends custominfo_record {
    protected $table = 'custom_info_field';

    /**
     * Builds a new instance not linked to a soecific category.
     * @param string $objectname
     * @return custominfo_field new instance
     */
    public static function type($objectname) {
        return new self($objectname);
    }

    /**
     * Builds a new instance from a category ID.
     * @param integer $id category ID
     * @return custominfo_field new instance
     */
    public static function findById($id) {
        global $DB;
        $record = $DB->get_record('custom_info_field', array('id' => $id));
        if (!$record) {
            print_error('invaliditemid');
        }
        $c = new self($record->objectname);
        $c->set_record($record);
        return $c;
    }

    /**
     * Delete this field
     * @param integer $id
     * @return  boolean   success of operation
     */
    function delete() {
        global $DB;
        /// Remove any data associated with this field
        if (!$DB->delete_records('custom_info_data', array('fieldid' => $this->id))) {
            print_error('cannotdeletecustomfield');
        }
        /// Try to remove the record from the database
        $deleted = $DB->delete_records('custom_info_field', array('id' => $this->id));
        /// Reorder the remaining fields in the same category
        $this->reorder();
        return $deleted;
    }

    /**
     * Change the sortorder of a field
     * @param   string  $dir  direction of move: "up" or "down"
     * @return  boolean       success of operation
     */
    function move($dir) {
        global $DB;

        if (!$this->record) {
            print_error('invalidcustomfieldid');
        }

        /// Count the number of fields in this category
        $fieldcount = $DB->count_records('custom_info_field', array('categoryid' => $this->record->categoryid));

        /// Calculate the new sortorder
        if (($dir == 'up') and ($this->record->sortorder > 1)) {
            $neworder = $this->record->sortorder - 1;
        } elseif (($dir == 'down') and ($this->record->sortorder < $fieldcount)) {
            $neworder = $this->record->sortorder + 1;
        } else {
            return false;
        }

        /// Retrieve the field object that is currently residing in the new position
        $changed = false;
        $swapfield = $DB->get_record(
                'custom_info_field', array('categoryid' => $this->record->categoryid, 'sortorder' => $neworder), 'id, sortorder'
        );
        if ($swapfield) {

            /// Swap the sortorders
            $swapfield->sortorder = $this->record->sortorder;
            $this->record->sortorder     = $neworder;

            /// Update the field records
            $DB->update_record('custom_info_field', $this->record);
            $DB->update_record('custom_info_field', $swapfield);
            $changed = true;
        }

        $this->reorder();
        return $changed;
    }

    /**
     * Reorder the profile fields within a given category
     */
    function reorder() {
        global $DB;
        $categories = $DB->get_records('custom_info_category', array('objectname' => $this->objectname));
        if ($categories) {
            foreach ($categories as $category) {
                $sortorder = 1;
                $fields = $DB->get_records('custom_info_field', array('categoryid' => $category->id), 'sortorder ASC');
                if ($fields) {
                    foreach ($fields as $field) {
                        $f = new stdClass();
                        $f->id = $field->id;
                        $f->sortorder = $sortorder++;
                        $DB->update_record('custom_info_field', $f);
                    }
                }
            }
        }
    }

    /**
     * Edit a field through its form.
     * @param string $datatype Name of the field plugin.
     * @return integer Code among self::EDIT_*
     */
    function edit($datatype) {
        if (!$this->record) {
            $this->record = new stdClass();
            $this->record->objectname = $this->objectname;
            $this->record->datatype = $datatype;
            $this->record->description = '';
            $this->record->descriptionformat = FORMAT_HTML;
            $this->record->defaultdata = '';
            $this->record->defaultdataformat = FORMAT_HTML;
        }

        $form = $this->get_form();

        if ($form->is_cancelled()) {
            return self::EDIT_CANCELLED;
        } else {
            if ($data = $form->get_data()) {
                require_once(__DIR__.'/field/'.$datatype.'/define.class.php');
                $newfield = 'profile_define_'.$datatype;
                $formfield = new $newfield();

                // Collect the description and format back into the proper data structure from the editor
                // Note: This field will ALWAYS be an editor
                $data->descriptionformat = $data->description['format'];
                $data->description = $data->description['text'];
                $data->objectname = $this->objectname;

                // Check whether the default data is an editor, this is (currently) only the
                // textarea field type
                if (is_array($data->defaultdata) && array_key_exists('text', $data->defaultdata)) {
                    // Collect the default data and format back into the proper data structure from the editor
                    $data->defaultdataformat = $data->defaultdata['format'];
                    $data->defaultdata = $data->defaultdata['text'];
                }

                // Convert the data format for
                if (is_array($form->editors())) {
                    foreach ($form->editors() as $editor) {
                        if (isset($this->record->$editor)) {
                            $this->record->{$editor.'format'} = $this->record->{$editor}['format'];
                            $this->record->$editor = $this->record->{$editor}['text'];
                        }
                    }
                }

                $formfield->define_save($data);
                $this->reorder();
                custominfo_category::type($this->objectname)->reorder();
                return self::EDIT_SAVED;
            }
            return self::EDIT_DISPLAY;
        }
    }

    /**
     * Return the form for this record
     * @return category_form
     */
    public function get_form() {
        if (empty($this->form)) {
            // Clean and prepare description for the editor
            $this->record->description = array(
                'text' => clean_text($this->record->description, $this->record->descriptionformat),
                'format' => $this->record->descriptionformat,
                'itemid' => 0
            );

            $this->form = new field_form(null, $this->record->datatype);

            // Convert the data format for
            if (is_array($this->form->editors())) {
                foreach ($this->form->editors() as $editor) {
                    if (isset($this->record->$editor)) {
                        $this->record->$editor = clean_text($this->record->$editor, $this->record->{$editor . 'format'});
                        $this->record->$editor = array(
                            'text' => $this->record->$editor,
                            'format' => $this->record->{$editor . 'format'},
                            'itemid' => 0
                        );
                    }
                }
            }

            $this->form->set_data($this->record);
        }
        return $this->form;
    }

    /**
     * Return an assoc array of the available fields datatypes
     * @return array assoc array(type => fullname)
     */
    public static function list_datatypes() {
        $datatypes = array();
        $plugins = get_plugin_list('profilefield');
        foreach ($plugins as $type => $unused) {
            $datatypes[$type] = get_string('pluginname', 'profilefield_'.$type);
        }
        asort($datatypes);

        return $datatypes;
    }
}
