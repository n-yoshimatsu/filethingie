#!/usr/bin/env python3
"""Package cutover operations with fixed accepted manifests; no site data."""
import argparse
import hashlib
from pathlib import Path
import zipfile
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('output', type=Path)
args = parser.parse_args()
root = Path(__file__).resolve().parent.parent
source = root / 'docs/verification/stage6/2026-10-24/sakura-rehearsal-relative-dir.zip'
backup = root / 'docs/verification/stage6/2026-10-24/sakura-backup-kit.zip'
for p, expected in [(source, '92a2942c441b766360000218a118bdeed4506b6a724acb35b2370bcfa9984c8a'), (backup, '6f3e6705bcb5aa298f11405a2fac985f21b33dd841aa3abb514dd7a371fd969a')]:
    if hashlib.sha256(p.read_bytes()).hexdigest() != expected:
        raise SystemExit('Fixed input ZIP checksum mismatch.')
with zipfile.ZipFile(source) as z:
    files = {n: z.read(n) for n in z.namelist() if n.startswith('app/') or n == 'rehearsal-manifest.json'}
with zipfile.ZipFile(backup) as z:
    files['upstream-manifest.json'] = z.read('upstream-manifest.json')
for n in ['sakura-cutover.php', 'sakura-cutover-prepare.php', 'sakura-rehearsal-verify.php', 'sakura-rehearsal.php', 'sakura-backup.php', 'sakura-inventory.php']:
    files[n] = (root / 'tools' / n).read_bytes()
with zipfile.ZipFile(args.output, 'x', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for n, data in sorted(files.items()):
        info = zipfile.ZipInfo(n, (2026, 10, 25, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100600 << 16
        z.writestr(info, data)
print(hashlib.sha256(args.output.read_bytes()).hexdigest(), args.output.name)
