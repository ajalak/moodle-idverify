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

namespace local_idverify\check;

use core\check\check;
use core\check\result;
use local_idverify\local\service_health;

/**
 * Status check: the identity provider seems to work (no run of failures, learners come back from it).
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service extends check {
    #[\Override]
    public function get_action_link(): ?\action_link {
        return new \action_link(
            new \moodle_url('/local/idverify/admin/index.php'),
            get_string('manage', 'local_idverify')
        );
    }

    #[\Override]
    public function get_result(): result {
        $provider = (string)get_config('local_idverify', 'provider');
        if ($provider === '') {
            return new result(result::NA, get_string('check:service_off', 'local_idverify'));
        }
        $warning = service_health::get_warning();
        if ($warning === null) {
            return new result(result::OK, get_string('check:service_ok', 'local_idverify'));
        }
        return new result(
            result::WARNING,
            get_string('check:service_warning', 'local_idverify'),
            service_health::describe($warning)
        );
    }
}
