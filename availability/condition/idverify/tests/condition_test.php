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

use core_availability\info_module;
use local_idverify\local\identity_manager;
use local_idverify\local\verified_person;

/**
 * Tests for the "Identity verified" restriction, including failing open.
 *
 * @package    availability_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(condition::class)]
final class condition_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Restricted activity. */
    private \stdClass $page;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->enableavailability = true;
        $CFG->local_idverify_hmackey = str_repeat('x', 48);
        $CFG->debugdeveloper = true;
        set_config('provider', 'mock', 'local_idverify');
        condition::wipe_static_cache();

        $this->course = $this->getDataGenerator()->create_course();
        $availability = json_encode(\core_availability\tree::get_root_json([condition::get_json()]));
        $this->page = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $this->course->id, 'availability' => $availability]
        );
    }

    /**
     * A learner enrolled in the course.
     *
     * @return \stdClass
     */
    private function learner(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        return $user;
    }

    /**
     * Whether the restricted activity is available to a user.
     *
     * @param \stdClass $user
     * @return bool
     */
    private function visible_to(\stdClass $user): bool {
        condition::wipe_static_cache();
        get_fast_modinfo(0, 0, true);
        return get_fast_modinfo($this->course, $user->id)->get_cm($this->page->cmid)->uservisible;
    }

    /**
     * Enforced: only verified users get through.
     */
    public function test_enforced(): void {
        $unverified = $this->learner();
        $verified = $this->learner();
        identity_manager::verify((int)$verified->id, new verified_person(
            'EE',
            '40404040009',
            'Ok',
            'Test',
            null,
            verified_person::METHOD_SMARTID,
            'mock'
        ));

        $this->assertTrue(condition::is_enforced());
        $this->assertFalse($this->visible_to($unverified));
        $this->assertTrue($this->visible_to($verified));
    }

    /**
     * The NOT form inverts the result while enforced.
     */
    public function test_not(): void {
        $user = $this->learner();
        $info = new info_module(get_fast_modinfo($this->course)->get_cm($this->page->cmid));
        $condition = new condition(condition::get_json());
        $this->assertFalse($condition->is_available(false, $info, false, $user->id));
        $this->assertTrue($condition->is_available(true, $info, false, $user->id));
    }

    /**
     * Fails open when verification does not work: provider disabled, key missing.
     */
    public function test_fails_open(): void {
        global $CFG;
        $user = $this->learner();

        set_config('provider', '', 'local_idverify');
        condition::wipe_static_cache();
        $this->assertFalse(condition::is_enforced());
        $this->assertTrue($this->visible_to($user));

        set_config('provider', 'mock', 'local_idverify');
        $CFG->local_idverify_hmackey = 'short';
        $this->assertTrue($this->visible_to($user));

        $CFG->local_idverify_hmackey = str_repeat('x', 48);
        $this->assertFalse($this->visible_to($user));
    }

    /**
     * Disabling the availability plugin makes Moodle ignore the condition.
     */
    public function test_plugin_disabled(): void {
        $user = $this->learner();
        $this->assertFalse($this->visible_to($user));
        \core\plugininfo\availability::enable_plugin('idverify', 0);
        $this->assertTrue($this->visible_to($user));
    }

    /**
     * The description links to the verification page and flags a condition that is not enforced.
     */
    public function test_description(): void {
        $info = new info_module(get_fast_modinfo($this->course)->get_cm($this->page->cmid));
        $condition = new condition(condition::get_json());
        $description = $condition->get_description(false, false, $info);
        $this->assertStringContainsString('/local/idverify/index.php', $description);
        $this->assertStringContainsString(get_string('verifylink', 'availability_idverify'), $description);
        $returnurl = rawurlencode('/mod/page/view.php?id=' . $this->page->cmid);
        $this->assertStringContainsString('returnurl=' . $returnurl, $description);

        set_config('provider', '', 'local_idverify');
        condition::wipe_static_cache();
        $this->assertStringContainsString(
            get_string('notenforced', 'availability_idverify'),
            $condition->get_description(true, false, $info)
        );
        $this->assertEquals(condition::get_json(), $condition->save());
    }
}
