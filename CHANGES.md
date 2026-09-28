# Changes

## 1.0.0-rc2 (2026-09-28)

Only `local_idverify` changes (2026092803); `customcertelement_idverify` and `availability_idverify` stay 1.0.0-rc1.

- Legal reference for the lawful basis in the privacy metadata (en, et), the code comments and README §8: the Adult
  Education Act (*Täiskasvanute koolituse seadus*) and the Continuing Education Standard (*Täienduskoolituse
  standard*, RT I, 22.04.2025, 2): name and personal code, or date of birth, on the certificate and attestation.
- README §8: the three-year retention of the certificate register (in force since 01.04.2025) as a review point for
  the data protection officer.

## 1.0.0-rc1 (2026-09-28): release candidate

The three plugins are released together and require each other's 1.0.0-rc1 versions:
`local_idverify` 2026092802, `customcertelement_idverify` 2026092801, `availability_idverify` 2026092801.
Moodle 5.2+, `mod_customcert` 5.2.8+ for the element.

### local_idverify
- Identity verification with Smart-ID, ID card and Mobile-ID through eID Easy (OAuth 2.0; test and production
  environments). Only these three login methods are accepted.
- Storage: the isikukood is encrypted with `\core\encryption`; a keyed hash (`$CFG->local_idverify_hmackey`) links
  one identity to one account; legal name, date of birth, method and provider. The Estonian checksum is validated.
- Conflict handling: an identity already linked to another account is refused and logged; no merging.
- Gating: `idverified` profile field (locked, hidden), kept in sync in the database and the session.
- Legal name: the account's first and last name become the legal name and are locked (setting).
- My identity page with the code masked; a configurable introduction (HTML, filtered).
- Profile page: an "Identity verification" line in User details, for the owner and identity managers only.
- Administration (*Users → Verified identities*): list and search, manual verification from a document (with a
  required note), the full code only after a logged "Show code", revoke (ends the user's sessions).
- Privacy provider: export with the decrypted code; deletion keeps identities printed on issued certificates and logs
  `identity_retained` (legal obligation); account deletion follows the same rule.
- Events: `identity_verified`, `identity_revoked`, `identity_retained`, `verification_conflict`, `verification_failed`,
  `idcode_viewed`, `idcode_decrypted`. They never contain the code or the name.
- Mock provider for development (developer debugging only). Status check for the HMAC key.

### customcertelement_idverify
- Certificate element "Verified identity": prints the isikukood (the date of birth for manual identities without a
  code) or the legal name. Previews show a placeholder; unverified learners get nothing. Every decryption is logged.
- Issued certificates that print the identity are recorded (`local_idverify_issued`) for the retention rule.

### availability_idverify
- Access restriction "Identity verified", with a link to the verification page in its message.
- Fails open: ignored when the plugin is missing or disabled, and lets everyone through while verification does
  not work (no HMAC key or no provider). Teachers can still issue certificates by hand.

### Known open items
- The section number of the Continuing Education Standard that lists the certificate contents is still to be confirmed
  from Riigi Teataja (the act and its RT reference are in place since 1.0.0-rc2).
- Tested live against eID Easy's **test** environment with Smart-ID only. ID card, Mobile-ID and the production
  environment are covered by automated tests with recorded responses and still need a live check on the live site.
