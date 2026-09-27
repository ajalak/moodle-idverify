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

use local_idverify\local\identity_manager;

/**
 * Event observers.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /** Register table. */
    public const ISSUED_TABLE = 'local_idverify_issued';

    /**
     * Record a certificate issued to a verified user when its template prints the verified identity.
     *
     * The register holds no personal data beyond the user id. It shows that the identity was printed on a
     * certificate, which the privacy provider uses to keep the identity (legal obligation) instead of deleting it.
     * customcert deletes its own issue rows on privacy requests, so this cannot rely on customcert_issues.
     *
     * @param \mod_customcert\event\issue_created $event
     */
    public static function certificate_issued(\mod_customcert\event\issue_created $event): void {
        global $DB;

        $issue = $DB->get_record('customcert_issues', ['id' => $event->objectid], 'id, userid, customcertid, code, timecreated');
        if (!$issue || !identity_manager::is_verified((int)$issue->userid)) {
            return;
        }
        if (!self::certificate_prints_identity((int)$issue->customcertid)) {
            return;
        }
        if ($DB->record_exists(self::ISSUED_TABLE, ['issueid' => $issue->id])) {
            return;
        }
        $DB->insert_record(self::ISSUED_TABLE, (object)[
            'userid' => $issue->userid,
            'customcertid' => $issue->customcertid,
            'issueid' => $issue->id,
            'code' => $issue->code,
            'timeissued' => $issue->timecreated,
        ]);
    }

    /**
     * Whether a certificate's template contains a Verified identity element.
     *
     * @param int $customcertid
     * @return bool
     */
    public static function certificate_prints_identity(int $customcertid): bool {
        global $DB;
        $sql = "SELECT 1
                  FROM {customcert} c
                  JOIN {customcert_pages} p ON p.templateid = c.templateid
                  JOIN {customcert_elements} e ON e.pageid = p.id
                 WHERE c.id = :customcertid AND e.element = :element";
        return $DB->record_exists_sql($sql, ['customcertid' => $customcertid, 'element' => 'idverify']);
    }
}
