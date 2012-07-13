<?php

require_once(__DIR__.'/lib.php');

class custominfo_controller {
    protected $objectname;

    /**
     * Constructor
     * @param string $objectname E.g. "user" or "course"
     */
    public function __construct($objectname) {
        $this->objectname = $objectname;
    }

    /**
     * Dispatch the action name and eventually redirects the browser
     * @global object $DB
     * @global object $OUTPUT
     * @param string $action   Name of the action requested
     * @param string $redirect URL to redirect after the action was performed
     */
    public function dispatch_action($action, $redirect) {
        global $DB, $OUTPUT;
        switch ($action) {
            case 'movecategory':
                $id  = required_param('id', PARAM_INT);
                $dir = required_param('dir', PARAM_ALPHA);

                if (confirm_sesskey()) {
                    custominfo_category::findById($id)->move($dir);
                }
                redirect($redirect);
                break;
            case 'movefield':
                $id  = required_param('id', PARAM_INT);
                $dir = required_param('dir', PARAM_ALPHA);

                if (confirm_sesskey()) {
                    custominfo_field::findById($id)->move($dir);
                }
                redirect($redirect);
                break;
            case 'deletecategory':
                $id = required_param('id', PARAM_INT);
                custominfo_category::findById($id)->delete();
                redirect($redirect, get_string('deleted'));
                break;
            case 'deletefield':
                $id      = required_param('id', PARAM_INT);
                $confirm = optional_param('confirm', 0, PARAM_BOOL);

                $datacount = $DB->count_records('custom_info_data', array('fieldid' => $id));
                if (data_submitted() and ($confirm and confirm_sesskey()) or $datacount === 0) {
                    custominfo_field::findById($id)->delete();
                    redirect($redirect, get_string('deleted'));
                }

                //ask for confirmation
                $fieldname = $DB->get_field('custom_info_field', 'name', array('id' => $id));
                $optionsyes = array ('id'=>$id, 'confirm'=>1, 'action'=>'deletefield', 'sesskey'=>sesskey());
                $strheading = get_string('profiledeletefield', 'admin', $fieldname);
                $PAGE->navbar->add($strheading);
                echo $OUTPUT->header();
                echo $OUTPUT->heading($strheading);
                $formcontinue = new single_button(new moodle_url($redirect, $optionsyes), get_string('yes'), 'post');
                $formcancel = new single_button(new moodle_url($redirect), get_string('no'), 'get');
                echo $OUTPUT->confirm(get_string('profileconfirmfielddeletion', 'admin', $datacount), $formcontinue, $formcancel);
                echo $OUTPUT->footer();
                die;
                break;
            case 'editfield':
                $id       = optional_param('id', 0, PARAM_INT);
                $datatype = optional_param('datatype', '', PARAM_ALPHA);

                $this->edit_field($id, $datatype, $redirect);
                die;
                break;
            case 'editcategory':
                $id = optional_param('id', 0, PARAM_INT);

                $this->edit_category($id, $redirect);
                die;
                break;
            default:
                //normal form
        }
    }

    /**
     * Redirect or display the category form according to the data submitted
     * @global object $OUTPUT
     * @param integer $id
     * @param string $redirect URL to redirect after the action was performed successfullly
     */
    protected function edit_category($id, $redirect) {
        global $OUTPUT;
        $category = custominfo_category::type($this->objectname);
        if ($id) {
            $category->set_id($id);
        }
        switch ($category->edit()) {
            case custominfo_category::EDIT_CANCELLED:
            case custominfo_category::EDIT_SAVED:
                redirect($redirect);
            case custominfo_category::EDIT_DISPLAY:
                if (empty($id)) {
                    $strheading = get_string('profilecreatenewcategory', 'admin');
                } else {
                    $strheading = get_string('profileeditcategory', 'admin', format_string($category->get_record()->name));
                }
                /// Print the page
                echo $OUTPUT->header();
                echo $OUTPUT->heading($strheading);
                $category->get_form()->display();
                echo $OUTPUT->footer();
                die;
        }
    }

    /**
     * Redirect or display the field form according to the data submitted
     * @global object $OUTPUT
     * @param integer $id
     * @param string $redirect URL to redirect after the action was performed successfullly
     */
    protected function edit_field($id, $datatype, $redirect) {
        global $OUTPUT, $PAGE;

        $field = custominfo_field::type($this->objectname);
        if ($id) {
            $field->set_id($id);
        }
        switch ($field->edit($datatype)) {
            case custominfo_field::EDIT_CANCELLED:
            case custominfo_field::EDIT_SAVED:
                redirect($redirect);
            case custominfo_field::EDIT_DISPLAY:

            $datatypes = custominfo_field::list_datatypes();

            if (empty($id)) {
                $strheading = get_string('profilecreatenewfield', 'admin', $datatypes[$datatype]);
            } else {
                $strheading = get_string('profileeditfield', 'admin', $field->get_record()->name);
            }

            /// Print the page
            $PAGE->navbar->add($strheading);
            echo $OUTPUT->header();
            echo $OUTPUT->heading($strheading);
            $field->get_form()->display();
            echo $OUTPUT->footer();
            die;
        }
    }
}