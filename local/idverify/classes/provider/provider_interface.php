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
 * An identity provider. A new provider (e.g. TARA, Dokobit) is one class implementing this, listed in
 * registry::PROVIDERS, plus its settings.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface provider_interface {
    /**
     * Short provider name stored with the identity (e.g. "eideasy").
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Whether the provider is configured and allowed on this site.
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Where to send the user to authenticate.
     *
     * @param string $state Session-bound, single-use state to pass through the provider.
     * @param \moodle_url $callback The callback URL (local/idverify/callback.php).
     * @param string $lang Moodle language of the user.
     * @return \moodle_url
     */
    public function start(string $state, \moodle_url $callback, string $lang): \moodle_url;

    /**
     * Turn the provider's callback request into a verified person. The state has already been checked.
     *
     * @param array $params Callback query parameters (e.g. code, error), already cleaned.
     * @param \moodle_url $callback The same callback URL passed to start().
     * @return verified_person
     * @throws provider_exception On cancellation, provider errors or unusable data.
     */
    public function handle_callback(array $params, \moodle_url $callback): verified_person;
}
