"""Package the plugins as Moodle-installable ZIPs in dist/.

If the environment variable PHP_BIN points to a php executable, every PHP file is linted first
(`php -l`); the build stops on a syntax error or a deprecation notice.
"""
import os
import re
import subprocess
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).parent
DIST = ROOT / 'dist'

PLUGINS = [
    ('local_idverify', ROOT / 'local' / 'idverify'),
    ('customcertelement_idverify', ROOT / 'mod' / 'customcert' / 'element' / 'idverify'),
]


def release(plugindir: Path) -> str:
    text = (plugindir / 'version.php').read_text(encoding='utf-8')
    return re.search(r"\$plugin->release\s*=\s*'([^']+)'", text).group(1)


def lint(plugindir: Path) -> bool:
    php = os.environ.get('PHP_BIN')
    if not php:
        print('PHP_BIN not set: skipping php -l')
        return True
    ok = True
    for path in sorted(plugindir.rglob('*.php')):
        result = subprocess.run([php, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-l', str(path)],
                                capture_output=True, text=True)
        # php -l prints deprecations to stderr but still exits 0, so any stderr output counts as a failure.
        if result.returncode != 0 or result.stderr.strip():
            ok = False
            print((result.stdout + result.stderr).strip())
    print(f'php -l {plugindir.relative_to(ROOT).as_posix()}: {"ok" if ok else "FAILED"}')
    return ok


def main() -> None:
    DIST.mkdir(exist_ok=True)
    for component, plugindir in PLUGINS:
        if not plugindir.is_dir():
            print(f'{component}: {plugindir.relative_to(ROOT).as_posix()} missing, skipped')
            continue
        if not lint(plugindir):
            sys.exit(1)
        target = DIST / f'{component}-{release(plugindir)}.zip'
        with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED) as zf:
            for path in sorted(plugindir.rglob('*')):
                if path.is_file():
                    # Moodle expects a single top-level folder named after the plugin ("idverify/...").
                    zf.write(path, (plugindir.name / path.relative_to(plugindir)).as_posix())
        print(target.relative_to(ROOT).as_posix())


if __name__ == '__main__':
    main()
