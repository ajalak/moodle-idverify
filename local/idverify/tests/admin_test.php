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

use local_idverify\form\manual_form;
use local_idverify\local\identity_manager;
use local_idverify\local\verified_person;

/**
 * Tests for manual verification, revoke with logout, the name lock and the session flag sync.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(manual_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(identity_manager::class)]
final class admin_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->local_idverify_hmackey = str_repeat('x', 48);
        require_once($CFG->dirroot . '/user/lib.php');
    }

    /**
     * Verify a user with eID-style data.
     *
     * @param int $userid
     */
    private function verify(int $userid): void {
        identity_manager::verify($userid, new verified_person(
            'EE',
            '40404040009',
            'Ok',
            'Test',
            null,
            verified_person::METHOD_SMARTID,
            'mock'
        ));
    }

    /**
     * A manual identity stores the admin, the note, and works without a code (birth date only).
     */
    public function test_manual_verification(): void {
        $admin = get_admin();
        $user = $this->getDataGenerator()->create_user();
        $record = identity_manager::verify((int)$user->id, new verified_person(
            'LV',
            null,
            'Anna',
            'Bērziņa',
            '1990-05-17',
            verified_person::METHOD_MANUAL,
            'manual'
        ), (int)$admin->id, 'Passport checked in person');
        $this->assertEquals($admin->id, $record->verifiedby);
        $this->assertSame('Passport checked in person', $record->note);
        $this->assertNull($record->idcode_enc);
        $this->assertNull($record->idcode_hmac);
        $this->assertSame('LV', $record->country, 'Manual verification is not limited to allowed countries');
        $this->assertNull(identity_manager::decrypt_idcode($record));
    }

    /**
     * Two manual identities without a code do not collide on the unique HMAC index.
     */
    public function test_manual_without_code_no_collision(): void {
        global $DB;
        foreach (['1990-05-17', '1985-01-02'] as $birthdate) {
            $user = $this->getDataGenerator()->create_user();
            identity_manager::verify((int)$user->id, new verified_person(
                'LV',
                null,
                'A',
                'B',
                $birthdate,
                verified_person::METHOD_MANUAL,
                'manual'
            ), 2, 'Passport');
        }
        $this->assertSame(2, $DB->count_records_select('local_idverify_identity', 'idcode_hmac IS NULL'));
    }

    /**
     * The manual form requires a code or birth date, a valid EE code, a note, and an unverified user.
     */
    public function test_manual_form_validation(): void {
        $user = $this->getDataGenerator()->create_user();
        $form = new manual_form();
        $base = ['userid' => $user->id, 'country' => 'EE', 'idcode' => '', 'birthdate' => 0,
            'firstname' => 'Ok', 'lastname' => 'Test', 'note' => 'Passport'];

        $this->assertArrayHasKey('idcode', $form->validation($base, []));
        $this->assertArrayHasKey('idcode', $form->validation(['idcode' => '39111123456'] + $base, []));
        $this->assertArrayHasKey('note', $form->validation(['note' => '  '] + $base, []));
        $this->assertSame([], $form->validation(['idcode' => '40404040009'] + $base, []));
        $this->assertSame([], $form->validation(['birthdate' => time()] + $base, []));

        $this->verify((int)$user->id);
        $this->assertArrayHasKey('userid', $form->validation(['idcode' => '40404040009'] + $base, []));
    }

    /**
     * Revoke ends the user's sessions (their session copy of idverified would otherwise stay 1).
     */
    public function test_revoke_ends_sessions(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->verify((int)$user->id);
        $DB->insert_record('sessions', (object)['state' => 0, 'sid' => md5('test'), 'userid' => $user->id,
            'timecreated' => time(), 'timemodified' => time(), 'firstip' => '127.0.0.1', 'lastip' => '127.0.0.1']);

        identity_manager::revoke((int)$user->id);
        $this->assertSame(0, $DB->count_records('sessions', ['userid' => $user->id]));
    }

    /**
     * Revoke keeps the issued-certificate register.
     */
    public function test_revoke_keeps_register(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->verify((int)$user->id);
        $DB->insert_record('local_idverify_issued', (object)['userid' => $user->id, 'customcertid' => 1, 'issueid' => 1,
            'code' => 'ABC', 'timeissued' => time()]);
        $this->assertSame(1, identity_manager::count_issued((int)$user->id));
        identity_manager::revoke((int)$user->id);
        $this->assertSame(1, identity_manager::count_issued((int)$user->id));
    }

    /**
     * Verified users cannot change their name; managers can; unverified users and "Use legal name" off are free.
     */
    public function test_name_lock(): void {
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Before', 'lastname' => 'Name']);
        $this->verify((int)$user->id);
        $this->assertSame('Ok', \core_user::get_user($user->id)->firstname);

        $this->setUser($user);
        user_update_user((object)['id' => $user->id, 'firstname' => 'Nick', 'lastname' => 'Name'], false, false);
        $updated = \core_user::get_user($user->id);
        $this->assertSame('Ok', $updated->firstname);
        $this->assertSame('Test', $updated->lastname);

        $this->setAdminUser();
        user_update_user((object)['id' => $user->id, 'firstname' => 'Admin fixed'], false, false);
        $this->assertSame('Admin fixed', \core_user::get_user($user->id)->firstname);

        $this->setUser($user);
        set_config('overwritenames', '0', 'local_idverify');
        user_update_user((object)['id' => $user->id, 'firstname' => 'Free'], false, false);
        $this->assertSame('Free', \core_user::get_user($user->id)->firstname);

        $other = $this->getDataGenerator()->create_user();
        set_config('overwritenames', '1', 'local_idverify');
        $this->setUser($other);
        user_update_user((object)['id' => $other->id, 'firstname' => 'Anything'], false, false);
        $this->assertSame('Anything', \core_user::get_user($other->id)->firstname);
    }

    /**
     * My identity re-syncs the session flag after an admin changed it.
     */
    public function test_sync_session_flag(): void {
        global $USER;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $USER->profile = ['idverified' => '0'];

        $this->setAdminUser();
        $this->verify((int)$user->id);
        $this->setUser($user);
        $USER->profile = ['idverified' => '0'];
        identity_manager::sync_session_flag();
        $this->assertSame('1', $USER->profile['idverified']);
    }
}
