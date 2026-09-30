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

namespace local_idverify\provider;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use local_idverify\local\identity_manager;
use local_idverify\local\verified_person;

/**
 * eeID (Estonian Internet Foundation) authentication: OpenID Connect authorization code flow.
 *
 * Per https://internetee.github.io/eeID-DOC/ (see docs/eeid-research.md): authorize → token (client_secret_basic,
 * with PKCE) → signed ID token (RS256). The identity comes from the ID token, which is verified against eeID's
 * published keys (JWKS), issuer, audience, validity times and the nonce. Only ID card, Mobile-ID and Smart-ID are
 * accepted; passkeys (webauthn) and cross-border eIDAS logins are refused.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class eeid implements provider_interface {
    /** Production base URL. */
    public const HOST_PRODUCTION = 'https://auth.eeid.ee/hydra-public';
    /** Test base URL (test users only; test authentications are free). */
    public const HOST_TEST = 'https://test-auth.eeid.ee/hydra-public';

    /** Accepted "amr" values of the ID token => our methods. */
    public const METHODS = [
        'idcard' => verified_person::METHOD_IDCARD,
        'mID' => verified_person::METHOD_MOBILEID,
        'smartid' => verified_person::METHOD_SMARTID,
    ];

    /** Seconds to wait for a connection. */
    protected const CONNECT_TIMEOUT = 10;
    /** Seconds a whole request may take. */
    protected const TIMEOUT = 20;
    /** Allowed clock difference with eeID, in seconds, for exp / nbf / iat. */
    public const LEEWAY = 60;
    /** Session key of the pending attempt (nonce and PKCE verifier). */
    protected const SESSION_KEY = 'local_idverify_eeid';

    #[\Override]
    public function get_name(): string {
        return 'eeid';
    }

    #[\Override]
    public function is_available(): bool {
        return $this->client_id() !== '' && $this->secret() !== '';
    }

    /**
     * Base URL of the configured environment.
     *
     * @return string
     */
    public function host(): string {
        return get_config('local_idverify', 'eeid_env') === 'production' ? self::HOST_PRODUCTION : self::HOST_TEST;
    }

    #[\Override]
    public function start(string $state, \moodle_url $callback, string $lang): \moodle_url {
        global $SESSION;

        $nonce = random_string(32);
        $verifier = random_string(64);
        $SESSION->{self::SESSION_KEY} = ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier];

        $params = [
            'client_id' => $this->client_id(),
            'redirect_uri' => $callback->out(false),
            'response_type' => 'code',
            'scope' => 'openid',
            'state' => $state,
            'nonce' => $nonce,
            'ui_locales' => substr($lang, 0, 2) === 'et' ? 'et' : 'en',
            'code_challenge_method' => 'S256',
            'code_challenge' => self::base64url(hash('sha256', $verifier, true)),
        ];
        $countries = identity_manager::allowed_countries();
        if ($countries) {
            $params['country'] = $countries[0];
        }
        return new \moodle_url($this->host() . '/oauth2/auth', $params);
    }

    #[\Override]
    public function handle_callback(array $params, \moodle_url $callback): verified_person {
        global $SESSION;

        $pending = $SESSION->{self::SESSION_KEY} ?? null;
        unset($SESSION->{self::SESSION_KEY});

        if (!empty($params['error'])) {
            $error = clean_param($params['error'], PARAM_ALPHANUMEXT);
            if ($error === 'access_denied') {
                throw new provider_exception('cancelled', 'error:cancelled');
            }
            throw new provider_exception('authorize_' . $error, 'error:provider', 'Authorize error: ' . $error);
        }
        if (!is_array($pending) || empty($pending['nonce']) || empty($pending['verifier'])) {
            throw new provider_exception('no_pending_attempt');
        }
        $code = (string)($params['code'] ?? '');
        if ($code === '') {
            throw new provider_exception('no_code');
        }

        $idtoken = $this->request_id_token($code, $callback, $pending['verifier']);
        $claims = $this->verify_id_token($idtoken, $pending['nonce']);
        return $this->to_person($claims);
    }

    /**
     * Exchange the authorization code for the ID token (within 30 seconds, per eeID).
     *
     * @param string $code
     * @param \moodle_url $callback
     * @param string $verifier PKCE code verifier.
     * @return string The ID token (JWS compact serialisation).
     * @throws provider_exception
     */
    protected function request_id_token(string $code, \moodle_url $callback, string $verifier): string {
        $curl = $this->new_curl();
        $basic = base64_encode(urlencode($this->client_id()) . ':' . urlencode($this->secret()));
        $curl->setHeader([
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $basic,
        ]);
        $body = $curl->post($this->host() . '/oauth2/token', http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $callback->out(false),
            'code_verifier' => $verifier,
        ], '', '&'));
        $json = $this->decode($curl, $body, 'token');
        if (empty($json['id_token']) || !is_string($json['id_token'])) {
            throw new provider_exception('token_missing', 'error:provider', 'No id_token in token response');
        }
        return $json['id_token'];
    }

    /**
     * Verify the ID token's signature and claims.
     *
     * @param string $idtoken
     * @param string $nonce The nonce sent in the authorization request.
     * @return array Verified claims.
     * @throws provider_exception
     */
    public function verify_id_token(string $idtoken, string $nonce): array {
        $discovery = $this->discovery();
        JWT::$leeway = self::LEEWAY;
        try {
            $claims = $this->decode_jwt($idtoken, $this->jwks($discovery['jwks_uri'], false));
        } catch (\UnexpectedValueException $e) {
            // A key we do not know yet (eeID rotated its keys): fetch the key set again once.
            if (strpos($e->getMessage(), 'kid') === false) {
                throw new provider_exception('idtoken_invalid', 'error:provider', 'ID token: ' . $e->getMessage());
            }
            try {
                $claims = $this->decode_jwt($idtoken, $this->jwks($discovery['jwks_uri'], true));
            } catch (\UnexpectedValueException $e) {
                throw new provider_exception('idtoken_key', 'error:provider', 'ID token: ' . $e->getMessage());
            }
        }

        if (($claims['iss'] ?? '') !== $discovery['issuer']) {
            throw new provider_exception('idtoken_issuer', 'error:provider', 'ID token issuer mismatch');
        }
        $audience = (array)($claims['aud'] ?? []);
        if (!in_array($this->client_id(), $audience, true)) {
            throw new provider_exception('idtoken_audience', 'error:provider', 'ID token audience mismatch');
        }
        if (!is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])) {
            throw new provider_exception('idtoken_nonce', 'error:provider', 'ID token nonce mismatch');
        }
        return $claims;
    }

    /**
     * Decode and verify a JWT with a key set, turning library errors into provider exceptions.
     *
     * @param string $jwt
     * @param array $jwks JWK set.
     * @return array Claims.
     * @throws provider_exception
     * @throws \UnexpectedValueException When the key id is unknown (caller may refresh the key set).
     */
    protected function decode_jwt(string $jwt, array $jwks): array {
        try {
            $payload = JWT::decode($jwt, JWK::parseKeySet($jwks, 'RS256'));
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new provider_exception('idtoken_expired', 'error:provider', 'ID token expired');
        } catch (\Firebase\JWT\BeforeValidException $e) {
            throw new provider_exception('idtoken_notyetvalid', 'error:provider', 'ID token not yet valid');
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            throw new provider_exception('idtoken_signature', 'error:provider', 'ID token signature invalid');
        } catch (\UnexpectedValueException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new provider_exception('idtoken_invalid', 'error:provider', 'ID token: ' . get_class($e));
        }
        return json_decode(json_encode($payload), true);
    }

    /**
     * Map verified ID token claims to a person.
     *
     * The ID token carries "sub" = country prefix + personal code (e.g. EE60001019906), the name and date of birth
     * in "profile_attributes" (Smart-ID may leave the date out; it is then taken from the Estonian code), the method
     * in "amr" and the level of assurance in "acr" (logged, not enforced).
     *
     * @param array $claims
     * @return verified_person
     * @throws provider_exception
     */
    public function to_person(array $claims): verified_person {
        $amr = array_values((array)($claims['amr'] ?? []));
        $method = null;
        foreach ($amr as $value) {
            $method = self::METHODS[(string)$value] ?? $method;
        }
        if ($method === null) {
            $given = clean_param(implode(',', array_map('strval', $amr)), PARAM_TEXT);
            throw new provider_exception('method_not_allowed', 'error:methodnotallowed', 'Login method: ' . $given);
        }

        if (!is_string($claims['sub'] ?? null) || !preg_match('/^([A-Z]{2})([0-9A-Za-z\-]+)$/', $claims['sub'], $m)) {
            throw new provider_exception('missing_sub', 'error:provider', 'Missing or malformed sub');
        }
        $profile = (array)($claims['profile_attributes'] ?? []);
        foreach (['given_name', 'family_name'] as $field) {
            if (!isset($profile[$field]) || !is_string($profile[$field]) || trim($profile[$field]) === '') {
                throw new provider_exception('missing_' . $field, 'error:provider', 'Missing field: ' . $field);
            }
        }
        $birthdate = isset($profile['date_of_birth']) && is_string($profile['date_of_birth'])
            ? $profile['date_of_birth'] : null;
        $acr = isset($claims['acr']) && is_string($claims['acr']) ? clean_param($claims['acr'], PARAM_ALPHA) : null;

        return new verified_person(
            $m[1],
            $m[2],
            $profile['given_name'],
            $profile['family_name'],
            $birthdate,
            $method,
            $this->get_name(),
            $acr ?: null
        );
    }

    /**
     * eeID's OpenID configuration (issuer and key set location), cached for a day.
     *
     * @return array With "issuer" and "jwks_uri".
     * @throws provider_exception
     */
    protected function discovery(): array {
        $cache = \cache::make('local_idverify', 'eeid');
        $key = 'discovery_' . md5($this->host());
        $discovery = $cache->get($key);
        if (is_array($discovery)) {
            return $discovery;
        }
        $curl = $this->new_curl();
        $curl->setHeader(['Accept: application/json']);
        $json = $this->decode($curl, $curl->get($this->host() . '/.well-known/openid-configuration'), 'discovery');
        if (
            empty($json['issuer']) || empty($json['jwks_uri']) || !is_string($json['issuer'])
                || !is_string($json['jwks_uri']) || strpos($json['jwks_uri'], 'https://') !== 0
        ) {
            throw new provider_exception('discovery_invalid', 'error:provider', 'Unusable OpenID configuration');
        }
        $discovery = ['issuer' => $json['issuer'], 'jwks_uri' => $json['jwks_uri']];
        $cache->set($key, $discovery);
        return $discovery;
    }

    /**
     * eeID's public signing keys, cached for a day.
     *
     * @param string $uri JWKS URI from the discovery document.
     * @param bool $refresh Fetch again even if cached.
     * @return array JWK set.
     * @throws provider_exception
     */
    protected function jwks(string $uri, bool $refresh): array {
        $cache = \cache::make('local_idverify', 'eeid');
        $key = 'jwks_' . md5($uri);
        $jwks = $refresh ? false : $cache->get($key);
        if (is_array($jwks)) {
            return $jwks;
        }
        $curl = $this->new_curl();
        $curl->setHeader(['Accept: application/json']);
        $jwks = $this->decode($curl, $curl->get($uri), 'jwks');
        if (empty($jwks['keys']) || !is_array($jwks['keys'])) {
            throw new provider_exception('jwks_invalid', 'error:provider', 'Unusable key set');
        }
        $cache->set($key, $jwks);
        return $jwks;
    }

    /**
     * Check transport errors and decode a JSON response.
     *
     * @param \curl $curl
     * @param string|bool $body
     * @param string $step "token", "discovery" or "jwks", for the reason code.
     * @return array
     * @throws provider_exception
     */
    protected function decode(\curl $curl, $body, string $step): array {
        if ($curl->get_errno()) {
            throw new provider_exception(
                $step . '_network',
                'error:provider',
                'cURL error ' . $curl->get_errno() . ': ' . $curl->error
            );
        }
        $status = (int)($curl->get_info()['http_code'] ?? 0);
        $json = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($json)) {
            throw new provider_exception($step . '_http_' . $status, 'error:provider', 'Non-JSON response, HTTP ' . $status);
        }
        if ($status >= 400 || isset($json['error'])) {
            $error = clean_param((string)($json['error'] ?? 'http_' . $status), PARAM_ALPHANUMEXT);
            $description = clean_param((string)($json['error_description'] ?? $json['error_hint'] ?? ''), PARAM_TEXT);
            throw new provider_exception(
                $step . '_' . $error,
                'error:provider',
                "HTTP $status $error " . shorten_text($description, 200)
            );
        }
        return $json;
    }

    /**
     * A configured cURL client (Moodle's \curl: proxy settings, blocked hosts, timeouts).
     *
     * @return \curl
     */
    protected function new_curl(): \curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl(['ignoresecurity' => false]);
        $curl->setopt(['CURLOPT_CONNECTTIMEOUT' => self::CONNECT_TIMEOUT, 'CURLOPT_TIMEOUT' => self::TIMEOUT]);
        return $curl;
    }

    /**
     * Base64url without padding (RFC 7636).
     *
     * @param string $data
     * @return string
     */
    public static function base64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Configured client id.
     *
     * @return string
     */
    protected function client_id(): string {
        return trim((string)get_config('local_idverify', 'eeid_clientid'));
    }

    /**
     * Configured client secret.
     *
     * @return string
     */
    protected function secret(): string {
        return trim((string)get_config('local_idverify', 'eeid_secret'));
    }
}
