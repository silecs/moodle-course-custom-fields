<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

global $CFG;
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * This class declares the form that describes a custominfo field.
 */
class field_form extends moodleform {

    public $field;

    /**
     * Define the form
     */
    public function definition () {
        global $CFG;

        $mform =& $this->_form;

        /// Everything else is dependant on the data type
        $datatype = $this->_customdata['datatype'];
        require_once(__DIR__.'/field/'.$datatype.'/define.class.php');
        $newfield = 'profile_define_'.$datatype;
        $this->field = new $newfield($this->_customdata['objectname']);

        $strrequired = get_string('required');

        /// Add some extra hidden fields
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'editfield');
        $mform->setType('action', PARAM_ACTION);
        $mform->addElement('hidden', 'datatype', $datatype);
        $mform->setType('datatype', PARAM_ALPHA);

        $this->field->define_form($mform);

        $this->add_action_buttons(true);
    }

    /**
     * Alter definition based on existing or submitted data
     */
    public function definition_after_data () {
        $mform =& $this->_form;
        $this->field->define_after_data($mform);
    }

    /**
     * perform some moodle validation
     */
    public function validation($data, $files) {
        return $this->field->define_validate($data, $files);
    }

    public function editors() {
        return $this->field->define_editors();
    }
}


