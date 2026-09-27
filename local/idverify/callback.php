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
 * Return from the identity provider (the redirect URI registered with it).
 *
 * No sesskey here: the provider redirects the browser back; the session-bound, single-use state is the
 * CSRF protection.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core\output\notification;
use local_idverify\local\conflict_exception;
use local_idverify\local\flow;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot verify an identity');
}
require_capability('local/idverify:verifyself', context_system::instance());

$state = optional_param('state', '', PARAM_ALPHANUM);
$params = [
    'code' => optional_param('code', '', PARAM_RAW_TRIMMED),
    'error' => optional_param('error', '', PARAM_ALPHANUMEXT),
];

$returnurl = new moodle_url('/local/idverify/index.php');
try {
    flow::complete($state, $params);
} catch (conflict_exception $e) {
    redirect($returnurl, get_string('error:conflict', 'local_idverify'), null, notification::NOTIFY_ERROR);
} catch (moodle_exception $e) {
    // The event log has the reason code; the user gets the message of the error (no personal data in it).
    $message = $e->module === 'local_idverify' ? $e->getMessage() : get_string('error:provider', 'local_idverify');
    redirect($returnurl, $message, null, notification::NOTIFY_ERROR);
}
redirect($returnurl, get_string('verified', 'local_idverify'), null, notification::NOTIFY_SUCCESS);
