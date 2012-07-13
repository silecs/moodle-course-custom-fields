<?php

require_once(__DIR__.'/lib.php');

class custominfo_controller {
    /**
     * @var string
     */
    protected $objectname;

    /**
     * @var string
     */
    protected $redirect;

    /**
     * Constructor
     * @param string $objectname E.g. "user" or "course"
     */
    public function __construct($objectname) {
        $this->objectname = $objectname;
    }

    /**
     * Accessor for redirect
     * @param string $url
     */
    public function set_redirect($url) {
        $this->redirect = $url;
    }

    /**
     * Dispatch the action name and eventually redirects the browser
     * @global object $DB
     * @global object $OUTPUT
     * @param string $action   Name of the action requested
     */
    public function dispatch_action($action) {
        global $DB, $OUTPUT;
        switch ($action) {
            case 'movecategory':
                $id  = required_param('id', PARAM_INT);
                $dir = required_param('dir', PARAM_ALPHA);

                if (confirm_sesskey()) {
                    custominfo_category::findById($id)->move($dir);
                }
                redirect($this->redirect);
                break;
            case 'movefield':
                $id  = required_param('id', PARAM_INT);
                $dir = required_param('dir', PARAM_ALPHA);

                if (confirm_sesskey()) {
                    custominfo_field::findById($id)->move($dir);
                }
                redirect($this->redirect);
                break;
            case 'deletecategory':
                $id = required_param('id', PARAM_INT);
                custominfo_category::findById($id)->delete();
                redirect($this->redirect, get_string('deleted'));
                break;
            case 'deletefield':
                $id      = required_param('id', PARAM_INT);
                $confirm = optional_param('confirm', 0, PARAM_BOOL);

                $datacount = $DB->count_records('custom_info_data', array('fieldid' => $id));
                if (data_submitted() and ($confirm and confirm_sesskey()) or $datacount === 0) {
                    custominfo_field::findById($id)->delete();
                    redirect($this->redirect, get_string('deleted'));
                }

                //ask for confirmation
                $fieldname = $DB->get_field('custom_info_field', 'name', array('id' => $id));
                $optionsyes = array ('id'=>$id, 'confirm'=>1, 'action'=>'deletefield', 'sesskey'=>sesskey());
                $strheading = get_string('profiledeletefield', 'admin', $fieldname);
                $PAGE->navbar->add($strheading);
                echo $OUTPUT->header();
                echo $OUTPUT->heading($strheading);
                $formcontinue = new single_button(new moodle_url($this->redirect, $optionsyes), get_string('yes'), 'post');
                $formcancel = new single_button(new moodle_url($this->redirect), get_string('no'), 'get');
                echo $OUTPUT->confirm(get_string('profileconfirmfielddeletion', 'admin', $datacount), $formcontinue, $formcancel);
                echo $OUTPUT->footer();
                die;
                break;
            case 'editfield':
                $id       = optional_param('id', 0, PARAM_INT);
                $datatype = optional_param('datatype', '', PARAM_ALPHA);

                $this->edit_field($id, $datatype, $this->redirect);
                die;
                break;
            case 'editcategory':
                $id = optional_param('id', 0, PARAM_INT);

                $this->edit_category($id, $this->redirect);
                die;
                break;
            default:
                //normal form
        }
    }

    /**
     * Check that we have at least one category defined
     * @global object $DB
     */
    public function check_category_defined() {
        global $DB;
        if ($DB->count_records('custom_info_category', array('objectname' => $this->objectname)) == 0) {
            $defaultcategory = new stdClass();
            $defaultcategory->objectname = $this->objectname;
            $defaultcategory->name = get_string('profiledefaultcategory', 'admin');
            $defaultcategory->sortorder = 1;
            $DB->insert_record('custom_info_category', $defaultcategory);
            redirect($this->redirect);
        }
    }

    /**
     * Redirect or display the category form according to the data submitted
     * @global object $OUTPUT
     * @param integer $id
     */
    protected function edit_category($id) {
        global $OUTPUT;
        $category = custominfo_category::type($this->objectname);
        if ($id) {
            $category->set_id($id);
        }
        switch ($category->edit()) {
            case custominfo_category::EDIT_CANCELLED:
            case custominfo_category::EDIT_SAVED:
                redirect($this->redirect);
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
     */
    protected function edit_field($id, $datatype) {
        global $OUTPUT, $PAGE;

        $field = custominfo_field::type($this->objectname);
        if ($id) {
            $field->set_id($id);
        }
        switch ($field->edit($datatype)) {
            case custominfo_field::EDIT_CANCELLED:
            case custominfo_field::EDIT_SAVED:
                redirect($this->redirect);
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

     /* Stricly speaking, the following methods shouldn't be in a controller but rather in a helper class. */

    /**
     * Print all the categories of this object type.
     */
    public function print_all_categories() {
        global $DB, $OUTPUT;

        $strnofields = get_string('profilenofieldsdefined', 'admin');
        $categories = $DB->get_records('custom_info_category', array('objectname' => $this->objectname), 'sortorder ASC');

        foreach ($categories as $category) {
            $table = new html_table();
            $table->head  = array(get_string('profilefield', 'admin'), get_string('edit'));
            $table->align = array('left', 'right');
            $table->width = '95%';
            $table->attributes['class'] = 'generaltable profilefield';
            $table->data = array();

            $fields = $DB->get_records('custom_info_field', array('categoryid' => $category->id), 'sortorder ASC');
            if ($fields) {
                foreach ($fields as $field) {
                    $table->data[] = array(format_string($field->name), $this->profile_field_icons($field));
                }
            }

            echo $OUTPUT->heading(format_string($category->name) .' '.$this->profile_category_icons($category));
            if (count($table->data)) {
                echo html_writer::table($table);
            } else {
                echo $OUTPUT->notification($strnofields);
            }

        }
    }

    /**
     * Create a string containing the editing icons for the user profile categories
     * @param   object   the category object
     * @return  string   the icon string
     */
    protected function profile_category_icons($category) {
        global $CFG, $DB, $OUTPUT;

        $strdelete   = get_string('delete');
        $strmoveup   = get_string('moveup');
        $strmovedown = get_string('movedown');
        $stredit     = get_string('edit');

        $categorycount = $DB->count_records('custom_info_category', array('objectname' => $this->objectname));
        $fieldcount    = $DB->count_records('custom_info_field', array('categoryid' => $category->id));

        /// Edit
        $editstr = '<a title="'.$stredit.'" href="index.php?id='.$category->id.'&amp;action=editcategory"><img src="'.$OUTPUT->pix_url('t/edit') . '" alt="'.$stredit.'" class="iconsmall" /></a> ';

        /// Delete
        /// Can only delete the last category if there are no fields in it
        if ( ($categorycount > 1) or ($fieldcount == 0) ) {
            $editstr .= '<a title="'.$strdelete.'" href="index.php?id='.$category->id.'&amp;action=deletecategory';
            $editstr .= '"><img src="'.$OUTPUT->pix_url('t/delete') . '" alt="'.$strdelete.'" class="iconsmall" /></a> ';
        } else {
            $editstr .= '<img src="'.$OUTPUT->pix_url('spacer') . '" alt="" class="iconsmall" /> ';
        }

        /// Move up
        if ($category->sortorder > 1) {
            $editstr .= '<a title="'.$strmoveup.'" href="index.php?id='.$category->id.'&amp;action=movecategory&amp;dir=up&amp;sesskey='.sesskey().'"><img src="'.$OUTPUT->pix_url('t/up') . '" alt="'.$strmoveup.'" class="iconsmall" /></a> ';
        } else {
            $editstr .= '<img src="'.$OUTPUT->pix_url('spacer') . '" alt="" class="iconsmall" /> ';
        }

        /// Move down
        if ($category->sortorder < $categorycount) {
            $editstr .= '<a title="'.$strmovedown.'" href="index.php?id='.$category->id.'&amp;action=movecategory&amp;dir=down&amp;sesskey='.sesskey().'"><img src="'.$OUTPUT->pix_url('t/down') . '" alt="'.$strmovedown.'" class="iconsmall" /></a> ';
        } else {
            $editstr .= '<img src="'.$OUTPUT->pix_url('spacer') . '" alt="" class="iconsmall" /> ';
        }

        return $editstr;
    }

    /**
     * Create a string containing the editing icons for the user profile fields
     * @param   object   the field object
     * @return  string   the icon string
     */
    protected function profile_field_icons($field) {
        global $CFG, $DB, $OUTPUT;

        $strdelete   = get_string('delete');
        $strmoveup   = get_string('moveup');
        $strmovedown = get_string('movedown');
        $stredit     = get_string('edit');

        $fieldcount = $DB->count_records('custom_info_field', array('categoryid' => $field->categoryid));
        $datacount  = $DB->count_records('custom_info_data', array('fieldid' => $field->id));

        /// Edit
        $editstr = '<a title="'.$stredit.'" href="index.php?id='.$field->id.'&amp;action=editfield"><img src="'.$OUTPUT->pix_url('t/edit') . '" alt="'.$stredit.'" class="iconsmall" /></a> ';

        /// Delete
        $editstr .= '<a title="'.$strdelete.'" href="index.php?id='.$field->id.'&amp;action=deletefield';
        $editstr .= '"><img src="'.$OUTPUT->pix_url('t/delete') . '" alt="'.$strdelete.'" class="iconsmall" /></a> ';

        /// Move up
        if ($field->sortorder > 1) {
            $editstr .= '<a title="'.$strmoveup.'" href="index.php?id='.$field->id.'&amp;action=movefield&amp;dir=up&amp;sesskey='.sesskey().'"><img src="'.$OUTPUT->pix_url('t/up') . '" alt="'.$strmoveup.'" class="iconsmall" /></a> ';
         } else {
            $editstr .= '<img src="'.$OUTPUT->pix_url('spacer') . '" alt="" class="iconsmall" /> ';
        }

        /// Move down
        if ($field->sortorder < $fieldcount) {
            $editstr .= '<a title="'.$strmovedown.'" href="index.php?id='.$field->id.'&amp;action=movefield&amp;dir=down&amp;sesskey='.sesskey().'"><img src="'.$OUTPUT->pix_url('t/down') . '" alt="'.$strmovedown.'" class="iconsmall" /></a> ';
        } else {
            $editstr .= '<img src="'.$OUTPUT->pix_url('spacer') . '" alt="" class="iconsmall" /> ';
        }

        return $editstr;
    }
}