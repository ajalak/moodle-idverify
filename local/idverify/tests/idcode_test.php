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

use local_idverify\local\idcode;

/**
 * Tests for ID code normalisation, isikukood validation and masking.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(idcode::class)]
final class idcode_test extends \basic_testcase {
    /**
     * Valid codes: public test identities, covering both weight sets and the "10 → 0" rule.
     *
     * @return array
     */
    public static function valid_provider(): array {
        return [
            'first weight set (Smart-ID demo)' => ['30303039914', '1903-03-03'],
            'first weight set (Mobile-ID demo)' => ['60001017869', '2000-01-01'],
            'second weight set' => ['38112086027', '1981-12-08'],
            'both sets give 10, check digit 0' => ['51307149560', '2013-07-14'],
            'remainder 0' => ['60001019950', '2000-01-01'],
        ];
    }

    /**
     * Invalid codes.
     *
     * @return array
     */
    public static function invalid_provider(): array {
        return [
            'wrong check digit' => ['30303039915'],
            'eID Easy doc example (bad checksum)' => ['39111123456'],
            'too short' => ['3030303991'],
            'too long' => ['303030399140'],
            'century digit 0' => ['00303039914'],
            'century digit 9' => ['90303039914'],
            'letters' => ['3030303991A'],
            'empty' => [''],
        ];
    }

    /**
     * Valid codes pass and give their birth date.
     *
     * @param string $code
     * @param string $birthdate
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('valid_provider')]
    public function test_valid(string $code, string $birthdate): void {
        $this->assertTrue(idcode::is_valid_ee($code));
        $this->assertSame($birthdate, idcode::ee_birthdate($code));
    }

    /**
     * Invalid codes are rejected.
     *
     * @param string $code
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_provider')]
    public function test_invalid(string $code): void {
        $this->assertFalse(idcode::is_valid_ee($code));
    }

    /**
     * Only the second weight set gives the right digit for 38112086027.
     */
    public function test_second_weight_set_is_used(): void {
        $this->assertSame(7, idcode::ee_checksum('38112086027'));
    }

    /**
     * Normalisation: trimmed, upper-case country; digits only.
     */
    public function test_normalise(): void {
        $this->assertSame('EE', idcode::normalise_country(' ee '));
        $this->assertSame('30303039914', idcode::normalise_code(' 303 0303-9914 '));
        $this->assertSame('30303039914', idcode::normalise_code('PNOEE-30303039914'));
    }

    /**
     * A code whose date part is not a real date has no birth date.
     */
    public function test_birthdate_invalid_date(): void {
        $this->assertNull(idcode::ee_birthdate('30302300000'));
    }

    /**
     * Masking keeps the first digit and the last four.
     */
    public function test_mask(): void {
        $this->assertSame('3xxxxxx9914', idcode::mask('30303039914'));
        $this->assertSame('xxxx', idcode::mask('1234'));
    }
}
