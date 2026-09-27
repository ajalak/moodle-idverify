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
 * Personal ID code helpers: normalisation, Estonian isikukood validation, masking.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class idcode {
    /** Weights of the first isikukood checksum round. */
    private const WEIGHTS1 = [1, 2, 3, 4, 5, 6, 7, 8, 9, 1];
    /** Weights of the second round, used when the first gives 10. */
    private const WEIGHTS2 = [3, 4, 5, 6, 7, 8, 9, 1, 2, 3];

    /**
     * Normalise a country code: trimmed, upper case.
     *
     * @param string $country
     * @return string
     */
    public static function normalise_country(string $country): string {
        return strtoupper(trim($country));
    }

    /**
     * Normalise an ID code: digits only (drops spaces, dashes and prefixes such as "PNOEE-").
     *
     * @param string $code
     * @return string
     */
    public static function normalise_code(string $code): string {
        return preg_replace('/\D/', '', trim($code));
    }

    /**
     * Check an Estonian isikukood: 11 digits, valid century digit and checksum (both weight sets).
     *
     * @param string $code Normalised code.
     * @return bool
     */
    public static function is_valid_ee(string $code): bool {
        if (!preg_match('/^[1-8]\d{10}$/', $code)) {
            return false;
        }
        return self::ee_checksum($code) === (int)$code[10];
    }

    /**
     * Compute the isikukood check digit from the first 10 digits.
     *
     * @param string $code At least 10 digits.
     * @return int
     */
    public static function ee_checksum(string $code): int {
        foreach ([self::WEIGHTS1, self::WEIGHTS2] as $weights) {
            $sum = 0;
            for ($i = 0; $i < 10; $i++) {
                $sum += (int)$code[$i] * $weights[$i];
            }
            $remainder = $sum % 11;
            if ($remainder !== 10) {
                return $remainder;
            }
        }
        return 0;
    }

    /**
     * Birth date encoded in a valid isikukood, or null when the date part is not a real date.
     *
     * @param string $code Normalised, valid code.
     * @return string|null YYYY-MM-DD
     */
    public static function ee_birthdate(string $code): ?string {
        $century = [1 => 1800, 2 => 1800, 3 => 1900, 4 => 1900, 5 => 2000, 6 => 2000, 7 => 2100, 8 => 2100];
        $year = $century[(int)$code[0]] + (int)substr($code, 1, 2);
        $month = (int)substr($code, 3, 2);
        $day = (int)substr($code, 5, 2);
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Mask a code for display to its owner: first digit and last four visible ("3xxxxxx1234").
     *
     * @param string $code
     * @return string
     */
    public static function mask(string $code): string {
        $length = strlen($code);
        if ($length <= 5) {
            return str_repeat('x', $length);
        }
        return $code[0] . str_repeat('x', $length - 5) . substr($code, -4);
    }
}
