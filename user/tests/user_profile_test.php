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

require_once dirname(__DIR__) . '/profile/lib.php';
require_once dirname(__DIR__) . '/profile/field/text/field.class.php';
require_once dirname(__DIR__) . '/profile/field/checkbox/field.class.php';

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
    public function test_text_empty() {
        $formfield = new profile_field_text();
        $this->assertInstanceOf('profile_field_base', $formfield);
        $this->assertTrue($formfield->is_empty());
        $this->assertEmpty($formfield->inputname);
    }

    public function test_text_empty_user() {
        $formfield = new profile_field_text(1, 2);
        $this->assertInstanceOf('profile_field_base', $formfield);
        $this->assertTrue($formfield->is_empty());
        $this->assertEmpty($formfield->inputname);
    }

    public function test_text_and_checkbox() {
        global $DB;

        $this->resetAfterTest(true);          // reset all changes automatically after this test

        $dataset = $this->createCsvDataSet(
            array(
                'user' => __DIR__ . '/fixtures/user_dataset.csv',
                'custom_info_category' => __DIR__ . '/fixtures/info_category_dataset.csv',
                'custom_info_field' => __DIR__ . '/fixtures/info_field_dataset.csv',
                'custom_info_data' => __DIR__ . '/fixtures/info_data_dataset.csv',
            )
        );
        $this->loadDataSet($dataset);

        // text field
        $formfield = new profile_field_text(1, 3);
        $this->assertInstanceOf('profile_field_base', $formfield);
        $this->assertFalse($formfield->is_empty());
        $this->assertEquals('my own text 1', $formfield->display_data());
        $this->assertNotEmpty($formfield->inputname);
        $this->assertTrue($formfield->is_visible());

        // checkbox field
        $formfield = new profile_field_checkbox(2, 3);
        $this->assertInstanceOf('profile_field_base', $formfield);
        $this->assertFalse($formfield->is_empty());
        $this->assertRegExp('/<input\b.*\btype="checkbox"/', $formfield->display_data());
        $this->assertNotEmpty($formfield->inputname);
        $this->assertFalse($formfield->is_visible());
    }
}
