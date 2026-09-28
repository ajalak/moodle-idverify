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
 * English strings for availability_idverify.
 *
 * @package    availability_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['description'] = 'Require a verified identity (Smart-ID, ID card or Mobile-ID). Not enforced while identity verification is not working on the site.';
$string['editorlabel'] = 'The learner must have verified their identity.';
$string['notenforced'] = '(not enforced at the moment: identity verification is not working on this site)';
$string['pluginname'] = 'Restriction by verified identity';
$string['privacy:metadata'] = 'The Restriction by verified identity plugin stores no personal data. It reads data stored by the Identity verification plugin (local_idverify).';
$string['requires_notverified'] = 'your identity is <strong>not</strong> verified';
$string['requires_verified'] = 'your identity is verified ({$a})';
$string['title'] = 'Identity verified';
$string['verifylink'] = 'click here to verify';
