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

declare(strict_types=1);

namespace customcertelement_idverify;

use mod_customcert\export\datatypes\enum_field;
use mod_customcert\export\subplugin_text_exportable;

/**
 * Template export/import of the element settings (which value is shown); no user data.
 *
 * @package    customcertelement_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter extends subplugin_text_exportable {
    #[\Override]
    protected function get_fields(): array {
        return parent::get_fields() + [
            'show' => new enum_field([element::SHOW_IDCODE, element::SHOW_LEGALNAME]),
        ];
    }
}
