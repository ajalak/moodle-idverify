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
 * Event: identity data was kept, not deleted, because issued certificates printed it (legal obligation).
 *
 * Fired for privacy deletion requests and account deletions. The data protection officer decides by hand.
 * Carries user ids, a reason code and the number of issued certificates only.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class identity_retained extends \core\event\base {
    #[\Override]
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_idverify_identity';
    }

    #[\Override]
    public static function get_name() {
        return get_string('event:identity_retained', 'local_idverify');
    }

    #[\Override]
    public function get_description() {
        return "The verified identity of the user with id '$this->relateduserid' was kept instead of deleted " .
            "(reason '{$this->other['reason']}'): it is printed on {$this->other['issued']} issued certificate(s).";
    }

    #[\Override]
    public function get_url() {
        return new \moodle_url('/local/idverify/admin/view.php', ['userid' => $this->relateduserid]);
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['reason'])) {
            throw new \coding_exception('The \'reason\' value must be set in other.');
        }
    }
}
