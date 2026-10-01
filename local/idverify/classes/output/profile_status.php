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

namespace local_idverify\output;

use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;
use local_idverify\local\flow;
use local_idverify\local\identity_manager;

/**
 * The "Identity verification" line in "User details" on the profile page.
 *
 * Shown to the profile owner and to identity managers only: whether and how someone verified is personal data.
 * Never shows the code or the legal name. Hidden for unverified users while verification does not work on the
 * site, so learners are not sent to a page where they cannot do anything.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_status {
    /**
     * Add the line to a profile tree, if the viewer may see it.
     *
     * @param tree $tree
     * @param \stdClass $user Profile owner.
     * @param bool $iscurrentuser Whether the viewer owns the profile.
     */
    public static function add_to_tree(tree $tree, \stdClass $user, bool $iscurrentuser): void {
        $content = self::content($user, $iscurrentuser);
        if ($content !== null) {
            $tree->add_node(new node(
                'contact',
                'local_idverify',
                get_string('profile:title', 'local_idverify'),
                null,
                null,
                $content
            ));
        }
    }

    /**
     * HTML of the line for this viewer, or null when nothing is shown.
     *
     * @param \stdClass $user Profile owner.
     * @param bool $iscurrentuser Whether the viewer owns the profile.
     * @return string|null
     */
    public static function content(\stdClass $user, bool $iscurrentuser): ?string {
        if (isguestuser($user) || !empty($user->deleted)) {
            return null;
        }
        $context = \context_system::instance();
        $canmanage = has_capability('local/idverify:manage', $context);
        if ($iscurrentuser ? !has_capability('local/idverify:verifyself', $context) : !$canmanage) {
            return null;
        }

        $identity = identity_manager::get((int)$user->id);
        if ($identity) {
            $date = userdate($identity->timeverified, get_string('strftimedate', 'langconfig'));
            $text = $identity->method === 'manual'
                ? get_string('profile:verifiedmanual', 'local_idverify', $date)
                : get_string('profile:verified', 'local_idverify', (object)[
                    'date' => $date,
                    'method' => get_string('method:' . $identity->method, 'local_idverify'),
                ]);
            $url = $iscurrentuser ? new \moodle_url('/local/idverify/index.php')
                : new \moodle_url('/local/idverify/admin/view.php', ['userid' => $user->id]);
            $linktext = get_string($iscurrentuser ? 'myidentity' : 'profile:details', 'local_idverify');
            return \html_writer::span(s($text), 'text-success') . ' · ' . \html_writer::link($url, $linktext);
        }

        if (!flow::is_available()) {
            return null;
        }
        $badge = \html_writer::span(get_string('profile:notverified', 'local_idverify'), 'badge bg-warning text-dark');
        if (!$iscurrentuser) {
            return $badge;
        }
        return $badge . ' ' . \html_writer::link(
            new \moodle_url('/local/idverify/index.php', [
                'returnurl' => (new \moodle_url('/user/profile.php', ['id' => $user->id]))->out_as_local_url(false),
            ]),
            get_string('verifynow', 'local_idverify'),
            ['class' => 'fw-bold']
        );
    }
}
