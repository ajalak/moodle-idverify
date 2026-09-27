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
 * One user's verified identity. The full ID code is shown only after a deliberate, logged "Show code" (POST).
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_idverify\event\idcode_viewed;
use local_idverify\local\identity_manager;
use local_idverify\local\idcode;

$userid = required_param('userid', PARAM_INT);
$url = new moodle_url('/local/idverify/admin/view.php', ['userid' => $userid]);
admin_externalpage_setup('local_idverify_manage', '', null, $url);
$context = context_system::instance();
require_capability('local/idverify:viewidcode', $context);

$user = core_user::get_user($userid, '*', MUST_EXIST);
$identity = identity_manager::get($userid);
if (!$identity) {
    throw new moodle_exception('error:noidentity', 'local_idverify', new moodle_url('/local/idverify/admin/index.php'));
}
$PAGE->navbar->add(fullname($user), $url);

$reveal = optional_param('reveal', 0, PARAM_BOOL) && data_submitted() && confirm_sesskey();
$code = identity_manager::decrypt_idcode($identity);
if ($code !== null && $reveal) {
    idcode_viewed::create([
        'objectid' => $identity->id,
        'relateduserid' => $userid,
        'context' => $context,
    ])->trigger();
}

$verifiedby = $identity->verifiedby ? core_user::get_user($identity->verifiedby) : null;
$rows = [
    get_string('user') => html_writer::link(new moodle_url('/user/profile.php', ['id' => $userid]), fullname($user)),
    get_string('legalname', 'local_idverify') => s($identity->firstname . ' ' . $identity->lastname),
    get_string('country') => s($identity->country),
    get_string('idcode', 'local_idverify') => $code === null ? '–' :
        html_writer::span(s($reveal ? $code : idcode::mask($code)), 'text-monospace'),
    get_string('birthdate', 'local_idverify') => s((string)$identity->birthdate),
    get_string('method', 'local_idverify') => get_string('method:' . $identity->method, 'local_idverify'),
    get_string('provider', 'local_idverify') => s($identity->provider),
    get_string('timeverified', 'local_idverify') => userdate($identity->timeverified),
    get_string('verifiedby', 'local_idverify') => $verifiedby ? s(fullname($verifiedby)) : '–',
    get_string('manual:note', 'local_idverify') => $identity->note ? format_text($identity->note, FORMAT_PLAIN) : '–',
    get_string('issuedcount', 'local_idverify') => identity_manager::count_issued($userid),
];
$table = new html_table();
foreach ($rows as $label => $value) {
    $table->data[] = [html_writer::tag('strong', $label), $value];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(fullname($user));
echo html_writer::table($table);
if ($code !== null && !$reveal) {
    echo html_writer::tag('p', get_string('view:revealnote', 'local_idverify'), ['class' => 'text-muted']);
    echo $OUTPUT->single_button(new moodle_url($url, ['reveal' => 1]), get_string('view:reveal', 'local_idverify'), 'post');
}
echo $OUTPUT->single_button(
    new moodle_url('/local/idverify/admin/revoke.php', ['userid' => $userid]),
    get_string('revoke', 'local_idverify'),
    'get',
    ['type' => single_button::BUTTON_DANGER]
);
echo $OUTPUT->footer();
