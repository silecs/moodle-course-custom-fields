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
 * Manage user profile fields.
 * @package core_user
 * @copyright  2007 onwards Shane Elliot {@link http://pukunui.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/custominfo/lib_controller.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once($CFG->dirroot.'/user/profile/definelib.php');

admin_externalpage_setup('profilefields');

$action   = optional_param('action', '', PARAM_ALPHA);

$strchangessaved    = get_string('changessaved');
$strcancelled       = get_string('cancelled');
$strcreatefield     = get_string('profilecreatefield', 'admin');

$controller = new custominfo_controller('user');
$controller->set_redirect($CFG->wwwroot.'/user/profile/index.php');

// Do we have any actions to perform before printing the header.
$controller->dispatch_action($action, $redirect);

$controller->check_category_defined();

// Show all categories.
$categories = $DB->get_records('custom_info_category', array('objectname' => 'user'), 'sortorder ASC');

// Print the header.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('profilefields', 'admin'));

$controller->print_all_categories();

echo '<hr />';
echo '<div class="profileeditor">';

// Create a new field link.
$options = custominfo_field::list_datatypes();
$popupurl = new moodle_url('/user/profile/index.php?id=0&action=editfield');
echo $OUTPUT->single_select($popupurl, 'datatype', $options, '', array('' => $strcreatefield), 'newfieldform');

// Add a div with a class so themers can hide, style or reposition the text.
html_writer::start_tag('div', array('class' => 'adminuseractionhint'));
echo get_string('or', 'lesson');
html_writer::end_tag('div');

// Create a new category link.
$options = array('action' => 'editcategory');
echo $OUTPUT->single_button(new moodle_url('index.php', $options), get_string('profilecreatecategory', 'admin'));

echo '</div>';

echo $OUTPUT->footer();
die;
