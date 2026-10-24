#!/usr/bin/env python3
"""Check release identity, reproducibility and absence of installed data."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import zipfile

root = Path(__file__).resolve().parents[1]
p = argparse.ArgumentParser(description=__doc__)
p.add_argument('output', type=Path)
a = p.parse_args();a.output.mkdir(parents=True, exist_ok=False)
checks = []

def check(name, ok):
    checks.append({'name': name, 'verdict': 'PASS' if ok else 'FAIL'})
    if not ok:
        raise AssertionError(name)

try:
    for directory in ['first', 'second']:
        subprocess.run(['python3', str(root/'tools/build-release.py'), str(a.output/directory)], check=True, capture_output=True)
    name = 'filethingie-php83-85-20261025.zip'
    first = a.output/'first'/name;second = a.output/'second'/name
    check('同じ入力からのZIP再現', first.read_bytes() == second.read_bytes())
    with zipfile.ZipFile(first) as z, zipfile.ZipFile(root/'docs/verification/stage6/2026-10-24/sakura-rehearsal-relative-dir.zip') as baseline:
        expected = json.loads(baseline.read('rehearsal-manifest.json'))['files']
        application = {n[4:]: hashlib.sha256(z.read(n)).hexdigest() for n in z.namelist() if n.startswith('app/')}
        check('受入済み46アプリファイルの完全一致', len(application) == 46 and application == expected)
        check('本体とjQueryのライセンス包含', all(n in z.namelist() for n in ['LICENSE','app/LICENSE','app/js/jquery-LICENSE.txt']))
        check('設定・認証・保存データ・開発環境の非同梱', set(z.namelist()) == set('app/'+n for n in expected) | {'INSTALL.ja.md','DEPENDENCIES.md','LICENSE','tools/hash-password.php','manifest.json'} and 'app/config.php' not in z.namelist())
        manifest = json.loads(z.read('manifest.json'))
        check('全同梱ファイルのmanifest一致', set(manifest['files']) == set(z.namelist())-{'manifest.json'} and all(hashlib.sha256(z.read(n)).hexdigest()==h for n,h in manifest['files'].items()))
        guide = z.read('INSTALL.ja.md').decode()
        check('導入・更新・復元・制約の手順包含', all(s in guide for s in ['## 新規導入','## 既存環境の更新','## 復元','## 主な変更と残る制約']))
    check('外部SHA-256の一致', (a.output/'first/SHA256SUMS').read_text().split()[0] == hashlib.sha256(first.read_bytes()).hexdigest())
    result = subprocess.run(['python3',str(root/'tools/build-release.py'),str(a.output/'first')],capture_output=True)
    check('既存出力への上書き拒否', result.returncode != 0 and first.read_bytes() == second.read_bytes())
    verdict = 'PASS'
except Exception as error:
    verdict = 'FAIL'
    message = str(error)
record = {'case_id':'TS-620','name':'正式配布物の再現性・内容照合','verdict':verdict,'checks':checks}
if verdict != 'PASS': record['error'] = message
(a.output/'result.json').write_text(json.dumps(record,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(record,ensure_ascii=False))
raise SystemExit(0 if verdict == 'PASS' else 1)
