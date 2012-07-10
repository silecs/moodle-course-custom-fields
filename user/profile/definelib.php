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
 * This file contains the profile_define_base class.
 *
 * @package core_user
 * @copyright  2007 onwards Shane Elliot {@link http://pukunui.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/custominfo/lib.php');

/**
 * Class profile_define_base
 *
 * @copyright  2007 onwards Shane Elliot {@link http://pukunui.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_define_base extends custominfo_define_base {
    protected $objectname = 'user';
}


/**
 * Reorder the profile fields within a given category starting at the field at the given startorder.
 */
function profile_reorder_fields() {
    return custominfo_field::type('user')->reorder();
}

/**
 * Reorder the profile categoriess starting at the category at the given startorder.
 */
function profile_reorder_categories() {
    return custominfo_category::type('user')->reorder();
}

/**
 * Delete a profile category
 * @param int $id of the category to be deleted
 * @return bool success of operation
 */
function profile_delete_category($id) {
    return custominfo_category::findById($id)->delete();
}

/**
 * Deletes a profile field.
 * @param int $id
 */
function profile_delete_field($id) {
    global $DB;

    // Remove any user data associated with this field.
    if (!$DB->delete_records('custom_info_data', array('fieldid' => $id))) {
        print_error('cannotdeletecustomfield');
    }

    // Need to rebuild course cache to update the info.
    rebuild_course_cache();

    return custominfo_field::findById($id)->delete();
}

/**
 * Change the sort order of a field
 *
 * @param int $id of the field
 * @param string $move direction of move
 * @return bool success of operation
 */
function profile_move_field($id, $move) {
    return custominfo_field::findById($id)->move($move);
}

/**
 * Change the sort order of a category.
 *
 * @param int $id of the category
 * @param string $move direction of move
 * @return bool success of operation
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


/**
 * Edit a category
 *
 * @param int $id
 * @param string $redirect
 */
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
            // Print the page.
            echo $OUTPUT->header();
            echo $OUTPUT->heading($strheading);
            $category->get_form()->display();
            echo $OUTPUT->footer();
            die;
    }
}

/**
 * Edit a profile field.
 *
 * @param int $id
 * @param string $datatype
 * @param string $redirect
 */
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

        // Print the page.
        $PAGE->navbar->add($strheading);
        echo $OUTPUT->header();
        echo $OUTPUT->heading($strheading);
        $field->get_form()->display();
        echo $OUTPUT->footer();
        die;
    }
}
