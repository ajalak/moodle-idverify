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
$string['error:noidentity'] = 'This user has no verified identity.';
$string['error:notavailable'] = 'Identity verification is not available at the moment. Please try again later or contact support.';
$string['error:provider'] = 'Verification failed. Please try again. If it keeps failing, contact support.';
$string['error:state'] = 'The verification session is invalid. Please start again.';
$string['error:stateexpired'] = 'The verification took too long. Please start again.';
$string['event:idcode_decrypted'] = 'Verified ID code printed on a certificate';
$string['event:idcode_viewed'] = 'Unmasked ID code viewed';
$string['event:identity_retained'] = 'Identity data retained (issued certificates)';
$string['event:identity_revoked'] = 'Identity verification revoked';
$string['event:identity_verified'] = 'Identity verified';
$string['event:verification_conflict'] = 'Identity already linked to another account';
$string['event:verification_failed'] = 'Identity verification failed';
$string['idcode'] = 'Personal identification code';
$string['idverify:manage'] = 'Manage verified identities';
$string['idverify:verifyself'] = 'Verify own identity';
$string['idverify:viewidcode'] = 'View unmasked personal identification codes';
$string['intro'] = 'Your course certificate must show your legal name and personal identification code. Verify your identity once with Smart-ID, ID card or Mobile-ID. It takes about 20 seconds.';
$string['issuedcount'] = 'Certificates issued';
$string['legalname'] = 'Legal name';
$string['manage'] = 'Verified identities';
$string['manage:empty'] = 'No verified identities found.';
$string['manage:search'] = 'Name or email';
$string['manual:alreadyverified'] = 'This user is already verified. Revoke the current identity first.';
$string['manual:codeorbirthdate'] = 'Enter the personal identification code, or a date of birth if the person has none.';
$string['manual:conflict'] = 'This identity is already linked to another account. Nothing was saved.';
$string['manual:done'] = 'Identity verified manually.';
$string['manual:firstname'] = 'Legal first name(s)';
$string['manual:idcode'] = 'Personal identification code';
$string['manual:idcode_help'] = 'As in the identity document. Estonian codes are checked. Leave empty if the person has no personal code, and enter the date of birth instead.';
$string['manual:intro'] = 'Use this only when the learner cannot verify with Smart-ID, ID card or Mobile-ID. Check the identity document yourself and describe how in the note.';
$string['manual:lastname'] = 'Legal last name';
$string['manual:note'] = 'Note';
$string['manual:note_help'] = 'How the identity was checked, e.g. "passport checked in person on 27.09.2026". Required.';
$string['manual:submit'] = 'Verify';
$string['manual:title'] = 'Verify manually';
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
$string['privacy:export:undecryptable'] = '(stored encrypted; cannot be decrypted because the site encryption key has changed)';
$string['privacy:metadata:core_user'] = 'When a user is verified, the plugin sets the "Identity verified" custom profile field and may replace the first and last name with the legal name.';
$string['privacy:metadata:eideasy'] = 'The learner signs in with Smart-ID, ID card or Mobile-ID at eID Easy (eideasy.com). Moodle sends eID Easy only its client id and a random value, and receives the verified identity.';
$string['privacy:metadata:eideasy:birthdate'] = 'Date of birth, received from eID Easy.';
$string['privacy:metadata:eideasy:firstname'] = 'Legal first name, received from eID Easy.';
$string['privacy:metadata:eideasy:idcode'] = 'Personal identification code, received from eID Easy.';
$string['privacy:metadata:eideasy:lastname'] = 'Legal last name, received from eID Easy.';
$string['privacy:metadata:identity'] = 'The verified legal identity of the user. Lawful basis: legal obligation (GDPR Art. 6(1)(c)), because Estonian adult education rules require the legal name and personal identification code on the course certificate. Once the identity has been printed on issued certificates, it is kept when deletion is requested and the data protection officer decides by hand.';
$string['privacy:metadata:identity:birthdate'] = 'Date of birth.';
$string['privacy:metadata:identity:country'] = 'Country of the personal identification code.';
$string['privacy:metadata:identity:firstname'] = 'Legal first name(s).';
$string['privacy:metadata:identity:idcode_enc'] = 'Personal identification code, encrypted.';
$string['privacy:metadata:identity:idcode_hmac'] = 'Keyed hash of the personal identification code, used only to stop one identity being linked to two accounts.';
$string['privacy:metadata:identity:lastname'] = 'Legal last name.';
$string['privacy:metadata:identity:method'] = 'How the identity was verified (Smart-ID, ID card, Mobile-ID or by an administrator).';
$string['privacy:metadata:identity:note'] = 'Administrator\'s note on a manual verification.';
$string['privacy:metadata:identity:provider'] = 'Service that verified the identity.';
$string['privacy:metadata:identity:timeverified'] = 'When the identity was verified.';
$string['privacy:metadata:identity:userid'] = 'The user the identity belongs to.';
$string['privacy:metadata:identity:verifiedby'] = 'The administrator who verified the identity manually.';
$string['privacy:metadata:issued'] = 'Record of issued certificates that printed the verified identity. Used to decide whether identity data must be kept.';
$string['privacy:metadata:issued:code'] = 'Verification code of the certificate.';
$string['privacy:metadata:issued:customcertid'] = 'The certificate activity.';
$string['privacy:metadata:issued:timeissued'] = 'When the certificate was issued.';
$string['privacy:metadata:issued:userid'] = 'The user the certificate was issued to.';
$string['privacynote'] = 'Your personal identification code is stored encrypted and is only printed on your certificates.';
$string['profile:details'] = 'Details';
$string['profile:notverified'] = 'Identity not verified';
$string['profile:title'] = 'Identity verification';
$string['profile:verified'] = 'Verified on {$a->date} ({$a->method})';
$string['profile:verifiedmanual'] = 'Verified by an administrator on {$a}';
$string['profilecategory'] = 'Identity verification';
$string['profilefield'] = 'Identity verified';
$string['profilefield_desc'] = 'Set by the Identity verification plugin. Use it in access restrictions (User profile: Identity verified is equal to 1). Do not edit by hand.';
$string['provider'] = 'Provider';
$string['provider:eideasy'] = 'eID Easy (Smart-ID, ID card, Mobile-ID)';
$string['provider:mock'] = 'Mock (development only)';
$string['provider:none'] = 'Disabled';
$string['revoke'] = 'Revoke';
$string['revoke:confirm'] = 'Revoke the verified identity of {$a}? The stored name and code are deleted, the Identity verified flag is cleared and the user is logged out.';
$string['revoke:done'] = 'The identity of {$a} was revoked.';
$string['revoke:issuedwarning'] = '{$a} certificate(s) already printed this identity. customcert re-renders certificates on every view, so they will show no code or legal name after revoking.';
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
$string['setting:introtext'] = 'Introduction on My identity';
$string['setting:introtext_desc'] = 'Shown above the "Verify now" button. Leave empty for the default: "{$a}". For several languages use the multi-language content filter, e.g. <code>&lt;span lang="et" class="multilang"&gt;…&lt;/span&gt;&lt;span lang="en" class="multilang"&gt;…&lt;/span&gt;</code>.';
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
$string['verifiedby'] = 'Verified by';
$string['verifyidentity'] = 'Verify identity';
$string['verifynow'] = 'Verify now';
$string['view:reveal'] = 'Show code';
$string['view:revealnote'] = 'Showing the full code is logged.';
