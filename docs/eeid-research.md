# eeID — research notes (2026-09-30)

eeID is the identification service of the Estonian Internet Foundation (EIS, Eesti Interneti SA). It replaced eID
Easy in `local_idverify` 1.0.0-rc3.

Sources:
- Documentation: https://internetee.github.io/eeID-DOC/ (sections Getting Started, eeID Authentication, PKCE,
  Protection, Endpoints and timeouts, Testing, Embedded sign-in)
- Test discovery document: https://test-auth.eeid.ee/hydra-public/.well-known/openid-configuration (fetched
  2026-09-30)
- Pricing: https://eeid.ee/pricing
- Terms of use / subscription agreement: https://meedia.internet.ee/files/Terms_of_use_eeID.pdf (entered into force
  31.08.2021)

## Protocol

OpenID Connect, authorization code flow only (Ory Hydra behind `/hydra-public`).

| Endpoint | Test | Production |
|---|---|---|
| Discovery | `https://test-auth.eeid.ee/hydra-public/.well-known/openid-configuration` | `https://auth.eeid.ee/hydra-public/.well-known/openid-configuration` |
| Authorize | `…/hydra-public/oauth2/auth` | same path on `auth.eeid.ee` |
| Token | `…/hydra-public/oauth2/token` | same |
| Userinfo | `…/hydra-public/userinfo` (not used by the plugin) | same |
| JWKS | `…/hydra-public/.well-known/jwks.json` | same |

- **Authorize parameters:**
  - required: `client_id`, `redirect_uri`, `response_type=code`, `scope` (must match the scope registered for the
    service; the plugin uses `openid` only), `state` (compulsory, at least 8 characters, bound to the session);
  - optional: `ui_locales` (et / en / ru), `country` (ISO code, e.g. `EE`), `nonce`, and PKCE `code_challenge`
    with `code_challenge_method=S256`.
- **Redirect back:** `?code=…&state=…`, or `?error=…&error_description=…&state=…`. `access_denied` is also used by
  the age restriction.
- **Token request:** POST, `client_secret_basic` (HTTP Basic `client_id:secret`) for confidential clients.
  - body: `grant_type=authorization_code`, `code`, `redirect_uri`, plus `code_verifier` when PKCE was used;
  - the authorization code is valid for **30 s**.
- **Token response:** `access_token`, `token_type`, `expires_in`, `id_token` (JWS).
- **Discovery (test, 2026-09-30):**
  - `issuer` is **`https://test-auth.eeid.ee/hydra-public/`**. The documentation's example token shows
    `https://auth.eeid.ee`, so the plugin checks the issuer against the discovery document, not a constant;
  - `id_token_signing_alg_values_supported` is `RS256`;
  - `token_endpoint_auth_methods_supported` is `client_secret_basic`, `none`;
  - `scopes_supported` is `openid`, `webauthn`, `phone`, `email`.
  - The real test JWKS (one RSA key, `alg` RS256, `use` sig) parses with Moodle core's `Firebase\JWT\JWK` (checked
    on the dev site).

## ID token claims used

| Claim | Example | Use |
|---|---|---|
| `sub` | `EE60001019906` | Country prefix + personal code |
| `profile_attributes.given_name` / `family_name` | `MARY ÄNN` / `O’CONNEŽ-ŠUSLIK TESTNUMBER` | Legal name, kept as given (capitals) |
| `profile_attributes.date_of_birth` | `2000-01-01` | Birth date. Smart-ID does not provide it (documentation, "Age restriction"); the plugin then takes it from the Estonian code |
| `amr` | `mID`, `idcard`, `smartid`, `eIDAS` (and passkey logins) | Only `idcard`, `mID`, `smartid` accepted |
| `acr` | `low` / `substantial` / `high` | Logged with `identity_verified`, not enforced |
| `iss`, `aud`, `exp`, `nbf`, `iat`, `nonce` | | Checked (60 s clock leeway) |

Passkeys (`webauthn` scope) can rest on an earlier verification by Veriff (AI document check), so they are not
accepted for the certificate's legal identity.

## Test users (Test environment)

- **Smart-ID:** EE `39901012239`; also LV `050405-10009`, LT `40504040001`, BE `05040400032`. The Smart-ID demo app
  can be used as well.
- **Mobile-ID:** EE phone `68000769` with code `60001017869`; LT `60000666` / `50001018865`. SK's demo numbers in
  general: https://github.com/SK-EID/MID/wiki/Test-number-for-automated-testing-in-DEMO (EE only).
- **eIDAS (Czech Republic):** "Testovací profily". Refused by the plugin.

## Service registration (eeID Manager at eeid.ee)

- **Account:** a personal account (free), or an organisation owning the services.
- **New service:**
  - type *Authentication*; name (with translations);
  - redirection URLs, HTTPS except `localhost`;
  - environment *Test* (free) or *Production*;
  - scope; methods; consent screen skip; logo; optional age restriction.
- **Approval:** EIS reviews the service and then issues the client ID and secret. A service exists in one
  environment only.
- **Embedded sign-in** (iframe widget, needs the client secret on the server and allowlisted origins) exists. The
  plugin uses the redirect flow.

## Pricing and terms

- **Price:** prepaid, no package fee. 0.08 € per ID card / Mobile-ID / Smart-ID authentication, 0.01 € per passkey,
  0.10 € per document verification (price list in force from 01.04.2026). Test authentications are free.
- **Terms of use** (subscription agreement between EIS and the customer, signed digitally):
  - **4.2, customer obligations:** protect the secret; announce significant volume increases 48 h ahead; pay;
    no redistribution without consent; report incidents within 24 h.
  - **6.4:** EIS logs time, IP, method and errors for three months.
  - **7.3.4:** when the prepaid amount runs out, the service may be suspended without notice.
  - Annex 2 (data protection conditions) and Annex 3 (price list) are not in the published PDF.
