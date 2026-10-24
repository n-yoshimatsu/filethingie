#!/usr/bin/env python3
"""Build a reproducible deployment candidate and a separate CLI backup kit.

Only git blobs from the accepted application commit enter the application ZIP.
The backup kit contains no installed configuration or managed user data.
"""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE = '3ab04adb323c1f5fe2d8b1cd144b6cbb3d0a1eb4'
UPSTREAM = 'a7511ad57e65b64d603e9cb68a20359ad0a3a4e6'
ROOT_FILES = ('ft2.php', 'hooks.php', 'config.sample.php', 'LICENSE')
ASSET_DIRS = ('css', 'js', 'locales', 'plugins')


def git(*args):
    return subprocess.check_output(['git', '-C', str(ROOT), *args])


def digest(data):
    return hashlib.sha256(data).hexdigest()


def encoded(value):
    return (json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + '\n').encode()


def sources(commit):
    paths = git('ls-tree', '-r', '--name-only', commit, '--', *ROOT_FILES, *ASSET_DIRS).decode().splitlines()
    return {('index.php' if p == 'ft2.php' else p): git('show', f'{commit}:{p}') for p in paths}


def archive(path, contents):
    # Exclusive output creation: an earlier package cannot be silently replaced.
    with zipfile.ZipFile(path, 'x', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for name, data in sorted(contents.items()):
            entry = zipfile.ZipInfo(name, (2026, 10, 24, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            entry.compress_type = zipfile.ZIP_DEFLATED
            z.writestr(entry, data, compresslevel=9)


def build(output):
    output.mkdir(parents=True, exist_ok=True)
    app = sources(SOURCE)
    manifest = {'source_commit': SOURCE, 'status': 'candidate; production migration not yet verified',
                'entrypoint': 'ft2.php copied byte-for-byte as index.php',
                'files': {p: digest(b) for p, b in sorted(app.items())}}
    candidate = {'app/' + p: b for p, b in app.items()}
    candidate['manifest.json'] = encoded(manifest)
    candidate['DEPLOYMENT.md'] = (ROOT / 'docs/STAGE6_PACKAGE.md').read_bytes()
    candidate['DEPENDENCIES.md'] = (ROOT / 'docs/DEPENDENCIES.md').read_bytes()
    archive(output / 'filethingie-php83-85-candidate.zip', candidate)

    # Upstream hashes permit local comparison without uploading installed source.
    baseline = {'upstream_commit': UPSTREAM, 'files': {
        p: {'sha256': digest(b), 'lf_sha256': digest(b.replace(b'\r\n', b'\n'))}
        for p, b in sorted(sources(UPSTREAM).items())}}
    kit = {p: (ROOT / 'tools' / p).read_bytes() for p in ['sakura-backup.php', 'sakura-inventory.php']}
    kit['upstream-manifest.json'] = encoded(baseline)
    kit['BACKUP.md'] = (ROOT / 'docs/STAGE6_BACKUP.md').read_bytes()
    kit['LICENSE'] = (ROOT / 'LICENSE').read_bytes()
    archive(output / 'sakura-backup-kit.zip', kit)
    (output / 'candidate-manifest.json').write_bytes(encoded(manifest))
    checksums = ''.join(digest((output / name).read_bytes()) + '  ' + name + '\n' for name in
                        ['filethingie-php83-85-candidate.zip', 'sakura-backup-kit.zip'])
    (output / 'SHA256SUMS').write_text(checksums)
    print(json.dumps({'application_files': len(app), 'source_commit': SOURCE, 'sha256sums': checksums}))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('output', type=Path)
    build(parser.parse_args().output)
