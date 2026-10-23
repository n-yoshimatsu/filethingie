#!/usr/bin/env python3
"""Stage 5 integration tests against disposable local containers."""
from stage4 import *
from stage4 import Case as Stage4Case
import concurrent.futures
import ssl
import threading
import time
import urllib.request

URL = 'https://raw.githubusercontent.com/n-yoshimatsu/filethingie/f2a67b64b79f786d3766090f1e977e74a8a9c01f/js/jquery-LICENSE.txt'

def has_error(body):
    class StatusParser(HTMLParser):
        found = False
        def handle_starttag(self, tag, attrs):
            if 'error' in dict(attrs).get('class','').split(): self.found = True
    parser = StatusParser()
    parser.feed(body.decode())
    return parser.found

class Case(Stage4Case):
    tls_context = None

    def connection(self):
        if self.tls_context:
            return http.client.HTTPSConnection('127.0.0.1', self.port, timeout=20, context=self.tls_context)
        return super().connection()

    def https(self):
        cert = checked('docker','compose','exec','-T',self.service,'cat','/etc/ssl/certs/filethingie-test.pem')
        (self.output / 'test-ca.pem').write_text(cert)
        self.tls_context = ssl.create_default_context(cadata=cert)
        inspect = json.loads(checked('docker','inspect',checked('docker','compose','ps','-q',self.service).strip()))[0]
        bindings = inspect['HostConfig']['PortBindings']['443/tcp']
        self.expect('TLS localhost binding', bindings[0]['HostIp'], '127.0.0.1')
        self.port = int(bindings[0]['HostPort'])
        body, headers = self.request()
        for flag in ['Secure','HttpOnly','SameSite=Lax']:
            self.expect('HTTPS Cookie '+flag, flag.lower() in headers.get('Set-Cookie','').lower(), True)
        _, headers = self.request('POST',{'act':'dologin','ft_user':'stage2','ft_pass':'local-only-filethingie'},status=302)
        self.expect('HTTPS redirect',headers['Location'].startswith(f'https://127.0.0.1:{self.port}/ft2.php'), True)
        body,_ = self.request()
        self.expect('TLS authenticated list',b"id='filelist'" in body, True)
        original = (ROOT / 'dev/config.test.php').read_text()
        newhash = checked('docker','compose','exec','-T',self.service,'php','-r','echo password_hash("stage5-rotated-fixture", PASSWORD_DEFAULT);')
        modified = re.sub(r"define\('PASSWORD_HASH', '[^']+'\)", "define('PASSWORD_HASH', '"+newhash+"')", original)
        self.expect('Credential config changed',modified != original, True)
        def deploy(content):
            checked('docker','compose','exec','-T',self.service,'php','-r',
                    'if(file_put_contents("config.php.new",$argv[1])===false || !rename("config.php.new","config.php")) exit(1);', content)
        try:
            deploy(modified)
            body,_ = self.request()
            self.expect('Changed credential invalidates session',b'id="loginbox"' in body, True)
            _,headers = self.request('POST',{'act':'dologin','ft_user':'stage2','ft_pass':'local-only-filethingie'},status=302)
            self.expect('Old password denied','act=error' in headers['Location'], True)
            self.login('stage2','stage5-rotated-fixture')
        finally:
            deploy(original)
        body,_ = self.request()
        self.expect('Restored credential invalidates changed session',b'id="loginbox"' in body, True)
        self.login()

    def url_disabled(self):
        body = self.login()
        self.expect('URL UI disabled by default',b'id="type-url"' in body,False)
        before = self.tree()
        body = self.action('createdir',type='url',newdir=URL)
        self.expect('Disabled URL import error',has_error(body),True)
        self.expect('Disabled URL preserves files',self.tree(),before)

    def url_preflight(self):
        try:
            with urllib.request.urlopen(URL,timeout=20) as response:
                data = response.read()
        except (OSError,ValueError) as error:
            raise RuntimeError('External HTTPS fixture unreachable: '+str(error)) from error
        self.expect('Immutable external fixture hash',digest(data),digest((ROOT/'js/jquery-LICENSE.txt').read_bytes()))
        return data

    def url(self):
        expected = self.url_preflight()
        body = self.login()
        self.expect('URL UI enabled',b'id="type-url"' in body,True)
        self.action('createdir',type='url',newdir=URL)
        self.download('jquery-LICENSE.txt',expected)
        for url in [URL, 'file:///etc/passwd', 'http://raw.githubusercontent.com/test.txt',
                    'https://user:pass@raw.githubusercontent.com/test.txt',
                    'https://raw.githubusercontent.com:444/test.txt',URL+'#fragment',
                    'https://example.com/test.txt','https://localhost/private.txt',
                    URL.replace('jquery-LICENSE.txt','bad.php'),
                    URL.replace('jquery-LICENSE.txt','a%2Fb.txt'),
                    URL.replace('jquery-LICENSE.txt','stage5-not-existing-file.txt')]:
            before = self.tree()
            body = self.action('createdir',type='url',newdir=url)
            self.expect('URL rejected: '+url,has_error(body),True)
            self.expect('Rejected URL preserves files',self.tree(),before)

    def url_size(self):
        expected = self.url_preflight()
        self.expect('External fixture exceeds 64 bytes',len(expected)>64,True)
        self.login()
        before = self.tree()
        body = self.action('createdir',type='url',newdir=URL)
        self.expect('Oversize URL rejected',has_error(body),True)
        self.expect('No partial URL file',self.tree(),before)

    def concurrent(self):
        clients = []
        for index in range(4):
            output = self.output / f'client-{index}'
            output.mkdir()
            client = Case(self.service, output)
            client.login()
            clients.append(client)
        try:
            for round_index in range(5):
                barrier = threading.Barrier(4)
                filename = f'stage5-concurrent-{round_index}.txt'
                contents = [bytes([65+i])*1000000 for i in range(4)]
                def upload(index):
                    barrier.wait(timeout=15)
                    return clients[index].upload(filename,contents[index])
                with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
                    responses = list(pool.map(upload,range(4)))
                winners = [i for i,body in enumerate(responses) if not has_error(body)]
                self.expect('Exactly one upload winner '+str(round_index),len(winners),1)
                self.expect('Three collision errors '+str(round_index),sum(has_error(b) for b in responses),3)
                clients[0].download(filename,contents[winners[0]])
        finally:
            for index,client in enumerate(clients):
                save(client.output/'http.json',client.events)
                save(client.output/'checks.json',client.checks)
                self.checks.extend(client.checks)
                self.events.extend(client.events)

    def idle(self):
        self.login()
        start = time.monotonic()
        while time.monotonic()-start < 1810:
            time.sleep(min(30,1810-(time.monotonic()-start)))
        body,_ = self.request()
        self.expect('Real 30-minute idle expiry',b'id="loginbox"' in body,True)
        save(self.output/'elapsed.json',{'seconds':time.monotonic()-start,'intermediate_requests':0})

    def absolute(self):
        self.login()
        start = time.monotonic()
        while time.monotonic()-start < 28810:
            remaining = 28810-(time.monotonic()-start)
            time.sleep(min(30,remaining))
            elapsed = time.monotonic()-start
            if elapsed < 28500 and int(elapsed)//300 > getattr(self,'last_tick',0):
                self.last_tick = int(elapsed)//300
                body,_ = self.request()
                self.expect('Active session before absolute deadline '+str(self.last_tick),b"id='filelist'" in body,True)
        body,_ = self.request()
        self.expect('Real 8-hour absolute expiry',b'id="loginbox"' in body,True)
        save(self.output/'elapsed.json',{'seconds':time.monotonic()-start})

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('case', choices=['https','url_disabled','url','url_size','concurrent','idle','absolute'])
    parser.add_argument('service', choices=['php83', 'php85'])
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=False)
    number = {'https':501,'url_disabled':503,'url':505,'url_size':507,'concurrent':509,'idle':513,'absolute':515}[args.case] + (args.service == 'php85')
    record = {'case_id': f'TS-{number}', 'case': args.case, 'service': args.service,
              'started_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
              'parent_commit': checked('git', 'rev-parse', 'HEAD').strip(), 'verdict': 'INCONCLUSIVE'}
    case = None
    try:
        case = Case(args.service, output)
        record['image_id'] = case.image
        paths = ['ft2.php', 'config.sample.php', 'hooks.php'] + [str(p.relative_to(ROOT)) for directory in ['plugins', 'locales', 'js', 'css'] for p in (ROOT / directory).rglob('*') if p.is_file()]
        code = '$r=[]; foreach(json_decode($argv[1],true) as $p) {$r[$p]=hash_file("sha256",$p);} echo json_encode($r);'
        actual = json.loads(checked('docker', 'compose', 'exec', '-T', args.service, 'php', '-r', code, json.dumps(paths)))
        local = {p: digest((ROOT / p).read_bytes()) for p in paths}
        if actual != local:
            raise RuntimeError('Container differs from checkout; rebuild required')
        save(output / 'source-sha256.json', actual)
        harness = [ROOT / 'compose.yaml', ROOT / 'tools/stage3.py', ROOT / 'tools/stage4.py', ROOT / 'tools/stage5.py', ROOT / 'tools/hash-password.php', ROOT / 'docs/STAGE5_TEST_PLAN.md', *sorted((ROOT / 'dev').glob('*'))]
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
