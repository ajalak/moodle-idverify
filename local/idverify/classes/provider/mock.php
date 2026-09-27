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

use local_idverify\local\verified_person;

/**
 * Development provider: no external calls; returns the fake person configured in the settings.
 *
 * Only available when $CFG->debugdeveloper is on. start() sends the user to local/idverify/mock.php, which
 * stands in for the provider's login page and returns to the normal callback with a one-time code.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock implements provider_interface {
    /** Default fake person: the Smart-ID demo identity from the eID Easy test documentation. */
    public const DEFAULTS = [
        'country' => 'EE',
        'idcode' => '30303039914',
        'firstname' => 'OK',
        'lastname' => 'TESTNUMBER',
        'birthdate' => '1903-03-03',
        'method' => verified_person::METHOD_SMARTID,
    ];

    /**
     * Whether the mock may be used at all.
     *
     * @return bool
     */
    public static function allowed(): bool {
        global $CFG;
        return !empty($CFG->debugdeveloper);
    }

    #[\Override]
    public function get_name(): string {
        return 'mock';
    }

    #[\Override]
    public function is_available(): bool {
        return self::allowed();
    }

    #[\Override]
    public function start(string $state, \moodle_url $callback, string $lang): \moodle_url {
        return new \moodle_url('/local/idverify/mock.php', ['state' => $state]);
    }

    /**
     * Called by mock.php when the tester approves: a one-time code for the callback.
     *
     * @return string
     */
    public static function issue_code(): string {
        global $SESSION;
        $SESSION->local_idverify_mockcode = random_string(32);
        return $SESSION->local_idverify_mockcode;
    }

    #[\Override]
    public function handle_callback(array $params, \moodle_url $callback): verified_person {
        global $SESSION;

        if (!self::allowed()) {
            throw new provider_exception('mock_disabled');
        }
        $expected = $SESSION->local_idverify_mockcode ?? '';
        unset($SESSION->local_idverify_mockcode);

        if (!empty($params['error'])) {
            throw new provider_exception('cancelled', 'error:cancelled');
        }
        $code = (string)($params['code'] ?? '');
        if ($expected === '' || $code === '' || !hash_equals($expected, $code)) {
            throw new provider_exception('invalid_code');
        }

        $person = self::person();
        return new verified_person(
            $person['country'],
            $person['idcode'],
            $person['firstname'],
            $person['lastname'],
            $person['birthdate'] === '' ? null : $person['birthdate'],
            $person['method'],
            $this->get_name()
        );
    }

    /**
     * The configured fake person (settings override the defaults).
     *
     * @return array
     */
    public static function person(): array {
        $person = self::DEFAULTS;
        foreach (array_keys($person) as $key) {
            $value = get_config('local_idverify', 'mock_' . $key);
            if ($value !== false && $value !== '') {
                $person[$key] = $value;
            }
        }
        return $person;
    }
}
