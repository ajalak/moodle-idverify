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

namespace availability_idverify;

use core_availability\info;
use local_idverify\local\flow;
use local_idverify\local\identity_manager;

/**
 * Restriction "Identity verified": available once the user has a verified identity (local_idverify).
 *
 * Fails open by design: whenever verification is not working on the site (no HMAC key, provider disabled,
 * local_idverify missing or throwing), the condition lets everyone through, so certificates are issued as
 * usual. When this plugin is disabled or uninstalled, Moodle ignores the condition altogether.
 *
 * Not applied to user lists: like completion or date conditions it is temporary (the learner can verify at
 * any time), and customcert's automatic issuing checks each user with is_available() anyway.
 *
 * @package    availability_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var bool[] userid => verified, per request. */
    protected static array $verified = [];

    /** @var bool|null Whether verification works on this site, per request. */
    protected static ?bool $enforced = null;

    /**
     * Constructor. The condition has no settings.
     *
     * @param \stdClass $structure Saved data.
     */
    public function __construct($structure) {
    }

    #[\Override]
    public function save() {
        return self::get_json();
    }

    /**
     * JSON for this condition (for tests and code that builds restrictions, e.g. the course builder).
     *
     * @return \stdClass
     */
    public static function get_json(): \stdClass {
        return (object)['type' => 'idverify'];
    }

    /**
     * Whether the condition is enforced: identity verification works on this site right now.
     *
     * @return bool
     */
    public static function is_enforced(): bool {
        if (self::$enforced === null) {
            try {
                self::$enforced = class_exists(flow::class) && flow::is_available();
            } catch (\Throwable $e) {
                self::$enforced = false;
            }
        }
        return self::$enforced;
    }

    #[\Override]
    public function is_available($not, info $info, $grabthelot, $userid) {
        if (!self::is_enforced()) {
            // Fail open, whichever way round the condition is used.
            return true;
        }
        $userid = (int)$userid;
        if (!array_key_exists($userid, self::$verified)) {
            try {
                self::$verified[$userid] = identity_manager::is_verified($userid);
            } catch (\Throwable $e) {
                return true;
            }
        }
        return $not ? !self::$verified[$userid] : self::$verified[$userid];
    }

    #[\Override]
    public function get_description($full, $not, info $info) {
        if ($not) {
            return get_string('requires_notverified', 'availability_idverify');
        }
        $link = \html_writer::link(
            new \moodle_url('/local/idverify/index.php'),
            get_string('verifylink', 'availability_idverify')
        );
        $description = get_string('requires_verified', 'availability_idverify', $link);
        if ($full && !self::is_enforced()) {
            $description .= ' ' . get_string('notenforced', 'availability_idverify');
        }
        return $description;
    }

    #[\Override]
    protected function get_debug_string() {
        return self::is_enforced() ? 'enforced' : 'not enforced';
    }

    /**
     * Clear the per-request caches (after a verification in the same request, and in unit tests).
     */
    public static function wipe_static_cache(): void {
        self::$verified = [];
        self::$enforced = null;
    }
}
