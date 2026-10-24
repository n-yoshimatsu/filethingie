#!/usr/bin/env python3
"""Build the PHP 8.3/8.5 release from the application accepted in production.

Never includes a deployed config, Basic credentials, user files, or test data.
"""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE = 'e02ca328c9668f0e4ce1374751f25647fdaa57f6'
RELEASE = 'php83-85-20261025'
ARCHIVE = 'filethingie-' + RELEASE + '.zip'


def digest(data):
    return hashlib.sha256(data).hexdigest()


def encoded(data):
    return (json.dumps(data, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()


def blob(name):
    return subprocess.check_output(['git', '-C', str(ROOT), 'show', SOURCE + ':' + name])


def build(output):
    evidence = ROOT / 'docs/verification/stage6/2026-10-25/sakura-production-acceptance.json'
    accepted = json.loads(evidence.read_text())
    if accepted['SA-608']['verdict'] != 'PASS' or accepted['SA-609']['verdict'] != 'PASS':
        raise SystemExit('Completed production acceptance is required.')
    fixed = ROOT / 'docs/verification/stage6/2026-10-24/sakura-rehearsal-relative-dir.zip'
    if digest(fixed.read_bytes()) != '92a2942c441b766360000218a118bdeed4506b6a724acb35b2370bcfa9984c8a':
        raise SystemExit('Accepted application archive mismatch.')
    with zipfile.ZipFile(fixed) as z:
        expected = json.loads(z.read('rehearsal-manifest.json'))['files']
    app = {name: blob('ft2.php' if name == 'index.php' else name) for name in expected}
    if len(app) != 46 or any(digest(app[n]) != h for n, h in expected.items()):
        raise SystemExit('Application differs from the accepted release.')
    payload = {'app/' + name: data for name, data in app.items()}
    payload['INSTALL.ja.md'] = (ROOT / 'docs/RELEASE_GUIDE.md').read_bytes()
    payload['DEPENDENCIES.md'] = (ROOT / 'docs/DEPENDENCIES.md').read_text().replace('(../LICENSE)', '(LICENSE)').encode()
    payload['LICENSE'] = blob('LICENSE')
    payload['tools/hash-password.php'] = blob('tools/hash-password.php')
    manifest = {'release': RELEASE, 'application_source_commit': SOURCE,
                'upstream_application_version': '2.5.7', 'application_files': expected,
                'entrypoint': 'ft2.php copied byte-for-byte as app/index.php',
                'validated_php': ['8.3.36', '8.5.12'], 'sakura_production_php': '8.3.36',
                'files': {n: digest(b) for n, b in sorted(payload.items())}}
    payload['manifest.json'] = encoded(manifest)
    output.mkdir(parents=True, exist_ok=False)
    with zipfile.ZipFile(output / ARCHIVE, 'x', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for name, data in sorted(payload.items()):
            info = zipfile.ZipInfo(name, (2026, 10, 25, 0, 0, 0))
            info.create_system = 3
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            z.writestr(info, data, compresslevel=9)
    checksum = digest((output / ARCHIVE).read_bytes())
    (output / 'SHA256SUMS').write_text(checksum + '  ' + ARCHIVE + '\n')
    (output / 'manifest.json').write_bytes(encoded(manifest))
    (output / 'RELEASE_NOTES.md').write_bytes((ROOT / 'docs/RELEASE_NOTES.md').read_bytes())
    print(json.dumps({'release': RELEASE, 'application_files': len(app), 'archive': ARCHIVE, 'sha256': checksum}))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('output', type=Path)
    build(parser.parse_args().output)
