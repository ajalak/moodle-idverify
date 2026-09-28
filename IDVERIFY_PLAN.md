# local_idverify — plan

**Status:** **1.0.0-rc1** (release candidate, 2026-09-28): local_idverify, customcertelement_idverify and availability_idverify, all 1.0.0-rc1 (70 + 8 + 5 tests); ZIPs in `releases/`, git tag `v1.0.0-rc1`; see CHANGES.md. Open: the legal reference (README §8), live checks of ID card / Mobile-ID / production eID Easy on kera.ee.

## 1. Components

| Plugin | Folder in repo | Installs to (5.2 layout) |
|---|---|---|
| `local_idverify` | `local/idverify/` | `public/local/idverify` |
| `customcertelement_idverify` (depends on `local_idverify` and `mod_customcert` ≥ 2026042013) | `mod/customcert/element/idverify/` | `public/mod/customcert/element/idverify` |

These are two ZIPs from `build.py`. CI installs `mod_customcert` with `moodle-plugin-ci add-plugin` and tests both.

## 2. Files (local_idverify)

```
version.php  settings.php  lib.php (myprofile_navigation)  index.php (My identity)  start.php  callback.php
mock.php (mock provider "login" page, debugdeveloper only)
admin/manual.php  admin/view.php (unmasked view)  admin/revoke.php
db/install.xml  db/install.php (create idverified field)  db/upgrade.php  db/access.php  db/hooks.php
db/events.php (observer: customcert issue_created)  db/caches.php (if needed)
classes/provider/provider_interface.php  classes/provider/base.php  classes/provider/eideasy.php  classes/provider/mock.php
classes/provider/registry.php (enabled provider from settings; mock only when debugdeveloper)
classes/local/verified_person.php (value object)  classes/local/idcode.php (normalise, validate EE checksum, mask)
classes/local/crypto.php (HMAC key check, hmac(), encrypt/decrypt wrappers)  classes/local/identity_manager.php
   (store/replace, conflict check, revoke, profile field sync, name overwrite, $USER->profile refresh)
classes/local/state.php (OAuth state: session-bound, single-use, 10 min TTL)
classes/hook_callbacks.php (extend_user_menu, before_user_updated, before_user_deleted)
classes/observer.php  classes/event/{identity_verified,identity_revoked,verification_conflict,
   verification_failed,idcode_viewed,idcode_decrypted}.php
classes/form/manual_form.php  classes/output/{callout,my_identity}.php  classes/privacy/provider.php
classes/check/hmackey.php (admin status check shown in Reports → System status + settings warning)
templates/{callout,my_identity}.mustache  lang/en/local_idverify.php  lang/et/local_idverify.php
tests/{idcode,crypto,identity_manager,mock_flow,eideasy_provider,privacy_provider}_test.php  tests/generator/lib.php
```

## 3. Data model (as specified) plus one proposed table

`local_idverify_identity`: `id`, `userid` (FK, **unique**), `idcode_enc` (text, null = manual without code),
`idcode_hmac` (char 64, **unique**, nullable: several NULLs are allowed in PostgreSQL, MySQL and MariaDB),
`country` (char 2), `firstname`, `lastname`, `birthdate` (char 10 `YYYY-MM-DD`, null allowed), `method`
(`smartid|idcard|mobileid|manual`), `provider` (`eideasy|mock|manual`), `note` (text, manual only),
`timeverified`, `timemodified`, `verifiedby` (FK user, null).

- `note` is **an addition** (the spec requires a note for manual verifications but gives it no column). ⟵ *needs your OK*
- **Proposed `local_idverify_issued`** (register of issued certificates): `id`, `userid`, `customcertid`,
  `issueid`, `code` (customcert verification code), `timeissued`. It is filled by an observer on customcert's
  `issue_created` event when the certificate contains our element. It stores **no** name or code; it only
  proves "this identity was printed on certificate X on date Y". The privacy rules in §6 rely on it, because
  customcert deletes its own issue rows on privacy requests. ⟵ *data model change, needs your OK*

HMAC: `hash_hmac('sha256', country . idcode, $CFG->local_idverify_hmackey)`, e.g. `"EE39111123456"`. The key is
used as a string and must be **at least 32 bytes** (README: `openssl rand -base64 48`). If it is missing or too
short: verification is refused, and there is a warning on the settings page, on My identity (for admins) and
in Reports → System status (`core\check`).

## 4. Flows

**Self-verification:** `index.php` (My identity: status, masked code `3xxxxxx1234`, "Verify identity" button) →
`start.php` (POST + sesskey; `require_capability verifyself`; key check; creates state `random_string(32)` stored
in `$SESSION` with a timestamp) → provider `start()` returns the redirect URL → eID Easy
(`/oauth/authorize?client_id&redirect_uri&response_type=code&state&lang=et|en&country=EE`) → `callback.php?code&state`
(`require_login`; `hash_equals` the state, then delete it whether it matches or not; handles `error`) → provider
`handle_callback()` (token POST, then user_data GET through `\curl` with 10 s connect / 20 s total timeouts,
checks `status = OK`, **whitelists `current_login_method`**: `smartid`→smartid, `ee-id-login`→idcard,
`ee-mid-login`/`mid-login`→mobileid) → `verified_person` → `identity_manager::verify()`:
normalise and validate the checksum; compute the HMAC; **conflict** (same HMAC, different user) → event and
message "this identity is already linked to another account…", nothing stored; otherwise upsert the row,
set `idverified = 1`, update `$USER->profile`, optionally overwrite the legal name → redirect to My identity with
a success message.

- The fixed redirect URI to register at eID Easy is `<wwwroot>/local/idverify/callback.php`.
- Re-verifying with the **same** identity refreshes the row. Re-verifying with a **different** identity on an
  already verified account is refused; an admin must revoke first.
- **Mock provider:** only listed when `$CFG->debugdeveloper`. `start()` redirects to `mock.php`, which shows the
  configured fake person (settings; default is the Smart-ID demo person `30303039914`) with *Approve* and
  *Cancel* buttons. It then returns to the same `callback.php` with a one-time code held in the session. The
  code path is identical apart from the provider.
- **Errors:** the user sees a generic, localised message. The admin log gets a `verification_failed` event whose
  `other` holds only a reason code (e.g. `http_401`, `method_not_allowed`, `invalid_idcode`), never data.

**Name lock** (setting, default on): after verification, `firstname` and `lastname` are set to the legal name.
The `before_user_updated` hook puts them back when anyone without `local/idverify:manage` changes them (profile
edit, OAuth2 sync on login). A notice on My identity explains this.

**Manual verification** (`manage`): search a user → form: country (default EE), idcode *or* birth date, legal
first and last name, required note → same `identity_manager::verify()` with method `manual` and
`verifiedby = $USER->id`.
**Revoke:** confirm page with sesskey → delete the row, `idverified = 0`, event. See §6 for the issued-certificate rule.

**Gating:** `db/install.php` creates the checkbox field `idverified` if it is missing (category "Identity"; locked;
visible: *not visible*; not signup; default 0). Teachers add the restriction *User profile → idverified → is equal
to → 1* to the certificate activity (README, with screenshots later). The plugin keeps the field in sync in the DB
and in `$USER->profile`.

**Entry points:** a user menu item (hook), a profile page node (`myprofile_navigation`), and a course **callout**:
a mustache template ("Tõenda isik — võtab 20 sekundit"). It can be placed as a label HTML snippet linking to
`/local/idverify/index.php`, or as a block. ⟵ *question below*

## 5. Certificate element

`customcertelement_idverify`. Option **show**: `idcode` (falls back to the birth date for manual identities without
a code) or `legalname`. It also has the usual font, size, colour and width options.

- `render($pdf, $preview, $user)`: in a preview it prints a placeholder (`39xxxxxxxxx`, "Eesnimi Perenimi") with
  no decryption. For a real render it loads the identity; if the user is not verified it renders **nothing** and
  calls `debugging()`. Otherwise it decrypts, prints, and fires `idcode_decrypted` (context = certificate module,
  relateduserid = learner, no data).
- `render_html()` (edit screen) shows only the placeholder.
- Its privacy provider is a `null_provider` (the data belongs to local_idverify).
- The public verification page never renders elements (confirmed in Step 0), so there is nothing to strip there.

## 6. Privacy and legal handling (your approval needed)

- **Lawful basis:** GDPR Art. 6(1)(c), legal obligation. By the Estonian adult education requirements, the
  certificate must show the legal name and isikukood. Verification itself is voluntary until the learner wants
  a certificate. This is stated in the privacy metadata string and in code comments. *You should give the exact
  legal reference for the README and metadata; I will not guess it.*
- **Metadata:** the `local_idverify_identity` fields, `local_idverify_issued`, the `idverified` user preference
  (profile field), and the eID Easy external location (`link_external_location('eideasy', …)`: idcode, name,
  birth date are received from it, and nothing is sent).
- **Export:** the learner's own record, with the isikukood **decrypted** (it is their own data), the method,
  dates and issued-certificate register.
- **Delete** (`delete_data_for_user`, `delete_data_for_users`, `delete_data_for_all_users_in_context` in the
  system context):
  - No rows in `local_idverify_issued` → delete everything (identity, register, `idverified = 0`).
  - Rows exist → **keep** the identity and register, do not delete silently, and fire
    `identity_retained` (new event ⟵ *your OK*). This shows up in the logs so the DPO can handle the request by
    hand after the retention period. `before_user_deleted` (account deletion) follows the same rule.
- **Revoke** by an admin when certificates have been issued: allowed, but the confirm page warns that
  already-issued PDFs **re-render without the code** (customcert does not store PDFs). ⟵ *alternative: block
  revoke when issued certificates exist*
- **Exposure:** the data is in our own table, not in `user` or profile fields, so it never appears in grade
  exports, reports, the profile page or bulk user download. The only profile field is the boolean `idverified`,
  and it is hidden.

## 7. Capabilities and events

`local/idverify:verifyself` (user, CONTEXT_SYSTEM), `local/idverify:manage` (manager), `local/idverify:viewidcode`
(manager, RISK_PERSONAL). Events: `identity_verified`, `identity_revoked`, `verification_conflict`,
`verification_failed`, `idcode_viewed`, `idcode_decrypted` (+ proposed `identity_retained`). They carry
`relateduserid` only; `other` holds method, provider and reason codes, never the code or name.

## 8. Phases (one commit each)

a. Schema, crypto, idcode, identity_manager, mock provider, start/callback, My identity, `idverified` field,
   hook for the user menu, and tests for these.
b. eID Easy provider and settings (test/prod host, client_id, secret, redirect URI shown read-only), tested with
   the `\curl` mock responses (`curl::mock_response`) and by hand against test.eideasy.com.
c. Certificate element plugin; `issue_created` observer; register table; CI add-plugin customcert.
d. Manual verification, admin view (unmasked, event) and revoke; name lock hook.
e. Privacy provider and remaining tests; README (install, config.php key, eID Easy registration, test env,
   key backup, gating); `et` strings.

## 9. Open questions

1. **Data model additions:** `note` column and the `local_idverify_issued` register table. OK?
2. **Deletion with issued certificates:** keep identity data and log `identity_retained`, as proposed? How long is
   the retention period? Should the plugin enforce it (a scheduled task that deletes after N years with no new
   certificates), or is it a manual DPO task?
3. **Revoke after issue:** allow with a warning (proposed), or block?
4. **Teachers see the isikukood** in learners' PDFs through the customcert report (`mod/customcert:viewreport`).
   That is inherent to the certificate. Is that acceptable, or should the element render the code only when the
   viewer is the learner themselves or has `local/idverify:viewidcode`?
5. **Course callout:** is a label snippet enough (static link; the page itself says "already verified"), or do you
   want a small block that hides itself for verified users?
6. **Foreign learners / other countries:** eID Easy also offers LV/LT methods. Allow only EE now (proposed), with
   a country whitelist setting for later?
7. **Legal reference** text for the lawful basis (act and section) for the README and privacy strings.
8. **eID Easy account:** do you have one (production client_id and secret)? For development I will use
   test.eideasy.com with its public sandbox credentials. Registering `http://localhost:8000/local/idverify/callback.php`
   may need your own test client; I will check whether the sandbox client accepts it.
9. **Production key hygiene:** I will recommend `$CFG->nokeygeneration = true` in production config.php, to stop
   Moodle silently creating a new encryption key if `moodledata/secret` goes missing. OK to document that?

## Decisions log

- 2026-09-27: dev environment is a local Moodle 5.2.3 (docs/DEVELOPMENT.md); CI on GitHub Actions.
- 2026-09-27: certificate module is `mod_customcert` 5.2.8 (Step 0 §1).
- 2026-09-27: plan approved with all proposals ("go with the proposals"):
  - Q1: `note` column and the `local_idverify_issued` register are added (both in install.xml from 0.1.0).
  - Q2: deletion with issued certificates keeps the data and logs `identity_retained`; retention is a manual
    DPO task, with no automatic expiry.
  - Q3: revoke after issue is allowed, with a warning.
  - Q4: teachers with `mod/customcert:viewreport` see the code in learners' PDFs, since it is part of the certificate.
  - Q5: the course callout is a label snippet with a link.
  - Q6: EE only, via the `allowedcountries` setting (default `EE`). Manual verification is not limited.
  - Q7: legal reference pending from the owner; a placeholder is in the strings and README.
  - Q8: eID Easy public test credentials for development.
  - Q9: document `$CFG->nokeygeneration = true` for production.
- 2026-09-27: provider setting default is "Disabled"; an admin must pick one explicitly.
- 2026-09-27: the eID Easy public test client accepts `http://localhost:8000/local/idverify/callback.php` as a
  redirect URI. Tested live.
- 2026-09-27: element option "idcode" prints the birth date (dd.mm.yyyy) for manual identities without a code;
  previews and the edit screen show a placeholder and decrypt nothing. Issued certificates are registered only
  when the user is verified and the template contains the element.
- 2026-09-27: CI tests each plugin in its own job; mod_customcert comes from GitHub (MOODLE_502_STABLE).
- 2026-09-27: revoke ends the user's sessions (`destroy_user_sessions`; an admin revoking themselves keeps the
  current one). After a manual verification, the learner's session is re-synced when they open My identity
  (`mark_user_dirty()` only reloads capabilities, not profile fields).
- 2026-09-27: manual verification is refused for already verified users (revoke first); the unmasked code is
  shown only after a POST "Show code" on the view page, one user at a time, and logged as `idcode_viewed`.
- 2026-09-27: deletion rule (privacy requests and account deletion): identity plus issued certificates → keep
  and log `identity_retained`; otherwise delete the identity and the register. After the DPO revokes, the next
  request removes the register too.
- 2026-09-27: name lock: `before_user_updated` restores the legal name unless the actor has
  `local/idverify:manage`.
- 2026-09-27: b5.ee is a sandbox only, with no real services connected: provider Disabled, or eID Easy *Test* with
  the public sandbox credentials. **kera.ee is the live site.** Go-live there (later, by the owner):
  1. Moodle 5.2+ and mod_customcert 5.2.8+; install the three ZIPs (local_idverify first).
  2. New HMAC key (not b5.ee's) and `$CFG->nokeygeneration = true` in config.php; back up the key and
     `moodledata/secret/key/sodium.key`.
  3. Register at id.eideasy.com with the redirect URI `https://kera.ee/local/idverify/callback.php`, enabling only EE
     Smart-ID, ID card and Mobile-ID; enter the client ID and secret, set Environment to Production.
  4. Add the Verified identity element to kera.ee's certificate template. Certificates get the *Identity verified*
     restriction (the course builder adds it; for other courses add it by hand).
  5. Set the provider to eID Easy; verify with a real ID once as a final check, then revoke if it was a test account.
  Do not copy identities from b5.ee.
- 2026-09-28: certificates are gated by our own restriction `availability_idverify` ("Identity verified", JSON
  `{"type":"idverify"}`), not by a profile-field condition. A plugin cannot add rows to another module's completion
  requirements, so a restriction is the right place. Owner's decisions:
  - fail open: without the plugin, with it disabled, or when verification does not work (no valid HMAC key or
    provider Disabled: `flow::is_available()`), certificates are issued as usual;
  - teachers may still issue certificates by hand (completion override); no warning, no blocking;
  - the `idverified` profile field stays;
  - the course builder adds the restriction to the certificates it builds, only where `availability_idverify` is
    installed and enabled (moodle-coursebuilder repo).
- 2026-09-28 (0.7.0): the introduction on My identity is an admin setting (`introtext`, HTML, filtered; empty =
  default string). The profile page shows an "Identity verification" line in User details instead of a link under
  Miscellaneous: unverified -> badge and "Verify now" (hidden while verification does not work), verified -> date
  and method, never the code or name. Visible to the owner and to identity managers only.
- 2026-09-28: owner declared the work so far release candidate 1.0: all three plugins 1.0.0-rc1 (MATURITY_RC),
  released together (the element and the restriction require local_idverify 2026092802); current ZIPs committed in
  `releases/`; CI YUI lint fixed (camelcase on `M.availability_idverify`).
- 2026-09-28: b5.ee live test paused. With the public eID Easy sandbox client (Environment Test), the eID Easy login
  page for `https://b5.ee/local/idverify/callback.php` opens, but login ends on eID Easy's page with "Midagi läks
  valesti … 0x7749" (not documented; an internal reference). Nothing reached Moodle. The same client worked with
  `localhost` on 2026-09-27. The owner has asked eID Easy support about 0x7749 and pricing. Next: their answer;
  likely fix is an own eID Easy account with a test client that has the b5.ee redirect URI registered.
