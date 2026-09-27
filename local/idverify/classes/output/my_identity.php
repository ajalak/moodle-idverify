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

namespace local_idverify\output;

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use local_idverify\local\crypto;
use local_idverify\local\identity_manager;
use local_idverify\local\idcode;
use local_idverify\provider\registry;

/**
 * The learner's own "My identity" view. Shows the ID code masked only.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class my_identity implements named_templatable, renderable {
    /**
     * Constructor.
     *
     * @param int $userid The user viewing their own identity.
     */
    public function __construct(
        /** @var int User id */
        protected int $userid,
    ) {
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'local_idverify/my_identity';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $keyok = crypto::has_valid_key();
        $provider = registry::get_active();
        $identity = identity_manager::get($this->userid);

        $data = [
            'verified' => (bool)$identity,
            'canstart' => !$identity && $keyok && $provider !== null,
            'notavailable' => !$identity && (!$keyok || $provider === null),
            'adminwarning' => !$keyok && has_capability('moodle/site:config', \context_system::instance()),
            'starturl' => (new \moodle_url('/local/idverify/start.php'))->out(false),
            'sesskey' => sesskey(),
        ];

        if ($identity) {
            // The owner sees the code masked; decrypting to mask is not an unmasked view, so no event.
            $code = identity_manager::decrypt_idcode($identity);
            $data += [
                'fullname' => $identity->firstname . ' ' . $identity->lastname,
                'idcode' => $code === null ? null : idcode::mask($code),
                'birthdate' => $code === null ? $identity->birthdate : null,
                'country' => $identity->country,
                'method' => get_string('method:' . $identity->method, 'local_idverify'),
                'timeverified' => userdate($identity->timeverified, get_string('strftimedatetime', 'langconfig')),
                'nameslocked' => get_config('local_idverify', 'overwritenames') !== '0',
            ];
        }
        return $data;
    }
}
