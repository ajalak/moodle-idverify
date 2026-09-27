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
use local_idverify\local\verified_person;
use local_idverify\provider\eideasy;
use local_idverify\provider\provider_exception;
use local_idverify\provider\registry;

/**
 * Tests for the eID Easy provider, with HTTP responses mocked through \curl::mock_response().
 *
 * \curl::mock_response() is a stack (last pushed is returned first), so responses are queued in reverse order.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(eideasy::class)]
final class eideasy_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');
        $CFG->local_idverify_hmackey = str_repeat('x', 48);
        set_config('provider', 'eideasy', 'local_idverify');
        set_config('eideasy_clientid', 'testclient', 'local_idverify');
        set_config('eideasy_secret', 'testsecret', 'local_idverify');
        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * A user_data response body as in the eID Easy API reference.
     *
     * @param array $overrides
     * @return string
     */
    private function user_data(array $overrides = []): string {
        return json_encode($overrides + [
            'status' => 'OK',
            'idcode' => '38112086027',
            'firstname' => 'MARGUS',
            'lastname' => 'PALA',
            'current_login_method' => 'smartid',
            'birth_date' => '1981-12-08',
            'country' => 'EE',
            'current_login_info' => ['valid_from' => '2020-01-19T08:07:06+00:00'],
        ]);
    }

    /**
     * Queue a token response followed by a user_data response.
     *
     * @param string $userdata
     */
    private function queue_success(string $userdata): void {
        \curl::mock_response($userdata);
        \curl::mock_response(json_encode(['token_type' => 'Bearer', 'expires_in' => 3600, 'access_token' => 'tok']));
    }

    /**
     * Availability needs both client id and secret.
     */
    public function test_is_available(): void {
        $this->assertTrue((new eideasy())->is_available());
        set_config('eideasy_secret', '', 'local_idverify');
        $this->assertFalse((new eideasy())->is_available());
        $this->assertNull(registry::get_active());
    }

    /**
     * The authorize URL carries the documented parameters and uses the configured environment.
     */
    public function test_start_url(): void {
        $url = (new eideasy())->start('st4te', flow::callback_url(), 'et');
        $this->assertSame('https://test.eideasy.com/oauth/authorize', $url->out_omit_querystring());
        $this->assertSame('testclient', $url->get_param('client_id'));
        $this->assertSame('code', $url->get_param('response_type'));
        $this->assertSame('st4te', $url->get_param('state'));
        $this->assertSame(flow::callback_url()->out(false), $url->get_param('redirect_uri'));
        $this->assertSame('et', $url->get_param('lang'));
        $this->assertSame('EE', $url->get_param('country'));
        $this->assertSame('false', $url->get_param('allow_country_change'));
        $this->assertNull($url->get_param('client_secret'));

        set_config('eideasy_env', 'production', 'local_idverify');
        $url = (new eideasy())->start('s', flow::callback_url(), 'ru');
        $this->assertSame('https://id.eideasy.com/oauth/authorize', $url->out_omit_querystring());
        $this->assertSame('en', $url->get_param('lang'));
    }

    /**
     * Method codes map to our methods.
     *
     * @return array
     */
    public static function method_provider(): array {
        return [
            ['smartid', verified_person::METHOD_SMARTID],
            ['ee-id-login', verified_person::METHOD_IDCARD],
            ['ee-mid-login', verified_person::METHOD_MOBILEID],
            ['mid-login', verified_person::METHOD_MOBILEID],
        ];
    }

    /**
     * A successful callback gives the person from user_data.
     *
     * @param string $logincode
     * @param string $method
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('method_provider')]
    public function test_handle_callback(string $logincode, string $method): void {
        $this->queue_success($this->user_data(['current_login_method' => $logincode]));
        $person = (new eideasy())->handle_callback(['code' => 'authcode'], flow::callback_url());
        $this->assertSame('38112086027', $person->idcode);
        $this->assertSame('MARGUS', $person->firstname);
        $this->assertSame('PALA', $person->lastname);
        $this->assertSame('1981-12-08', $person->birthdate);
        $this->assertSame('EE', $person->country);
        $this->assertSame($method, $person->method);
        $this->assertSame('eideasy', $person->provider);
    }

    /**
     * Full flow through state, provider and storage.
     */
    public function test_full_flow(): void {
        global $USER;
        $url = flow::begin(registry::get_active());
        $this->queue_success($this->user_data());
        $identity = flow::complete($url->get_param('state'), ['code' => 'authcode']);
        $this->assertSame('eideasy', $identity->provider);
        $this->assertTrue(identity_manager::is_verified($USER->id));
    }

    /**
     * Non-eID login methods (e.g. Google) are refused.
     */
    public function test_method_not_allowed(): void {
        $this->queue_success($this->user_data(['current_login_method' => 'Google']));
        try {
            (new eideasy())->handle_callback(['code' => 'authcode'], flow::callback_url());
            $this->fail('Expected method_not_allowed');
        } catch (provider_exception $e) {
            $this->assertSame('method_not_allowed', $e->reason);
            $this->assertSame('error:methodnotallowed', $e->errorcode);
        }
    }

    /**
     * Provider errors become provider_exceptions with safe reason codes.
     *
     * @return array
     */
    public static function error_provider(): array {
        return [
            'token: invalid client' => [
                [json_encode(['error' => 'invalid_client', 'message' => 'Client authentication failed'])],
                'token_invalid_client',
            ],
            'token: code reused' => [
                [json_encode(['error' => 'invalid_request', 'hint' => 'Authorization code has been revoked'])],
                'token_invalid_request',
            ],
            'token: not JSON' => [['<html>Bad gateway</html>'], 'token_http_200'],
            'token: no access_token' => [[json_encode(['token_type' => 'Bearer'])], 'token_missing'],
            'user_data: status not OK' => [
                [json_encode(['access_token' => 't']), json_encode(['status' => 'ERROR', 'idcode' => '38112086027'])],
                'userdata_status',
            ],
            'user_data: missing idcode' => [
                [json_encode(['access_token' => 't']), json_encode(['status' => 'OK', 'current_login_method' => 'smartid',
                    'firstname' => 'A', 'lastname' => 'B', 'country' => 'EE'])],
                'missing_idcode',
            ],
        ];
    }

    /**
     * Errors from eID Easy.
     *
     * @param array $responses Responses in request order.
     * @param string $reason Expected reason code.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('error_provider')]
    public function test_errors(array $responses, string $reason): void {
        foreach (array_reverse($responses) as $response) {
            \curl::mock_response($response);
        }
        try {
            (new eideasy())->handle_callback(['code' => 'authcode'], flow::callback_url());
            $this->fail('Expected a provider exception');
        } catch (provider_exception $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertStringNotContainsString('38112086027', (string)$e->debuginfo);
        }
    }

    /**
     * Cancelling on the eID Easy page, and a missing code.
     */
    public function test_cancel_and_missing_code(): void {
        $provider = new eideasy();
        try {
            $provider->handle_callback(['error' => 'access_denied'], flow::callback_url());
            $this->fail('Expected cancellation');
        } catch (provider_exception $e) {
            $this->assertSame('cancelled', $e->reason);
        }
        $this->expectException(provider_exception::class);
        $provider->handle_callback(['code' => ''], flow::callback_url());
    }

    /**
     * An invalid isikukood from the provider is rejected and logged without the code.
     */
    public function test_invalid_idcode_from_provider(): void {
        global $USER;
        $url = flow::begin(registry::get_active());
        $this->queue_success($this->user_data(['idcode' => '39111123456']));
        $sink = $this->redirectEvents();
        try {
            flow::complete($url->get_param('state'), ['code' => 'authcode']);
            $this->fail('Expected invalid code');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:invalididcode', $e->errorcode);
        }
        $this->assertFalse(identity_manager::is_verified($USER->id));
        $failed = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\verification_failed));
        $this->assertSame('error_invalididcode', $failed[0]->other['reason']);
        $this->assertStringNotContainsString('39111123456', json_encode($failed[0]->get_data()));
    }
}
