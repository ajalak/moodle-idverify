# Local development environment

Everything lives in `D:\MoodleDev` (outside this repository). No admin rights, services or Docker.

| Part | Where | Notes |
|---|---|---|
| PHP 8.3 (ZTS, x64) | `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe` | `winget install --id PHP.PHP.8.3 --scope user`; `php.ini` enables curl, exif, fileinfo, gd, intl, mbstring, openssl, pgsql, pdo_pgsql, soap, sodium, zip, opcache; CA bundle from Git for Windows |
| PostgreSQL 17 (portable) | `D:\MoodleDev\pgsql`, data in `D:\MoodleDev\pgdata` | EDB binaries zip; localhost only, trust auth. Start after reboot: `D:\MoodleDev\start-db.ps1` |
| Moodle 5.2.3 | `D:\MoodleDev\moodle` (git tag `v5.2.3`, `public/` layout) | `config.php` in the root: developer debugging, dev-only `$CFG->local_idverify_hmackey` |
| mod_customcert | `D:\MoodleDev\moodle\public\mod\customcert` | branch `MOODLE_502_STABLE` |
| moodledata / PHPUnit data | `D:\MoodleDev\moodledata`, `D:\MoodleDev\phpunitdata` | PHPUnit prefix `phpu_` |
| phpcs + moodle-cs | `D:\MoodleDev\tools\vendor\bin\phpcs` | standard `moodle` |
| Admin login | `D:\MoodleDev\dev-credentials.txt` | local file only |

The plugin is linked into Moodle with a directory junction (no admin rights needed):

```
mklink /J D:\MoodleDev\moodle\public\local\idverify D:\Projects\moodle-idverify\local\idverify
```

PHP resolves `__DIR__` through the junction to the real path, so a page's `require('../../config.php')` lands in
the repository root. A gitignored stub `config.php` there loads the dev site's config:

```php
<?php
require('D:/MoodleDev/moodle/config.php');
```

Dev site settings: provider `mock` (`php admin/cli/cfg.php --component=local_idverify --name=provider --set=mock`).
Test accounts (admin, learner1) are in `D:\MoodleDev\dev-credentials.txt`.

Web server: `php -S localhost:8000 -t D:\MoodleDev\moodle\public` (Claude Code launch config `moodle-dev`),
then http://localhost:8000.

Common commands (from `D:\MoodleDev\moodle`):

```
php admin/cli/upgrade.php --non-interactive          # after version.php bumps
php admin/cli/purge_caches.php
php public/admin/tool/phpunit/cli/init.php           # after install.xml / version changes
vendor/bin/phpunit --testsuite local_idverify_testsuite
D:\MoodleDev\tools\vendor\bin\phpcs --standard=moodle D:\Projects\moodle-idverify\local\idverify
```

Build installable ZIPs: `set PHP_BIN=<php.exe>` then `python build.py` (lints with `php -l` first).
