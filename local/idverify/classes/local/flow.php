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
        return $provider->start($state, self::callback_url(), current_language());
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
            return identity_manager::verify((int)$USER->id, $person);
        } catch (conflict_exception $e) {
            throw $e;
        } catch (\moodle_exception $e) {
            $reason = $e instanceof provider_exception ? $e->reason : str_replace(':', '_', $e->errorcode);
            verification_failed::create([
                'relateduserid' => (int)$USER->id,
                'context' => \context_system::instance(),
                'other' => ['reason' => $reason, 'provider' => $name ?? ''],
            ])->trigger();
            throw $e;
        }
    }
}
