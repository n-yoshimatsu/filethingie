#!/usr/bin/env python3
"""Compare independent package builds against accepted stage-5 hashes."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import zipfile

root = Path(__file__).resolve().parents[1]
out = Path(sys.argv[1]).resolve()
out.mkdir(parents=True, exist_ok=True)
checks = []


def check(name, actual, expected):
    ok = actual == expected
    checks.append(dict(name=name, actual=actual, expected=expected, verdict='PASS' if ok else 'FAIL'))
    assert ok, name


result = dict(case_id='TS-603', name='配布候補の再現と対象確認', verdict='INCONCLUSIVE')
try:
    for name in ['first', 'second']:
        subprocess.run([sys.executable, str(root / 'tools/build-stage6.py'), str(out / name)], check=True, capture_output=True)
    for name in ['filethingie-php83-85-candidate.zip', 'sakura-backup-kit.zip']:
        a, b = [(out / d / name).read_bytes() for d in ['first', 'second']]
        check(name + 'の再現一致', hashlib.sha256(a).hexdigest(), hashlib.sha256(b).hexdigest())
    accepted = json.loads((root / 'docs/verification/stage5/2026-10-24/preview/manifest.json').read_text())
    expected = {p: h for p, h in accepted['files'].items() if not p.startswith('acceptance-input/')}
    with zipfile.ZipFile(out / 'first/filethingie-php83-85-candidate.zip') as z:
        hashes = {p.removeprefix('app/'): hashlib.sha256(z.read(p)).hexdigest() for p in z.namelist() if p.startswith('app/')}
        check('アプリ46ファイルの段階5固定元との一致', hashes, expected)
        check('manifestの内容一致', json.loads(z.read('manifest.json'))['files'], hashes)
        check('設定・認証・保存データ・検証入力・開発環境の非混入', sorted(z.namelist()), sorted(['app/' + p for p in expected] + ['manifest.json', 'DEPLOYMENT.md', 'DEPENDENCIES.md']))
        check('本体ライセンスの保持', z.read('app/LICENSE'), (root / 'LICENSE').read_bytes())
        check('jQueryライセンスの保持', z.read('app/js/jquery-LICENSE.txt'), (root / 'js/jquery-LICENSE.txt').read_bytes())
    completed = subprocess.run([sys.executable, str(root / 'tools/build-stage6.py'), str(out / 'first')], capture_output=True)
    check('既存パッケージ上書きの拒否', completed.returncode != 0, True)
    result['verdict'] = 'PASS'
except Exception as exc:
    result.update(verdict='FAIL', error=str(exc))
finally:
    # License bytes are compared above; represent them by hash in the JSON record.
    for item in checks:
        for key in ['actual', 'expected']:
            if isinstance(item[key], bytes):
                item[key] = hashlib.sha256(item[key]).hexdigest()
    result['checks'] = checks
    (out / 'result.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({k: v for k, v in result.items() if k != 'checks'}), 'checks=' + str(len(checks)))
sys.exit(0 if result['verdict'] == 'PASS' else 1)
