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

namespace local_idverify;

use local_idverify\local\identity_manager;

/**
 * Hook callbacks.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add "Verify identity" (or "My identity" once verified) to the user menu.
     *
     * Uses the idverified flag already in the session ($USER->profile), so no query on every page.
     *
     * @param \core_user\hook\extend_user_menu $hook
     */
    public static function extend_user_menu(\core_user\hook\extend_user_menu $hook): void {
        global $USER;
        if (
            !isloggedin() || isguestuser()
                || !has_capability('local/idverify:verifyself', \context_system::instance())
        ) {
            return;
        }
        $verified = ($USER->profile[identity_manager::PROFILE_FIELD] ?? '') === '1';
        $hook->add_navitem((object)[
            'itemtype' => 'link',
            'url' => new \moodle_url('/local/idverify/index.php'),
            'title' => get_string($verified ? 'myidentity' : 'verifyidentity', 'local_idverify'),
            'titleidentifier' => ($verified ? 'myidentity' : 'verifyidentity') . ',local_idverify',
        ]);
    }
}
