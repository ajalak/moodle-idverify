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

namespace local_idverify\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_idverify\local\identity_manager;

/**
 * Privacy provider for local_idverify.
 *
 * Lawful basis: legal obligation (GDPR Art. 6(1)(c)). Under the Adult Education Act (Täiskasvanute koolituse seadus)
 * and the Continuing Education Standard (Täienduskoolituse standard, RT I, 22.04.2025, 2), the continuing education
 * certificate ("tunnistus") or attestation ("tõend") must show the learner's name and personal ID code, or the date
 * of birth if there is none; verification is how that data is obtained reliably.
 *
 * All data lives in the data subject's user context. Export returns the learner's own data, including the
 * decrypted code. Deletion follows identity_manager::delete_personal_data(): an identity that has been printed on
 * issued certificates is NOT deleted automatically; it is kept and an identity_retained event is logged for the
 * data protection officer. Everything else is deleted.
 *
 * Admins recorded as "verifiedby" on other users' identities: that id describes the learner's verification and
 * stays with the learner's record; it is not exported or deleted as the admin's own data.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider
{
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_idverify_identity', [
            'userid' => 'privacy:metadata:identity:userid',
            'idcode_enc' => 'privacy:metadata:identity:idcode_enc',
            'idcode_hmac' => 'privacy:metadata:identity:idcode_hmac',
            'country' => 'privacy:metadata:identity:country',
            'firstname' => 'privacy:metadata:identity:firstname',
            'lastname' => 'privacy:metadata:identity:lastname',
            'birthdate' => 'privacy:metadata:identity:birthdate',
            'method' => 'privacy:metadata:identity:method',
            'provider' => 'privacy:metadata:identity:provider',
            'note' => 'privacy:metadata:identity:note',
            'timeverified' => 'privacy:metadata:identity:timeverified',
            'verifiedby' => 'privacy:metadata:identity:verifiedby',
        ], 'privacy:metadata:identity');

        $collection->add_database_table('local_idverify_issued', [
            'userid' => 'privacy:metadata:issued:userid',
            'customcertid' => 'privacy:metadata:issued:customcertid',
            'code' => 'privacy:metadata:issued:code',
            'timeissued' => 'privacy:metadata:issued:timeissued',
        ], 'privacy:metadata:issued');

        // The learner signs in at eeID (Estonian Internet Foundation) directly; Moodle sends only its client id and
        // random values (state, nonce, PKCE challenge), and receives the fields below back in a signed ID token.
        $collection->add_external_location_link('eeid', [
            'idcode' => 'privacy:metadata:eeid:idcode',
            'firstname' => 'privacy:metadata:eeid:firstname',
            'lastname' => 'privacy:metadata:eeid:lastname',
            'birthdate' => 'privacy:metadata:eeid:birthdate',
            'method' => 'privacy:metadata:eeid:method',
        ], 'privacy:metadata:eeid');

        $collection->add_subsystem_link('core_user', [], 'privacy:metadata:core_user');
        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if (
            $DB->record_exists('local_idverify_identity', ['userid' => $userid])
                || $DB->record_exists('local_idverify_issued', ['userid' => $userid])
        ) {
            $contextlist->add_user_context($userid);
        }
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }
        if (
            $DB->record_exists('local_idverify_identity', ['userid' => $context->instanceid])
                || $DB->record_exists('local_idverify_issued', ['userid' => $context->instanceid])
        ) {
            $userlist->add_user($context->instanceid);
        }
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_user || (int)$context->instanceid !== $userid) {
                continue;
            }
            $subcontext = [get_string('pluginname', 'local_idverify')];
            $identity = identity_manager::get($userid);
            if ($identity) {
                try {
                    $code = identity_manager::decrypt_idcode($identity);
                } catch (\moodle_exception $e) {
                    $code = get_string('privacy:export:undecryptable', 'local_idverify');
                }
                writer::with_context($context)->export_data($subcontext, (object)[
                    'country' => $identity->country,
                    'idcode' => $code,
                    'firstname' => $identity->firstname,
                    'lastname' => $identity->lastname,
                    'birthdate' => $identity->birthdate,
                    'method' => get_string('method:' . $identity->method, 'local_idverify'),
                    'provider' => $identity->provider,
                    'note' => $identity->note,
                    'timeverified' => transform::datetime($identity->timeverified),
                    'verifiedbyadmin' => transform::yesno(!empty($identity->verifiedby)),
                ]);
            }
            $issued = $DB->get_records('local_idverify_issued', ['userid' => $userid], 'timeissued');
            if ($issued) {
                $rows = [];
                foreach ($issued as $row) {
                    $rows[] = (object)['customcertid' => $row->customcertid, 'code' => $row->code,
                        'timeissued' => transform::datetime($row->timeissued)];
                }
                writer::with_context($context)->export_related_data($subcontext, 'issued', (object)['certificates' => $rows]);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context instanceof \context_user) {
            identity_manager::delete_personal_data((int)$context->instanceid, 'privacyrequest');
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && (int)$context->instanceid === $userid) {
                identity_manager::delete_personal_data($userid, 'privacyrequest');
            }
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }
        if (in_array((int)$context->instanceid, array_map('intval', $userlist->get_userids()), true)) {
            identity_manager::delete_personal_data((int)$context->instanceid, 'privacyrequest');
        }
    }
}
