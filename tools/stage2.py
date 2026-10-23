#!/usr/bin/env python3
"""Record one stage-2 case without changing application source or test criteria."""
import argparse
import datetime
import hashlib
import http.client
import json
from pathlib import Path
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
BASELINE = 'a7511ad57e65b64d603e9cb68a20359ad0a3a4e6'


def run(*args):
    return subprocess.run(args, cwd=ROOT, text=True, capture_output=True, timeout=120)


def checked(*args):
    result = run(*args)
    if result.returncode:
        raise RuntimeError(f'{args!r}: {result.stderr or result.stdout}')
    return result.stdout


def compose(*args):
    return checked('docker', 'compose', *args)


def save(path, data):
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def request(port, path):
    # Direct localhost connection: no proxies, redirects, cookies or retry of the app.
    connection = http.client.HTTPConnection('127.0.0.1', port, timeout=15)
    try:
        connection.request('GET', path, headers={'User-Agent': 'filethingie-stage2/1'})
        response = connection.getresponse()
        return response.status, list(response.getheaders()), response.read()
    finally:
        connection.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('case', choices=['lint', 'startup'])
    parser.add_argument('service', choices=['php83', 'php85'])
    parser.add_argument('--output', required=True, type=Path, help='New evidence directory; never overwritten')
    args = parser.parse_args()
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=False)
    service = args.service
    case_id = ('LINT-' if args.case == 'lint' else 'TS-') + ('201' if service == 'php83' else '202')
    record = {'case_id': case_id, 'service': service, 'case': args.case,
              'started_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
              'baseline_commit': BASELINE, 'checkout_commit': checked('git', 'rev-parse', 'HEAD').strip()}
    exit_code = 2
    try:
        source_paths = sorted(['ft2.php', 'config.sample.php', 'hooks.php'] +
                              [str(p.relative_to(ROOT)) for directory in ['plugins', 'locales']
                               for p in (ROOT / directory).rglob('*.php')])
        local_hashes = {p: hashlib.sha256((ROOT / p).read_bytes()).hexdigest() for p in source_paths}
        # Hash the actual container files as well as the checkout files.
        hash_code = '$r=[]; foreach (json_decode($argv[1], true) as $p) {$r[$p]=hash_file("sha256", $p);} echo json_encode($r);'
        container_hashes = json.loads(compose('exec', '-T', service, 'php', '-r', hash_code, json.dumps(source_paths)))
        save(output / 'source-sha256.json', container_hashes)
        record['container_matches_checkout'] = container_hashes == local_hashes
        baseline_files = set(checked('git', 'ls-tree', '-r', '--name-only', BASELINE).splitlines())
        record['application_matches_upstream'] = all(
            p in baseline_files and subprocess.run(
                ['git', 'diff', '--quiet', BASELINE, '--', p], cwd=ROOT, check=False
            ).returncode == 0 for p in source_paths)
        # Fingerprint the reproduction harness, including uncommitted additions.
        harness = [ROOT / 'compose.yaml', ROOT / '.dockerignore', Path(__file__)] + sorted((ROOT / 'dev').rglob('*'))
        save(output / 'harness-sha256.json', {str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest()
                                            for p in harness if p.is_file()})
        container_id = compose('ps', '-q', service).strip()
        inspect = json.loads(checked('docker', 'inspect', container_id))[0]
        record['container_image_id'] = inspect['Image']
        record['port_bindings'] = inspect['HostConfig']['PortBindings']
        record['cli_runtime'] = json.loads(compose('exec', '-T', service, 'php', '__environment.php'))
        if not record['container_matches_checkout']:
            raise RuntimeError('Container source differs from checkout; rebuild required')

        if args.case == 'lint':
            items = []
            for path in source_paths:
                command = ['docker', 'compose', 'exec', '-T', service, 'php', '-d', 'error_reporting=-1',
                           '-d', 'display_errors=stderr', '-d', 'log_errors=0', '-l', path]
                result = run(*command)
                diagnostic = result.stdout + result.stderr
                items.append({'path': path, 'command': command, 'exit_code': result.returncode,
                              'stdout': result.stdout, 'stderr': result.stderr,
                              'syntax_ok': result.returncode == 0,
                              'diagnostic_notice': bool(re.search(r'Deprecated:|Warning:|Notice:', diagnostic))})
            save(output / 'lint.json', items)
            record['files'] = len(items)
            record['syntax_failures'] = sum(not item['syntax_ok'] for item in items)
            record['files_with_notices'] = sum(item['diagnostic_notice'] for item in items)
            record['verdict'] = 'PASS' if not record['syntax_failures'] and not record['files_with_notices'] else 'FAIL'
        else:
            bindings = record['port_bindings']['80/tcp']
            if len(bindings) != 1 or bindings[0]['HostIp'] != '127.0.0.1':
                raise RuntimeError('Expected one localhost-only port binding')
            port = int(bindings[0]['HostPort'])
            status, headers, body = request(port, '/__environment.php')
            (output / 'environment-response.json').write_bytes(body)
            runtime = json.loads(body)
            record['environment_http_status'] = status
            record['environment_ready'] = status == 200 and runtime['sapi'] == 'apache2handler' and runtime['php_version'].startswith('8.3.' if service == 'php83' else '8.5.')
            if not record['environment_ready']:
                raise RuntimeError('Web PHP environment check failed before application request')
            record['request'] = {'method': 'GET', 'url': f'http://127.0.0.1:{port}/ft2.php', 'cookies': None, 'body': None}
            status, headers, body = request(port, '/ft2.php')
            save(output / 'http-headers.json', {'status': status, 'headers': headers})
            (output / 'http-body.html').write_bytes(body)
            text = body.decode('utf-8', errors='replace')
            record['http_status'] = status
            record['login_fields_present'] = all(re.search(r'name=[\"\x27]' + name + r'[\"\x27]', text)
                                                  for name in ['ft_user', 'ft_pass'])
            record['login_form_present'] = bool(re.search(r'<form\b[^>]*\bid=[\"\x27]loginbox[\"\x27]', text))
            record['response_diagnostic'] = bool(re.search(r'Fatal error:|Parse error:|Deprecated:|Warning:', text))
            record['verdict'] = 'PASS' if status == 200 and record['login_fields_present'] and record['login_form_present'] and not record['response_diagnostic'] else 'FAIL'
        exit_code = 0 if record['verdict'] == 'PASS' else 1
    except (RuntimeError, OSError, ValueError, KeyError, subprocess.TimeoutExpired, http.client.HTTPException) as error:
        record['infrastructure_error'] = str(error)
        # Preserve any already established application failure.
        if record.get('http_status') is not None and record['http_status'] != 200:
            record['verdict'] = 'FAIL'
            exit_code = 1
        else:
            record['verdict'] = 'BLOCKED'
    finally:
        logs = run('docker', 'compose', 'logs', '--no-color', '--timestamps', '--since', record['started_at'], service)
        (output / 'container.log').write_text(logs.stdout + logs.stderr, encoding='utf-8')
        record['log_command_exit_code'] = logs.returncode
        if args.case == 'startup':
            record['server_diagnostic'] = bool(re.search(r'PHP (Fatal error|Parse error|Deprecated|Warning|Notice):', logs.stdout + logs.stderr))
            if record.get('verdict') == 'PASS' and record['server_diagnostic']:
                record['verdict'] = 'FAIL'
                exit_code = 1
            if record.get('verdict') == 'PASS' and logs.returncode:
                record['verdict'] = 'INCONCLUSIVE'
                exit_code = 2
        record['finished_at'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
        save(output / 'result.json', record)
    print(json.dumps({'case_id': case_id, 'verdict': record['verdict'], 'evidence': str(output)}, ensure_ascii=False))
    return exit_code


if __name__ == '__main__':
    sys.exit(main())
