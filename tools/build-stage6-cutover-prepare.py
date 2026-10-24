#!/usr/bin/env python3
"""Package private cutover preparation with the already accepted application bytes."""
import argparse
import hashlib
from pathlib import Path
import zipfile
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('output', type=Path, help='New ZIP path')
args = parser.parse_args()
root = Path(__file__).resolve().parent.parent
source = root / 'docs/verification/stage6/2026-10-24/sakura-rehearsal-relative-dir.zip'
if hashlib.sha256(source.read_bytes()).hexdigest() != '92a2942c441b766360000218a118bdeed4506b6a724acb35b2370bcfa9984c8a':
    raise SystemExit('Accepted rehearsal kit checksum mismatch.')
with zipfile.ZipFile(source) as original:
    files = {n: original.read(n) for n in original.namelist() if n.startswith('app/') or n == 'rehearsal-manifest.json'}
for name in ['sakura-cutover-prepare.php', 'sakura-rehearsal-verify.php', 'sakura-rehearsal.php', 'sakura-backup.php', 'sakura-inventory.php']:
    files[name] = (root / 'tools' / name).read_bytes()
with zipfile.ZipFile(args.output, 'x', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as out:
    for name, data in sorted(files.items()):
        info = zipfile.ZipInfo(name, (2026, 10, 24, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100600 << 16
        out.writestr(info, data)
print(hashlib.sha256(args.output.read_bytes()).hexdigest(), args.output.name)
