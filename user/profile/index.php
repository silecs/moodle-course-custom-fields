<?php

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

/// Do we have any actions to perform before printing the header?
$controller->dispatch_action($action);

$controller->check_category_defined();

/// Print the header
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('profilefields', 'admin'));

$controller->print_all_categories();

echo '<hr />';
echo '<div class="profileeditor">';

/// Create a new field link
$options = custominfo_field::list_datatypes();
$popupurl = new moodle_url('/user/profile/index.php?id=0&action=editfield');
echo $OUTPUT->single_select($popupurl, 'datatype', $options, '', array(''=>$strcreatefield), 'newfieldform');

//add a div with a class so themers can hide, style or reposition the text
html_writer::start_tag('div',array('class'=>'adminuseractionhint'));
echo get_string('or', 'lesson');
html_writer::end_tag('div');

/// Create a new category link
$options = array('action'=>'editcategory');
echo $OUTPUT->single_button(new moodle_url('index.php', $options), get_string('profilecreatecategory', 'admin'));

echo '</div>';

echo $OUTPUT->footer();
die;
