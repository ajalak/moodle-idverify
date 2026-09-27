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
 * My identity: verification status and the "Verify identity" button.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot verify an identity');
}
$context = context_system::instance();
require_capability('local/idverify:verifyself', $context);

$PAGE->set_url(new moodle_url('/local/idverify/index.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('myidentity', 'local_idverify'));
$PAGE->set_heading(get_string('myidentity', 'local_idverify'));

echo $OUTPUT->header();
echo $OUTPUT->render(new \local_idverify\output\my_identity((int)$USER->id));
echo $OUTPUT->footer();
