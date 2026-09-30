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

/**
 * Known providers and the one selected in the settings.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {
    /** Provider name => class. */
    public const PROVIDERS = [
        'eeid' => eeid::class,
        'mock' => mock::class,
    ];

    /**
     * A provider by name.
     *
     * @param string $name
     * @return provider_interface|null Null if unknown.
     */
    public static function get(string $name): ?provider_interface {
        $class = self::PROVIDERS[$name] ?? null;
        return $class ? new $class() : null;
    }

    /**
     * The provider selected in the settings, if it is available.
     *
     * @return provider_interface|null
     */
    public static function get_active(): ?provider_interface {
        $provider = self::get((string)get_config('local_idverify', 'provider'));
        return ($provider && $provider->is_available()) ? $provider : null;
    }

    /**
     * Names of the providers that can be selected in the settings (the mock only in developer debug mode).
     *
     * @return array name => display name
     */
    public static function get_choices(): array {
        $choices = [];
        foreach (array_keys(self::PROVIDERS) as $name) {
            if ($name === 'mock' && !mock::allowed()) {
                continue;
            }
            $choices[$name] = get_string('provider:' . $name, 'local_idverify');
        }
        return $choices;
    }
}
