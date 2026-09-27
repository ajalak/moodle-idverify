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
 * Revoke a user's verified identity (confirm page).
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\output\notification;
use local_idverify\local\identity_manager;

$userid = required_param('userid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$url = new moodle_url('/local/idverify/admin/revoke.php', ['userid' => $userid]);
admin_externalpage_setup('local_idverify_manage', '', null, $url);
require_capability('local/idverify:manage', context_system::instance());
$returnurl = new moodle_url('/local/idverify/admin/index.php');

$user = core_user::get_user($userid, '*', MUST_EXIST);
$identity = identity_manager::get($userid);
if (!$identity) {
    throw new moodle_exception('error:noidentity', 'local_idverify', $returnurl);
}

if ($confirm && data_submitted() && confirm_sesskey()) {
    identity_manager::revoke($userid);
    redirect($returnurl, get_string('revoke:done', 'local_idverify', fullname($user)), null, notification::NOTIFY_SUCCESS);
}

$PAGE->navbar->add(get_string('revoke', 'local_idverify'), $url);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('revoke', 'local_idverify'));
$issued = identity_manager::count_issued($userid);
if ($issued) {
    echo $OUTPUT->notification(get_string('revoke:issuedwarning', 'local_idverify', $issued), notification::NOTIFY_WARNING);
}
echo $OUTPUT->confirm(
    get_string('revoke:confirm', 'local_idverify', fullname($user)),
    new single_button(
        new moodle_url($url, ['confirm' => 1]),
        get_string('revoke', 'local_idverify'),
        'post',
        single_button::BUTTON_DANGER
    ),
    $returnurl
);
echo $OUTPUT->footer();
