<?php

/**
 * Provide methods that allow to embed the custom info fields into an existing form
 */
class custominfo_form_extension {
    protected $objectname;

    /**
     * Constructor
     * @param string $objectname E.g. user, course
     */
    public function __construct($objectname) {
        $this->objectname = $objectname;
    }

    /**
     * Declare the customisable categories and fields on a form
     * @param object $mform     instance of the moodleform class
     * @param bool $canviewall  (opt) if true, force the visibility of all fields
     */
    public function definition($mform, $canviewall=false) {
        global $DB;

        $categories = $DB->get_records('custom_info_category', array('objectname' => $this->objectname), 'sortorder ASC');
        if ($categories) {
            foreach ($categories as $category) {
                $fields = $DB->get_records('custom_info_field', array('categoryid' => $category->id), 'sortorder ASC');
                if ($fields) {
                    // check first if *any* fields will be displayed
                    $display = false;
                    if (!$canviewall) {
                        foreach ($fields as $field) {
                            if ($field->visible != CUSTOMINFO_VISIBLE_NONE) {
                                $display = true;
                            }
                        }
                    }

                    // display the header and the fields
                    if ($display or $canviewall) {
                        $mform->addElement('header', 'category_'.$category->id, format_string($category->name));
                        foreach ($fields as $field) {
                            $formfield = custominfo_field_factory($this->objectname, $field->datatype, $field->id);
                            $formfield->edit_field($mform);
                        }
                    }
                }
            }
        }
    }

    /**
     *
     * @global object $DB
     * @param object  $mform
     * @param integer $objectid
     */
    public function definition_after_data($mform, $objectid) {
        global $DB;

        $objectid = ($objectid < 0) ? 0 : (int)$objectid;

        $fields = $DB->get_records('custom_info_field', array('objectname' => $this->objectname));
        if ($fields) {
            foreach ($fields as $field) {
                $formfield = custominfo_field_factory($this->objectname, $field->datatype, $field->id, $objectid);
                $formfield->edit_after_data($mform);
            }
        }
    }

    /**
     * Validates the custom fields and return an array of errors
     * @global object $DB
     * @param object $objectnew
     * @param array $files
     * @return array of errors
     */
    public function validation($objectnew, $files) {
        global $DB;

        $err = array();
        $fields = $DB->get_records('custom_info_field', array('objectname' => $this->objectname));
        if ($fields) {
            foreach ($fields as $field) {
                $formfield = custominfo_field_factory($this->objectname, $field->datatype, $field->id, $objectnew->id);
                $err += $formfield->edit_validate_field($objectnew, $files);
            }
        }
        return $err;
    }
}
