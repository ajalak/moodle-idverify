# Identity verification for Moodle (`local_idverify`)

Learners verify their identity once with **Smart-ID, ID card or Mobile-ID** through
[eID Easy](https://eideasy.com). The plugin stores their legal name and personal identification code
(isikukood), encrypted, and a certificate element prints them on the course certificate (tunnistus), as Estonian
adult education rules require. Login is not affected: verification is an attribute of an existing account (email
self-registration, Google or Microsoft).

| Folder | What |
|---|---|
| `local/idverify/` | Moodle plugin `local_idverify`: verification flow, storage, admin pages, privacy |
| `mod/customcert/element/idverify/` | Certificate element `customcertelement_idverify` for `mod_customcert` |
| `availability/condition/idverify/` | Access restriction `availability_idverify` ("Identity verified"), which gates the certificate |
| `releases/` | The current installable ZIPs of the three plugins (download without Python) |
| `build.py` | Builds the three ZIPs into `dist/` and copies them to `releases/` |
| `docs/` | [DEVELOPMENT.md](docs/DEVELOPMENT.md) (local dev site), [STEP0-research.md](docs/STEP0-research.md) (API findings, test accounts) |
| `CHANGES.md` | [What each release contains](CHANGES.md) and known open items |
| `IDVERIFY_PLAN.md` | Plan, decisions log |

**Current release: 1.0.0-rc1** (release candidate, 2026-09-28): `local_idverify` 2026092802,
`customcertelement_idverify` 2026092801, `availability_idverify` 2026092801. Install and upgrade the three together.

Requirements: Moodle 5.2+, `mod_customcert` 5.2.8+ (for the element), PHP `sodium` (standard in Moodle 5.2).

## Sites

| Site | Role | eID Easy |
|---|---|---|
| b5.ee | Sandbox; no real services connected | Environment *Test* with the public sandbox credentials, test identities only (or provider *Disabled*) |
| kera.ee | Live site | Environment *Production* with its own eID Easy registration (redirect URI `https://kera.ee/local/idverify/callback.php`) |

Each site has **its own HMAC key** and its own backups of that key and `moodledata/secret/`. Never copy verified
identities between sites: the stored codes are tied to each site's keys. On b5.ee they are test data anyway.

## 1. Install

1. Download the ZIPs from [`releases/`](releases/) (signed in to GitHub, open each file, then *Download raw file*):
   `local_idverify-<release>.zip`, `customcertelement_idverify-<release>.zip` and
   `availability_idverify-<release>.zip`. Or build them with `python build.py`.
2. *Site administration → Plugins → Install plugins*: install **local_idverify first**, then the element and the
   restriction. Without ZIP installs: upload the folders by FTP to `public/local/idverify`,
   `public/mod/customcert/element/idverify` and `public/availability/condition/idverify`, then open
   *Site administration → Notifications*.
3. The install creates the custom profile field **Identity verified** (`idverified`, checkbox, locked, not
   visible on profiles). The upgrade page also lists LDAP, Shibboleth and external database settings for this
   field. Leave them at their defaults.
4. Leave *Identity provider* on **Disabled** until steps 2 and 3 below are done.

## 2. Keys in config.php (required)

Two keys protect the data. **Back up both**, outside the server.

| Key | Where | What it does | If lost |
|---|---|---|---|
| HMAC key | `$CFG->local_idverify_hmackey` in `config.php` | Keyed hash of each code, so one identity can be linked to only one account | Duplicate detection breaks for identities stored so far |
| Moodle encryption key | `moodledata/secret/key/sodium.key` (or `$CFG->secretdataroot`) | Encrypts the stored codes (`\core\encryption`) | **Every stored code becomes unreadable**; certificates print without it |

Generate the HMAC key once (at least 32 bytes; this gives 48 random bytes):

```bash
php -r "echo base64_encode(random_bytes(48)), PHP_EOL;"
```

Add it to `config.php` (in Moodle 5.1+ the one **outside** `public/`), above the `require_once(... '/lib/setup.php')`
line:

```php
$CFG->local_idverify_hmackey = '<the generated key>';
// Stop Moodle from silently creating a new encryption key if moodledata/secret goes missing.
// Set this only once moodledata/secret/key/sodium.key exists.
$CFG->nokeygeneration = true;
```

- Never change the HMAC key after the first verification.
- Without the key, or with one shorter than 32 bytes, verification is refused. Admins then see a warning on the
  settings page and in *Reports → System status*.
- Never commit either key, and never send them by email.

## 3. eID Easy

1. Sign up at https://id.eideasy.com, go to *My Webpages → Register new webpage*, and register the redirect URI
   **`https://<your site>/local/idverify/callback.php`** (the settings page shows the exact URI). Enable only
   **Estonian Smart-ID, ID card and Mobile-ID**. The plugin refuses other login methods (e.g. Google) anyway.
2. *Site administration → Plugins → Local plugins → Identity verification*:
   - **Environment:** *Production* (`id.eideasy.com`)
   - **Client ID** and **Client secret** from eID Easy
   - **Identity provider:** *eID Easy*
   - **Allowed countries:** `EE`
   - **Use legal name:** on, so the account's first and last name become the legal name and are locked
3. Test first with Environment *Test* (`test.eideasy.com`). It uses the public sandbox client id and secret from
   https://docs.eideasy.com/guide/test-environment.html, and only test identities work there. The Smart-ID test
   account **`40404040009`** approves automatically. See [docs/STEP0-research.md](docs/STEP0-research.md) §2 for
   more accounts; the code `30303039914` on eID Easy's own page no longer works.

## 4. Put the code on the certificate

1. Edit the certificate template. For coursebuilder courses this is the site template **CB sertifikaat**, under
   *Site administration → Plugins → Activity modules → Custom certificate → Manage templates*.
2. Add a **Text** element "Isikukood:" and a **Verified identity** element with *Show: Personal identification
   code*. A second Verified identity element with *Show: Legal name* can replace the Student name element.
   - Previews show a placeholder (`12345678901`).
   - Learners without a verified identity get nothing printed.
   - A manual identity without a code prints the date of birth instead of the code.
3. Use a Unicode font, e.g. FreeSans, for Estonian letters.

## 5. Restrict the certificate to verified learners

On the certificate activity: *Restrict access → Add restriction → **Identity verified*** (`availability_idverify`).
The JSON form is `{"type":"idverify"}`; the course builder adds it automatically to the certificates it builds. It
combines with the other restrictions (e.g. course completion) with AND.

Learners who are not verified see *"Not available unless: your identity is verified (click here to verify)"*, in
Estonian *"Pole saadaval, kui: sinu isik on tuvastatud (kliki siin tuvastamiseks)"*. The link opens *My identity*.
The check reads the database, so it works straight after a self or manual verification.

**Fails open by design:** certificates are issued as usual when

- `availability_idverify` is not installed, or is disabled under *Site administration → Plugins → Availability
  restrictions → Manage restrictions* (Moodle ignores the condition);
- identity verification does not work on the site (no valid HMAC key, or *Identity provider* is Disabled). The
  restriction then passes everyone; teachers see "(not enforced at the moment …)" next to it.

The restriction covers every normal issuing path of customcert: viewing the activity, the mobile app, and
automatic issuing and emailing. **Teachers can still issue a certificate by hand** (by marking the certificate
activity complete for a learner), whether or not the learner is verified. The element then prints nothing for
an unverified learner.

Alternative without the restriction plugin: *User profile → **Identity verified** → **is equal to** → `1`*
(`{"type":"profile","cf":"idverified","op":"isequalto","v":"1"}`). This does not fail open. It reads the flag from the
learner's session, so a manually verified learner must open *My identity* or log in again first.

## 6. Where learners verify

- User menu: **Verify identity** (**My identity** once verified).
- Profile page, **User details**: a line **Identity verification**.
  - Unverified: "Identity not verified" with **Verify now**. Hidden while verification does not work on the site.
  - Verified: "Verified on <date> (Smart-ID)" or "Verified by an administrator on <date>", with a link to *My
    identity*. The code and legal name are never shown there.
  - Only the profile owner sees it. Identity managers (`local/idverify:manage`) also see it on other profiles,
    with a link to the admin view.
- Page: `/local/idverify/index.php`. It shows the status, the code masked (`4xxxxxx0009`), the method and the
  date. The introduction above the button is set under *Settings → Introduction on My identity* (empty = the
  default text; the multi-language content filter works there).
- Course callout: add a text-and-media area (label) with this HTML:

```html
<div class="alert alert-info d-flex flex-wrap align-items-center gap-3" role="note">
  <div class="flex-grow-1"><strong>Tunnistuse saamiseks tuvasta oma isik.</strong><br>
  Smart-ID, ID-kaart või Mobiil-ID – võtab umbes 20 sekundit.</div>
  <a class="btn btn-primary" href="/local/idverify/index.php">Tuvasta kohe</a>
</div>
```

## 7. Administration

*Site administration → Users → Accounts → **Verified identities*** (`/local/idverify/admin/index.php`):

- **List and search.** Codes are shown masked, with the number of certificates issued.
- **Verify manually.** For learners who cannot use eID. Enter the user, country, code (or date of birth), legal
  name, and a required note on how the document was checked. Estonian codes are checksum-checked. An already
  verified user must be revoked first.
- **View.** The full code appears only after pressing **Show code**. Each view is logged.
- **Revoke.** Deletes the identity, clears the flag and logs the user out. Certificates already issued re-render
  without the code; the page warns about this.

| Capability | Default | Allows |
|---|---|---|
| `local/idverify:verifyself` | Authenticated user | Verify own identity |
| `local/idverify:manage` | Manager | Admin pages, manual verification, revoke; changing a verified user's name |
| `local/idverify:viewidcode` | Manager | Show a full code (logged) |

Teachers who can download learners' certificates (`mod/customcert:viewreport`) see the code on the PDF, because
it is part of the certificate. customcert's public verification page never shows it.

## 8. Privacy and retention

- **Lawful basis:** legal obligation (GDPR Art. 6(1)(c)). The certificate must show the legal name and personal
  code. **TODO (site owner):** add the exact act and section to this README and to the privacy notice.
- **Stored:** the code (encrypted), its keyed hash, country, legal name, date of birth, method, provider, time, the
  admin and note for manual verifications, and a register of issued certificates that printed the identity (user,
  certificate, verification code, date).
- **Not stored:**
  - the code in logs, events, URLs or error messages (events carry user ids and reason codes only);
  - the code in grade exports, reports, the profile page or user bulk download (they only see the yes/no flag).
- **Export** (data request): the learner gets their own data, including the decrypted code.
- **Deletion** (privacy request or account deletion):
  - **Never printed on an issued certificate:** everything is deleted.
  - **Printed on an issued certificate:** nothing is deleted automatically. The event *Identity data retained
    (issued certificates)* is logged. The data protection officer decides: after the retention period, revoke the
    identity on the admin page. The next deletion request then also removes the register.
- **eID Easy:** Moodle sends only its client id and a random `state`. The learner authenticates at eID Easy, and
  Moodle receives the code, name and date of birth.

## 9. Events

| Event | When |
|---|---|
| `identity_verified` | Self or manual verification |
| `identity_revoked` | Admin revoke |
| `verification_conflict` | The identity is already linked to another account (nothing stored) |
| `verification_failed` | Cancelled, provider error, invalid code, bad state (the reason code is in the log) |
| `idcode_viewed` | Admin pressed *Show code* |
| `idcode_decrypted` | A certificate PDF printed the code |
| `identity_retained` | Deletion refused because certificates printed the identity |

## Development

Local Moodle 5.2 dev site, tests and code style: [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md). The mock provider
(no external calls) is available only with developer debugging on. CI runs moodle-plugin-ci for both plugins
(`.github/workflows/ci.yml`).
