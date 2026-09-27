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
 * Manual verification of a user's identity from a document.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\output\notification;
use local_idverify\local\conflict_exception;
use local_idverify\local\identity_manager;
use local_idverify\local\verified_person;

$url = new moodle_url('/local/idverify/admin/manual.php');
admin_externalpage_setup('local_idverify_manage', '', null, $url);
require_capability('local/idverify:manage', context_system::instance());
$PAGE->navbar->add(get_string('manual:title', 'local_idverify'), $url);
$returnurl = new moodle_url('/local/idverify/admin/index.php');

$form = new \local_idverify\form\manual_form($url);
$error = null;
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $birthdate = empty($data->birthdate) ? null : userdate($data->birthdate, '%Y-%m-%d', 99, false, false);
    $person = new verified_person(
        $data->country,
        trim($data->idcode) === '' ? null : $data->idcode,
        $data->firstname,
        $data->lastname,
        $birthdate,
        verified_person::METHOD_MANUAL,
        'manual'
    );
    try {
        identity_manager::verify((int)$data->userid, $person, (int)$USER->id, trim($data->note));
        redirect($returnurl, get_string('manual:done', 'local_idverify'), null, notification::NOTIFY_SUCCESS);
    } catch (conflict_exception $e) {
        $error = get_string('manual:conflict', 'local_idverify');
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manual:title', 'local_idverify'));
echo html_writer::tag('p', get_string('manual:intro', 'local_idverify'));
if ($error !== null) {
    echo $OUTPUT->notification($error, notification::NOTIFY_ERROR);
}
$form->display();
echo $OUTPUT->footer();
