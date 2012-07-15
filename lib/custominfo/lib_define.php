<?php

/**
 * This class defines the form that each field type will extend.
 */
abstract class custominfo_define_base {
    protected $objectname;

    /**
     * Constructor
     * @param string $objectname
     */
    public function __construct($objectname) {
        $this->objectname = $objectname;
    }

    /**
     * Prints out the form snippet for creating or editing a profile field
     * @param   object $form  instance of the moodleform class
     */
    function define_form(&$form) {
        $form->addElement('header', '_commonsettings', get_string('profilecommonsettings', 'admin'));
        $this->define_form_common($form);

        $form->addElement('header', '_specificsettings', get_string('profilespecificsettings', 'admin'));
        $this->define_form_specific($form);
    }

    /**
     * Prints out the form snippet for the part of creating or
     * editing a profile field common to all data types
     * @param   object $form  instance of the moodleform class
     */
    function define_form_common(&$form) {

        $strrequired = get_string('required');

        $form->addElement('text', 'shortname', get_string('profileshortname', 'admin'), 'maxlength="100" size="25"');
        $form->addRule('shortname', $strrequired, 'required', null, 'client');
        $form->setType('shortname', PARAM_ALPHANUM);

        $form->addElement('text', 'name', get_string('profilename', 'admin'), 'size="50"');
        $form->addRule('name', $strrequired, 'required', null, 'client');
        $form->setType('name', PARAM_MULTILANG);

        $form->addElement('editor', 'description', get_string('profiledescription', 'admin'), null, null);

        $form->addElement('selectyesno', 'required', get_string('profilerequired', 'admin'));

        $form->addElement('selectyesno', 'locked', get_string('profilelocked', 'admin'));

        $form->addElement('selectyesno', 'forceunique', get_string('profileforceunique', 'admin'));

        $form->addElement('selectyesno', 'signup', get_string('profilesignup', 'admin'));

        $choices = array();
        $choices[CUSTOMINFO_VISIBLE_NONE]    = get_string('profilevisiblenone', 'admin');
        $choices[CUSTOMINFO_VISIBLE_PRIVATE] = get_string('profilevisibleprivate', 'admin');
        $choices[CUSTOMINFO_VISIBLE_ALL]     = get_string('profilevisibleall', 'admin');
        $form->addElement('select', 'visible', get_string('profilevisible', 'admin'), $choices);
        $form->addHelpButton('visible', 'profilevisible', 'admin');
        $form->setDefault('visible', CUSTOMINFO_VISIBLE_ALL);

        $choices = custominfo_category::type($this->objectname)->list_assoc();
        $form->addElement('select', 'categoryid', get_string('profilecategory', 'admin'), $choices);
    }

    /**
     * Prints out the form snippet for the part of creating or
     * editing a profile field specific to the current data type
     * @param   object $form  instance of the moodleform class
     */
    function define_form_specific($form) {
        /// do nothing - overwrite if necessary
    }

    /**
     * Validate the data from the add/edit profile field form.
     * Generally this method should not be overwritten by child
     * classes.
     * @param   object|array data  data from the add/edit profile field form
     * @return  array    associative array of error messages
     */
    function define_validate($data, $files) {

        $data = (object)$data;
        $err = array();

        $err += $this->define_validate_common($data, $files);
        $err += $this->define_validate_specific($data, $files);

        return $err;
    }

    /**
     * Validate the data from the add/edit profile field form
     * that is common to all data types. Generally this method
     * should not be overwritten by child classes.
     * @param   object $data  data from the add/edit profile field form
     * @param   array  $files
     * @return  array    associative array of error messages
     */
    function define_validate_common($data, $files) {
        global $DB;

        $err = array();

        /// Check the shortname was not truncated by cleaning
        if (empty($data->shortname)) {
            $err['shortname'] = get_string('required');

        } else {
        /// Fetch field-record from DB
            $field = $DB->get_record('custom_info_field', array('objectname' => $this->objectname, 'shortname' => $data->shortname));
        /// Check the shortname is unique
            if ($field and $field->id <> $data->id) {
                $err['shortname'] = get_string('profileshortnamenotunique', 'admin');
            }
        /// Check the category exists
            if (!empty($data->categoryid)) {
                $category = $DB->get_record('custom_info_category', array('id' => $data->categoryid));
            }
            if (empty($category) || $category->objectname != $this->objectname) {
                $err['categoryid'] = get_string('invalidcategoryid', 'core_error');
            }

            //NOTE: since 2.0 the shortname may collide with existing fields in $USER because we load these fields into $USER->profile array instead
        }

        /// No further checks necessary as the form class will take care of it
        return $err;
    }

    /**
     * Validate the data from the add/edit profile field form
     * that is specific to the current data type
     * @param   object $data   data from the add/edit profile field form
     * @param   array  $files  files
     * @return  array    associative array of error messages
     */
    function define_validate_specific($data, $files) {
        /// do nothing - overwrite if necessary
        return array();
    }

    /**
     * Alter form based on submitted or existing data
     * @param   object $mform  form
     */
    function define_after_data(&$mform) {
        /// do nothing - overwrite if necessary
    }

    /**
     * Add a new profile field or save changes to current field
     * @param   object $data  data from the add/edit profile field form
     * @return  boolean  status of the insert/update record
     */
    function define_save($data) {
        global $DB;

        $data = $this->define_save_preprocess($data); /// hook for child classes
        $data->objectname = $this->objectname;

        $old = false;
        if (!empty($data->id)) {
            $old = $DB->get_record('custom_info_field', array('id' => (int)$data->id));
        }

        /// check to see if the category has changed
        if (!$old or $old->categoryid != $data->categoryid) {
            $data->sortorder = 1 + $DB->count_records('custom_info_field', array('categoryid' => $data->categoryid));
        }


        if (empty($data->id)) {
            unset($data->id);
            $data->id = $DB->insert_record('custom_info_field', $data);
        } else {
            $DB->update_record('custom_info_field', $data);
        }
    }

    /**
     * Preprocess data from the add/edit profile field form
     * before it is saved. This method is a hook for the child
     * classes to overwrite.
     * @param   object $data  data from the add/edit profile field form
     * @return  object   processed data object
     */
    function define_save_preprocess($data) {
        /// do nothing - overwrite if necessary
        return $data;
    }

    /**
     * Provides a method by which we can allow the default data in $this->define_*
     * to use an editor
     *
     * This should return an array of editor names (which will need to be formatted/cleaned)
     *
     * @return array
     */
    function define_editors() {
        return array();
    }
}
