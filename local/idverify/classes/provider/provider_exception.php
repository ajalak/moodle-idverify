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

namespace local_idverify\provider;

/**
 * A provider could not verify the user. Carries a short reason code for the log; never personal data.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_exception extends \moodle_exception {
    /** @var string Reason code for the verification_failed event, e.g. "cancelled", "http_401". */
    public readonly string $reason;

    /**
     * Constructor.
     *
     * @param string $reason Reason code (letters, digits, underscores).
     * @param string $errorcode Language string shown to the user.
     * @param string|null $debuginfo Technical detail for admins (no personal data).
     */
    public function __construct(string $reason, string $errorcode = 'error:provider', ?string $debuginfo = null) {
        $this->reason = clean_param($reason, PARAM_ALPHANUMEXT);
        parent::__construct($errorcode, 'local_idverify', '', null, $debuginfo);
    }
}
