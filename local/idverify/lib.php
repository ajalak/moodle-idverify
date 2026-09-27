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
 * Callbacks for local_idverify.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Status checks shown in Site administration > Reports > System status.
 *
 * @return \core\check\check[]
 */
function local_idverify_status_checks(): array {
    return [new \local_idverify\check\hmackey()];
}

/**
 * Add a "My identity" link to the user's own profile page.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user Profile owner.
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 */
function local_idverify_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (
        !$iscurrentuser || isguestuser($user)
            || !has_capability('local/idverify:verifyself', \context_system::instance())
    ) {
        return;
    }
    $node = new \core_user\output\myprofile\node(
        'miscellaneous',
        'local_idverify',
        get_string('myidentity', 'local_idverify'),
        null,
        new moodle_url('/local/idverify/index.php')
    );
    $tree->add_node($node);
}
