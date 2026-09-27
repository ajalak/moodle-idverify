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

namespace local_idverify\form;

use local_idverify\local\identity_manager;
use local_idverify\local\idcode;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Manual verification by an admin, from an identity document.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manual_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('autocomplete', 'userid', get_string('user'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
        ]);
        $mform->addRule('userid', null, 'required', null, 'client');
        $mform->setType('userid', PARAM_INT);

        $countries = get_string_manager()->get_list_of_countries();
        $mform->addElement('select', 'country', get_string('country'), $countries);
        $mform->setDefault('country', 'EE');

        $mform->addElement('text', 'idcode', get_string('idcode', 'local_idverify'), ['size' => 20]);
        $mform->setType('idcode', PARAM_TEXT);
        $mform->addHelpButton('idcode', 'manual:idcode', 'local_idverify');

        $mform->addElement(
            'date_selector',
            'birthdate',
            get_string('birthdate', 'local_idverify'),
            ['startyear' => 1900, 'optional' => true]
        );

        $mform->addElement('text', 'firstname', get_string('manual:firstname', 'local_idverify'), ['size' => 40]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required', null, 'client');

        $mform->addElement('text', 'lastname', get_string('manual:lastname', 'local_idverify'), ['size' => 40]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required', null, 'client');

        $mform->addElement('textarea', 'note', get_string('manual:note', 'local_idverify'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addRule('note', null, 'required', null, 'client');
        $mform->addHelpButton('note', 'manual:note', 'local_idverify');

        $this->add_action_buttons(true, get_string('manual:submit', 'local_idverify'));
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['userid']) || !\core_user::get_user((int)$data['userid'])) {
            $errors['userid'] = get_string('required');
        } else if (identity_manager::is_verified((int)$data['userid'])) {
            $errors['userid'] = get_string('manual:alreadyverified', 'local_idverify');
        }

        $code = idcode::normalise_code((string)($data['idcode'] ?? ''));
        if ($code === '' && empty($data['birthdate'])) {
            $errors['idcode'] = get_string('manual:codeorbirthdate', 'local_idverify');
        }
        if ($code !== '' && ($data['country'] ?? '') === 'EE' && !idcode::is_valid_ee($code)) {
            $errors['idcode'] = get_string('error:invalididcode', 'local_idverify');
        }
        foreach (['firstname', 'lastname', 'note'] as $field) {
            if (trim((string)($data[$field] ?? '')) === '') {
                $errors[$field] = get_string('required');
            }
        }
        return $errors;
    }
}
