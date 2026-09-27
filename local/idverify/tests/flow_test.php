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

use local_idverify\local\flow;
use local_idverify\local\identity_manager;
use local_idverify\local\state;
use local_idverify\provider\mock;
use local_idverify\provider\provider_exception;
use local_idverify\provider\registry;

/**
 * Full self-verification flow with the mock provider, and the OAuth state rules.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(flow::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(state::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(mock::class)]
final class flow_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->local_idverify_hmackey = str_repeat('x', 48);
        $CFG->debugdeveloper = true;
        set_config('provider', 'mock', 'local_idverify');
        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * Start → mock page → approve → callback stores the configured person.
     */
    public function test_full_flow(): void {
        global $USER;
        set_config('mock_idcode', '60001017869', 'local_idverify');

        $provider = registry::get_active();
        $this->assertInstanceOf(mock::class, $provider);
        $url = flow::begin($provider);
        $this->assertStringEndsWith('/local/idverify/mock.php', $url->get_path(false));
        $statevalue = $url->get_param('state');

        $identity = flow::complete($statevalue, ['code' => mock::issue_code(), 'error' => '']);
        $this->assertEquals($USER->id, $identity->userid);
        $this->assertSame('mock', $identity->provider);
        $this->assertSame('60001017869', identity_manager::decrypt_idcode($identity));
    }

    /**
     * A state works once only.
     */
    public function test_state_single_use(): void {
        $url = flow::begin(registry::get_active());
        $statevalue = $url->get_param('state');
        $this->assertSame('mock', state::consume($statevalue));
        $this->expectException(\moodle_exception::class);
        state::consume($statevalue);
    }

    /**
     * A wrong or expired state is refused and logged; nothing is stored.
     */
    public function test_bad_state(): void {
        global $SESSION, $USER;
        flow::begin(registry::get_active());
        $sink = $this->redirectEvents();
        try {
            flow::complete('wrongstate', ['code' => mock::issue_code()]);
            $this->fail('Expected a state error');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:state', $e->errorcode);
        }
        $failed = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\verification_failed));
        $this->assertSame('error_state', $failed[0]->other['reason']);

        $url = flow::begin(registry::get_active());
        $SESSION->local_idverify_state['time'] -= state::TTL + 1;
        try {
            flow::complete($url->get_param('state'), ['code' => mock::issue_code()]);
            $this->fail('Expected an expiry error');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:stateexpired', $e->errorcode);
        }
        $this->assertFalse(identity_manager::is_verified($USER->id));
    }

    /**
     * Cancelling at the provider and replaying a mock code both fail.
     */
    public function test_cancel_and_code_replay(): void {
        global $USER;
        $url = flow::begin(registry::get_active());
        try {
            flow::complete($url->get_param('state'), ['error' => 'user_cancelled']);
            $this->fail('Expected cancellation');
        } catch (provider_exception $e) {
            $this->assertSame('cancelled', $e->reason);
        }

        $code = mock::issue_code();
        $url = flow::begin(registry::get_active());
        flow::complete($url->get_param('state'), ['code' => $code]);
        identity_manager::revoke($USER->id);

        $url = flow::begin(registry::get_active());
        $this->expectException(provider_exception::class);
        flow::complete($url->get_param('state'), ['code' => $code]);
    }

    /**
     * The mock is unavailable without developer debugging.
     */
    public function test_mock_needs_debugdeveloper(): void {
        global $CFG;
        $CFG->debugdeveloper = false;
        $this->assertNull(registry::get_active());
        $this->assertArrayNotHasKey('mock', registry::get_choices());
    }
}
