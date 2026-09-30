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
 * Upgrade steps for local_idverify.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade local_idverify.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_idverify_upgrade($oldversion) {
    if ($oldversion < 2026093000) {
        // 1.0.0-rc3: eID Easy replaced by eeID. Switch verification off until eeID is configured, and delete the
        // eID Easy credentials. Identities verified through eID Easy stay as they are (provider "eideasy").
        if (get_config('local_idverify', 'provider') === 'eideasy') {
            set_config('provider', '', 'local_idverify');
        }
        foreach (['eideasy_env', 'eideasy_clientid', 'eideasy_secret'] as $name) {
            unset_config($name, 'local_idverify');
        }
        upgrade_plugin_savepoint(true, 2026093000, 'local', 'idverify');
    }
    return true;
}
