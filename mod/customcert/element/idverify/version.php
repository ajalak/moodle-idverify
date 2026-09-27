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

/**
 * Version details for customcertelement_idverify.
 *
 * @package    customcertelement_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'customcertelement_idverify';
$plugin->version   = 2026092700;        // YYYYMMDDXX.
$plugin->requires  = 2026042000;        // Moodle 5.2.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->dependencies = [
    'mod_customcert' => 2026042013, // 5.2.8, the element API used here.
    'local_idverify' => 2026092702,
];
