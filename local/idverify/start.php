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
 * Start identity verification: redirect to the provider.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_idverify\local\crypto;
use local_idverify\local\flow;
use local_idverify\provider\registry;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot verify an identity');
}
require_sesskey();
require_capability('local/idverify:verifyself', context_system::instance());

$returnurl = new moodle_url('/local/idverify/index.php');
$provider = registry::get_active();
if (!$provider || !crypto::has_valid_key()) {
    redirect($returnurl, get_string('error:notavailable', 'local_idverify'), null, \core\output\notification::NOTIFY_ERROR);
}

redirect(flow::begin($provider));
