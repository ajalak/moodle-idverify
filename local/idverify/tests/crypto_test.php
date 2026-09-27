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

use local_idverify\local\crypto;

/**
 * Tests for the HMAC key check, HMAC and encryption round trip.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(crypto::class)]
final class crypto_test extends \advanced_testcase {
    /**
     * Missing and short keys are refused.
     */
    public function test_key_validation(): void {
        global $CFG;
        $this->resetAfterTest();

        unset($CFG->local_idverify_hmackey);
        $this->assertFalse(crypto::has_valid_key());

        $CFG->local_idverify_hmackey = str_repeat('k', 31);
        $this->assertFalse(crypto::has_valid_key());
        try {
            crypto::hmac('EE', '30303039914');
            $this->fail('Expected an exception for a short key');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:hmackey', $e->errorcode);
        }

        $CFG->local_idverify_hmackey = str_repeat('k', 32);
        $this->assertTrue(crypto::has_valid_key());
    }

    /**
     * HMAC is deterministic, depends on country and key, and is 64 hex characters.
     */
    public function test_hmac(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->local_idverify_hmackey = str_repeat('a', 40);

        $hmac = crypto::hmac('EE', '30303039914');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hmac);
        $this->assertSame($hmac, crypto::hmac('EE', '30303039914'));
        $this->assertSame(hash_hmac('sha256', 'EE30303039914', str_repeat('a', 40)), $hmac);
        $this->assertNotSame($hmac, crypto::hmac('LV', '30303039914'));

        $CFG->local_idverify_hmackey = str_repeat('b', 40);
        $this->assertNotSame($hmac, crypto::hmac('EE', '30303039914'));
    }

    /**
     * Encryption round trip; ciphertext does not contain the code.
     */
    public function test_encryption_round_trip(): void {
        $this->resetAfterTest();
        $encrypted = crypto::encrypt('30303039914');
        $this->assertStringStartsWith('sodium:', $encrypted);
        $this->assertStringNotContainsString('30303039914', $encrypted);
        $this->assertNotSame($encrypted, crypto::encrypt('30303039914'), 'A random nonce makes each ciphertext unique');
        $this->assertSame('30303039914', crypto::decrypt($encrypted));
    }
}
