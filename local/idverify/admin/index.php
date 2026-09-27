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
 * Verified identities: list, search, links to manual verification, unmasked view and revoke.
 *
 * Codes are shown masked here; the full code is only on the view page, one user at a time, and logged.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_idverify\local\identity_manager;
use local_idverify\local\idcode;

$q = optional_param('q', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 30;

admin_externalpage_setup('local_idverify_manage', '', ['q' => $q]);
$context = context_system::instance();
require_capability('local/idverify:manage', $context);
$canviewcode = has_capability('local/idverify:viewidcode', $context);
$baseurl = new moodle_url('/local/idverify/admin/index.php', ['q' => $q]);

$where = '1 = 1';
$params = [];
if (trim($q) !== '') {
    $conditions = [];
    foreach (['u.firstname', 'u.lastname', 'u.email', 'i.firstname', 'i.lastname'] as $i => $field) {
        $conditions[] = $DB->sql_like($field, ':q' . $i, false, false);
        $params['q' . $i] = '%' . $DB->sql_like_escape(trim($q)) . '%';
    }
    $where = '(' . implode(' OR ', $conditions) . ')';
}
$userfields = \core_user\fields::for_name()->get_sql('u', false, 'u_', '', false)->selects;
$sql = "SELECT i.*, $userfields, u.email AS u_email
          FROM {local_idverify_identity} i
          JOIN {user} u ON u.id = i.userid
         WHERE $where
      ORDER BY i.timeverified DESC";
$total = $DB->count_records_sql("SELECT COUNT(1) FROM {local_idverify_identity} i JOIN {user} u ON u.id = i.userid
                                  WHERE $where", $params);
$records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

$issued = [];
if ($records) {
    [$insql, $inparams] = $DB->get_in_or_equal(array_column($records, 'userid'));
    $issued = $DB->get_records_sql_menu("SELECT userid, COUNT(1) FROM {local_idverify_issued}
                                          WHERE userid $insql GROUP BY userid", $inparams);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage', 'local_idverify'));

echo html_writer::start_div('d-flex flex-wrap gap-2 mb-3 align-items-center');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'class' => 'd-flex gap-2']);
echo html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $q, 'class' => 'form-control',
    'placeholder' => get_string('manage:search', 'local_idverify'), 'aria-label' => get_string('search')]);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
echo $OUTPUT->single_button(
    new moodle_url('/local/idverify/admin/manual.php'),
    get_string('manual:title', 'local_idverify'),
    'get',
    ['type' => single_button::BUTTON_PRIMARY]
);
echo html_writer::end_div();

if (!$records) {
    echo $OUTPUT->notification(get_string('manage:empty', 'local_idverify'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [get_string('user'), get_string('legalname', 'local_idverify'), get_string('idcode', 'local_idverify'),
        get_string('method', 'local_idverify'), get_string('timeverified', 'local_idverify'),
        get_string('issuedcount', 'local_idverify'), get_string('actions')];
    foreach ($records as $record) {
        $user = user_picture::unalias($record, ['email'], 'id', 'u_');
        $user->id = $record->userid;
        $code = identity_manager::decrypt_idcode($record);
        $actions = [];
        if ($canviewcode) {
            $actions[] = html_writer::link(
                new moodle_url('/local/idverify/admin/view.php', ['userid' => $record->userid]),
                get_string('view')
            );
        }
        $actions[] = html_writer::link(
            new moodle_url('/local/idverify/admin/revoke.php', ['userid' => $record->userid]),
            get_string('revoke', 'local_idverify')
        );
        $table->data[] = [
            html_writer::link(new moodle_url('/user/profile.php', ['id' => $record->userid]), fullname($user)),
            s($record->firstname . ' ' . $record->lastname),
            $code === null ? s($record->birthdate) : html_writer::span(s(idcode::mask($code)), 'text-monospace') .
                ' (' . s($record->country) . ')',
            get_string('method:' . $record->method, 'local_idverify'),
            userdate($record->timeverified, get_string('strftimedatetimeshort', 'langconfig')),
            (int)($issued[$record->userid] ?? 0),
            implode(' · ', $actions),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
