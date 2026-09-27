<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Settings for local_idverify.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_idverify\local\crypto;
use local_idverify\local\verified_person;
use local_idverify\provider\mock;
use local_idverify\provider\registry;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_idverify', new lang_string('pluginname', 'local_idverify'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        if (!crypto::has_valid_key()) {
            $settings->add(new admin_setting_heading(
                'local_idverify/hmackeywarning',
                '',
                html_writer::div(get_string('error:hmackey_admin', 'local_idverify'), 'alert alert-danger')
            ));
        }

        $settings->add(new admin_setting_configselect(
            'local_idverify/provider',
            new lang_string('setting:provider', 'local_idverify'),
            new lang_string('setting:provider_desc', 'local_idverify'),
            '',
            ['' => get_string('provider:none', 'local_idverify')] + registry::get_choices()
        ));

        $settings->add(new admin_setting_configtext(
            'local_idverify/allowedcountries',
            new lang_string('setting:allowedcountries', 'local_idverify'),
            new lang_string('setting:allowedcountries_desc', 'local_idverify'),
            'EE',
            '/^\s*[A-Za-z]{2}(\s*,\s*[A-Za-z]{2})*\s*$/'
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_idverify/overwritenames',
            new lang_string('setting:overwritenames', 'local_idverify'),
            new lang_string('setting:overwritenames_desc', 'local_idverify'),
            1
        ));

        if (mock::allowed()) {
            $settings->add(new admin_setting_heading(
                'local_idverify/mockheading',
                new lang_string('setting:mockheading', 'local_idverify'),
                new lang_string('setting:mockheading_desc', 'local_idverify')
            ));
            foreach (['country', 'idcode', 'firstname', 'lastname', 'birthdate'] as $key) {
                $settings->add(new admin_setting_configtext(
                    'local_idverify/mock_' . $key,
                    new lang_string('setting:mock_' . $key, 'local_idverify'),
                    '',
                    mock::DEFAULTS[$key],
                    PARAM_TEXT
                ));
            }
            $methods = [];
            $eidmethods = [verified_person::METHOD_SMARTID, verified_person::METHOD_IDCARD, verified_person::METHOD_MOBILEID];
            foreach ($eidmethods as $method) {
                $methods[$method] = get_string('method:' . $method, 'local_idverify');
            }
            $settings->add(new admin_setting_configselect(
                'local_idverify/mock_method',
                new lang_string('method', 'local_idverify'),
                '',
                mock::DEFAULTS['method'],
                $methods
            ));
        }
    }
}
