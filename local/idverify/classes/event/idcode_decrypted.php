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

namespace local_idverify\event;

/**
 * Event: a verified ID code was decrypted to print it on a certificate.
 *
 * Carries user ids and method, provider or reason codes only, never an ID code or name.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class idcode_decrypted extends \core\event\base {
    #[\Override]
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    #[\Override]
    public static function get_name() {
        return get_string('event:idcode_decrypted', 'local_idverify');
    }

    #[\Override]
    public function get_description() {
        return "The user with id '$this->userid' rendered a certificate showing the verified ID code of the user " .
            "with id '$this->relateduserid' (certificate element id '{$this->other['elementid']}').";
    }

    #[\Override]
    public function get_url() {
        return $this->context->get_url();
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }
}
