#!/usr/bin/env python3
"""Stage 4 HTTP tests: real form tokens, no session or database injection."""
from stage3 import *
from stage3 import Case as Stage3Case
import csv
import io
import zipfile

class Case(Stage3Case):
    token = None
    def request(self, method='GET', fields=None, files=None, path='/ft2.php', status=200, max_field=True, csrf=True, preserve_get=False):
        fields = dict(fields or {})
        # Stage 4 protocol: logout is a submitted form, never a GET mutation.
        if method == 'GET' and fields.get('act') == 'logout' and not preserve_get:
            method = 'POST'
        if method == 'POST' and csrf:
            if self.token is None:
                self.request()
            fields.setdefault('csrf_token', self.token)
        data, headers = super().request(method, fields, files, path, status, max_field)
        if 'text/html' in headers.get('Content-Type', ''):
            match = re.search(rb'<meta name="csrf-token" content="([a-f0-9]+)"', data)
            if match:
                self.token = match[1].decode()
        if fields.get('act') == 'logout' and status == 302:
            self.token = None
        return data, headers

    def auth(self):
        body, headers = self.request()
        self.expect('Session cookie HttpOnly', 'HttpOnly' in headers.get('Set-Cookie', ''), True)
        self.expect('Session cookie SameSite', 'SameSite=Lax' in headers.get('Set-Cookie', ''), True)
        self.expect('Remember UI removed', b'id="ft_cookie"' in body, False)
        self.request('POST', {'act':'dologin','ft_user':'stage2','ft_pass':'local-only-filethingie'}, status=403, csrf=False)
        self.request('POST', {'act':'dologin','ft_user':'stage2','ft_pass':'local-only-filethingie','csrf_token':'bad'}, status=403)
        sid = next(key for key in self.cookies if key.startswith('FTSID'))
        self.cookies[sid] = 'attacker-fixed-session-id'
        self.request()
        self.expect('Uninitialized session ID rejected', self.cookies[sid] != 'attacker-fixed-session-id', True)
        before = self.cookies[sid]
        self.login()
        authenticated = self.cookies[sid]
        self.expect('Login rotates session ID', before != authenticated, True)
        for fields in [{'act':'delete','file':'ascii.txt'}, {'act':'logout'}]:
            self.request('GET', fields, status=405, preserve_get=True)
        self.request('POST', {'act':'delete','file':'ascii.txt'}, status=403, csrf=False)
        self.request('POST', {'act':'delete','file':'ascii.txt','csrf_token':'bad'}, status=403)
        self.download('ascii.txt', b'File Thingie stage 2\n')
        oldtoken = self.token
        self.request('POST', {'act':'logout'}, status=302)
        self.cookies[sid] = authenticated
        body, _ = self.request()
        self.expect('Logout invalidates old session', b'id="loginbox"' in body, True)
        self.request('POST', {'act':'dologin','ft_user':'stage2','ft_pass':'local-only-filethingie','csrf_token':oldtoken}, status=403)
        self.cookies['ft_user_/ft2_php'] = hashlib.md5(b'stage2local-only-filethingie').hexdigest()
        body, _ = self.request()
        self.expect('Legacy MD5 cookie ignored', b'id="loginbox"' in body, True)
        self.cookies.pop('ft_user_/ft2_php', None)
        self.login('legacy', 'local-only-legacy')
        self.request('POST', {'act':'logout'}, status=302)
        self.request()
        _, headers = self.request('POST', {'act':'dologin','ft_user':'brokenhash','ft_pass':'must-not-work'}, status=302)
        self.expect('Hash takes precedence over plain password', 'act=error' in headers['Location'], True)
        self.login('ungrouped','local-only-ungrouped')
        for fields in [{'act':'log'}, {'act':'log_csv'}, {'act':'db'}]:
            self.request('GET',fields,status=403)
        self.request('POST',{'act':'log_prune'},status=403)
        self.request('POST',{'act':'db_delete_submit','value':'log'},status=403)
        self.request('POST',{'act':'logout'},status=302)
        script = (ROOT / 'tools/hash-password.php').read_bytes()
        # Pipe fixture stdin to CLI in the existing container; no app-state modification.
        result = subprocess.run(['docker','compose','exec','-T',self.service,'php','-r',
            'eval("?>".$argv[1]);', script.decode()], input=b'local-hash-fixture\n', capture_output=True, cwd=ROOT)
        self.expect('Password hash tool exit', result.returncode, 0)
        verification = checked('docker','compose','exec','-T',self.service,'php','-r',
            'echo password_verify("local-hash-fixture", trim($argv[1])) ? "yes" : "no";', result.stdout.decode())
        self.expect('Generated password hash verified', verification, 'yes')
        save(self.output / 'hash-tool.json', {'exit_code':result.returncode,'stderr':result.stderr.decode(),'verification':verification})

    def plugins(self):
        self.login()
        name = '=stage4 日本語 "quote".txt'
        self.upload(name, CONTENT)
        self.download(name, CONTENT)
        body,_ = self.request('GET', {'act':'log','type':'download'})
        self.expect('Download log displayed', html.escape(name, quote=True) in body.decode(), True)
        data,_ = self.request('GET', {'act':'log_csv','type':'download'})
        rows = list(csv.reader(io.StringIO(data.decode()),delimiter=';'))
        self.expect('CSV header', rows[0], ['Type','Message','Location','Timestamp','User'])
        self.expect('CSV round trip quote and Japanese', any(row[2].endswith('/'+name) for row in rows[1:]), True)
        self.expect('CSV type filter', all(row[0]=='download' for row in rows[1:]), True)
        for query in [{'from':'bad'}, {'to':'2026-02','to_day':'31'}, {'from[]':'2026-01'}]:
            self.request('GET', {'act':'log',**query},status=400)
        for query in [{'from':'2099-01'}, {'to':'2000-01'}, {'type':"' OR 1=1 --"}]:
            data,_ = self.request('GET', {'act':'log_csv',**query})
            self.expect('Log filter excludes all rows', len(list(csv.reader(io.StringIO(data.decode()),delimiter=';'))), 1)
        data,_ = self.request('GET', {'act':'log_csv','from':datetime.date.today().strftime('%Y-%m'),'from_day':'1','to':datetime.date.today().strftime('%Y-%m'),'to_day':str(datetime.date.today().day)})
        self.expect('Log inclusive date filter', name in data.decode().replace('""','"'), True)
        before_prune,_ = self.request('GET', {'act':'log_csv','type':'download'})
        self.request('POST', {'act':'log_prune'},status=302)
        after,_=self.request('GET', {'act':'log_csv','type':'download'})
        self.expect('Prune preserves recent logs', after, before_prune)
        # A username beginning with '=' exercises spreadsheet formula neutralisation.
        self.request('POST', {'act':'logout'},status=302);self.request()
        self.request('POST', {'act':'dologin','ft_user':'=formula','ft_pass':'bad'},status=302)
        self.login()
        data,_=self.request('GET', {'act':'log_csv','type':'user'})
        self.expect('CSV formula neutralisation', any(row[4]=="'=formula" for row in csv.reader(io.StringIO(data.decode()),delimiter=';')), True)
        self.request('GET', {'act':'db'})
        self.request('POST', {'act':'db_delete_submit','value':'log; DROP TABLE log'},status=400)
        self.action('createdir',newdir='stage4-zipdir',type='folder')
        self.action('createdir',newdir='空フォルダー',type='folder',dir='/stage4-zipdir')
        self.upload('stage4-empty.txt',b'')
        self.action('move',file='stage4-empty.txt',newvalue='stage4-zipdir')
        self.action('move',file=name,newvalue='stage4-zipdir')
        archive,_=self.request('GET', {'act':'zip','file':'stage4-zipdir','dir':''})
        with zipfile.ZipFile(io.BytesIO(archive)) as zipped:
            self.expect('ZIP filenames', sorted(zipped.namelist()), sorted(['stage4-zipdir/','stage4-zipdir/空フォルダー/','stage4-zipdir/stage4-empty.txt','stage4-zipdir/'+name]))
            self.expect('ZIP content', zipped.read('stage4-zipdir/'+name), CONTENT)
        self.upload('stage4-roundtrip.zip',archive)
        self.action('unzip',file='stage4-roundtrip.zip')
        self.download(name,CONTENT,directory='/stage4-roundtrip/stage4-zipdir')
        before=self.tree()
        self.action('unzip',file='stage4-roundtrip.zip')
        self.expect('ZIP collision preserves tree',self.tree(),before)
        malicious = [('../escape.txt', b'x',None), ('/absolute.txt',b'x',None), ('bad.php',b'<?php',None), ('link',b'/etc/passwd',0o120777), ('oversize.txt',b'x'*2000001,None), ('dir/../../bad.txt',b'x',None)]
        for index,(entry,content,mode) in enumerate(malicious):
            buffer=io.BytesIO()
            with zipfile.ZipFile(buffer,'w',compression=zipfile.ZIP_DEFLATED) as zipped:
                info=zipfile.ZipInfo(entry);info.compress_type=zipfile.ZIP_DEFLATED
                if mode: info.create_system=3;info.external_attr=mode<<16
                zipped.writestr(info,content)
            filename=f'stage4-unsafe-{index}.zip'
            self.upload(filename,buffer.getvalue()); before=self.tree()
            body=self.action('unzip',file=filename)
            self.expect('Unsafe ZIP rejected '+str(index), b'class="error"' in body or b"class='error'" in body, True)
            self.expect('Unsafe ZIP preserves tree '+str(index),self.tree(),before)
        self.request('POST',{'act':'db_delete_submit','value':'log'},status=302)
        data,_=self.request('GET',{'act':'log_csv'})
        self.expect('DB drop and recreate empty log',len(list(csv.reader(io.StringIO(data.decode()),delimiter=';'))),1)

    def content(self):
        self.login()
        original = b'line one\r\n    line two\r\n'
        name = 'stage4-crlf.txt'
        self.upload(name, original)
        body,_ = self.request('GET', {'act':'edit','file':name})
        self.expect('CRLF convention in edit form', Markup(body).fields.get('lineending'), 'crlf')
        new = 'new line\n    second line\n'
        self.action('savefile',file=name,filecontent=new,lineending='crlf')
        self.download(name,new.replace('\n','\r\n').encode())
        self.request('POST', {'method':'ajax','act':'saveedit','file':name,'filecontent':new+'third\n','lineending':'crlf'})
        expected=(new+'third\n').replace('\n','\r\n').encode()
        self.download(name,expected)
        for field in [{'lineending':'invalid'}, {'lineending[]':'lf'}]:
            self.request('POST', {'act':'savefile','file':name,'filecontent':'denied',**field},status=400)
            self.download(name,expected)
        # Browser-style multipart quote escaping with an independently submitted original filename.
        literal = 'stage4-literal%22.txt'
        quoted = 'stage4-double"quote.txt'
        for transmitted, actual in [(literal,literal),(quoted.replace('"','%22'),quoted)]:
            self.request('POST', {'act':'upload','upload_names[localfile]':actual}, [('localfile',transmitted,b'exact-name')],status=302)
            self.download(actual,b'exact-name')
        before=self.tree()
        for fields in [{'upload_names':'bad'},{'upload_names[localfile]':'../escape.txt'},{'upload_names[localfile]':'mismatch.txt'},{'upload_names[localfile][]':'nested'}]:
            self.request('POST', {'act':'upload',**fields}, [('localfile','stage4-denied.txt',b'denied')],status=400)
        self.expect('Invalid upload metadata preserves tree',self.tree(),before)

    def degraded(self):
        body = self.login()
        for name in ['config','fileinfo','tinymce','editorck','fckeditor']:
            self.expect('Disabled plugin notice '+name, ('Plugin disabled in this release: '+name) in body.decode(), True)
        self.expect('DB failure notice', b'DB/log disabled:' in body,True)
        self.expect('ZIP failure notice', b'ZIP disabled:' in body,True)
        self.expect('No public database created','unsafe.sqlite' in self.tree(),False)
        self.expect('Legacy dependencies not loaded', bool(re.search(rb'src=[\'\"](?:[^\'\"]*(?:tablesorter|tiny_mce|ckeditor|jquery-1))',body)),False)
        body,_=self.request('GET',{'act':'edit','file':'ascii.txt'})
        self.expect('Plain text editor available', b'id="filecontent"' in body,True)
        self.download('ascii.txt',b'File Thingie stage 2\n')

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('case', choices=['basic', 'negative', 'groups', 'auth', 'plugins', 'degraded', 'content'])
    parser.add_argument('service', choices=['php83', 'php85'])
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=False)
    number = {'basic': 401, 'negative': 403, 'groups': 405, 'auth': 407, 'plugins': 409, 'degraded': 411, 'content': 415}[args.case] + (args.service == 'php85')
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
        harness = [ROOT / 'compose.yaml', ROOT / 'tools/stage3.py', ROOT / 'tools/stage4.py', ROOT / 'tools/hash-password.php', ROOT / 'docs/STAGE4_TEST_PLAN.md', *sorted((ROOT / 'dev').glob('*'))]
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
