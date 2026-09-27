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
 * HMAC and encryption of ID codes.
 *
 * The HMAC key lives only in config.php ($CFG->local_idverify_hmackey), never in the database, so a database
 * dump alone cannot be used to test guessed codes against idcode_hmac. The code itself is encrypted with
 * \core\encryption, whose key is in moodledata/secret/. Both keys must be backed up (see README).
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class crypto {
    /** Minimum HMAC key length in bytes. */
    public const MIN_KEY_BYTES = 32;

    /**
     * Whether a usable HMAC key is configured.
     *
     * @return bool
     */
    public static function has_valid_key(): bool {
        global $CFG;
        $key = $CFG->local_idverify_hmackey ?? null;
        return is_string($key) && strlen($key) >= self::MIN_KEY_BYTES;
    }

    /**
     * Throw unless a usable HMAC key is configured.
     *
     * @throws \moodle_exception
     */
    public static function require_valid_key(): void {
        if (!self::has_valid_key()) {
            throw new \moodle_exception('error:hmackey', 'local_idverify');
        }
    }

    /**
     * HMAC-SHA256 (hex) of the normalised identity, e.g. of "EE38112086027".
     *
     * @param string $country Normalised country code.
     * @param string $code Normalised ID code.
     * @return string 64 hex characters.
     */
    public static function hmac(string $country, string $code): string {
        global $CFG;
        self::require_valid_key();
        return hash_hmac('sha256', $country . $code, $CFG->local_idverify_hmackey);
    }

    /**
     * Encrypt an ID code.
     *
     * @param string $code
     * @return string
     */
    public static function encrypt(string $code): string {
        return \core\encryption::encrypt($code);
    }

    /**
     * Decrypt an ID code.
     *
     * @param string $encrypted
     * @return string
     * @throws \moodle_exception If the site encryption key is missing or has changed.
     */
    public static function decrypt(string $encrypted): string {
        return \core\encryption::decrypt($encrypted);
    }
}
