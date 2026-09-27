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

use local_idverify\local\conflict_exception;
use local_idverify\local\identity_manager;
use local_idverify\local\verified_person;

/**
 * Tests for storing, conflict handling and revoking identities.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(identity_manager::class)]
final class identity_manager_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->local_idverify_hmackey = str_repeat('x', 48);
    }

    /**
     * A Smart-ID person.
     *
     * @param string $code
     * @param string $method
     * @return verified_person
     */
    private function person(string $code = '38112086027', string $method = verified_person::METHOD_SMARTID): verified_person {
        return new verified_person(' ee ', $code, 'Margus', 'Pala', null, $method, 'mock');
    }

    /**
     * Verification stores an encrypted code, HMAC, derived birth date, flag and legal name.
     */
    public function test_verify_stores_identity(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Maggy', 'lastname' => 'P']);
        $sink = $this->redirectEvents();

        $record = identity_manager::verify($user->id, $this->person());

        $stored = $DB->get_record('local_idverify_identity', ['userid' => $user->id]);
        $this->assertSame('EE', $stored->country);
        $this->assertSame('1981-12-08', $stored->birthdate);
        $this->assertStringNotContainsString('38112086027', $stored->idcode_enc);
        $this->assertSame('38112086027', identity_manager::decrypt_idcode($stored));
        $this->assertSame(64, strlen($stored->idcode_hmac));
        $this->assertEquals($record->id, $stored->id);
        $this->assertNull($stored->verifiedby);

        $this->assertTrue(identity_manager::is_verified($user->id));
        $this->assertSame('1', $this->profile_flag($user->id));

        $updated = \core_user::get_user($user->id);
        $this->assertSame('Margus', $updated->firstname);
        $this->assertSame('Pala', $updated->lastname);

        $events = array_filter($sink->get_events(), fn($e) => $e instanceof event\identity_verified);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertStringNotContainsString('38112086027', json_encode($event->get_data()));
    }

    /**
     * With "Use legal name" off, the account name is kept.
     */
    public function test_verify_keeps_name_when_disabled(): void {
        set_config('overwritenames', '0', 'local_idverify');
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Maggy', 'lastname' => 'P']);
        identity_manager::verify($user->id, $this->person());
        $this->assertSame('Maggy', \core_user::get_user($user->id)->firstname);
    }

    /**
     * The current user's session flag is updated too (availability_profile reads it from $USER->profile).
     */
    public function test_verify_updates_session_flag(): void {
        global $USER;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $USER->profile = ['idverified' => '0'];
        identity_manager::verify($user->id, $this->person());
        $this->assertSame('1', $USER->profile['idverified']);
    }

    /**
     * The same identity on a second account is refused, logged, and nothing is stored.
     */
    public function test_conflict(): void {
        global $DB;
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        identity_manager::verify($first->id, $this->person());

        $sink = $this->redirectEvents();
        try {
            identity_manager::verify($second->id, $this->person());
            $this->fail('Expected a conflict');
        } catch (conflict_exception $e) {
            $this->assertSame('error:conflict', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('local_idverify_identity', ['userid' => $second->id]));
        $this->assertSame('0', $this->profile_flag($second->id) ?? '0');

        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\verification_conflict));
        $this->assertCount(1, $events);
        $this->assertEquals($second->id, $events[0]->relateduserid);
        $this->assertEquals($first->id, $events[0]->other['holderid']);
    }

    /**
     * Re-verifying with the same identity refreshes it; a different identity on the same account is refused.
     */
    public function test_reverify(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        identity_manager::verify($user->id, $this->person());
        identity_manager::verify($user->id, $this->person('38112086027', verified_person::METHOD_IDCARD));
        $this->assertSame('idcard', $DB->get_field('local_idverify_identity', 'method', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('local_idverify_identity'));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:alreadyverified', 'local_idverify'));
        identity_manager::verify($user->id, $this->person('30303039914'));
    }

    /**
     * Invalid isikukood and disallowed countries are rejected.
     */
    public function test_invalid_input(): void {
        $user = $this->getDataGenerator()->create_user();
        try {
            identity_manager::verify($user->id, $this->person('39111123456'));
            $this->fail('Expected invalid code');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:invalididcode', $e->errorcode);
        }
        try {
            identity_manager::verify(
                $user->id,
                new verified_person('LV', '010101-10006', 'A', 'B', null, verified_person::METHOD_SMARTID, 'mock')
            );
            $this->fail('Expected country not allowed');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:countrynotallowed', $e->errorcode);
        }
        $this->assertFalse(identity_manager::is_verified($user->id));
    }

    /**
     * Verification is refused without a usable HMAC key.
     */
    public function test_requires_key(): void {
        global $CFG;
        $CFG->local_idverify_hmackey = 'short';
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        identity_manager::verify($user->id, $this->person());
    }

    /**
     * Revoke removes the identity, clears the flag, logs, and frees the identity for another account.
     */
    public function test_revoke(): void {
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        identity_manager::verify($first->id, $this->person());

        $sink = $this->redirectEvents();
        $this->assertTrue(identity_manager::revoke($first->id));
        $this->assertFalse(identity_manager::revoke($first->id));
        $this->assertFalse(identity_manager::is_verified($first->id));
        $this->assertSame('0', $this->profile_flag($first->id));
        $this->assertCount(1, array_filter($sink->get_events(), fn($e) => $e instanceof event\identity_revoked));

        identity_manager::verify($second->id, $this->person());
        $this->assertTrue(identity_manager::is_verified($second->id));
    }

    /**
     * The profile field exists after install and is locked and hidden.
     */
    public function test_profile_field(): void {
        global $DB;
        $field = $DB->get_record('user_info_field', ['shortname' => 'idverified'], '*', MUST_EXIST);
        $this->assertSame('checkbox', $field->datatype);
        $this->assertEquals(1, $field->locked);
        $this->assertEquals(0, $field->visible);
        $this->assertEquals($field->id, identity_manager::ensure_profile_field());
    }

    /**
     * The stored idverified value of a user.
     *
     * @param int $userid
     * @return string|null
     */
    private function profile_flag(int $userid): ?string {
        global $DB;
        $sql = "SELECT d.data
                  FROM {user_info_data} d
                  JOIN {user_info_field} f ON f.id = d.fieldid
                 WHERE f.shortname = 'idverified' AND d.userid = ?";
        $value = $DB->get_field_sql($sql, [$userid]);
        return $value === false ? null : $value;
    }
}
