/* Readiness and maintenance HTTP observation. No data-changing application request. */
'use strict';
const {request}=require('playwright-core');
const fs=require('node:fs'),path=require('node:path');
const [credentialFile,mode,output,localFlag]=process.argv.slice(2);
if(!credentialFile || !['ready','maintenance'].includes(mode) || !output || (localFlag && localFlag!=='--local-fixture')) throw Error('Usage: node sakura-cutover-maintenance.cjs PRIVATE_CREDENTIALS ready|maintenance NEW_OUTPUT [--local-fixture]');
const file=fs.lstatSync(credentialFile),parent=fs.lstatSync(path.dirname(credentialFile));
if(!file.isFile() || file.isSymbolicLink() || !parent.isDirectory() || parent.isSymbolicLink() || [file,parent].some(s=>s.uid!==process.getuid() || (s.mode&0o077))) throw Error('Owner-only input required');
const c=JSON.parse(fs.readFileSync(credentialFile,'utf8')),u=new URL(c.url),local=localFlag==='--local-fixture';
if(c.purpose!=='filethingie-sakura-cutover' || u.protocol!=='https:' || u.username || u.password || u.search || u.hash || !u.pathname.endsWith('/index.php') || /\/ft-(?:stage[0-9]|php-check)-/.test(u.pathname) || !Number.isFinite(Date.parse(c.expires_at)) || Date.parse(c.expires_at)<=Date.now() || ![c.basic_user,c.basic_password].every(s=>typeof s==='string' && s.length)) throw Error('Explicit valid cutover credentials required');
if(local && (u.hostname!=='127.0.0.1' || !['18843','18943'].includes(u.port) || u.pathname!=='/app/index.php' || c.basic_user!=='fixture' || c.basic_password!=='local-rehearsal-only')) throw Error('Local fixture required');
const r={case_id:local?(u.port==='18843'?'TS-618':'TS-619'):'SA-608',step:mode,scope:'Basic認証とWeb停止の観測。配置・バックアップの判定対象外',started_at:new Date().toISOString(),checks:[],verdict:'INCONCLUSIVE'};
function eq(name,actual,expected){const ok=actual===expected;r.checks.push({name,actual,expected,verdict:ok?'PASS':'FAIL'});if(!ok)throw Error(name);}
(async()=>{let anon,auth;
try{
 anon=await request.newContext({ignoreHTTPSErrors:local,maxRedirects:0});
 auth=await request.newContext({ignoreHTTPSErrors:local,maxRedirects:0,httpCredentials:{username:c.basic_user,password:c.basic_password,origin:u.origin}});
 const icon=new URL('favicon.ico',u).href;
 eq('未認証のfavicon取得401',(await anon.get(icon)).status(),401);
 eq('Basic認証後のfavicon取得200',(await auth.get(icon)).status(),200);
 if(mode==='maintenance'){
  eq('未認証の入口401',(await anon.get(u.href)).status(),401);
  for(const method of ['GET','POST']){
   const res=await auth.fetch(u.href,{method,...(method==='POST'?{data:''}:{})});
   eq(method+'停止応答503',res.status(),503);
   eq(method+'停止本文の一致',(await res.text()).trim(),'File Thingie is temporarily unavailable for maintenance.');
   eq(method+'キャッシュ禁止',res.headers()['cache-control'],'no-store');
   eq(method+'Retry-After',res.headers()['retry-after'],'300');
  }
 }
 r.verdict='PASS';
}catch(e){r.verdict='FAIL';r.error='Web確認の不一致または通信エラー。認証情報と停止状態の確認が必要';}
finally{
 if(anon)await anon.dispose();if(auth)await auth.dispose();r.finished_at=new Date().toISOString();fs.writeFileSync(output,JSON.stringify(r,null,2)+'\n',{mode:0o600,flag:'wx'});console.log(JSON.stringify({case_id:r.case_id,step:mode,verdict:r.verdict,checks:r.checks.length}));process.exitCode=r.verdict==='PASS'?0:1;
}
})();
