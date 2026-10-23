#!/usr/bin/env python3
"""HTTP regression cases for the PHP migration; no direct application-state writes."""
import argparse
import datetime
import hashlib
import html
from html.parser import HTMLParser
import http.client
import json
from pathlib import Path
import re
import subprocess
import sys
from urllib.parse import urlencode, urlsplit, unquote, parse_qs

ROOT = Path(__file__).resolve().parents[1]
CONTENT = '日本語 UTF-8\nquote: \' " & <tag>\npath: C:\\data\\file\n    spaces\tTAB\n'.encode()


def digest(data):
    return hashlib.sha256(data).hexdigest()


def command(*args):
    return subprocess.run(args, cwd=ROOT, capture_output=True, text=True, timeout=60)


def checked(*args):
    result = command(*args)
    if result.returncode:
        raise RuntimeError(result.stderr or result.stdout)
    return result.stdout


def save(path, data):
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


class Markup(HTMLParser):
    def __init__(self, body):
        super().__init__(convert_charrefs=True)
        self.links, self.fields, self.textarea = [], {}, ''
        self.in_textarea = False
        self.feed(body.decode('utf-8'))

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'a':
            self.links.append(attrs.get('href', ''))
        if tag == 'input' and 'name' in attrs:
            self.fields[attrs['name']] = attrs.get('value', '')
        if tag == 'textarea':
            self.in_textarea = True

    def handle_endtag(self, tag):
        if tag == 'textarea':
            self.in_textarea = False

    def handle_data(self, data):
        if self.in_textarea:
            self.textarea += data


class Case:
    def __init__(self, service, output):
        self.service, self.output = service, output
        self.cookies, self.events, self.checks = {}, [], []
        bindings = json.loads(checked('docker', 'inspect', checked('docker', 'compose', 'ps', '-q', service).strip()))[0]
        ports = bindings['HostConfig']['PortBindings']['80/tcp']
        if len(ports) != 1 or ports[0]['HostIp'] != '127.0.0.1':
            raise RuntimeError('Expected localhost binding')
        self.port, self.image = int(ports[0]['HostPort']), bindings['Image']

    def expect(self, label, actual, expected):
        passed = actual == expected
        self.checks.append({'check': label, 'expected': expected, 'actual': actual, 'verdict': 'PASS' if passed else 'FAIL'})
        if not passed:
            raise AssertionError(label)

    def request(self, method='GET', fields=None, files=None, path='/ft2.php', status=200, max_field=True):
        fields = fields or {}
        headers = {'User-Agent': 'filethingie-stage3/1'}
        payload = None
        if method == 'GET' and fields:
            path += '?' + urlencode(fields)
        elif files is not None:
            boundary = 'filethingie-stage3-multipart'
            body = bytearray()
            if max_field:
                fields = {'MAX_FILE_SIZE': '2000000', **fields}
            for key, value in fields.items():
                body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            for control, name, data in files:
                name = name.replace('\\', '\\\\').replace('"', '\\"')
                body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{control}"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode())
                body.extend(data)
                body.extend(b'\r\n')
            body.extend(f'--{boundary}--\r\n'.encode())
            payload = bytes(body)
            headers['Content-Type'] = f'multipart/form-data; boundary={boundary}'
        elif method == 'POST':
            payload = urlencode(fields).encode()
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        if self.cookies:
            headers['Cookie'] = '; '.join(f'{key}={value}' for key, value in self.cookies.items())
        connection = http.client.HTTPConnection('127.0.0.1', self.port, timeout=20)
        try:
            connection.request(method, path, body=payload, headers=headers)
            response = connection.getresponse()
            data = response.read()
            response_headers = response.getheaders()
        finally:
            connection.close()
        for key, value in response_headers:
            if key.lower() == 'set-cookie':
                # Preserve the legacy cookie name verbatim (it contains '/').
                name, separator, cookie_value = value.split(';', 1)[0].partition('=')
                if separator:
                    self.cookies[name.strip()] = cookie_value
        content_type = dict(response_headers).get('Content-Type', '')
        event = {'method': method, 'path': path,
                 'fields': {key: ('<fixture password>' if key == 'ft_pass' else value) for key, value in fields.items()},
                 'files': [{'control': key, 'name': name, 'bytes': len(data), 'sha256': digest(data)} for key, name, data in (files or [])],
                 'request_bytes': len(payload or b''), 'request_sha256': digest(payload or b''),
                 'cookie_names': sorted(self.cookies), 'status': response.status,
                 'headers': [(key, '<redacted>' if key.lower() == 'set-cookie' else value) for key, value in response_headers],
                 'response_bytes': len(data), 'response_sha256': digest(data)}
        self.events.append(event)
        if 'application/octet-stream' not in content_type:
            filename = f'response-{len(self.events):03}.txt'
            (self.output / filename).write_bytes(data)
            event['response_file'] = filename
            self.expect(f'HTTP {len(self.events)} PHP診断通知なし', bool(re.search(rb'(?:Fatal error|Parse error|Warning|Deprecated|Notice):', data)), False)
        self.expect(f'HTTP {len(self.events)} 応答コード', response.status, status)
        return data, dict(response_headers)

    def login(self, user='stage2', password='local-only-filethingie'):
        self.request('POST', {'act': 'dologin', 'ft_user': user, 'ft_pass': password}, status=302)
        body, _ = self.request()
        self.expect('ログイン後の一覧', b"id='filelist'" in body, True)
        return body

    def action(self, act, **fields):
        _, headers = self.request('POST', {'act': act, 'dir': '', **fields}, status=302)
        location = urlsplit(headers.get('Location', ''))
        self.expect('リダイレクト先ホスト', location.netloc, f'127.0.0.1:{self.port}')
        self.expect('リダイレクト先ディレクトリ', parse_qs(location.query, keep_blank_values=True).get('dir'), [fields.get('dir', '')])
        return self.request(path=location.path + ('?' + location.query if location.query else ''))[0]

    def download(self, name, expected=None, directory='', status=200):
        body, headers = self.request('GET', {'method': 'getfile', 'dir': directory, 'file': name}, status=status)
        if expected is not None:
            self.expect('ダウンロード内容SHA-256: ' + name, digest(body), digest(expected))
            self.expect('ダウンロード長: ' + name, len(body), len(expected))
            self.expect('応答ファイル名: ' + name, unquote(headers.get('Content-Disposition', '').partition("filename*=UTF-8''")[2]), name)
        return body

    def upload(self, name, data, max_field=True):
        self.request('POST', {'act': 'upload', 'dir': ''}, [('localfile', name, data)], status=302, max_field=max_field)
        return self.request()[0]

    def basic(self):
        body, _ = self.request()
        self.expect('未認証ログインフォーム', b'id="loginbox"' in body, True)
        self.download('ascii.txt', status=302)
        for fields in [{'act': 'dologin'}, {'act': 'dologin', 'ft_user': 'stage2', 'ft_pass': 'wrong'}]:
            _, headers = self.request('POST', fields, status=302)
            self.expect('誤認証の拒否', 'act=error' in headers['Location'], True)
        body = self.login()
        self.expect('日本語一覧', '日本語.txt' in body.decode(), True)
        self.expect('容量表示', '1.91&nbsp;MB' in body.decode(), True)
        metadata_code = '$r=[]; foreach(glob("files/*.txt") as $p) {$r[basename($p)]=filemtime($p);} echo json_encode($r);'
        dates = json.loads(checked('docker', 'compose', 'exec', '-T', '--user', 'www-data', self.service, 'php', '-r', metadata_code))
        save(self.output / 'fixture-dates.json', {'command_php': metadata_code, 'mtime': dates})
        for sort in ['name', 'size', 'date', 'type']:
            sorted_body, _ = self.request('GET', {'sort': sort})
            ordered_names = [parse_qs(urlsplit(url).query)['file'][0] for url in Markup(sorted_body).links if 'method=getfile' in url]
            expected = ['ascii.txt', 'empty.txt', 'read-only.txt', 'space name.txt', '日本語.txt']
            if sort == 'size':
                expected = ['empty.txt', 'read-only.txt', 'space name.txt', 'ascii.txt', '日本語.txt']
            if sort == 'date':
                expected.sort(key=lambda name: (-dates[name], name.lower()))
            self.expect('並替え順: ' + sort, ordered_names, expected)
        folder = 'stage3 日本語 & folder'
        self.action('createdir', newdir=folder, type='folder')
        self.request('GET', {'dir': '/' + folder})
        self.action('createdir', newdir='stage3-empty.txt', type='file')
        self.download('stage3-empty.txt', b'')
        names = ['stage3-ascii.txt', 'stage3-日本語.txt', 'stage3 space.txt', "stage3-'quote\"&+%.txt", 'stage3-&amp;.txt']
        for name in names:
            body = self.upload(name, CONTENT)
            links = [url for url in Markup(body).links if 'method=getfile' in url and ('file=' + urlencode({'x': name})[2:].replace('+', '%20')) in url]
            self.expect('生成ダウンロードリンク: ' + name, len(links), 1)
            link_body, _ = self.request(path='/' + links[0])
            self.expect('生成リンクの内容一致: ' + name, digest(link_body), digest(CONTENT))
            self.download(name, CONTENT)
        name = names[3]
        body, _ = self.request('GET', {'act': 'edit', 'file': name, 'dir': ''})
        form = Markup(body)
        self.expect('編集対象のhidden値', form.fields.get('file'), name)
        self.expect('編集欄の内容', form.textarea.encode(), CONTENT)
        edited = CONTENT + b'normal POST\n'
        self.action('savefile', file=name, filecontent=edited.decode(), submit='Save & exit')
        self.download(name, edited)
        ajax = CONTENT + b'AJAX save\n'
        body, _ = self.request('POST', {'method': 'ajax', 'act': 'saveedit', 'file': name, 'dir': '', 'filecontent': ajax.decode()})
        self.expect('AJAX保存成功応答', b'class="ok"' in body or b"class='ok'" in body, True)
        self.download(name, ajax)
        self.action('savefile', file=name, filecontent='')
        self.download(name, b'')
        body, _ = self.request('POST', {'method': 'ajax', 'act': 'search', 'dir': '', 'q': '日本語', 'type': 'true'})
        self.expect('日本語検索', 'stage3-日本語.txt' in html.unescape(body.decode()), True)
        body, _ = self.request('POST', {'method': 'ajax', 'act': 'search', 'dir': '', 'q': 'child', 'type': 'true'})
        self.expect('再帰検索', 'child.txt' in body.decode(), True)
        body, _ = self.request('POST', {'method': 'ajax', 'act': 'search', 'dir': '', 'q': 'NO_MATCH_STAGE3', 'type': 'true'})
        self.expect('検索該当なし', b"class='error'" in body, True)
        renamed = 'stage3-renamed.txt'
        duplicate = 'stage3-duplicate.txt'
        self.action('rename', file=names[0], newvalue=renamed)
        self.download(names[0], status=404)
        self.download(renamed, CONTENT)
        self.action('duplicate', file=renamed, newvalue=duplicate)
        self.download(duplicate, CONTENT)
        self.action('move', file=duplicate, newvalue=folder)
        self.download(duplicate, status=404)
        self.download(duplicate, CONTENT, '/' + folder)
        self.action('move', file=duplicate, newvalue='..', dir='/' + folder)
        self.download(duplicate, CONTENT)
        for file in [renamed, duplicate, *names[1:], 'stage3-empty.txt', folder]:
            self.action('delete', file=file)
        self.download(renamed, status=404)
        self.request('GET', {'act': 'logout'}, status=302)
        self.expect('ログアウト後フォーム', b'id="loginbox"' in self.request()[0], True)
        self.download('ascii.txt', status=302)

    def negative(self):
        self.request('POST', {'method': 'ajax', 'act': 'search', 'q': 'ascii'}, status=403)
        self.request('POST', {'act': 'dologin', 'ft_user[]': 'stage2'}, status=400)
        self.login()
        for fields in [{'dir[]': 'nested'}, {'dir': '/../'}, {'method': 'getfile', 'file': '../config.php'},
                       {'act': 'delete'}, {'act': 'rename', 'file': 'ascii.txt'},
                       {'act': 'createdir', 'type': 'file'}, {'act': 'createdir', 'type': 'file', 'newdir': '../escape.txt'},
                       {'act': 'delete', 'file': 'bad\x00name'}, {'act': 'move', 'file': 'ascii.txt', 'newvalue': '..'}]:
            self.request('POST', fields, status=400)
        self.request('GET', {'dir': '/'})
        original = b'File Thingie stage 2\n'
        self.upload('ascii.txt', b'overwrite attempt')
        self.download('ascii.txt', original)
        self.action('createdir', newdir='ascii.txt', type='file')
        self.download('ascii.txt', original)
        self.upload('stage3-denied.php', b'<?php echo 1;')
        self.download('stage3-denied.php', status=400)
        self.expect('禁止拡張子の不作成', 'stage3-denied.php' in self.tree(), False)
        readonly = b'Read-only fixture\n'
        self.action('savefile', file='read-only.txt', filecontent='overwrite attempt')
        self.download('read-only.txt', readonly)
        self.upload('child.txt', b'collision source\n')
        self.action('move', file='child.txt', newvalue='nested')
        self.download('child.txt', b'collision source\n')
        self.download('child.txt', b'Nested fixture\n', '/nested')
        self.action('delete', file='child.txt')
        for size in [1999999, 2000000, 2000001]:
            name = f'stage3-size-{size}.txt'
            data = b'X' * size
            self.upload(name, data, max_field=False)
            if size <= 2000000:
                self.download(name, data)
                self.action('delete', file=name)
            else:
                self.download(name, status=404)
        self.request('POST', {'act': 'upload', 'dir': ''},
                     [('localfile0', 'stage3-too-large.txt', b'X' * 2097153), ('localfile1', 'stage3-valid.txt', CONTENT)],
                     status=302, max_field=False)
        self.request()
        self.download('stage3-too-large.txt', status=404)
        self.download('stage3-valid.txt', CONTENT)
        self.action('delete', file='stage3-valid.txt')
        self.download('ascii.txt', original)

    def tree(self):
        # Read-only observation as the web account; mutations remain HTTP-only.
        code = '$r=[]; foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator("files", FilesystemIterator::SKIP_DOTS)) as $f) {$r[]=substr($f->getPathname(),6);} sort($r); echo json_encode($r);'
        return json.loads(checked('docker', 'compose', 'exec', '-T', '--user', 'www-data', self.service, 'php', '-r', code))

    def groups(self):
        before = self.tree()
        body = self.login('viewer', 'local-only-viewer')
        self.expect('グループの限定ルート', b'child.txt' in body and b'ascii.txt' not in body, True)
        self.expect('無効searchの不表示', b'id="searchform"' in body, False)
        self.expect('操作UIの非表示', b'id="uploadsection"' in body or b'id="new"' in body, False)
        self.download('child.txt', b'Nested fixture\n')
        self.request('GET', {'dir': '/../'}, status=400)
        for act, extra in [('rename', {'newvalue': 'stage3-forbidden.txt'}), ('duplicate', {'newvalue': 'stage3-forbidden.txt'}),
                           ('delete', {}), ('savefile', {'filecontent': 'denied'})]:
            self.request('POST', {'act': act, 'dir': '', 'file': 'child.txt', **extra})
            self.download('child.txt', b'Nested fixture\n')
        self.request('POST', {'act': 'createdir', 'dir': '', 'type': 'file', 'newdir': 'stage3-forbidden.txt'})
        self.request('POST', {'act': 'upload', 'dir': ''}, [('localfile', 'stage3-forbidden.txt', b'denied')])
        self.expect('禁止操作によるファイル一覧の不変', self.tree(), before)
        self.request('GET', {'act': 'logout'}, status=302)
        body = self.login('ungrouped', 'local-only-ungrouped')
        self.expect('グループ未指定の既定ルート', b'ascii.txt' in body, True)
        self.request('GET', {'act': 'logout'}, status=302)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('case', choices=['basic', 'negative', 'groups'])
    parser.add_argument('service', choices=['php83', 'php85'])
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=False)
    number = {'basic': 301, 'negative': 303, 'groups': 305}[args.case] + (args.service == 'php85')
    record = {'case_id': f'TS-{number}', 'case': args.case, 'service': args.service,
              'started_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
              'parent_commit': checked('git', 'rev-parse', 'HEAD').strip(), 'verdict': 'INCONCLUSIVE'}
    case = None
    try:
        case = Case(args.service, output)
        record['image_id'] = case.image
        paths = ['ft2.php', 'config.sample.php', 'hooks.php'] + [str(p.relative_to(ROOT)) for directory in ['plugins', 'locales'] for p in (ROOT / directory).rglob('*.php')]
        code = '$r=[]; foreach(json_decode($argv[1],true) as $p) {$r[$p]=hash_file("sha256",$p);} echo json_encode($r);'
        actual = json.loads(checked('docker', 'compose', 'exec', '-T', args.service, 'php', '-r', code, json.dumps(paths)))
        local = {p: digest((ROOT / p).read_bytes()) for p in paths}
        if actual != local:
            raise RuntimeError('Container differs from checkout; rebuild required')
        save(output / 'source-sha256.json', actual)
        harness = [ROOT / 'compose.yaml', ROOT / 'tools/stage3.py', ROOT / 'docs/STAGE3_TEST_PLAN.md', *sorted((ROOT / 'dev').glob('*'))]
        save(output / 'harness-sha256.json', {str(p.relative_to(ROOT)): digest(p.read_bytes()) for p in harness if p.is_file()})
        environment, _ = case.request(path='/__environment.php')
        record['runtime'] = json.loads(environment)
        case.expect('PHP系列', record['runtime']['php_version'].startswith('8.3.' if args.service == 'php83' else '8.5.'), True)
        getattr(case, args.case)()
        record['verdict'] = 'PASS'
    except AssertionError as error:
        record.update(verdict='FAIL', failed_check=str(error), remaining_steps='NOT RUN')
    except (RuntimeError, OSError, ValueError, KeyError, subprocess.TimeoutExpired, http.client.HTTPException) as error:
        record.update(verdict='BLOCKED', infrastructure_error=str(error), remaining_steps='NOT RUN')
    finally:
        logs = command('docker', 'compose', 'logs', '--no-color', '--timestamps', '--since', record['started_at'], args.service)
        (output / 'container.log').write_text(logs.stdout + logs.stderr)
        diagnostics = re.findall(r'.*PHP (?:Fatal error|Parse error|Warning|Deprecated|Notice):.*', logs.stdout + logs.stderr)
        record['diagnostics'] = diagnostics
        if diagnostics:
            record['verdict'] = 'FAIL'
        elif logs.returncode and record['verdict'] == 'PASS':
            record['verdict'] = 'INCONCLUSIVE'
        record['finished_at'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
        if case:
            save(output / 'http.json', case.events)
            # Byte comparisons are rendered as text only in the assertion record.
            save(output / 'checks.json', json.loads(json.dumps(case.checks, default=lambda value: {'bytes': len(value), 'sha256': digest(value)})))
            record['requests'] = len(case.events)
            record['checks'] = len(case.checks)
        save(output / 'result.json', record)
    print(json.dumps({'case_id': record['case_id'], 'verdict': record['verdict'], 'failed_check': record.get('failed_check'), 'diagnostics': len(record['diagnostics'])}, ensure_ascii=False))
    return 0 if record['verdict'] == 'PASS' else 1


if __name__ == '__main__':
    sys.exit(main())
