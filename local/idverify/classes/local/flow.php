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

namespace local_idverify\local;

use local_idverify\event\verification_failed;
use local_idverify\provider\provider_exception;
use local_idverify\provider\provider_interface;
use local_idverify\provider\registry;

/**
 * The self-verification flow: start.php → provider → callback.php.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flow {
    /**
     * The fixed callback URL (the redirect URI registered with the provider).
     *
     * @return \moodle_url
     */
    public static function callback_url(): \moodle_url {
        return new \moodle_url('/local/idverify/callback.php');
    }

    /**
     * Remember where the learner came from, for the "Continue" button after verifying.
     *
     * Only local URLs outside this plugin are kept; anything else leaves the remembered page unchanged.
     *
     * @param string $url An explicit return URL, or the HTTP referer.
     */
    public static function remember_return(string $url): void {
        global $SESSION;
        $url = clean_param($url, PARAM_LOCALURL);
        if ($url === '') {
            return;
        }
        $url = new \moodle_url($url);
        if (str_contains($url->get_path(false), '/local/idverify/')) {
            return;
        }
        $SESSION->local_idverify_returnurl = $url->out(false);
    }

    /**
     * The page the learner came from, if known.
     *
     * @return \moodle_url|null
     */
    public static function get_return(): ?\moodle_url {
        global $SESSION;
        return empty($SESSION->local_idverify_returnurl) ? null : new \moodle_url($SESSION->local_idverify_returnurl);
    }

    /**
     * Whether self-verification works on this site: a valid HMAC key and an active provider.
     *
     * availability_idverify enforces its restriction only while this is true (fails open otherwise).
     *
     * @return bool
     */
    public static function is_available(): bool {
        return crypto::has_valid_key() && registry::get_active() !== null;
    }

    /**
     * Begin a verification for the current user.
     *
     * @param provider_interface $provider
     * @return \moodle_url Where to redirect the user.
     */
    public static function begin(provider_interface $provider): \moodle_url {
        crypto::require_valid_key();
        $state = state::create($provider->get_name());
        $url = $provider->start($state, self::callback_url(), current_language());
        service_health::record_start($provider->get_name());
        return $url;
    }

    /**
     * Finish a verification for the current user from the callback request.
     *
     * @param string $state State returned by the provider.
     * @param array $params Other callback parameters (code, error).
     * @return \stdClass The stored identity.
     * @throws conflict_exception When the identity belongs to another account (already logged).
     * @throws \moodle_exception Any other failure (logged as verification_failed).
     */
    public static function complete(string $state, array $params): \stdClass {
        global $USER;

        try {
            $name = state::consume($state);
            $provider = registry::get($name);
            if (!$provider || !$provider->is_available()) {
                throw new provider_exception('provider_unavailable');
            }
            $person = $provider->handle_callback($params, self::callback_url());
            service_health::record_success($name);
            return identity_manager::verify((int)$USER->id, $person);
        } catch (conflict_exception $e) {
            throw $e;
        } catch (\moodle_exception $e) {
            $reason = $e instanceof provider_exception ? $e->reason : str_replace(':', '_', $e->errorcode);
            if (isset($name)) {
                service_health::record_failure($name, $reason);
            }
            verification_failed::create([
                'relateduserid' => (int)$USER->id,
                'context' => \context_system::instance(),
                'other' => ['reason' => $reason, 'provider' => $name ?? ''],
            ])->trigger();
            throw $e;
        }
    }
}
