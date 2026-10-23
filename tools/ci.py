#!/usr/bin/env python3
"""Run the documented regression phases, preserving results even on failure."""
import argparse
import datetime
import http.client
import json
import os
from pathlib import Path
import subprocess
import time

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('service',choices=['php83','php85'])
parser.add_argument('--output',type=Path,required=True)
args = parser.parse_args()
output = args.output.resolve()
output.mkdir(parents=True,exist_ok=False)
results = []

def start(phase,override=None,maxsize=None):
    cmd=['docker','compose','-f','compose.yaml']
    if override: cmd += ['-f',override]
    cmd += ['up','-d','--build','--force-recreate',args.service]
    env=os.environ.copy()
    if maxsize is not None: env['FT_TEST_MAXSIZE']=str(maxsize)
    with (output/(phase+'-build.log')).open('w') as log:
        subprocess.run(cmd,cwd=ROOT,env=env,stdout=log,stderr=subprocess.STDOUT,check=True,timeout=900)
    container=subprocess.check_output(['docker','compose','ps','-q',args.service],cwd=ROOT,text=True).strip()
    inspect=json.loads(subprocess.check_output(['docker','inspect',container],text=True))[0]
    port=int(inspect['HostConfig']['PortBindings']['80/tcp'][0]['HostPort'])
    deadline=time.monotonic()+30
    while True:
        connection=http.client.HTTPConnection('127.0.0.1',port,timeout=2)
        try:
            connection.request('GET','/__environment.php')
            response=connection.getresponse()
            body=response.read()
            if response.status==200 and json.loads(body)['sapi']=='apache2handler':return
        except (OSError,ValueError,http.client.HTTPException): pass
        finally:connection.close()
        if time.monotonic()>deadline:raise RuntimeError('Runtime endpoint unavailable: '+phase)
        time.sleep(0.5)

def case(tool,name):
    target=output/(name+'-'+args.service)
    if tool.startswith('browser/'):
        cmd=['node','tools/'+tool+'.cjs',args.service,str(target)]
    else:cmd=['python3','tools/'+tool+'.py',name,args.service,'--output',str(target)]
    code=subprocess.run(cmd,cwd=ROOT,timeout=300).returncode
    path=target/'result.json'
    result=json.loads(path.read_text()) if path.exists() else {'case':name,'verdict':'BLOCKED','reason':'No result from harness'}
    result['runner_exit_code']=code
    results.append(result)

try:
    start('normal')
    for name in ['lint','startup']:case('stage2',name)
    for name in ['basic','negative','groups','auth','plugins','content']:case('stage4',name)
    for name in ['https','url_disabled','concurrent']:case('stage5',name)
    case('browser/stage4','chrome')
    start('firefox')
    case('browser/stage5','firefox')
    start('url','dev/compose.stage5-url.yaml')
    case('stage5','url')
    start('url-size','dev/compose.stage5-url.yaml',64)
    case('stage5','url_size')
    start('degraded','dev/compose.stage4-degraded.yaml')
    case('stage4','degraded')
except (OSError,RuntimeError,subprocess.SubprocessError) as error:
    results.append({'verdict':'BLOCKED','infrastructure_error':str(error),'remaining_steps':'NOT RUN'})
finally:
    summary={'service':args.service,'finished_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'results':results}
    (output/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2)+'\n')
    table='\n'.join('| '+str(r.get('case_id','setup'))+' | '+r['verdict']+' |' for r in results)
    if os.environ.get('GITHUB_STEP_SUMMARY'):
        with open(os.environ['GITHUB_STEP_SUMMARY'],'a') as stream:
            stream.write('| Case | Result |\n|---|---|\n'+table+'\n')
    print(table)
raise SystemExit(0 if len(results)==16 and all(r['verdict']=='PASS' and r['runner_exit_code']==0 for r in results) else 1)
