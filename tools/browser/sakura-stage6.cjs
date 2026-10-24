/* Real Basic-only acceptance. Writes limited to one newly created rehearsal test folder. */
'use strict';
const {chromium, request} = require('playwright-core');
const fs = require('node:fs'), path = require('node:path'), crypto = require('node:crypto');
const [credentialFile, expectationFile, outputArg] = process.argv.slice(2);
if (!credentialFile || !expectationFile || !outputArg) throw Error('Usage: node sakura-stage6.cjs PRIVATE_CREDENTIALS PRIVATE_PRESENTATION NEW_OUTPUT');
function privateFile(file) {
  const s=fs.lstatSync(file), p=fs.lstatSync(path.dirname(file));
  if (!s.isFile() || s.isSymbolicLink() || s.uid!==process.getuid() || (s.mode&0o077)
      || !p.isDirectory() || p.isSymbolicLink() || p.uid!==process.getuid() || (p.mode&0o077)) throw Error('Owner-only private input required.');
  return JSON.parse(fs.readFileSync(file,'utf8'));
}
const credentials=privateFile(credentialFile), presentation=privateFile(expectationFile), target=new URL(credentials.url);
if (credentials.purpose!=='filethingie-sakura-rehearsal' || target.protocol!=='https:' || target.username || target.password || target.search || target.hash
    || !/\/ft-stage6-rehearsal-\d{8}-\d{6}-[a-f0-9]{10}\/index\.php$/.test(target.pathname)
    || !Number.isFinite(Date.parse(credentials.expires_at)) || Date.parse(credentials.expires_at)<=Date.now()
    || ![credentials.basic_user,credentials.basic_password].every(v=>typeof v==='string' && v.length)) throw Error('Valid isolated-rehearsal credentials required.');
for (const key of ['title','home_label','notice_heading','notice_text','accent']) if (typeof presentation[key]!=='string') throw Error('Reviewed presentation expectations required.');
const prefix=target.pathname.slice(0,target.pathname.lastIndexOf('/')+1), base=new URL('./',target).href;
const output=path.resolve(outputArg);fs.mkdirSync(output,{recursive:false,mode:0o700});
const root='ft-auto-stage6-'+crypto.randomBytes(6).toString('hex'), rootDir='/'+root;
const hash=b=>crypto.createHash('sha256').update(b).digest('hex');
const rec={case_id:'SA-606',name:'さくらのBasic認証だけの画面・通常操作',started_at:new Date().toISOString(),verdict:'INCONCLUSIVE',checks:[],errors:[],test_directory:root,
  harness_sha256:hash(fs.readFileSync(__filename)),server_verification:'NOT RUN',remaining:['サーバー内の配備・複製337ファイル・PHPログ確認'],production_cutover:false};
function scrub(value) {
  let s=String(value);
  for(const secret of [credentials.basic_password,credentials.basic_user,target.href,target.origin]) s=s.split(secret).join('<redacted>');
  return s;
}
function eq(label,actual,expected) {
  const ok=JSON.stringify(actual)===JSON.stringify(expected);rec.checks.push({label,actual,expected,verdict:ok?'PASS':'FAIL'});
  if(!ok)throw Error('Assertion: '+label);
}
class Blocked extends Error {}
let browser,ctx,page,created=false,seq=0;
const ownDir=d=>d===rootDir || (d.startsWith(rootDir+'/') && !d.split('/').includes('..'));
function scope(name) {
  const u=new URL(page.url()),d=u.searchParams.get('dir')||'';
  if(u.origin!==target.origin || u.pathname!==target.pathname || !(ownDir(d) || (!d && name===root))) throw new Blocked('Operation outside the dedicated test folder prevented.');
}
function row(name){return page.locator('tr[data-filename='+JSON.stringify(name)+']');}
async function nav(fn){const [response]=await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),fn()]);if(response)eq('画面遷移HTTP状態',response.status(),200);}
async function action(name,kind,value){
  scope(name);
  for(const item of await page.locator('td.details span.hide:visible').all())await item.click();
  await row(name).locator('td.details span.show').click();
  eq('操作対象ファイル名の一致',await page.locator('.filedetails input[name=file]').inputValue(),name);
  await page.locator('.filedetails li.'+kind).click();
  if(value!==undefined)await page.locator('.filedetails input.newvalue').fill(value);
  await nav(()=>page.locator('.filedetails input[type=submit]').click());
}
async function folder(name){scope(name);eq('新規フォルダー名の未使用',await row(name).count(),0);await page.locator('#type-folder').check();await page.locator('#newdir').fill(name);await nav(()=>page.locator('#mkdirsubmit').click());eq('フォルダー作成',await row(name).count(),1);}
async function enter(name){await nav(()=>row(name).locator('td.name a').click());}
async function remove(name){await action(name,'del');eq('削除後の一覧からの除外',await row(name).count(),0);}
async function upload(files){
  scope(files[0].name);
  for(const f of files)await page.locator('#uploadsection input[type=file]:not(.safarihide)').setInputFiles({name:f.name,mimeType:'text/plain',buffer:f.bytes});
  eq('アップロード選択数',await page.locator('#files_list li').count(),files.length);
  await nav(()=>page.locator('#uploadbutton input').click());
}
async function download(name,bytes){
  scope(name);const link=row(name).locator('td.name a');const u=new URL(await link.getAttribute('href'),page.url());
  eq('試験フォルダー内の直接ファイルリンク',u.origin===target.origin && decodeURIComponent(u.pathname).startsWith(prefix+'files/'+root+'/'),true);
  const [d]=await Promise.all([page.waitForEvent('download'),link.click({modifiers:['Alt']})]);
  eq('保存時のファイル名',d.suggestedFilename(),name);const dest=path.join(output,'download-'+(++seq)+'.bin');await d.saveAs(dest);fs.chmodSync(dest,0o600);
  eq('保存ファイルSHA-256 '+name,hash(fs.readFileSync(dest)),hash(bytes));
}
// Browser request guard, independent of UI selection: inspect the actual form destination.
function postFields(req){
  const type=req.headers()['content-type']||'',body=req.postData()||'';
  if(type.startsWith('application/x-www-form-urlencoded'))return Object.fromEntries(new URLSearchParams(body));
  if(type.startsWith('multipart/form-data')){
    const boundary=type.match(/boundary=(?:"([^"]+)"|([^;\s]+))/);if(!boundary)return {};
    const fields={};for(const part of body.split('--'+(boundary[1]||boundary[2]))){
      const split=part.indexOf('\r\n\r\n');if(split<0)continue;const head=part.slice(0,split);
      if(/; filename=/.test(head))continue;const name=head.match(/; name="([^"]+)"/);if(name)fields[name[1]]=part.slice(split+4).replace(/\r\n$/,'');
    }return fields;
  }return {};
}
function allowedPost(fields){
  const d=fields.dir||'';
  if(fields.act==='search')return ownDir(d);
  if(!ownDir(d))return d==='' && ((fields.act==='createdir' && fields.type==='folder' && fields.newdir===root && !created)
      || (fields.act==='delete' && fields.file===root && created));
  if(fields.act==='move' && (!fields.newvalue || fields.newvalue.startsWith('/') || !ownDir(path.posix.normalize(d+'/'+fields.newvalue))))return false;
  if(fields.file && (fields.file.includes('/') || fields.file==='..'))return false;
  return ['upload','createdir','rename','move','delete','edit','savefile','saveedit','edit_get_lock'].includes(fields.act);
}
(async()=>{
 let anon;
 try{
  anon=await request.newContext({maxRedirects:0});
  for(const rel of ['index.php','css/ft.css'])eq('未認証401 '+rel,(await anon.get(new URL(rel,base).href)).status(),401);
  browser=await chromium.launch({executablePath:'/usr/bin/google-chrome',headless:true});rec.browser=browser.version();
  ctx=await browser.newContext({httpCredentials:{username:credentials.basic_user,password:credentials.basic_password,origin:target.origin},viewport:{width:1280,height:900},acceptDownloads:true,serviceWorkers:'block'});
  await ctx.route('**/*',async route=>{
    const req=route.request(),u=new URL(req.url());
    if(u.origin!==target.origin || !u.pathname.startsWith(prefix))return route.abort('blockedbyclient');
    if(req.method()==='POST' && (u.pathname!==target.pathname || !allowedPost(postFields(req)))){
      rec.errors.push('操作先制限によるPOSTの停止');return route.abort('blockedbyclient');
    }
    return route.continue();
  });
  page=await ctx.newPage();page.setDefaultTimeout(20000);
  page.on('pageerror',e=>rec.errors.push(scrub(e.message)));page.on('dialog',async d=>{rec.errors.push('想定外ダイアログ');await d.dismiss();});
  const first=await page.goto(target.href,{waitUntil:'networkidle'});
  if(first.status()===401)throw new Blocked('Supplied Basic credentials were not accepted.');
  eq('Basic認証後のHTTP状態',first.status(),200);eq('追加アプリログインなし',await page.locator('#loginbox,#login').count(),0);eq('一覧表示',await page.locator('#filelist').count(),1);
  eq('管理設定ファイルの非表示',await row('.htaccess').count(),0);
  eq('ページタイトルの引継ぎ',await page.title()===presentation.title,true);
  eq('見出しの引継ぎ',await page.locator('#title a').first().textContent()===presentation.home_label,true);
  eq('案内見出しの引継ぎ',await page.locator('#site-notice-heading').textContent()===presentation.notice_heading,true);
  eq('案内文の引継ぎ・同名拒否への訂正',await page.locator('#site-notice').textContent()===presentation.notice_text,true);
  const rgb='rgb('+presentation.accent.slice(1).match(/../g).map(n=>parseInt(n,16)).join(', ')+')';
  eq('配色の引継ぎ',await page.locator('h1').evaluate(e=>getComputedStyle(e).backgroundColor)===rgb,true);
  eq('3箇所の幅の引継ぎ',await page.evaluate(()=>['#filelist','#sidebar','#uploadsection input.upload'].map(s=>getComputedStyle(document.querySelector(s)).width)),[presentation.filelist_width,presentation.sidebar_width,presentation.upload_width].map(v=>v+'px'));
  eq('作成メニュー',await page.locator('#new input[type=radio]').evaluateAll(es=>es.map(e=>e.value)),['folder']);
  eq('空のフッター',await page.locator('#footer').textContent(),'');eq('favicon指定',await page.locator('link[rel=icon]').getAttribute('href'),'favicon.ico');
  eq('favicon取得',(await ctx.request.get(new URL('favicon.ico',base).href,{maxRedirects:0})).status(),200);
  for(const rel of ['config.php','config.sample.php','.htaccess','files/'])eq('直接取得拒否 '+rel,(await ctx.request.get(new URL(rel,base).href,{maxRedirects:0})).status(),403);
  await folder(root);created=true;await enter(root);const working=page.url();scope('');
  const files=[{name:'日本語.txt',bytes:Buffer.from('転送確認 日本語\n')},{name:'empty.txt',bytes:Buffer.alloc(0)}];
  await upload(files);for(const f of files){eq('アップロード一覧 '+f.name,await row(f.name).count(),1);await download(f.name,f.bytes);}
  eq('未認証の直接ファイル取得401',(await anon.get(base+'files/'+root+'/'+encodeURIComponent(files[0].name))).status(),401);
  await upload([{name:files[0].name,bytes:Buffer.from('overwrite attempt')}]);eq('同名アップロードのエラー表示',await page.locator('.error').count()>0,true);await download(files[0].name,files[0].bytes);
  await row(files[0].name).locator('td.details span.show').click();
  eq('複製・解凍項目なし',await page.locator('.filedetails li.duplicate,.filedetails li.unzip').count(),0);
  eq('空メニュー項目なし',await page.locator('.filedetails li').evaluateAll(es=>es.some(e=>e.textContent.trim()==='')),false);
  await page.goto(working,{waitUntil:'networkidle'});await folder('移動先');
  await action(files[0].name,'rename','変更後.txt');eq('変更前の名前なし',await row(files[0].name).count(),0);eq('変更後の名前表示',await row('変更後.txt').count(),1);
  await action('変更後.txt','edit');eq('編集元の内容',await page.locator('#filecontent').inputValue(),files[0].bytes.toString());
  // The POST-rendered editor has no dir query; its hidden form field is the actual destination.
  eq('編集フォームの保存先制限',ownDir(await page.locator('#edit input[name=dir]').inputValue()),true);
  eq('編集フォームのファイル名',await page.locator('#edit input[name=file]').inputValue(),'変更後.txt');
  await page.locator('#filecontent').fill('編集後 日本語\n');await nav(()=>page.locator('#edit input[type=submit]').first().click());await download('変更後.txt',Buffer.from('編集後 日本語\n'));
  scope('');await page.locator('#q').fill('変更後');await Promise.all([page.waitForResponse(r=>r.request().postData()?.includes('act=search')),page.locator('#dosearch').click()]);await page.locator('#searchlist a').first().waitFor();eq('検索結果', (await page.locator('#searchlist').textContent()).includes('変更後.txt'),true);
  await page.goto(working,{waitUntil:'networkidle'});await action('変更後.txt','move','移動先');eq('移動元からの除去',await row('変更後.txt').count(),0);
  await enter('移動先');eq('移動先の表示',await row('変更後.txt').count(),1);await download('変更後.txt',Buffer.from('編集後 日本語\n'));await remove('変更後.txt');
  await page.goto(working,{waitUntil:'networkidle'});await remove('移動先');await remove('empty.txt');
  // Negative request targets only this trial's fresh directory; no production path involved.
  scope('');eq('CSRFなしの作成要求403',(await ctx.request.post(target.href,{maxRedirects:0,form:{act:'createdir',type:'folder',newdir:'must-not-create',dir:rootDir}})).status(),403);
  await page.goto(working,{waitUntil:'networkidle'});eq('CSRF拒否後の未作成',await row('must-not-create').count(),0);
  eq('試験フォルダーが空',await page.locator('#filelist tbody tr[data-filename]').count(),0);
  await page.screenshot({path:path.join(output,'test-folder.png'),fullPage:true});fs.chmodSync(path.join(output,'test-folder.png'),0o600);
  await page.goto(target.href,{waitUntil:'networkidle'});await remove(root);created=false;rec.fixture_cleanup='新規試験フォルダー・試験ファイルのUI削除完了';
  eq('ブラウザーエラーなし',rec.errors,[]);rec.verdict='PASS';rec.overall_case_verdict='サーバー内部確認待ち';
 }catch(e){rec.verdict=e instanceof Blocked || /net::ERR_/.test(e.message)?'BLOCKED':'FAIL';rec.error=scrub(e.message);rec.remaining_steps='NOT RUN';rec.fixture_cleanup=created?'作成した試験フォルダーの確認が必要':'作成済み試験フォルダーなし';}
 finally{
  if(anon)await anon.dispose();if(browser)await browser.close();rec.finished_at=new Date().toISOString();
  fs.writeFileSync(path.join(output,'result.json'),JSON.stringify(rec,null,2)+'\n',{mode:0o600});
  console.log(JSON.stringify({case_id:rec.case_id,verdict:rec.verdict,checks:rec.checks.length,error:rec.error,fixture_cleanup:rec.fixture_cleanup}));process.exitCode=rec.verdict==='PASS'?0:1;
 }
})();
