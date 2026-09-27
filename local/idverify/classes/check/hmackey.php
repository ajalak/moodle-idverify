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
use local_idverify\local\crypto;

/**
 * Status check: the HMAC key in config.php is present and long enough.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hmackey extends check {
    #[\Override]
    public function get_result(): result {
        if (crypto::has_valid_key()) {
            return new result(result::OK, get_string('check:hmackey_ok', 'local_idverify'));
        }
        return new result(
            result::ERROR,
            get_string('check:hmackey_error', 'local_idverify'),
            get_string('error:hmackey_admin', 'local_idverify')
        );
    }
}
