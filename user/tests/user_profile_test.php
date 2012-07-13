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
 * PHPUnit integration tests
 *
 * @package    core
 * @category   phpunit
 * @copyright  2012 François Gannaz <francois.gannaz@silecs.info>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global$CFG;

require_once dirname(__DIR__) . '/profile/lib.php';
require_once dirname(__DIR__) . '/profile/definelib.php';
require_once $CFG->libdir . '/custominfo/field/text/field.class.php';
require_once $CFG->libdir . '/custominfo/field/checkbox/field.class.php';

// To run only this test case: phpunit user_testcase user/tests/user_test.php

/**
 * Test user profile classes and functions.
 *
 * @package    core
 * @category   phpunit
 * @copyright  2012 François Gannaz <francois.gannaz@silecs.info>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_profile_testcase extends advanced_testcase {
    public function initDb() {
        $this->resetAllData();
        $dataset = $this->createCsvDataSet(
            array(
                'user' => __DIR__ . '/fixtures/user_dataset.csv',
                'custom_info_category' => __DIR__ . '/fixtures/info_category_dataset.csv',
                'custom_info_field' => __DIR__ . '/fixtures/info_field_dataset.csv',
                'custom_info_data' => __DIR__ . '/fixtures/info_data_dataset.csv',
            )
        );
        $this->loadDataSet($dataset);
    }

    public function test_text_empty() {
        $this->resetAfterTest(false);
        $formfield = new profile_field_text('user');
        $this->assertInstanceOf('custominfo_field_base', $formfield);
        $this->assertTrue($formfield->is_empty());
        $this->assertEmpty($formfield->inputname);
    }

    public function test_text_empty_user() {
        $this->resetAfterTest(false);
        $formfield = new profile_field_text('user', 1, 2);
        $this->assertInstanceOf('custominfo_field_base', $formfield);
        $this->assertTrue($formfield->is_empty());
        $this->assertEmpty($formfield->inputname);
    }

    public function test_text_and_checkbox() {
        $this->resetAfterTest(false);
        $this->initDb();

        // text field
        $formfield = new profile_field_text('user', 1, 3);
        $this->assertInstanceOf('custominfo_field_base', $formfield);
        $this->assertFalse($formfield->is_empty());
        $this->assertEquals('my own text 1', $formfield->display_data());
        $this->assertNotEmpty($formfield->inputname);
        $this->assertTrue($formfield->is_visible());

        // checkbox field
        $formfield = new profile_field_checkbox('user', 2, 3);
        $this->assertInstanceOf('custominfo_field_base', $formfield);
        $this->assertFalse($formfield->is_empty());
        $this->assertRegExp('/<input\b.*\btype="checkbox"/', $formfield->display_data());
        $this->assertNotEmpty($formfield->inputname);
        $this->assertFalse($formfield->is_visible());
    }

    public function test_category_list_assoc() {
        $this->resetAfterTest(false);
        $expected = array("1" => "maincategory", "3" => "second category", "2" => "third category");
        $this->assertEquals($expected, custominfo_category::type('user')->list_assoc());
    }

    public function test_custominfo_category_move() {
        $this->resetAfterTest(true);
        $this->assertTrue(custominfo_category::findById(3)->move('down'));
        $expected = array("1" => "maincategory", "2" => "third category", "3" => "second category");
        $this->assertEquals($expected, custominfo_category::type('user')->list_assoc());
        $this->assertFalse(custominfo_category::findById(3)->move('down'));
        $this->assertTrue(custominfo_category::findById(2)->move('up'));
        $expected = array("2" => "third category", "1" => "maincategory", "3" => "second category");
        $this->assertEquals($expected, custominfo_category::type('user')->list_assoc());
    }

    public function test_custominfo_category_delete() {
        global $DB;
        $this->resetAfterTest(true);
        $this->initDb();
        $expected = array("1" => "maincategory", "3" => "second category", "2" => "third category");
        $this->assertEquals($expected, custominfo_category::type('user')->list_assoc());
        $this->assertEquals('1', $DB->get_field('custom_info_field', 'categoryid', array('id' => 1)));
        $this->assertTrue(custominfo_category::findById(1)->delete());
        $expected = array("3" => "second category", "2" => "third category");
        $this->assertEquals($expected, custominfo_category::type('user')->list_assoc());
        $this->assertNotNull($DB->get_field('custom_info_field', 'categoryid', array('id' => 1)));

        $this->setExpectedException('moodle_exception', 'Incorrect category id!', 0);
        $this->assertFalse(custominfo_category::findById(1)->delete());
    }

    public function test_custominfo_field_move() {
        global $DB;
        $this->resetAfterTest(true);
        $this->initDb();
        $this->assertEquals('1', $DB->get_field('custom_info_field', 'sortorder', array('id' => 1)));
        $this->assertTrue(custominfo_field::findById(1)->move('down'));
        $this->assertEquals('2', $DB->get_field('custom_info_field', 'sortorder', array('id' => 1)));
        $this->assertEquals('1', $DB->get_field('custom_info_field', 'sortorder', array('id' => 2)));
        $this->assertFalse(custominfo_field::findById(1)->move('down'));
        $this->assertTrue(custominfo_field::findById(1)->move('up'));
        $this->assertEquals('1', $DB->get_field('custom_info_field', 'sortorder', array('id' => 1)));
        $this->assertEquals('2', $DB->get_field('custom_info_field', 'sortorder', array('id' => 2)));
    }

    public function test_custominfo_field_delete() {
        global $DB;
        $this->resetAfterTest(true);
        $this->initDb();
        $this->assertEquals(2, $DB->count_records('custom_info_data', array('fieldid' => 1)));
        $this->assertTrue(custominfo_field::findById(1)->delete());
        $this->assertEquals(0, $DB->count_records('custom_info_field', array('id' => 1)));
        $this->assertEquals(0, $DB->count_records('custom_info_data', array('fieldid' => 1)));
    }
}
