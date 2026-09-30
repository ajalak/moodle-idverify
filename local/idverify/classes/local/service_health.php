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

/**
 * Whether the identity provider seems to work: counts service-side failures and attempts nobody returned from.
 *
 * eeID has no balance API. When the prepaid balance runs out, the service may be suspended without notice, which
 * shows here as repeated failures or as learners who go to the provider and never come back. The warning is
 * advisory only: it never changes whether the certificate restriction is enforced (the signals can be produced by
 * a learner on purpose, so they must not open the restriction).
 *
 * Kept in one plugin config value (JSON, no personal data): provider, failures, unreturned, lastsuccess,
 * lastfailure, reason, notified.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_health {
    /** Consecutive service-side failures that raise the warning. */
    public const FAILURE_LIMIT = 3;

    /** Consecutive attempts without any return to the site that raise the warning. */
    public const UNRETURNED_LIMIT = 10;

    /** Config name. */
    protected const CONFIG = 'health';

    /** Failure reasons caused by the learner or the browser, not by the service. */
    protected const USER_REASONS = [
        'cancelled', 'no_pending_attempt', 'no_code', 'method_not_allowed', 'idtoken_nonce', 'token_invalid_grant',
        'authorize_access_denied',
    ];

    /**
     * A learner was sent to the provider.
     *
     * @param string $provider
     */
    public static function record_start(string $provider): void {
        $data = self::load($provider);
        $data['unreturned']++;
        self::save_and_notify($data);
    }

    /**
     * The provider identified a person (whether or not the identity could then be stored).
     *
     * @param string $provider
     */
    public static function record_success(string $provider): void {
        $data = self::load($provider);
        $data['failures'] = 0;
        $data['unreturned'] = 0;
        $data['lastsuccess'] = time();
        $data['notified'] = false;
        self::save($data);
    }

    /**
     * A verification that came back to the site failed.
     *
     * @param string $provider
     * @param string $reason Reason code from the verification_failed event.
     */
    public static function record_failure(string $provider, string $reason): void {
        $data = self::load($provider);
        $data['unreturned'] = 0;
        if (self::is_service_fault($reason)) {
            $data['failures']++;
            $data['lastfailure'] = time();
            $data['reason'] = $reason;
        }
        self::save_and_notify($data);
    }

    /**
     * Whether a failure reason points at the service or its configuration rather than at the learner.
     *
     * @param string $reason
     * @return bool
     */
    public static function is_service_fault(string $reason): bool {
        if (in_array($reason, self::USER_REASONS, true)) {
            return false;
        }
        foreach (['authorize_', 'token_', 'discovery_', 'jwks_', 'idtoken_'] as $prefix) {
            if (str_starts_with($reason, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The current warning for the active provider, if any.
     *
     * @return \stdClass|null Object with type ("failures" or "unreturned"), count, reason, lastfailure,
     *     lastsuccess and provider; null when there is nothing to warn about.
     */
    public static function get_warning(): ?\stdClass {
        $provider = (string)get_config('local_idverify', 'provider');
        if ($provider === '' || $provider === 'mock') {
            return null;
        }
        $data = self::load($provider);
        if ($data['failures'] >= self::FAILURE_LIMIT) {
            $type = 'failures';
            $count = $data['failures'];
        } else if ($data['unreturned'] >= self::UNRETURNED_LIMIT) {
            $type = 'unreturned';
            $count = $data['unreturned'];
        } else {
            return null;
        }
        return (object)[
            'type' => $type,
            'count' => $count,
            'reason' => $data['reason'],
            'lastfailure' => $data['lastfailure'],
            'lastsuccess' => $data['lastsuccess'],
            'provider' => $provider,
        ];
    }

    /**
     * The warning as text for administrators (status check, admin page, message).
     *
     * @param \stdClass $warning From get_warning().
     * @return string
     */
    public static function describe(\stdClass $warning): string {
        $a = (object)[
            'count' => $warning->count,
            'reason' => $warning->reason !== '' ? $warning->reason : '-',
            'lastsuccess' => $warning->lastsuccess
                ? userdate($warning->lastsuccess, get_string('strftimedatetimeshort', 'langconfig'))
                : get_string('never'),
            'provider' => get_string('provider:' . $warning->provider, 'local_idverify'),
        ];
        return get_string('health:' . $warning->type, 'local_idverify', $a) . ' ' .
            get_string('health:advice', 'local_idverify');
    }

    /**
     * Forget everything (the provider settings changed).
     */
    public static function reset(): void {
        unset_config(self::CONFIG, 'local_idverify');
    }

    /**
     * Current counters for a provider; a different provider starts from zero.
     *
     * @param string $provider
     * @return array
     */
    protected static function load(string $provider): array {
        $empty = [
            'provider' => $provider, 'failures' => 0, 'unreturned' => 0, 'lastsuccess' => 0, 'lastfailure' => 0,
            'reason' => '', 'notified' => false,
        ];
        $stored = json_decode((string)get_config('local_idverify', self::CONFIG), true);
        if (!is_array($stored) || ($stored['provider'] ?? '') !== $provider) {
            return $empty;
        }
        return array_intersect_key($stored, $empty) + $empty;
    }

    /**
     * Store the counters.
     *
     * @param array $data
     */
    protected static function save(array $data): void {
        set_config(self::CONFIG, json_encode($data), 'local_idverify');
    }

    /**
     * Store the counters and, the first time the warning appears, message the identity managers.
     *
     * @param array $data
     */
    protected static function save_and_notify(array $data): void {
        self::save($data);
        if ($data['notified'] || $data['provider'] !== (string)get_config('local_idverify', 'provider')) {
            return;
        }
        $warning = self::get_warning();
        if ($warning === null) {
            return;
        }
        $data['notified'] = true;
        self::save($data);
        self::notify($warning);
    }

    /**
     * Send the warning to everyone who manages identities (site administrators included).
     *
     * @param \stdClass $warning
     */
    protected static function notify(\stdClass $warning): void {
        $context = \context_system::instance();
        $recipients = get_users_by_capability($context, 'local/idverify:manage', 'u.*');
        $recipients += get_admins();
        $url = new \moodle_url('/local/idverify/admin/index.php');
        foreach ($recipients as $recipient) {
            if (!empty($recipient->deleted) || !empty($recipient->suspended)) {
                continue;
            }
            $text = self::describe($warning);
            $message = new \core\message\message();
            $message->component = 'local_idverify';
            $message->name = 'servicewarning';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->subject = get_string('health:subject', 'local_idverify');
            $message->fullmessage = $text . "\n\n" . $url->out(false);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = \html_writer::tag('p', s($text));
            $message->smallmessage = get_string('health:subject', 'local_idverify');
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('manage', 'local_idverify');
            try {
                message_send($message);
            } catch (\Throwable $e) {
                debugging('local_idverify: could not send the service warning: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}
