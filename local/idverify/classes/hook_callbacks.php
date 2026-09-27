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

    /**
     * Keep the legal name on verified accounts ("Use legal name" setting).
     *
     * Reverts first and last name changes made through user_update_user() (profile edit form, OAuth2 profile
     * sync at login, web services) unless the acting user can manage identities.
     *
     * @param \core_user\hook\before_user_updated $hook
     */
    public static function before_user_updated(\core_user\hook\before_user_updated $hook): void {
        if (empty($hook->user->id) || get_config('local_idverify', 'overwritenames') === '0') {
            return;
        }
        $identity = identity_manager::get((int)$hook->user->id);
        if (!$identity) {
            return;
        }
        if (isloggedin() && has_capability('local/idverify:manage', \context_system::instance())) {
            return;
        }
        foreach (['firstname', 'lastname'] as $field) {
            if (property_exists($hook->user, $field) && $hook->user->$field !== $identity->$field) {
                $hook->user->$field = $identity->$field;
            }
        }
    }

    /**
     * Account deletion: same rule as a privacy deletion request (identity_manager::delete_personal_data()).
     *
     * @param \core_user\hook\before_user_deleted $hook
     */
    public static function before_user_deleted(\core_user\hook\before_user_deleted $hook): void {
        identity_manager::delete_personal_data((int)$hook->user->id, 'userdeleted');
    }
}
