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
 * A person as returned by an identity provider (or entered by an admin), before normalisation.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class verified_person {
    /** Method: Smart-ID. */
    public const METHOD_SMARTID = 'smartid';
    /** Method: ID card. */
    public const METHOD_IDCARD = 'idcard';
    /** Method: Mobile-ID. */
    public const METHOD_MOBILEID = 'mobileid';
    /** Method: verified by an admin from a document. */
    public const METHOD_MANUAL = 'manual';

    /**
     * Constructor.
     *
     * @param string $country ISO 3166-1 alpha-2 country of the ID code.
     * @param string|null $idcode Personal ID code; null only for manual identities without one.
     * @param string $firstname Legal first name(s).
     * @param string $lastname Legal last name.
     * @param string|null $birthdate YYYY-MM-DD, if known.
     * @param string $method One of the METHOD_ constants.
     * @param string $provider Provider name (eideasy, mock, manual).
     */
    public function __construct(
        /** @var string Country */
        public readonly string $country,
        /** @var string|null ID code */
        public readonly ?string $idcode,
        /** @var string First name */
        public readonly string $firstname,
        /** @var string Last name */
        public readonly string $lastname,
        /** @var string|null Birth date */
        public readonly ?string $birthdate,
        /** @var string Method */
        public readonly string $method,
        /** @var string Provider */
        public readonly string $provider,
    ) {
    }

    /**
     * All valid method names.
     *
     * @return string[]
     */
    public static function methods(): array {
        return [self::METHOD_SMARTID, self::METHOD_IDCARD, self::METHOD_MOBILEID, self::METHOD_MANUAL];
    }
}
