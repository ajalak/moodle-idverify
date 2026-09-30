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

use core\check\result;
use local_idverify\check\service;
use local_idverify\local\flow;
use local_idverify\local\service_health;
use local_idverify\provider\mock;
use local_idverify\provider\registry;

/**
 * The service warning: failure runs, learners who never come back, the admin message and the status check.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(service_health::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(service::class)]
final class service_health_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('provider', 'eeid', 'local_idverify');
    }

    /**
     * Three service-side failures in a row raise the warning and message the managers once.
     */
    public function test_failures_raise_warning_once(): void {
        $sink = $this->redirectMessages();
        service_health::record_failure('eeid', 'token_invalid_client');
        service_health::record_failure('eeid', 'discovery_network');
        $this->assertNull(service_health::get_warning());
        $this->assertSame(0, $sink->count());

        service_health::record_failure('eeid', 'token_http_503');
        $warning = service_health::get_warning();
        $this->assertSame('failures', $warning->type);
        $this->assertSame(3, $warning->count);
        $this->assertSame('token_http_503', $warning->reason);
        $this->assertStringContainsString('token_http_503', service_health::describe($warning));

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('servicewarning', $messages[0]->eventtype);
        $this->assertEquals(get_admin()->id, $messages[0]->useridto);

        service_health::record_failure('eeid', 'token_http_503');
        $this->assertSame(1, $sink->count());
        $this->assertSame(4, service_health::get_warning()->count);
    }

    /**
     * Failures caused by the learner do not count, and do not break a run either.
     */
    public function test_user_failures_ignored(): void {
        $reasons = ['cancelled', 'method_not_allowed', 'no_pending_attempt', 'idtoken_nonce', 'token_invalid_grant',
            'error_state', 'error_conflict'];
        foreach ($reasons as $reason) {
            $this->assertFalse(service_health::is_service_fault($reason), $reason);
            service_health::record_failure('eeid', $reason);
        }
        $this->assertNull(service_health::get_warning());
        $this->assertTrue(service_health::is_service_fault('authorize_unauthorized_client'));
        $this->assertTrue(service_health::is_service_fault('idtoken_audience'));
    }

    /**
     * A success clears the warning; the next run messages again.
     */
    public function test_success_clears(): void {
        $sink = $this->redirectMessages();
        for ($i = 0; $i < service_health::FAILURE_LIMIT; $i++) {
            service_health::record_failure('eeid', 'token_network');
        }
        $this->assertNotNull(service_health::get_warning());
        service_health::record_success('eeid');
        $this->assertNull(service_health::get_warning());
        for ($i = 0; $i < service_health::FAILURE_LIMIT; $i++) {
            service_health::record_failure('eeid', 'token_network');
        }
        $this->assertSame(2, $sink->count());
    }

    /**
     * Learners who go to the provider and never return raise the warning; any return resets the count.
     */
    public function test_unreturned(): void {
        $this->redirectMessages();
        for ($i = 1; $i < service_health::UNRETURNED_LIMIT; $i++) {
            service_health::record_start('eeid');
        }
        service_health::record_failure('eeid', 'cancelled');
        for ($i = 1; $i < service_health::UNRETURNED_LIMIT; $i++) {
            service_health::record_start('eeid');
        }
        $this->assertNull(service_health::get_warning());
        service_health::record_start('eeid');
        $warning = service_health::get_warning();
        $this->assertSame('unreturned', $warning->type);
        $this->assertSame(service_health::UNRETURNED_LIMIT, $warning->count);
    }

    /**
     * Counters belong to one provider; the mock and a disabled provider never warn; reset forgets everything.
     */
    public function test_provider_scope(): void {
        $this->redirectMessages();
        for ($i = 0; $i < service_health::FAILURE_LIMIT; $i++) {
            service_health::record_failure('eeid', 'token_network');
        }
        $this->assertNotNull(service_health::get_warning());

        set_config('provider', 'mock', 'local_idverify');
        $this->assertNull(service_health::get_warning());
        set_config('provider', '', 'local_idverify');
        $this->assertNull(service_health::get_warning());

        set_config('provider', 'eeid', 'local_idverify');
        $this->assertNotNull(service_health::get_warning());
        service_health::reset();
        $this->assertNull(service_health::get_warning());
    }

    /**
     * The status check follows the warning.
     */
    public function test_check(): void {
        $this->redirectMessages();
        $check = new service();
        $this->assertSame(result::OK, $check->get_result()->get_status());
        for ($i = 0; $i < service_health::FAILURE_LIMIT; $i++) {
            service_health::record_failure('eeid', 'token_network');
        }
        $result = $check->get_result();
        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertStringContainsString('eeid.ee', $result->get_details());

        set_config('provider', '', 'local_idverify');
        $this->assertSame(result::NA, $check->get_result()->get_status());
    }

    /**
     * The flow records starts and returns.
     */
    public function test_flow_records(): void {
        global $CFG;
        $CFG->local_idverify_hmackey = str_repeat('x', 48);
        $CFG->debugdeveloper = true;
        set_config('provider', 'mock', 'local_idverify');
        set_config('mock_idcode', '60001017869', 'local_idverify');
        $this->setUser($this->getDataGenerator()->create_user());

        $url = flow::begin(registry::get_active());
        $health = json_decode(get_config('local_idverify', 'health'), true);
        $this->assertSame(1, $health['unreturned']);

        flow::complete($url->get_param('state'), ['code' => mock::issue_code(), 'error' => '']);
        $health = json_decode(get_config('local_idverify', 'health'), true);
        $this->assertSame(0, $health['unreturned']);
        $this->assertGreaterThan(0, $health['lastsuccess']);
    }
}
