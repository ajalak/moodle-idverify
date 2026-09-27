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
 * Event: a user's verified identity was removed.
 *
 * Carries user ids and method, provider or reason codes only, never an ID code or name.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class identity_revoked extends \core\event\base {
    #[\Override]
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_idverify_identity';
    }

    #[\Override]
    public static function get_name() {
        return get_string('event:identity_revoked', 'local_idverify');
    }

    #[\Override]
    public function get_description() {
        return "The user with id '$this->userid' revoked the verified identity of the user with id '$this->relateduserid'.";
    }

    #[\Override]
    public function get_url() {
        return new \moodle_url('/local/idverify/index.php');
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }
}
