<?php

require_once($CFG->libdir . '/custominfo/lib.php');

class profile_define_base extends custominfo_define_base {
    protected $objectname = 'user';
}


/**
 * Reorder the profile fields within a given category starting
 * at the field at the given startorder
 */
function profile_reorder_fields() {
    return custominfo_field::type('user')->reorder();
}

/**
 * Reorder the profile categoriess starting at the category
 * at the given startorder
 */
function profile_reorder_categories() {
    return custominfo_category::type('user')->reorder();
}

/**
 * Delete a profile category
 * @param   integer   $id id of the category to be deleted
 * @return  boolean   success of operation
 */
function profile_delete_category($id) {
    return custominfo_category::findById($id)->delete();
}


function profile_delete_field($id) {
    return custominfo_field::findById($id)->delete();
}

/**
 * Change the sortorder of a field
 * @param   integer   id of the field
 * @param   string    direction of move
 * @return  boolean   success of operation
 */
function profile_move_field($id, $move) {
    return custominfo_field::findById($id)->move($move);
}

/**
 * Change the sortorder of a category
 * @param   integer   id of the category
 * @param   string    direction of move
 * @return  boolean   success of operation
 */
function profile_move_category($id, $move) {
    return custominfo_category::findById($id)->move($move);
}

/**
 * Retrieve a list of all the available data types
 * @return   array   a list of the datatypes suitable to use in a select statement
 */
function profile_list_datatypes() {
    return custominfo_field::list_datatypes();
}

/**
 * Retrieve a list of categories and ids suitable for use in a form
 * @return   array
 */
function profile_list_categories() {
    return custominfo_category::type('user')->list_assoc();
}


/// Are we adding or editing a category?
function profile_edit_category($id, $redirect) {
    global $OUTPUT;
    $category = custominfo_category::type('user');
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

function profile_edit_field($id, $datatype, $redirect) {
    global $OUTPUT, $PAGE;

    $field = custominfo_field::type('user');
    if ($id) {
        $field->set_id($id);
    }
    switch ($field->edit($datatype)) {
        case custominfo_category::EDIT_CANCELLED:
        case custominfo_category::EDIT_SAVED:
            redirect($redirect);
        case custominfo_category::EDIT_DISPLAY:

        $datatypes = profile_list_datatypes();

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
