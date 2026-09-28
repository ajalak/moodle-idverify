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

use core_user\output\myprofile\tree;
use local_idverify\local\identity_manager;
use local_idverify\local\verified_person;
use local_idverify\output\my_identity;
use local_idverify\output\profile_status;

/**
 * Tests for the profile page line and the configurable introduction on My identity.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(profile_status::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(my_identity::class)]
final class profile_status_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->local_idverify_hmackey = str_repeat('x', 48);
        $CFG->debugdeveloper = true;
        set_config('provider', 'mock', 'local_idverify');
    }

    /**
     * Verify a user.
     *
     * @param \stdClass $user
     * @param string $method
     */
    private function verify(\stdClass $user, string $method = verified_person::METHOD_SMARTID): void {
        $manual = $method === verified_person::METHOD_MANUAL;
        identity_manager::verify((int)$user->id, new verified_person(
            'EE',
            '40404040009',
            'Ok',
            'Test',
            null,
            $method,
            $manual ? 'manual' : 'mock'
        ), $manual ? 2 : null, $manual ? 'Passport' : null);
    }

    /**
     * Unverified owner: a badge and "Verify now" while verification works; nothing while it does not.
     */
    public function test_unverified_owner(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $content = profile_status::content($user, true);
        $this->assertStringContainsString(get_string('profile:notverified', 'local_idverify'), $content);
        $this->assertStringContainsString('/local/idverify/index.php', $content);
        $this->assertStringContainsString(get_string('verifynow', 'local_idverify'), $content);

        set_config('provider', '', 'local_idverify');
        $this->assertNull(profile_status::content($user, true));
    }

    /**
     * Verified owner: date and method, a link to My identity, never the code or legal name.
     */
    public function test_verified_owner(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->verify($user);
        $this->setUser($user);
        $content = profile_status::content($user, true);
        $this->assertStringContainsString(get_string('method:smartid', 'local_idverify'), $content);
        $this->assertStringContainsString('/local/idverify/index.php', $content);
        $this->assertStringNotContainsString('40404040009', $content);
        $this->assertStringNotContainsString(get_string('verifynow', 'local_idverify'), $content);

        // Still shown after verification stops working: it describes what happened.
        set_config('provider', '', 'local_idverify');
        $this->assertNotNull(profile_status::content($user, true));
    }

    /**
     * A manual verification says so.
     */
    public function test_manual(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->verify($user, verified_person::METHOD_MANUAL);
        $this->setUser($user);
        $date = userdate(identity_manager::get((int)$user->id)->timeverified, get_string('strftimedate', 'langconfig'));
        $this->assertStringContainsString(
            s(get_string('profile:verifiedmanual', 'local_idverify', $date)),
            profile_status::content($user, true)
        );
    }

    /**
     * Other users see nothing; identity managers see the status and a link to the admin view.
     */
    public function test_other_viewers(): void {
        $owner = $this->getDataGenerator()->create_user();
        $this->verify($owner);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertNull(profile_status::content($owner, false));

        $this->setAdminUser();
        $content = profile_status::content($owner, false);
        $this->assertStringContainsString('/local/idverify/admin/view.php', $content);
        $this->assertStringNotContainsString('40404040009', $content);
    }

    /**
     * The line goes into "User details" (the contact category).
     */
    public function test_tree(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $tree = new tree();
        profile_status::add_to_tree($tree, $user, true);
        $node = $tree->nodes['local_idverify'];
        $this->assertSame('contact', $node->parentcat);
        $this->assertSame(get_string('profile:title', 'local_idverify'), $node->title);
    }

    /**
     * The introduction: default string, or the admin's text run through the filters.
     */
    public function test_intro(): void {
        $this->assertStringContainsString(s(get_string('intro', 'local_idverify')), my_identity::intro_html());
        set_config('introtext', '<p>Oma tekst <strong>siin</strong></p>', 'local_idverify');
        $this->assertStringContainsString('<strong>siin</strong>', my_identity::intro_html());
        set_config('introtext', '<p> </p>', 'local_idverify');
        $this->assertStringContainsString(s(get_string('intro', 'local_idverify')), my_identity::intro_html());
    }
}
