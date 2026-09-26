# Identity verification (`local_idverify`)

Estonian eID identity verification (Smart-ID, ID card, Mobile-ID via eID Easy) for Moodle 5.2+. Stores the
learner's verified legal name and isikukood (encrypted) so the course certificate ("tunnistus") can print them.

| Folder | What |
|---|---|
| `local/idverify/` | Moodle plugin `local_idverify` |
| `build.py` | Builds installable ZIPs into `dist/` (set `PHP_BIN` to lint with `php -l` first) |
| `.github/workflows/ci.yml` | moodle-plugin-ci: phpcs, phpdoc, validate, PHPUnit on Moodle 5.2 |

Status: plan awaiting approval, see [IDVERIFY_PLAN.md](IDVERIFY_PLAN.md) and [docs/STEP0-research.md](docs/STEP0-research.md).

Secrets (HMAC key, eID Easy client secret) never go in this repository.
