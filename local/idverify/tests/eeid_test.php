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

use Firebase\JWT\JWT;
use local_idverify\local\flow;
use local_idverify\local\identity_manager;
use local_idverify\local\verified_person;
use local_idverify\provider\eeid;
use local_idverify\provider\provider_exception;
use local_idverify\provider\registry;

/**
 * Tests for the eeID provider. HTTP is mocked with \curl::mock_response() (a stack: responses are queued in reverse
 * order) and ID tokens are signed here with a test-only key (tests/fixtures/eeid_test_key.pem), published as the
 * mocked JWKS, as eeID does with its own key.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(eeid::class)]
final class eeid_test extends \advanced_testcase {
    /** Issuer as published by the eeID test environment's discovery document. */
    private const ISSUER = 'https://test-auth.eeid.ee/hydra-public/';
    /** Test client id. */
    private const CLIENT = 'oidc-test-client-11';

    /** @var \OpenSSLAsymmetricKey Test signing key. */
    private $key;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/filelib.php');
        $CFG->local_idverify_hmackey = str_repeat('x', 48);
        set_config('provider', 'eeid', 'local_idverify');
        set_config('eeid_env', 'test', 'local_idverify');
        set_config('eeid_clientid', self::CLIENT, 'local_idverify');
        set_config('eeid_secret', 'testsecret', 'local_idverify');
        \cache::make('local_idverify', 'eeid')->purge();
        $this->key = openssl_pkey_get_private(file_get_contents(__DIR__ . '/fixtures/eeid_test_key.pem'));
        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * The JWK set publishing the test key.
     *
     * @param string $kid
     * @return string JSON
     */
    private function jwks(string $kid = 'k1'): string {
        $rsa = openssl_pkey_get_details($this->key)['rsa'];
        return json_encode(['keys' => [[
            'kty' => 'RSA', 'kid' => $kid, 'use' => 'sig', 'alg' => 'RS256',
            'n' => eeid::base64url($rsa['n']), 'e' => eeid::base64url($rsa['e']),
        ]]]);
    }

    /**
     * A signed ID token like eeID's.
     *
     * @param string $nonce
     * @param array $overrides Claims to change (null removes one).
     * @return string
     */
    private function idtoken(string $nonce, array $overrides = []): string {
        $claims = array_merge([
            'iss' => self::ISSUER,
            'aud' => [self::CLIENT],
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 300,
            'sub' => 'EE40404040009',
            'profile_attributes' => ['date_of_birth' => '1904-04-04', 'given_name' => 'OK', 'family_name' => 'TEST'],
            'amr' => ['smartid'],
            'acr' => 'high',
            'nonce' => $nonce,
        ], $overrides);
        return JWT::encode(array_filter($claims, fn($v) => $v !== null), $this->key, 'RS256', 'k1');
    }

    /**
     * Queue the responses of one callback in request order: token, then (if not cached) discovery and key set(s).
     *
     * @param string $token Token endpoint response body.
     * @param string ...$more Discovery / JWKS bodies.
     */
    private function queue(string $token, string ...$more): void {
        foreach (array_reverse([$token, ...$more]) as $body) {
            \curl::mock_response($body);
        }
    }

    /**
     * Discovery document.
     *
     * @return string
     */
    private function discovery(): string {
        return json_encode(['issuer' => self::ISSUER,
            'jwks_uri' => 'https://test-auth.eeid.ee/hydra-public/.well-known/jwks.json']);
    }

    /**
     * A token endpoint response carrying an ID token.
     *
     * @param string $idtoken
     * @return string
     */
    private function tokenresponse(string $idtoken): string {
        return json_encode(['access_token' => 'at', 'token_type' => 'bearer', 'expires_in' => 3600, 'id_token' => $idtoken]);
    }

    /**
     * Start an attempt on the provider directly and return the nonce it sent.
     *
     * @param eeid $provider
     * @return string
     */
    private function start(eeid $provider): string {
        return $provider->start('st4te1234', flow::callback_url(), 'et')->get_param('nonce');
    }

    /**
     * Run a callback expecting a provider exception with a reason.
     *
     * @param string $reason
     * @param array $params Callback parameters.
     */
    private function expect_reason(string $reason, array $params = ['code' => 'c0de']): void {
        try {
            (new eeid())->handle_callback($params, flow::callback_url());
            $this->fail('Expected ' . $reason);
        } catch (provider_exception $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertStringNotContainsString('40404040009', (string)$e->debuginfo);
        }
    }

    /**
     * Availability needs both client id and secret.
     */
    public function test_is_available(): void {
        $this->assertInstanceOf(eeid::class, registry::get_active());
        set_config('eeid_secret', '', 'local_idverify');
        $this->assertNull(registry::get_active());
    }

    /**
     * The authorization request carries the documented parameters, a nonce and a PKCE S256 challenge.
     */
    public function test_start_url(): void {
        global $SESSION;
        $url = (new eeid())->start('st4te1234', flow::callback_url(), 'et');
        $this->assertSame('https://test-auth.eeid.ee/hydra-public/oauth2/auth', $url->out_omit_querystring());
        $this->assertSame(self::CLIENT, $url->get_param('client_id'));
        $this->assertSame('code', $url->get_param('response_type'));
        $this->assertSame('openid', $url->get_param('scope'));
        $this->assertSame('st4te1234', $url->get_param('state'));
        $this->assertSame(flow::callback_url()->out(false), $url->get_param('redirect_uri'));
        $this->assertSame('et', $url->get_param('ui_locales'));
        $this->assertSame('EE', $url->get_param('country'));
        $this->assertSame('S256', $url->get_param('code_challenge_method'));
        $pending = $SESSION->local_idverify_eeid;
        $this->assertSame($pending['nonce'], $url->get_param('nonce'));
        $this->assertSame(eeid::base64url(hash('sha256', $pending['verifier'], true)), $url->get_param('code_challenge'));
        $this->assertNull($url->get_param('client_secret'));

        set_config('eeid_env', 'production', 'local_idverify');
        $url = (new eeid())->start('s1234567', flow::callback_url(), 'ru');
        $this->assertSame('https://auth.eeid.ee/hydra-public/oauth2/auth', $url->out_omit_querystring());
        $this->assertSame('en', $url->get_param('ui_locales'));
    }

    /**
     * Full flow: state, token with PKCE verifier, verified ID token, stored identity, acr logged.
     */
    public function test_full_flow(): void {
        global $USER, $SESSION;
        $url = flow::begin(registry::get_active());
        $nonce = $url->get_param('nonce');
        $this->queue($this->tokenresponse($this->idtoken($nonce)), $this->discovery(), $this->jwks());
        $sink = $this->redirectEvents();

        $identity = flow::complete($url->get_param('state'), ['code' => 'c0de']);
        $this->assertSame('eeid', $identity->provider);
        $this->assertSame('smartid', $identity->method);
        $this->assertSame('OK', $identity->firstname, 'names are kept as eeID sends them');
        $this->assertSame('40404040009', identity_manager::decrypt_idcode($identity));
        $this->assertTrue(identity_manager::is_verified((int)$USER->id));
        $this->assertObjectNotHasProperty('local_idverify_eeid', $SESSION, 'the attempt is single use');

        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\identity_verified));
        $this->assertSame('high', $events[0]->other['acr']);
    }

    /**
     * Accepted methods.
     *
     * @return array
     */
    public static function method_provider(): array {
        return [
            ['idcard', verified_person::METHOD_IDCARD],
            ['mID', verified_person::METHOD_MOBILEID],
            ['smartid', verified_person::METHOD_SMARTID],
        ];
    }

    /**
     * The amr value maps to our method.
     *
     * @param string $amr
     * @param string $method
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('method_provider')]
    public function test_methods(string $amr, string $method): void {
        $provider = new eeid();
        $nonce = $this->start($provider);
        $this->queue($this->tokenresponse($this->idtoken($nonce, ['amr' => [$amr]])), $this->discovery(), $this->jwks());
        $person = $provider->handle_callback(['code' => 'c0de'], flow::callback_url());
        $this->assertSame($method, $person->method);
        $this->assertSame('EE', $person->country);
        $this->assertSame('40404040009', $person->idcode);
        $this->assertSame('eeid', $person->provider);
        $this->assertSame('high', $person->assurance);
    }

    /**
     * Passkeys and cross-border logins are refused.
     */
    public function test_methods_refused(): void {
        foreach (['webauthn', 'eIDAS'] as $amr) {
            $nonce = $this->start(new eeid());
            $this->queue($this->tokenresponse($this->idtoken($nonce, ['amr' => [$amr]])), $this->discovery(), $this->jwks());
            $this->expect_reason('method_not_allowed');
        }
    }

    /**
     * ID token checks: audience, issuer, nonce, expiry, signature.
     */
    public function test_idtoken_checks(): void {
        $cases = [
            'idtoken_audience' => ['aud' => ['someone-else']],
            'idtoken_issuer' => ['iss' => 'https://evil.example/'],
            'idtoken_nonce' => ['nonce' => 'not-the-nonce'],
            'idtoken_expired' => ['exp' => time() - 3600],
        ];
        foreach ($cases as $reason => $overrides) {
            \cache::make('local_idverify', 'eeid')->purge();
            $nonce = $this->start(new eeid());
            $this->queue($this->tokenresponse($this->idtoken($nonce, $overrides)), $this->discovery(), $this->jwks());
            $this->expect_reason($reason);
        }

        // A changed payload no longer matches the signature.
        \cache::make('local_idverify', 'eeid')->purge();
        $nonce = $this->start(new eeid());
        [$head, , $sig] = explode('.', $this->idtoken($nonce));
        $forged = eeid::base64url(json_encode(['iss' => self::ISSUER, 'aud' => [self::CLIENT], 'exp' => time() + 300,
            'sub' => 'EE38112086027', 'nonce' => $nonce, 'amr' => ['smartid']]));
        $this->queue($this->tokenresponse("$head.$forged.$sig"), $this->discovery(), $this->jwks());
        $this->expect_reason('idtoken_signature');
    }

    /**
     * An unknown key id makes the provider fetch the key set again once (key rotation).
     */
    public function test_key_rotation(): void {
        $provider = new eeid();
        $nonce = $this->start($provider);
        $this->queue($this->tokenresponse($this->idtoken($nonce)), $this->discovery(), $this->jwks('old'), $this->jwks('k1'));
        $this->assertSame('40404040009', $provider->handle_callback(['code' => 'c0de'], flow::callback_url())->idcode);
    }

    /**
     * Discovery and keys are cached: a second verification only calls the token endpoint.
     */
    public function test_cache(): void {
        $provider = new eeid();
        $nonce = $this->start($provider);
        $this->queue($this->tokenresponse($this->idtoken($nonce)), $this->discovery(), $this->jwks());
        $provider->handle_callback(['code' => 'c0de'], flow::callback_url());

        $nonce = $this->start($provider);
        $this->queue($this->tokenresponse($this->idtoken($nonce, ['amr' => ['idcard']])));
        $this->assertSame('idcard', $provider->handle_callback(['code' => 'c0de'], flow::callback_url())->method);
    }

    /**
     * Cancelling, eeID errors in the redirect and at the token endpoint, and a callback without an attempt.
     */
    public function test_errors(): void {
        $this->start(new eeid());
        $this->expect_reason('cancelled', ['error' => 'access_denied']);
        $this->start(new eeid());
        $this->expect_reason('authorize_invalid_scope', ['error' => 'invalid_scope']);

        $this->start(new eeid());
        $this->queue(json_encode(['error' => 'invalid_client', 'error_description' => 'Client authentication failed']));
        $this->expect_reason('token_invalid_client');

        $this->start(new eeid());
        $this->queue(json_encode(['access_token' => 'at']));
        $this->expect_reason('token_missing');

        $this->expect_reason('no_pending_attempt');
    }

    /**
     * Smart-ID sends no date of birth: it is taken from the Estonian code. An invalid code is refused.
     */
    public function test_birthdate_and_invalid_code(): void {
        global $USER;
        $url = flow::begin(registry::get_active());
        $this->queue(
            $this->tokenresponse($this->idtoken($url->get_param('nonce'), ['profile_attributes' =>
                ['given_name' => 'OK', 'family_name' => 'TEST']])),
            $this->discovery(),
            $this->jwks()
        );
        $this->assertSame('1904-04-04', flow::complete($url->get_param('state'), ['code' => 'c0de'])->birthdate);

        identity_manager::revoke((int)$USER->id);
        $url = flow::begin(registry::get_active());
        $this->queue($this->tokenresponse($this->idtoken($url->get_param('nonce'), ['sub' => 'EE39111123456'])));
        try {
            flow::complete($url->get_param('state'), ['code' => 'c0de']);
            $this->fail('Expected invalid code');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:invalididcode', $e->errorcode);
        }
    }
}
