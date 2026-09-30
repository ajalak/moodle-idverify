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

use local_idverify\event\identity_retained;
use local_idverify\event\identity_revoked;
use local_idverify\event\identity_verified;
use local_idverify\event\verification_conflict;
use stdClass;

/**
 * Stores, looks up and revokes verified identities, and keeps the idverified profile field in sync.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class identity_manager {
    /** Identity table. */
    public const TABLE = 'local_idverify_identity';
    /** Shortname of the checkbox profile field that availability_profile conditions use. */
    public const PROFILE_FIELD = 'idverified';

    /**
     * The identity record of a user.
     *
     * @param int $userid
     * @return stdClass|null
     */
    public static function get(int $userid): ?stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['userid' => $userid]) ?: null;
    }

    /**
     * Whether a user has a verified identity.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_verified(int $userid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, ['userid' => $userid]);
    }

    /**
     * Decrypted ID code of an identity record, or null if it has none (manual identity without a code).
     *
     * Callers that show or print the code must fire the matching event (idcode_viewed / idcode_decrypted).
     *
     * @param stdClass $identity Identity record.
     * @return string|null
     */
    public static function decrypt_idcode(stdClass $identity): ?string {
        if ($identity->idcode_enc === null || $identity->idcode_enc === '') {
            return null;
        }
        return crypto::decrypt($identity->idcode_enc);
    }

    /**
     * Countries whose identities providers may verify (admin setting, default EE).
     *
     * @return string[]
     */
    public static function allowed_countries(): array {
        $setting = get_config('local_idverify', 'allowedcountries');
        $setting = ($setting === false || trim($setting) === '') ? 'EE' : $setting;
        return array_values(array_filter(array_map([idcode::class, 'normalise_country'], explode(',', $setting))));
    }

    /**
     * Store a verified identity for a user.
     *
     * @param int $userid User the identity belongs to.
     * @param verified_person $person Identity from a provider or an admin.
     * @param int|null $verifiedby Admin user id for manual verifications.
     * @param string|null $note Admin note for manual verifications.
     * @return stdClass The stored identity record.
     * @throws conflict_exception If the identity is linked to another account.
     * @throws \moodle_exception If the data is invalid, the key is missing or the account has another identity.
     */
    public static function verify(
        int $userid,
        verified_person $person,
        ?int $verifiedby = null,
        ?string $note = null
    ): stdClass {
        global $DB;

        crypto::require_valid_key();
        $manual = $person->method === verified_person::METHOD_MANUAL;
        if (!in_array($person->method, verified_person::methods(), true)) {
            throw new \moodle_exception('error:invaliddata', 'local_idverify');
        }

        $country = idcode::normalise_country($person->country);
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            throw new \moodle_exception('error:invaliddata', 'local_idverify');
        }
        if (!$manual && !in_array($country, self::allowed_countries(), true)) {
            throw new \moodle_exception('error:countrynotallowed', 'local_idverify');
        }

        $code = $person->idcode === null ? '' : idcode::normalise_code($person->idcode);
        if ($code === '' && !$manual) {
            throw new \moodle_exception('error:invalididcode', 'local_idverify');
        }
        if ($code !== '' && $country === 'EE' && !idcode::is_valid_ee($code)) {
            throw new \moodle_exception('error:invalididcode', 'local_idverify');
        }

        $birthdate = self::normalise_date($person->birthdate);
        if ($birthdate === null && $code !== '' && $country === 'EE') {
            $birthdate = idcode::ee_birthdate($code);
        }
        if ($code === '' && $birthdate === null) {
            // A manual identity without a code must at least have a birth date.
            throw new \moodle_exception('error:invaliddata', 'local_idverify');
        }

        $firstname = trim(clean_param($person->firstname, PARAM_TEXT));
        $lastname = trim(clean_param($person->lastname, PARAM_TEXT));
        if ($firstname === '' || $lastname === '') {
            throw new \moodle_exception('error:invaliddata', 'local_idverify');
        }

        $hmac = $code === '' ? null : crypto::hmac($country, $code);

        if ($hmac !== null) {
            $holder = $DB->get_field(self::TABLE, 'userid', ['idcode_hmac' => $hmac]);
            if ($holder !== false && (int)$holder !== $userid) {
                self::conflict($userid, (int)$holder, $person);
            }
        }

        $existing = self::get($userid);
        if ($existing && $existing->idcode_hmac !== null && $existing->idcode_hmac !== $hmac) {
            // Another identity is already on this account: an admin must revoke it first.
            throw new \moodle_exception('error:alreadyverified', 'local_idverify');
        }

        $now = time();
        $record = (object)[
            'userid' => $userid,
            'idcode_enc' => $code === '' ? null : crypto::encrypt($code),
            'idcode_hmac' => $hmac,
            'country' => $country,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'birthdate' => $birthdate,
            'method' => $person->method,
            'provider' => $person->provider,
            'note' => $manual ? $note : null,
            'timeverified' => $now,
            'timemodified' => $now,
            'verifiedby' => $manual ? $verifiedby : null,
        ];

        try {
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record(self::TABLE, $record);
            } else {
                $record->id = $DB->insert_record(self::TABLE, $record);
            }
        } catch (\dml_write_exception $e) {
            // Unique index hit by a concurrent verification of the same identity on another account.
            $holder = $hmac === null ? false : $DB->get_field(self::TABLE, 'userid', ['idcode_hmac' => $hmac]);
            if ($holder !== false && (int)$holder !== $userid) {
                self::conflict($userid, (int)$holder, $person);
            }
            throw $e;
        }

        self::set_profile_flag($userid, true);
        if (get_config('local_idverify', 'overwritenames') !== '0') {
            self::apply_legal_name($userid, $firstname, $lastname);
        }

        identity_verified::create([
            'objectid' => $record->id,
            'relateduserid' => $userid,
            'context' => \context_system::instance(),
            'other' => ['method' => $person->method, 'provider' => $person->provider]
                + ($person->assurance !== null ? ['acr' => $person->assurance] : []),
        ])->trigger();

        return $record;
    }

    /**
     * Remove a user's identity, clear the idverified flag and end the user's sessions.
     *
     * Certificates already issued re-render without the identity (customcert does not store PDFs); the
     * issued-certificate register is kept.
     *
     * @param int $userid
     * @return bool False if the user had no identity.
     */
    public static function revoke(int $userid): bool {
        global $DB, $USER;
        $existing = self::get($userid);
        if (!$existing) {
            return false;
        }
        $DB->delete_records(self::TABLE, ['id' => $existing->id]);
        self::set_profile_flag($userid, false);
        // The user's other sessions still hold idverified = 1 in $USER->profile (availability_profile reads it
        // from there), so end them; the flag is reloaded from the database at the next login.
        \core\session\manager::destroy_user_sessions($userid, (int)$USER->id === $userid ? session_id() : null);

        identity_revoked::create([
            'objectid' => $existing->id,
            'relateduserid' => $userid,
            'context' => \context_system::instance(),
            'other' => ['method' => $existing->method, 'provider' => $existing->provider],
        ])->trigger();
        return true;
    }

    /**
     * Delete a user's identity data for a privacy request or account deletion, unless it must be kept.
     *
     * Lawful basis is legal obligation (GDPR Art. 6(1)(c)): the Continuing Education Standard (Täienduskoolituse
     * standard, RT I, 22.04.2025, 2, under the Adult Education Act) requires the name and personal ID code (or date of
     * birth) on the certificate. While a verified identity exists and certificates have printed it
     * (rows in local_idverify_issued), nothing is deleted: the data is kept and an identity_retained event is
     * logged so the data protection officer can decide by hand, e.g. revoke after the retention period. Once the
     * identity is gone (never verified or revoked), the next request deletes the register rows too.
     *
     * @param int $userid
     * @param string $reason "privacyrequest" or "userdeleted", logged with identity_retained.
     * @return bool True if data was deleted (or there was none), false if it was retained.
     */
    public static function delete_personal_data(int $userid, string $reason): bool {
        global $DB;

        $identity = self::get($userid);
        $issued = self::count_issued($userid);
        if ($identity && $issued > 0) {
            identity_retained::create([
                'objectid' => $identity->id,
                'relateduserid' => $userid,
                'context' => \context_system::instance(),
                'other' => ['reason' => $reason, 'issued' => $issued],
            ])->trigger();
            return false;
        }

        $DB->delete_records(self::TABLE, ['userid' => $userid]);
        $DB->delete_records('local_idverify_issued', ['userid' => $userid]);
        if ($identity && $DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            self::set_profile_flag($userid, false);
        }
        return true;
    }

    /**
     * Number of issued certificates that printed this user's identity (local_idverify_issued).
     *
     * @param int $userid
     * @return int
     */
    public static function count_issued(int $userid): int {
        global $DB;
        return $DB->count_records('local_idverify_issued', ['userid' => $userid]);
    }

    /**
     * Bring the current user's session copy of idverified in line with the database.
     *
     * Needed after an admin verified the user while they were logged in (their session still says 0).
     */
    public static function sync_session_flag(): void {
        global $USER;
        if (isloggedin() && !isguestuser() && isset($USER->profile) && is_array($USER->profile)) {
            $USER->profile[self::PROFILE_FIELD] = self::is_verified((int)$USER->id) ? '1' : '0';
        }
    }

    /**
     * Set the idverified profile field in the database and, for the current user, in the session.
     *
     * availability_profile reads the current user's custom fields from $USER->profile (loaded at login), so
     * without the session update a learner would stay locked out until the next login.
     *
     * @param int $userid
     * @param bool $verified
     */
    public static function set_profile_flag(int $userid, bool $verified): void {
        global $DB, $USER;

        $fieldid = self::ensure_profile_field();
        $value = $verified ? '1' : '0';
        $dataid = $DB->get_field('user_info_data', 'id', ['userid' => $userid, 'fieldid' => $fieldid]);
        if ($dataid) {
            $DB->set_field('user_info_data', 'data', $value, ['id' => $dataid]);
        } else {
            $DB->insert_record('user_info_data', (object)['userid' => $userid, 'fieldid' => $fieldid, 'data' => $value,
                'dataformat' => 0]);
        }

        if ((int)$USER->id === $userid && isset($USER->profile) && is_array($USER->profile)) {
            $USER->profile[self::PROFILE_FIELD] = $value;
        }
    }

    /**
     * Create the idverified checkbox profile field (and its category) if it does not exist.
     *
     * Locked (users cannot edit it) and not visible on profiles; it only carries a yes/no flag.
     *
     * @return int Field id.
     */
    public static function ensure_profile_field(): int {
        global $CFG, $DB;

        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => self::PROFILE_FIELD]);
        if ($fieldid) {
            return (int)$fieldid;
        }

        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        $categoryname = get_string('profilecategory', 'local_idverify');
        $categoryid = $DB->get_field('user_info_category', 'id', ['name' => $categoryname], IGNORE_MULTIPLE);
        if (!$categoryid) {
            $categoryid = $DB->insert_record('user_info_category', (object)[
                'name' => $categoryname,
                'sortorder' => $DB->count_records('user_info_category') + 1,
            ]);
        }

        $fieldid = $DB->insert_record('user_info_field', (object)[
            'shortname' => self::PROFILE_FIELD,
            'name' => get_string('profilefield', 'local_idverify'),
            'datatype' => 'checkbox',
            'description' => get_string('profilefield_desc', 'local_idverify'),
            'descriptionformat' => FORMAT_HTML,
            'categoryid' => $categoryid,
            'sortorder' => $DB->count_records('user_info_field', ['categoryid' => $categoryid]) + 1,
            'required' => 0,
            'locked' => 1,
            'visible' => PROFILE_VISIBLE_NONE,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '0',
            'defaultdataformat' => 0,
        ]);
        profile_purge_user_fields_cache();
        return (int)$fieldid;
    }

    /**
     * Replace the account's first and last name with the legal name.
     *
     * @param int $userid
     * @param string $firstname
     * @param string $lastname
     */
    protected static function apply_legal_name(int $userid, string $firstname, string $lastname): void {
        global $CFG, $USER;

        $user = \core_user::get_user($userid, 'id, firstname, lastname', MUST_EXIST);
        if ($user->firstname === $firstname && $user->lastname === $lastname) {
            return;
        }
        require_once($CFG->dirroot . '/user/lib.php');
        user_update_user((object)['id' => $userid, 'firstname' => $firstname, 'lastname' => $lastname], false, true);
        if ((int)$USER->id === $userid) {
            $USER->firstname = $firstname;
            $USER->lastname = $lastname;
        }
    }

    /**
     * Log a conflict and stop.
     *
     * @param int $userid User trying to verify.
     * @param int $holderid User the identity is linked to.
     * @param verified_person $person
     * @throws conflict_exception Always.
     */
    protected static function conflict(int $userid, int $holderid, verified_person $person): void {
        verification_conflict::create([
            'relateduserid' => $userid,
            'context' => \context_system::instance(),
            'other' => ['holderid' => $holderid, 'method' => $person->method, 'provider' => $person->provider],
        ])->trigger();
        throw new conflict_exception();
    }

    /**
     * A valid YYYY-MM-DD date, or null.
     *
     * @param string|null $date
     * @return string|null
     */
    protected static function normalise_date(?string $date): ?string {
        if ($date === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($date), $m)) {
            return null;
        }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? trim($date) : null;
    }
}
