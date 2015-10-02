<?php
/**
 * This file contains classes that deals with custom_info fields and categories.
 * These classes are not meant for the user data, they handle the structure of custom_info.
 */

/**
 * Abstract class that provides a few useful methods to classes that inherit from it.
 */
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
    public function set_id($id) {
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
    public function set_record($record) {
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
    public function get_record() {
        return $this->record;
    }
}

/**
 * Class that manipulates a custominfo category.
 */
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
        $this->reorder();
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
     * @global moodle_database $DB
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
            $this->form = new category_form(null, array('objectname' => $this->objectname));

            if ($this->id && $this->record) {
                $this->form->set_data($this->record);
            }
        }
        return $this->form;
    }
}

/**
 * Class that manipulates a custominfo field.
 */
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
    public function delete() {
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
    public function move($dir) {
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
    public function reorder() {
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
    public function edit($datatype) {
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
            $data = $form->get_data();
            if ($data) {
                require_once(__DIR__.'/field/'.$datatype.'/define.class.php');
                $newfield = 'profile_define_'.$datatype;
                $formfield = new $newfield($this->objectname);

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
     * @return field_form
     */
    public function get_form() {
        if (empty($this->form)) {
            // Clean and prepare description for the editor
            $this->record->description = array(
                'text' => clean_text($this->record->description, $this->record->descriptionformat),
                'format' => $this->record->descriptionformat,
                'itemid' => 0
            );

            $this->form = new field_form(null, array('datatype' => $this->record->datatype, 'objectname' => $this->objectname));

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
        $plugins = core_component::get_plugin_list('profilefield');
        foreach ($plugins as $type => $unused) {
            $datatypes[$type] = get_string('pluginname', 'profilefield_'.$type);
        }
        asort($datatypes);

        return $datatypes;
    }
}
