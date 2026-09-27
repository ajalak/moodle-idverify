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
 * English strings for local_idverify.
 *
 * @package    local_idverify
 * @copyright  2026 Andres
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['birthdate'] = 'Date of birth';
$string['check:hmackey_error'] = 'Identity verification HMAC key is missing or too short';
$string['check:hmackey_ok'] = 'Identity verification HMAC key is configured';
$string['checkhmackey'] = 'Identity verification key';
$string['error:alreadyverified'] = 'This account is already verified with a different identity. Please contact support.';
$string['error:cancelled'] = 'Verification was cancelled.';
$string['error:conflict'] = 'This identity is already linked to another account. Please contact support.';
$string['error:countrynotallowed'] = 'Identities from this country cannot be verified here. Please contact support.';
$string['error:hmackey'] = 'Identity verification is not configured on this site.';
$string['error:hmackey_admin'] = 'Identity verification is disabled: <code>$CFG->local_idverify_hmackey</code> is missing from config.php or shorter than 32 bytes. See the plugin README.';
$string['error:invaliddata'] = 'The identity data is incomplete or invalid.';
$string['error:invalididcode'] = 'The personal identification code is not valid.';
$string['error:methodnotallowed'] = 'This sign-in method cannot be used for identity verification. Please use Smart-ID, ID card or Mobile-ID.';
$string['error:notavailable'] = 'Identity verification is not available at the moment. Please try again later or contact support.';
$string['error:provider'] = 'Verification failed. Please try again. If it keeps failing, contact support.';
$string['error:state'] = 'The verification session is invalid. Please start again.';
$string['error:stateexpired'] = 'The verification took too long. Please start again.';
$string['event:identity_revoked'] = 'Identity verification revoked';
$string['event:identity_verified'] = 'Identity verified';
$string['event:verification_conflict'] = 'Identity already linked to another account';
$string['event:verification_failed'] = 'Identity verification failed';
$string['idcode'] = 'Personal identification code';
$string['idverify:manage'] = 'Manage verified identities';
$string['idverify:verifyself'] = 'Verify own identity';
$string['idverify:viewidcode'] = 'View unmasked personal identification codes';
$string['intro'] = 'Your course certificate must show your legal name and personal identification code. Verify your identity once with Smart-ID, ID card or Mobile-ID. It takes about 20 seconds.';
$string['legalname'] = 'Legal name';
$string['method'] = 'Method';
$string['method:idcard'] = 'ID card';
$string['method:manual'] = 'Verified by an administrator';
$string['method:mobileid'] = 'Mobile-ID';
$string['method:smartid'] = 'Smart-ID';
$string['mock:approve'] = 'Approve as this person';
$string['mock:title'] = 'Mock identity provider';
$string['mock:warning'] = 'Development only: no real identity check happens. This page is available only in developer debug mode.';
$string['myidentity'] = 'My identity';
$string['nameslocked'] = 'Your first and last name on this site are set to your legal name and cannot be changed.';
$string['pluginname'] = 'Identity verification';
$string['privacynote'] = 'Your personal identification code is stored encrypted and is only printed on your certificates.';
$string['profilecategory'] = 'Identity verification';
$string['profilefield'] = 'Identity verified';
$string['profilefield_desc'] = 'Set by the Identity verification plugin. Use it in access restrictions (User profile: Identity verified is equal to 1). Do not edit by hand.';
$string['provider:eideasy'] = 'eID Easy (Smart-ID, ID card, Mobile-ID)';
$string['provider:mock'] = 'Mock (development only)';
$string['provider:none'] = 'Disabled';
$string['setting:allowedcountries'] = 'Allowed countries';
$string['setting:allowedcountries_desc'] = 'Comma-separated ISO country codes whose identities providers may verify, e.g. EE. Does not apply to manual verification.';
$string['setting:eideasy_clientid'] = 'Client ID';
$string['setting:eideasy_env'] = 'Environment';
$string['setting:eideasy_env_desc'] = 'Test uses test.eideasy.com, where only test identities work. Production uses id.eideasy.com.';
$string['setting:eideasy_env_production'] = 'Production (id.eideasy.com)';
$string['setting:eideasy_env_test'] = 'Test (test.eideasy.com)';
$string['setting:eideasy_secret'] = 'Client secret';
$string['setting:eideasy_secret_desc'] = 'Keep secret. It lets anyone use eID Easy at your expense.';
$string['setting:eideasyheading'] = 'eID Easy';
$string['setting:eideasyheading_desc'] = 'Register this site at id.eideasy.com (My Webpages, Register new webpage) with the redirect URI <code>{$a}</code>. Enable only the Estonian Smart-ID, ID card and Mobile-ID login methods.';
$string['setting:mock_birthdate'] = 'Mock date of birth (YYYY-MM-DD)';
$string['setting:mock_country'] = 'Mock country';
$string['setting:mock_firstname'] = 'Mock first name';
$string['setting:mock_idcode'] = 'Mock personal identification code';
$string['setting:mock_lastname'] = 'Mock last name';
$string['setting:mockheading'] = 'Mock provider';
$string['setting:mockheading_desc'] = 'The fake person the mock provider returns. Shown only in developer debug mode.';
$string['setting:overwritenames'] = 'Use legal name';
$string['setting:overwritenames_desc'] = 'After verification, replace the user\'s first and last name with the verified legal name and keep them from being changed.';
$string['setting:provider'] = 'Identity provider';
$string['setting:provider_desc'] = 'Service used to verify identities.';
$string['status:verified'] = 'Your identity is verified.';
$string['timeverified'] = 'Verified on';
$string['verified'] = 'Your identity has been verified.';
$string['verifyidentity'] = 'Verify identity';
$string['verifynow'] = 'Verify now';
