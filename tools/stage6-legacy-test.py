#!/usr/bin/env python3
"""Run cutover checks in a disposable localhost-only Apache/PHP container."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import time

ROOT = Path(__file__).resolve().parents[1]
p = argparse.ArgumentParser(description=__doc__)
p.add_argument('service', choices=['php83', 'php85'])
p.add_argument('suite', choices=['baseline', 'legacy'])
p.add_argument('--kit', type=Path, required=True, help='Extracted build-stage6-cutover.py kit inside repository')
p.add_argument('--output', type=Path, required=True)
a = p.parse_args()
out = a.output.resolve()
out.mkdir(parents=True, exist_ok=False)
kit = '/workspace/' + a.kit.resolve().relative_to(ROOT).as_posix()
name = f'ft-cutover-{os.getpid()}-{a.service}-{a.suite}'
records = []

def run(args, **kwargs):
    return subprocess.run(args, check=True, capture_output=True, text=True, timeout=180, **kwargs)

def wait_http():
    for attempt in range(50):
        result = subprocess.run(['docker', 'exec', name, 'php', '-r',
                                 'exit(@fsockopen("127.0.0.1",443)?0:1);'], capture_output=True)
        if result.returncode == 0:
            return
        time.sleep(0.2)
    raise RuntimeError('Local Apache did not start')

def case(script, label, *args):
    result = subprocess.run(['docker', 'exec', '--user', 'www-data', name, 'php',
                             '/workspace/tools/' + script, *args], capture_output=True, text=True, timeout=180)
    (out / (label + '.stdout')).write_text(result.stdout)
    (out / (label + '.stderr')).write_text(result.stderr)
    record = json.loads(result.stdout)
    (out / (label + '.json')).write_text(json.dumps(record, ensure_ascii=False, indent=2) + '\n')
    records.append({'label': label, 'case_id': record.get('case_id'), 'verdict': record.get('verdict'),
                    'checks': len(record.get('checks', [])), 'exit_code': result.returncode})
    if result.returncode or record.get('verdict') != 'PASS':
        raise RuntimeError(label + ' did not pass')

started = False
error = None
try:
    image = 'filethingie-stage2-' + a.service + ':latest'
    image_id = run(['docker', 'image', 'inspect', '--format', '{{.Id}}', image]).stdout.strip()
    conf = '''Alias /app /tmp/ft-rehearsal-local/home/www/app
<Directory /tmp/ft-rehearsal-local/home/www>
    AllowOverride All
    Require all granted
</Directory>
'''
    config = out / 'apache.conf'
    config.write_text(conf)
    run(['docker', 'run', '-d', '--network', 'none', '--name', name,
         '-v', str(ROOT) + ':/workspace:ro',
         '-v', str(config) + ':/etc/apache2/conf-enabled/ft-cutover-test.conf:ro', image])
    started = True
    wait_http()
    case('stage6-rehearsal-test.php', 'fixture', kit, '/tmp/ft-rehearsal-local', 'https://localhost/app/', './files')
    if a.suite == 'baseline':
        case('stage6-cutover-prepare-check.php', 'prepare', kit)
        case('stage6-cutover-test.php', 'cutover', kit)
    else:
        case('stage6-legacy-cutover-test.php', 'legacy-cutover', kit)
except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as e:
    error = str(e)
    if isinstance(e, subprocess.CalledProcessError):
        (out / 'setup-error.log').write_text((e.stdout or '') + (e.stderr or ''))
finally:
    if started:
        logs = subprocess.run(['docker', 'logs', name], capture_output=True, text=True)
        (out / 'apache.log').write_text(logs.stdout + logs.stderr)
        subprocess.run(['docker', 'rm', '-f', name], check=True, capture_output=True)
    verdict = 'PASS' if error is None else ('FAIL' if any(r['verdict'] == 'FAIL' for r in records) else 'BLOCKED')
    result = {'service': a.service, 'suite': a.suite, 'image_id': locals().get('image_id'),
              'records': records, 'error': error, 'verdict': verdict,
              'source_hashes': {n: hashlib.sha256((ROOT / 'tools' / n).read_bytes()).hexdigest()
                                for n in ['sakura-cutover.php', 'sakura-cutover-prepare.php', 'stage6-legacy-cutover-test.php']},
              'scope': '使い捨てLinux環境。実機PHP版の切替と旧本番アプリの稼働復旧は対象外'}
    (out / 'result.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps(result, ensure_ascii=False))
raise SystemExit(0 if error is None else 1)
