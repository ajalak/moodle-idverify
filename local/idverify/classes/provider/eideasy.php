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

use local_idverify\local\identity_manager;
use local_idverify\local\verified_person;

/**
 * eID Easy OAuth 2.0 identification (Smart-ID, ID card, Mobile-ID).
 *
 * Flow and fields per https://docs.eideasy.com/authentication/eideasy-authentication-page-flow.html and the
 * API reference (see docs/STEP0-research.md §2): authorize → access_token → api/v2/user_data.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class eideasy implements provider_interface {
    /** Production host. */
    public const HOST_PRODUCTION = 'https://id.eideasy.com';
    /** Test host (test identities only; real eIDs do not work there). */
    public const HOST_TEST = 'https://test.eideasy.com';

    /**
     * Accepted current_login_method values. Anything else (Google, Facebook, other countries' methods an eID Easy
     * client may have enabled) is refused: only these prove a legal identity with a personal code.
     */
    public const METHODS = [
        'smartid' => verified_person::METHOD_SMARTID,
        'ee-id-login' => verified_person::METHOD_IDCARD,
        'ee-mid-login' => verified_person::METHOD_MOBILEID,
        'mid-login' => verified_person::METHOD_MOBILEID,
    ];

    /** Seconds to wait for a connection. */
    protected const CONNECT_TIMEOUT = 10;
    /** Seconds a whole request may take. */
    protected const TIMEOUT = 20;

    #[\Override]
    public function get_name(): string {
        return 'eideasy';
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
        return get_config('local_idverify', 'eideasy_env') === 'production' ? self::HOST_PRODUCTION : self::HOST_TEST;
    }

    #[\Override]
    public function start(string $state, \moodle_url $callback, string $lang): \moodle_url {
        $params = [
            'client_id' => $this->client_id(),
            'redirect_uri' => $callback->out(false),
            'response_type' => 'code',
            'state' => $state,
            'lang' => substr($lang, 0, 2) === 'et' ? 'et' : 'en',
        ];
        $countries = identity_manager::allowed_countries();
        if ($countries) {
            $params['country'] = $countries[0];
            if (count($countries) === 1) {
                $params['allow_country_change'] = 'false';
            }
        }
        return new \moodle_url($this->host() . '/oauth/authorize', $params);
    }

    #[\Override]
    public function handle_callback(array $params, \moodle_url $callback): verified_person {
        if (!empty($params['error'])) {
            $error = clean_param($params['error'], PARAM_ALPHANUMEXT);
            if (in_array($error, ['access_denied', 'user_cancelled', 'cancelled'], true)) {
                throw new provider_exception('cancelled', 'error:cancelled');
            }
            throw new provider_exception('authorize_' . $error, 'error:provider', 'Authorize error: ' . $error);
        }
        $code = (string)($params['code'] ?? '');
        if ($code === '') {
            throw new provider_exception('no_code');
        }

        $token = $this->request_token($code, $callback);
        $data = $this->request_user_data($token);
        return $this->to_person($data);
    }

    /**
     * Exchange the authorization code for an access token.
     *
     * @param string $code
     * @param \moodle_url $callback
     * @return string
     * @throws provider_exception
     */
    protected function request_token(string $code, \moodle_url $callback): string {
        $curl = $this->new_curl();
        $curl->setHeader(['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
        $body = $curl->post($this->host() . '/oauth/access_token', http_build_query([
            'client_id' => $this->client_id(),
            'client_secret' => $this->secret(),
            'redirect_uri' => $callback->out(false),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ], '', '&'));
        $json = $this->decode($curl, $body, 'token');
        if (empty($json['access_token']) || !is_string($json['access_token'])) {
            throw new provider_exception('token_missing', 'error:provider', 'No access_token in token response');
        }
        return $json['access_token'];
    }

    /**
     * Fetch the authenticated person's data.
     *
     * @param string $token
     * @return array
     * @throws provider_exception
     */
    protected function request_user_data(string $token): array {
        $curl = $this->new_curl();
        $curl->setHeader(['Accept: application/json', 'Authorization: Bearer ' . $token]);
        $body = $curl->get($this->host() . '/api/v2/user_data');
        $json = $this->decode($curl, $body, 'userdata');
        if (($json['status'] ?? '') !== 'OK') {
            // Only the status value is logged; never the rest of the body, which may hold personal data.
            $status = clean_param((string)($json['status'] ?? 'none'), PARAM_ALPHANUMEXT);
            throw new provider_exception('userdata_status', 'error:provider', 'user_data status: ' . $status);
        }
        return $json;
    }

    /**
     * Map a user_data response to a verified person.
     *
     * @param array $data
     * @return verified_person
     * @throws provider_exception
     */
    public function to_person(array $data): verified_person {
        $method = self::METHODS[(string)($data['current_login_method'] ?? '')] ?? null;
        if ($method === null) {
            $given = clean_param((string)($data['current_login_method'] ?? ''), PARAM_ALPHANUMEXT);
            throw new provider_exception('method_not_allowed', 'error:methodnotallowed', 'Login method: ' . $given);
        }
        foreach (['idcode', 'firstname', 'lastname', 'country'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                throw new provider_exception('missing_' . $field, 'error:provider', 'Missing field: ' . $field);
            }
        }
        $birthdate = isset($data['birth_date']) && is_string($data['birth_date']) ? $data['birth_date'] : null;
        return new verified_person(
            $data['country'],
            $data['idcode'],
            $data['firstname'],
            $data['lastname'],
            $birthdate,
            $method,
            $this->get_name()
        );
    }

    /**
     * Check transport errors and decode a JSON response.
     *
     * @param \curl $curl
     * @param string|bool $body
     * @param string $step "token" or "userdata", for the reason code.
     * @return array
     * @throws provider_exception
     */
    protected function decode(\curl $curl, $body, string $step): array {
        if ($curl->get_errno()) {
            throw new provider_exception($step . '_network', 'error:provider', 'cURL error ' . $curl->get_errno() . ': ' .
                $curl->error);
        }
        $status = (int)($curl->get_info()['http_code'] ?? 0);
        $json = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($json)) {
            throw new provider_exception($step . '_http_' . $status, 'error:provider', 'Non-JSON response, HTTP ' . $status);
        }
        if ($status >= 400 || isset($json['error'])) {
            $error = clean_param((string)($json['error'] ?? 'http_' . $status), PARAM_ALPHANUMEXT);
            $description = clean_param((string)($json['hint'] ?? $json['error_description'] ?? ''), PARAM_TEXT);
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
     * Configured client id.
     *
     * @return string
     */
    protected function client_id(): string {
        return trim((string)get_config('local_idverify', 'eideasy_clientid'));
    }

    /**
     * Configured client secret.
     *
     * @return string
     */
    protected function secret(): string {
        return trim((string)get_config('local_idverify', 'eideasy_secret'));
    }
}
