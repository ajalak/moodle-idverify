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

namespace local_idverify\local;

/**
 * OAuth "state": bound to the Moodle session, single use, short lived.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class state {
    /** Seconds a verification attempt may take. */
    public const TTL = 600;

    /**
     * Start an attempt: store a new random state in the session.
     *
     * @param string $provider Provider name the attempt belongs to.
     * @return string The state value to send to the provider.
     */
    public static function create(string $provider): string {
        global $SESSION;
        $value = random_string(40);
        $SESSION->local_idverify_state = ['value' => $value, 'provider' => $provider, 'time' => time()];
        return $value;
    }

    /**
     * Check a returned state and remove it from the session, matching or not.
     *
     * @param string $received State from the callback request.
     * @return string The provider name of the attempt.
     * @throws \moodle_exception If there is no attempt, it has expired or the state does not match.
     */
    public static function consume(string $received): string {
        global $SESSION;
        $stored = $SESSION->local_idverify_state ?? null;
        unset($SESSION->local_idverify_state);

        if (!is_array($stored) || $received === '' || !hash_equals($stored['value'], $received)) {
            throw new \moodle_exception('error:state', 'local_idverify');
        }
        if (time() - $stored['time'] > self::TTL) {
            throw new \moodle_exception('error:stateexpired', 'local_idverify');
        }
        return $stored['provider'];
    }
}
