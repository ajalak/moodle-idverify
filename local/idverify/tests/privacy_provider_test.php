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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_idverify\local\identity_manager;
use local_idverify\local\verified_person;
use local_idverify\privacy\provider;

/**
 * Privacy provider tests: metadata, contexts, export, and the retention rule for deletion.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->local_idverify_hmackey = str_repeat('x', 48);
    }

    /**
     * A verified user, optionally with an issued certificate in the register.
     *
     * @param bool $issued
     * @param string $code Personal code (each user needs a different one).
     * @return \stdClass
     */
    private function verified_user(bool $issued = false, string $code = '40404040009'): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        identity_manager::verify((int)$user->id, new verified_person(
            'EE',
            $code,
            'Ok',
            'Test',
            null,
            verified_person::METHOD_SMARTID,
            'eideasy'
        ));
        if ($issued) {
            $DB->insert_record('local_idverify_issued', (object)['userid' => $user->id, 'customcertid' => 7,
                'issueid' => $user->id, 'code' => 'CODE' . $user->id, 'timeissued' => time()]);
        }
        return $user;
    }

    /**
     * Metadata lists both tables, eID Easy and the core_user link.
     */
    public function test_metadata(): void {
        $items = provider::get_metadata(new collection('local_idverify'))->get_collection();
        $names = array_map(fn($item) => $item->get_name(), $items);
        $this->assertContains('local_idverify_identity', $names);
        $this->assertContains('local_idverify_issued', $names);
        $this->assertContains('eideasy', $names);
        $this->assertContains('core_user', $names);
    }

    /**
     * Only the data subject's user context is reported.
     */
    public function test_contexts_and_users(): void {
        $user = $this->verified_user();
        $other = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);

        $this->assertEquals([$context->id], provider::get_contexts_for_userid((int)$user->id)->get_contextids());
        $this->assertSame([], provider::get_contexts_for_userid((int)$other->id)->get_contextids());

        $userlist = new userlist($context, 'local_idverify');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$user->id], $userlist->get_userids());

        $userlist = new userlist(\context_system::instance(), 'local_idverify');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());
    }

    /**
     * Export gives the learner their own data, with the code decrypted, and the issued certificates.
     */
    public function test_export(): void {
        $user = $this->verified_user(true);
        $context = \context_user::instance($user->id);
        $this->export_context_data_for_user((int)$user->id, $context, 'local_idverify');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $subcontext = [get_string('pluginname', 'local_idverify')];
        $data = $writer->get_data($subcontext);
        $this->assertSame('40404040009', $data->idcode);
        $this->assertSame('Ok', $data->firstname);
        $this->assertSame('1904-04-04', $data->birthdate);
        $issued = $writer->get_related_data($subcontext, 'issued');
        $this->assertCount(1, $issued->certificates);
    }

    /**
     * Without issued certificates, a deletion request deletes the identity and clears the flag.
     */
    public function test_delete_without_issued(): void {
        global $DB;
        $user = $this->verified_user();
        $context = \context_user::instance($user->id);
        provider::delete_data_for_user(new approved_contextlist($user, 'local_idverify', [$context->id]));

        $this->assertFalse(identity_manager::is_verified((int)$user->id));
        $sql = "SELECT d.data FROM {user_info_data} d JOIN {user_info_field} f ON f.id = d.fieldid
                 WHERE f.shortname = 'idverified' AND d.userid = ?";
        $this->assertSame('0', $DB->get_field_sql($sql, [$user->id]));
    }

    /**
     * With issued certificates, the identity is kept and identity_retained is logged, for every delete method.
     */
    public function test_delete_with_issued_is_retained(): void {
        $user = $this->verified_user(true);
        $context = \context_user::instance($user->id);
        $sink = $this->redirectEvents();

        provider::delete_data_for_user(new approved_contextlist($user, 'local_idverify', [$context->id]));
        provider::delete_data_for_users(new approved_userlist($context, 'local_idverify', [$user->id]));
        provider::delete_data_for_all_users_in_context($context);

        $this->assertTrue(identity_manager::is_verified((int)$user->id));
        $this->assertSame(1, identity_manager::count_issued((int)$user->id));
        $retained = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\identity_retained));
        $this->assertCount(3, $retained);
        $this->assertSame('privacyrequest', $retained[0]->other['reason']);
        $this->assertSame(1, $retained[0]->other['issued']);
    }

    /**
     * After the DPO revokes the identity, the next deletion request also removes the register.
     */
    public function test_delete_after_revoke_removes_register(): void {
        $user = $this->verified_user(true);
        identity_manager::revoke((int)$user->id);
        $this->assertSame(1, identity_manager::count_issued((int)$user->id));

        $context = \context_user::instance($user->id);
        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, identity_manager::count_issued((int)$user->id));
    }

    /**
     * Account deletion follows the same rule.
     */
    public function test_user_deletion(): void {
        $plain = $this->verified_user();
        $issued = $this->verified_user(true, '60001017869');
        $sink = $this->redirectEvents();

        delete_user($plain);
        delete_user($issued);

        $this->assertFalse(identity_manager::is_verified((int)$plain->id));
        $this->assertTrue(identity_manager::is_verified((int)$issued->id));
        $retained = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\identity_retained));
        $this->assertCount(1, $retained);
        $this->assertSame('userdeleted', $retained[0]->other['reason']);
    }
}
