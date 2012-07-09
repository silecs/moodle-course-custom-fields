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

require_once dirname(__DIR__) . '/lib.php';

// To run only this test case: phpunit user_testcase user/tests/user_test.php

/**
 * Test user functions.
 *
 * @package    core
 * @category   phpunit
 * @copyright  2012 François Gannaz <francois.gannaz@silecs.info>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_testcase extends advanced_testcase {
    public function test_user_get_user_details() {
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

        // customfields partly visible
        $user = $DB->get_record('user', array('id' => 3));
        $user_details = user_get_user_details($user);
        $this->assertInternalType('array', $user_details);
        $this->assertArrayHasKey('customfields', $user_details);
        $this->assertCount(1, $user_details['customfields']);
        $this->assertArrayHasKey('name', $user_details['customfields'][0]);
        $this->assertEquals('my text field', $user_details['customfields'][0]['name']);
        $this->assertArrayHasKey('value', $user_details['customfields'][0]);
        $this->assertEquals('my own text 1', $user_details['customfields'][0]['value']);
        unset($user, $user_details);

        // customfields visible
        $this->setAdminUser();
        $user = $DB->get_record('user', array('id' => 3));
        $user_details = user_get_user_details($user);
        $this->assertCount(2, $user_details['customfields']);
        $this->assertArrayHasKey('value', $user_details['customfields'][1]);
        $this->assertEquals('1', $user_details['customfields'][1]['value']);
        unset($user, $user_details);

        // no customfields
        $this->setGuestUser();
        $user = $DB->get_record('user', array('id' => 4));
        $user_details = user_get_user_details($user);
        $this->assertInternalType('array', $user_details);
        $this->assertfalse(isset($user_details['customfields']));
    }
}
