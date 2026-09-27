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
 * Hook callbacks for local_idverify.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core_user\hook\extend_user_menu::class,
        'callback' => [\local_idverify\hook_callbacks::class, 'extend_user_menu'],
    ],
    [
        'hook' => \core_user\hook\before_user_updated::class,
        'callback' => [\local_idverify\hook_callbacks::class, 'before_user_updated'],
    ],
    [
        'hook' => \core_user\hook\before_user_deleted::class,
        'callback' => [\local_idverify\hook_callbacks::class, 'before_user_deleted'],
    ],
];
