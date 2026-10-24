#!/usr/bin/env python3
"""Build the small, separate source-comparison kit without changing release ZIPs."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
UPSTREAM = 'a7511ad57e65b64d603e9cb68a20359ad0a3a4e6'
contents = {'sakura-display-diff.php': (ROOT / 'tools/sakura-display-diff.php').read_bytes(),
            'LICENSE': (ROOT / 'LICENSE').read_bytes()}
hashes = {}
for source, name in [('ft2.php', 'index.php'), ('css/ft.css', 'ft.css')]:
    data = subprocess.check_output(['git', '-C', str(ROOT), 'show', f'{UPSTREAM}:{source}'])
    contents['upstream/' + name] = data
    hashes[name] = hashlib.sha256(data).hexdigest()
contents['diff-baseline.json'] = (json.dumps({'upstream_commit': UPSTREAM, 'files': hashes}, indent=2) + '\n').encode()
target = Path(sys.argv[1])
target.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(target, 'x', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for path, value in sorted(contents.items()):
        info = zipfile.ZipInfo(path, (2026, 10, 24, 0, 0, 0))
        info.create_system = 3
        info.external_attr = 0o100644 << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        z.writestr(info, value, compresslevel=9)
print(hashlib.sha256(target.read_bytes()).hexdigest(), target.name)
