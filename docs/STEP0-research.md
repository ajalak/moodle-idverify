# Step 0 — Investigation findings

> **Note (2026-09-30):** eID Easy (§2) was replaced by eeID in 1.0.0-rc3; see [eeid-research.md](eeid-research.md).
> §2 is kept for history.

Read on 2026-09-27. Moodle source: tag `v5.2.3` (local copy `D:\MoodleDev\moodle`). Live site: b5.ee, Moodle
5.2.3 (Build 20260914), checked with the coursebuilder `server_info` tool.

## 1. Certificate module

- **`mod_customcert` v5.2.8** (version `2026042013`) on b5.ee; the dev site runs the same version (branch
  `MOODLE_502_STABLE`). `tool_certificate` is not used: the coursebuilder only calls customcert
  (`local_coursebuilder_apply_certificate_template`, `mod/customcert:manage`).
- The coursebuilder copies the site template **`CB sertifikaat`** into each course certificate with
  `\mod_customcert\service\template_load_service::replace()` (coursebuilder `docs/M6.2-customcert-research.md`).
  Our element must be added to that site template once; every course certificate built afterwards inherits it.
- Element subplugins: type `customcertelement`, folder `mod/customcert/element/<name>/`. 5.2 uses a new
  interface-based element API (`classes/element/*_interface.php`). Reference implementation:
  `element/userfield/classes/element.php`, which extends `\mod_customcert\element` and implements
  `form_element_interface`, `persistable_element_interface`, `preparable_form_interface`,
  `renderable_element_interface` (`render(pdf, bool $preview, stdClass $user, ?element_renderer)` and
  `render_html()`), and `validatable_element_interface`. Element settings are JSON (`get_payload()`).
- **Public verification page** (`verify_certificate.php`): it queries `customcert_issues` joined to `user` and shows
  the name, course, certificate name, code and dates (plus expiry). It **never renders elements**, so an
  isikukood element cannot appear there.
- **PDFs are not stored.** Every view or download re-renders the PDF from the template and current data. So:
  (a) the element must be able to decrypt the isikukood whenever a certificate is viewed, as long as it exists;
  (b) the certificate changes if the identity is revoked or deleted.
- Who can see a learner's PDF: the learner, anyone with `mod/customcert:viewreport` (teachers, who can
  download it from the report), and recipients of customcert's e-mail options (`emailstudents`,
  `emailteachers`, `emailothers`). The coursebuilder sets these to 0 / empty.
- customcert's privacy provider **deletes `customcert_issues` rows** on data deletion requests
  (`classes/privacy/provider.php` lines 194–254). Retention of issued-certificate data therefore cannot rely on
  customcert.

## 2. eID Easy OAuth 2.0 identification

Sources:
- https://docs.eideasy.com/authentication/eideasy-authentication-page-flow.html (flow, endpoints, parameters)
- https://docs.eideasy.com/guide/test-environment.html (test host, public sandbox credentials)
- https://docs.eideasy.com/guide/api-credentials.html (registration, redirect URI)
- https://docs.eideasy.com/guide/test-user/smartid.html, `.../mobileid.html`, `.../id-cards.html` (test users)
- Postman API reference https://documenter.getpostman.com/view/3869493/Szf6WoG1, "Identification" folder
  (example responses, method codes)
- https://github.com/SK-EID/MID/wiki/Test-number-for-automated-testing-in-DEMO (Mobile-ID demo numbers)

| Step | Details |
|---|---|
| Authorize | `GET https://id.eideasy.com/oauth/authorize` — required `client_id`, `redirect_uri`, `response_type=code`; optional `state`, `lang`, `country` (e.g. `EE`), `start` (method code, skip method choice), `idcode`, `phone`, `lang_selector`, `cancel_button`, `allow_method_change`, `allow_country_change`, `scope` |
| Token | `POST https://id.eideasy.com/oauth/access_token`, `application/x-www-form-urlencoded`: `client_id`, `client_secret`, `redirect_uri`, `code`, `grant_type=authorization_code` → `{token_type: "Bearer", expires_in: 3600, access_token, refresh_token}`. Errors: 400 `invalid_request` (e.g. hint "Authorization code has been revoked" on reuse), 401 `invalid_client` |
| User data | `GET https://id.eideasy.com/api/v2/user_data`, header `Authorization: Bearer <access_token>` → `{status: "OK", idcode, firstname, lastname, current_login_method, birth_date (YYYY-MM-DD), country, current_login_info: {valid_from, valid_to}}` |
| Test host | `https://test.eideasy.com` instead of `https://id.eideasy.com`. Public sandbox credentials are published on the test-environment page. Real eIDs do not work there |
| Registration | Sign up at id.eideasy.com → *My Webpages* → *Register new webpage*: site URL and **redirect URI**; the client_id and secret are shown there |

- **Method codes** (`current_login_method`, also the `start` values): `ee-id-login` (ID card), `smartid`,
  `ee-mid-login` (Mobile-ID). The client config (`/api/client-config/{client_id}`) lists login types including
  `mid-login`, **`Google`, `Facebook`, `agrello`** and other countries' methods. **The plugin must whitelist the
  method codes** and reject everything else; the eID Easy client should enable only the three EE methods.
- Name fields: the flow page's example has first and last name swapped (`"lastname": "John"`). The API
  reference examples are correct (`firstname` "Margus", `lastname` "Pala"). We use `firstname` / `lastname` as
  named.
- `idcode` is returned without a country prefix (`"38112086027"`), and `country` is separate.
- Mobile browsers: the docs warn against in-app browsers and iframes. We use a full-page redirect.
- Test identities:
  - Smart-ID EE demo: the eID Easy page lists `30303039914`, but in SK's demo environment it is now a BASIC
    account ("only valid for bank login"), which **fails** (checked 2026-09-27). Use SK's current qualified test
    accounts (https://sk-eid.github.io/smart-id-documentation/test_accounts.html): **`40404040009`** (MOCK-Q,
    adult, OK, auto-approves). A live test on 2026-09-27 via test.eideasy.com returned "Ok" "Test", born
    1904-04-04, method `smartid`. Others: `30403039917` (USER_REFUSED), `30403039983` (TIMEOUT).
  - Mobile-ID demo: `+37268000769` / `60001017869` (OK), `+37201100266` / `60001019950` (USER_CANCELLED).
  - ID card: needs a physical test card from id.ee.

## 3. `\core\encryption` (lib/classes/encryption.php)

- `encrypt(string $data, ?string $method = null): string` → `"sodium:" . base64(iv . ciphertext)`; `''` for empty
  input. `decrypt(string $data): string`. Only method is `METHOD_SODIUM` (`sodium_crypto_secretbox`).
- Key file: `($CFG->secretdataroot ?? $CFG->dataroot . '/secret') . '/key/sodium.key'` (`get_key_file()`).
- **If the key file is missing it is silently re-created** (unless `$CFG->nokeygeneration` is set), and every
  previously encrypted value becomes undecryptable (`moodle_exception('encryption_decryptfailed')`). We recommend
  setting `$CFG->nokeygeneration = true` in production after the key exists, and backing up the key file.
- Errors are thrown as `moodle_exception` (`encryption_nokey`, `encryption_encryptfailed`,
  `encryption_decryptfailed`). The core tests pass locally (`lib/tests/encryption_test.php`, 11 tests).

## 4. `availability_profile` with custom profile fields (availability/condition/profile)

- The condition JSON uses `cf` = custom field **shortname** (`{"type":"profile","cf":"idverified","op":"isequalto","v":"1"}`);
  `sf` is for standard fields. Operators: `isequalto`, `contains`, `doesnotcontain`, `startswith`, `endswith`,
  `isempty`, `isnotempty`. The comparison is a strict string comparison (`$value !== $uservalue`).
- Fields come from `profile_get_custom_fields(true)`, **regardless of visibility**, so a hidden field
  (`visible = PROFILE_VISIBLE_NONE`) still appears in the condition editor.
- A checkbox field stores `'1'` / `'0'`. With no `user_info_data` row, the field's `defaultdata` is used.
- **Session caching:** for the current user the value is read from **`$USER->profile`** (loaded at login). After
  verification the plugin must update `$USER->profile['idverified']` in the session, or the learner stays locked
  out until they log in again. Other users are read from the DB.

## 5. Other hooks available in 5.2

- `\core_user\hook\extend_user_menu` — adds a user menu item ("Verify identity").
- `\core_user\hook\before_user_updated` (fired in `user_update_user()`; `$hook->user` is a mutable object) — used
  to keep the legal first and last name for verified users. This also covers OAuth2 profile sync on login.
- `\core_user\hook\before_user_deleted` — decides what happens to identity data when an account is deleted.
- Profile page: legacy callback `<component>_myprofile_navigation()` (lib/myprofilelib.php).
- Moodle has no per-user field lock, only per auth method (`field_lock_*`). A per-user lock must be done with the
  hook above.

## 6. Coursebuilder conventions followed

`@copyright 2026 Andres`, GPL v3 or later, `requires = 2026042000`, `MATURITY_ALPHA`, semver `0.x` releases,
research notes in `docs/`, one master plan file with status, decisions and progress, and a build script
producing ZIPs in `dist/`.
