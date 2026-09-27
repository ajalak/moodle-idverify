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
 * Mock provider "login page" (developer debug mode only).
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_idverify\provider\mock;

require_login(null, false);
if (!mock::allowed() || isguestuser()) {
    throw new moodle_exception('error:notavailable', 'local_idverify');
}

$state = required_param('state', PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHA);
$callback = new moodle_url('/local/idverify/callback.php', ['state' => $state]);

if ($action === 'approve' && confirm_sesskey()) {
    $callback->param('code', mock::issue_code());
    redirect($callback);
} else if ($action === 'cancel' && confirm_sesskey()) {
    $callback->param('error', 'user_cancelled');
    redirect($callback);
}

$url = new moodle_url('/local/idverify/mock.php', ['state' => $state]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mock:title', 'local_idverify'));
$PAGE->set_heading(get_string('mock:title', 'local_idverify'));

$person = mock::person();
$table = new html_table();
foreach ($person as $key => $value) {
    $table->data[] = [s($key), s($value)];
}

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('mock:warning', 'local_idverify'), \core\output\notification::NOTIFY_WARNING);
echo html_writer::table($table);
echo $OUTPUT->single_button(
    new moodle_url($url, ['action' => 'approve']),
    get_string('mock:approve', 'local_idverify'),
    'post',
    ['type' => single_button::BUTTON_PRIMARY]
);
echo $OUTPUT->single_button(new moodle_url($url, ['action' => 'cancel']), get_string('cancel'));
echo $OUTPUT->footer();
