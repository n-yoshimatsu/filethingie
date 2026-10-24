#!/usr/bin/env python3
"""Local synthetic cutover fixture only; normal Chrome operations and CLI verification."""
import argparse
import datetime
import json
import subprocess
from pathlib import Path

p=argparse.ArgumentParser(description=__doc__)
p.add_argument('version',choices=['83','85']);p.add_argument('output',type=Path)
a=p.parse_args();root=Path(__file__).resolve().parent.parent
out=a.output.resolve();out.mkdir(mode=0o700)
container='ft-stage6-php'+a.version

def docker(*args):
    return subprocess.check_output(['docker','exec','--user','www-data',container,*args])

state=json.loads(docker('cat','/tmp/ft-cutover-browser-state.json'))
assert state['home']=='/tmp/ft-rehearsal-local/home' and state['public']==state['home']+'/www/app'
plan=json.loads(docker('cat',state['private']+'/plan.json'))
creds=out/'credentials.json';expect=out/'presentation.json'
for path,data in [(creds,{'purpose':'filethingie-sakura-cutover','url':state['url'],'basic_user':'fixture','basic_password':'local-rehearsal-only','expires_at':(datetime.datetime.now(datetime.timezone.utc)+datetime.timedelta(hours=1)).isoformat()}),(expect,plan['presentation'])]:
    path.write_text(json.dumps(data,ensure_ascii=False));path.chmod(0o600)

def action(name):
    code="require '/workspace/artifacts/stage6/cutover-operations-kit/sakura-cutover.php';$s=json_decode(file_get_contents('/tmp/ft-cutover-browser-state.json'),true);echo ftb_json(ftc_execute($argv[1],$s['home'],$s['preparation_id']));"
    r=json.loads(docker('php','-r',code,name));f=out/(name+'.json');f.write_text(json.dumps(r,ensure_ascii=False,indent=2)+'\n');f.chmod(0o600);return r

def node(script,*args):
    subprocess.run(['node',str(root/'tools/browser'/script),*map(str,args)],check=True,cwd=root)

try:
    node('sakura-cutover-maintenance.cjs',creds,'ready',out/'readiness.json','--local-fixture')
    action('hold')
    node('sakura-cutover-maintenance.cjs',creds,'maintenance',out/'maintenance.json','--local-fixture')
    action('resume')
    node('sakura-cutover.cjs',creds,expect,out/'browser','--local-fixture')
    result=action('verify');assert result['verdict']=='PASS'
    print(json.dumps({'case_id':'TS-618' if a.version=='83' else 'TS-619','verdict':'PASS','server_checks':len(result['verification']['checks'])}))
finally:
    creds.unlink(missing_ok=True);expect.unlink(missing_ok=True)
