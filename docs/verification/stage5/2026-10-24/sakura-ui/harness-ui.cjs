/* Real HTTPS UI acceptance for an isolated ft-stage5 preview. No session injection. */
'use strict';
const {chromium} = require('playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const cp = require('node:child_process');
const [phase, credentialFile, outputArg] = process.argv.slice(2);
if (!['basic', 'plugins'].includes(phase) || !credentialFile || !outputArg) {
  throw Error('Usage: node tools/browser/sakura.cjs basic|plugins PRIVATE_CREDENTIAL_FILE NEW_OUTPUT');
}
const stat = fs.lstatSync(credentialFile), parent = fs.lstatSync(path.dirname(credentialFile));
if (!stat.isFile() || stat.isSymbolicLink() || stat.uid !== process.getuid() || (stat.mode & 0o077)
    || !parent.isDirectory() || parent.isSymbolicLink() || parent.uid !== process.getuid() || (parent.mode & 0o077)
    || path.basename(credentialFile) !== 'credentials.json' || !path.basename(path.dirname(credentialFile)).startsWith('ft-sakura-auth-')) {
  throw Error('Owner-only temporary credential file required.');
}
const credentials = JSON.parse(fs.readFileSync(credentialFile, 'utf8'));
const target = new URL(credentials.url);
if (credentials.purpose !== 'filethingie-sakura-preview' || credentials.app_user !== 'stage5'
    || target.protocol !== 'https:' || target.username || target.password || target.search || target.hash
    || !/\/ft-stage5-\d{8}-\d{6}-[a-f0-9]{8}\/index\.php$/.test(target.pathname)
    || !Number.isFinite(Date.parse(credentials.expires_at)) || Date.parse(credentials.expires_at) <= Date.now()
    || ![credentials.basic_user, credentials.basic_password, credentials.app_password].every(v => typeof v === 'string' && v.length)) {
  throw Error('Valid, unexpired isolated-preview credentials required.');
}
const output = path.resolve(outputArg);
if (fs.existsSync(output)) throw Error('Use a new evidence directory.');
fs.mkdirSync(output, {recursive:true, mode:0o700});
fs.chmodSync(output, 0o700);
const prefix = target.pathname.slice(0, target.pathname.lastIndexOf('/') + 1);
const rootName = 'ft-auto-' + phase + '-' + crypto.randomBytes(5).toString('hex');
const record = {
  case_id: phase === 'basic' ? 'SA-502' : 'SA-503',
  scope: phase === 'basic' ? '実機Chrome UIの基本操作・認証' : '実機Chrome UIのログ・CSV・ZIP',
  started_at:new Date().toISOString(), verdict:'INCONCLUSIVE', checks:[], steps:[], errors:[],
  test_directory:rootName, request_counts:{},
  remaining: phase === 'basic' ? ['非公開PHPログの確認'] : ['非公開SQLite・設定・PHPログの配置と権限確認'],
  overall_case_verdict:'実機内部の確認を含む全体判定は別途',
  harness_sha256:crypto.createHash('sha256').update(fs.readFileSync(__filename)).digest('hex'),
};
const hash = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
function scrub(value) {
  if (Array.isArray(value)) return value.map(scrub);
  if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([k,v]) => [k,scrub(v)]));
  if (typeof value !== 'string') return value;
  for (const secret of [credentials.basic_password, credentials.app_password]) value = value.split(secret).join('<redacted>');
  value = value.split(target.origin + prefix).join('<preview>/').split(target.origin).join('<origin>');
  // Locations in log rows may contain the private filesystem prefix.
  if (value.startsWith('/') && value.includes('/' + rootName)) value = '<managed-root>' + value.slice(value.indexOf('/' + rootName));
  return value;
}
function expect(label, actual, expected) {
  const canonical = value => Array.isArray(value) ? value.map(canonical)
    : value && typeof value === 'object' ? Object.fromEntries(Object.keys(value).sort().map(key => [key,canonical(value[key])])) : value;
  const pass = JSON.stringify(canonical(actual)) === JSON.stringify(canonical(expected));
  record.checks.push({label, actual:scrub(actual), expected:scrub(expected), verdict:pass?'PASS':'FAIL'});
  if (!pass) throw Error('Assertion: ' + label);
}
class Blocked extends Error {}
let browser, context, page, workingURL, fileSequence = 0;
function row(name) { return page.locator('tr[data-filename=' + JSON.stringify(name) + ']'); }
async function nav(action) { await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}), action()]); }
function mutationScope(name) {
  const current = new URL(page.url());
  const dir = current.searchParams.get('dir') || '';
  if (current.origin !== target.origin || current.pathname !== target.pathname
      || !(dir === '/' + rootName || dir.startsWith('/' + rootName + '/') || (!dir && name === rootName))) {
    throw new Blocked('Mutation outside the new test directory prevented.');
  }
}
async function create(name, type) {
  mutationScope(name);
  expect('Create destination absent: ' + name, await row(name).count(), 0);
  await page.locator('#type-' + type).check();
  await page.locator('#newdir').fill(name);
  await nav(() => page.locator('#mkdirsubmit').click());
  expect('Created ' + type + ': ' + name, await row(name).count(), 1);
}
async function enter(name) { await nav(() => row(name).locator('td.name a').first().click()); }
async function selectAction(name, action, value) {
  mutationScope(name);
  const open = page.locator('td.details span.hide:visible');
  for (let i = await open.count() - 1; i >= 0; i--) await open.nth(i).click();
  await row(name).locator('td.details span.show').click();
  expect('Action uses complete name', await page.locator('.filedetails input[name=file]').inputValue(), name);
  await page.locator('.filedetails li.' + action).click();
  if (value !== undefined) await page.locator('.filedetails input[name=newvalue]').fill(value);
}
async function action(name, kind, value) {
  await selectAction(name, kind, value);
  await nav(() => page.locator('.filedetails input[type=submit]').click());
}
async function remove(name) {
  await action(name, 'del');
  expect('Deleted: ' + name, await row(name).count(), 0);
}
async function cancelEdit() { await nav(() => page.locator('#edit input[type=submit][value="キャンセル"]').click()); }
async function downloadClick(click, expectedName) {
  const promise = page.waitForEvent('download'); await click(); const download = await promise;
  if (expectedName) expect('Downloaded filename', download.suggestedFilename(), expectedName);
  const destination = path.join(output, 'download-' + (++fileSequence) + (download.suggestedFilename().endsWith('.zip')?'.zip':'.bin'));
  await download.saveAs(destination);
  const bytes = fs.readFileSync(destination);
  record.steps.push({download:download.suggestedFilename(), bytes:bytes.length, sha256:hash(bytes)});
  return {bytes, destination};
}
async function downloadFile(name, expected) {
  const result = await downloadClick(() => row(name).locator('td.name a').first().click(), name);
  expect('Downloaded bytes: ' + name, hash(result.bytes), hash(expected));
  return result;
}
async function upload(files) {
  mutationScope(files[0].name);
  for (const file of files) {
    expect('Upload destination absent: ' + file.name, await row(file.name).count(), 0);
    await page.locator('#uploadsection input[type=file]:not(.safarihide)').setInputFiles({name:file.name, mimeType:'application/octet-stream', buffer:file.bytes});
  }
  expect('Upload selection count', await page.locator('#files_list li').count(), files.length);
  await nav(() => page.locator('#uploadbutton input').click());
  for (const file of files) expect('Uploaded: ' + file.name, await row(file.name).count(), 1);
}
async function login(password) {
  await page.locator('#ft_user').fill(credentials.app_user);
  await page.locator('#ft_pass').fill(password);
  await nav(() => page.locator('#login_button').click());
}
async function basic() {
  const folder='受入テスト', original='操作テスト.txt', copy='複製テスト.txt', renamed='変更後の日本語.txt';
  await create(folder,'folder'); await create(original,'file');
  await action(original,'edit');
  expect('New file empty', await page.locator('#filecontent').inputValue(), '');
  await page.locator('#filecontent').fill('保存確認1');
  await Promise.all([
    page.waitForResponse(r => r.request().method()==='POST' && r.request().postData()?.includes('act=saveedit')),
    page.locator('#save').click(),
  ]);
  await page.locator('#savestatus .ok').filter({hasText:'保存しました'}).waitFor(); await cancelEdit();
  await action(original,'edit');
  expect('AJAX save persisted after reopening', await page.locator('#filecontent').inputValue(), '保存確認1');
  const content=Buffer.from('保存確認1\n保存確認2');
  await page.locator('#filecontent').fill(content.toString());
  await nav(() => page.locator('#edit input[type=submit][value="保存して終了"]').click());
  await action(original,'edit');
  expect('Normal save persisted after reopening', await page.locator('#filecontent').inputValue(), content.toString());
  await cancelEdit(); await downloadFile(original,content);
  await action(original,'duplicate',copy);
  expect('Copy visible',await row(copy).count(),1); await downloadFile(copy,content); await downloadFile(original,content);
  await action(copy,'rename',original);
  expect('Existing name collision rejected',await page.locator('.error').count()>0,true);
  await downloadFile(copy,content); await downloadFile(original,content);
  await action(copy,'rename',renamed);
  expect('Old name removed',await row(copy).count(),0); expect('New name visible',await row(renamed).count(),1);
  await downloadFile(renamed,content);
  await action(renamed,'move',folder);
  expect('Moved file absent in origin',await row(renamed).count(),0);
  await enter(folder); await downloadFile(renamed,content);
  await page.goto(workingURL,{waitUntil:'networkidle'});
  await page.locator('#q').fill('変更後の日本語');
  await Promise.all([page.waitForResponse(r => r.request().postData()?.includes('act=search')), page.locator('#dosearch').click()]);
  const found=page.locator('#searchlist dt a').filter({hasText:renamed}); await found.waitFor();
  expect('Japanese search finds moved file',await found.count(),1);
  const searchResult=await downloadClick(() => found.click(),renamed);
  expect('Search download content',hash(searchResult.bytes),hash(content));
  await page.screenshot({path:path.join(output,'basic.png'),fullPage:true});
  await enter(folder); await remove(renamed);
  await page.goto(workingURL,{waitUntil:'networkidle'}); await remove(folder);
  const protectedURL=new URL(await row(original).locator('td.name a').getAttribute('href'),page.url()).href;
  await nav(() => page.locator('#logout button').click());
  expect('Logout returns login form',await page.locator('#loginbox').count(),1);
  await page.reload({waitUntil:'networkidle'});
  expect('Reload stays logged out',await page.locator('#loginbox').count(),1);
  await page.goto(protectedURL,{waitUntil:'networkidle'});
  expect('Unauthenticated download returns login form',await page.locator('#loginbox').count(),1);
  await login(credentials.app_password);
  expect('Login restored for fixture cleanup',await page.locator('#filelist').count(),1);
  await page.goto(workingURL,{waitUntil:'networkidle'}); await downloadFile(original,content); await remove(original);
}
async function plugins() {
  const content=Buffer.from('File Thingie acceptance\n日本語 UTF-8\n    spaces\tTAB\n');
  const inputs=[{name:'日本語.txt',bytes:content},{name:'empty.txt',bytes:Buffer.alloc(0)}];
  await upload(inputs); for (const file of inputs) await downloadFile(file.name,file.bytes);
  await page.goto(target.href,{waitUntil:'networkidle'});
  await selectAction(rootName,'zip');
  const archive=await downloadClick(() => page.locator('.filedetails input[type=submit]').click(),rootName+'.zip');
  const zipEntries=JSON.parse(cp.execFileSync('python3',['-c',
    'import zipfile,json,hashlib,sys; z=zipfile.ZipFile(sys.argv[1]); print(json.dumps({n:hashlib.sha256(z.read(n)).hexdigest() for n in z.namelist() if not n.endswith("/")},ensure_ascii=False))', archive.destination],{encoding:'utf8'}));
  expect('ZIP contains exact input files',zipEntries,Object.fromEntries(inputs.map(f=>[rootName+'/'+f.name,hash(f.bytes)])));
  await page.goto(workingURL,{waitUntil:'networkidle'});
  await upload([{name:'展開確認.zip',bytes:archive.bytes}]); await action('展開確認.zip','unzip');
  expect('ZIP extraction directory created',await row('展開確認').count(),1);
  await enter('展開確認'); const extractionURL=page.url(); await enter(rootName); const extractedURL=page.url();
  for (const file of inputs) await downloadFile(file.name,file.bytes);
  await page.goto(workingURL,{waitUntil:'networkidle'}); await action('展開確認.zip','unzip');
  expect('Existing extraction destination rejected',await page.locator('.error').count()>0,true);
  await page.goto(extractedURL,{waitUntil:'networkidle'}); for (const file of inputs) await downloadFile(file.name,file.bytes);
  await nav(() => page.locator('#secondary_menu a[href*="act=log"]').click());
  const rows=await page.locator('#log_table tbody tr').evaluateAll(rs=>rs.map(r=>Array.from(r.cells).map(c=>c.textContent)));
  const own=rows.filter(r=>r[2].includes('/'+rootName+'/'));
  for (const file of inputs) for (const type of ['upload','download']) {
    expect('Log records '+type+': '+file.name,own.some(r=>r[0]===type && r[2].endsWith('/'+file.name) && r[4]==='stage5'),true);
  }
  expect('Login event in log',rows.some(r=>r[0]==='user' && r[1]==='Logged in.' && r[4]==='stage5'),true);
  const date=own.find(r=>r[0]==='upload')[3].slice(0,10), [year,month,day]=date.split('-');
  const form=page.locator('form[method=get]');
  for (const [name,value] of Object.entries({type:'upload',from:year+'-'+month,from_day:day,to:year+'-'+month,to_day:day})) await form.locator('input[name='+name+']').fill(value);
  await nav(() => form.locator('button[type=submit]').click());
  const filtered=await page.locator('#log_table tbody tr').evaluateAll(rs=>rs.map(r=>Array.from(r.cells).map(c=>c.textContent)));
  expect('Type and inclusive date filter',filtered.length>0 && filtered.every(r=>r[0]==='upload' && r[3].startsWith(date)),true);
  expect('Filtered log retains both uploads',inputs.every(f=>filtered.some(r=>r[2].endsWith('/'+rootName+'/'+f.name))),true);
  const csv=await downloadClick(() => page.locator('a[href*="act=log_csv"]').click());
  const csvRows=JSON.parse(cp.execFileSync('python3',['-c','import csv,json,sys; print(json.dumps(list(csv.reader(open(sys.argv[1],encoding="utf-8",newline=""),delimiter=";")),ensure_ascii=False))',csv.destination],{encoding:'utf8'}));
  expect('CSV header',csvRows.shift(),['Type','Message','Location','Timestamp','User']);
  expect('CSV agrees with filtered UI',csvRows,filtered);
  await page.screenshot({path:path.join(output,'logs.png'),fullPage:true});
  await page.goto(extractedURL,{waitUntil:'networkidle'}); for (const file of inputs) await remove(file.name);
  await page.goto(extractionURL,{waitUntil:'networkidle'}); await remove(rootName);
  await page.goto(workingURL,{waitUntil:'networkidle'});
  await remove('展開確認'); await remove('展開確認.zip'); for (const file of inputs) await remove(file.name);
}
(async () => {
  try {
    try { browser=await chromium.launch({executablePath:process.env.FT_CHROME || '/usr/bin/google-chrome',headless:true}); }
    catch (_) { throw new Blocked('Automatic Chrome could not start.'); }
    record.browser_version=browser.version(); record.client_os=process.platform;
    context=await browser.newContext({locale:'ja-JP',viewport:{width:1280,height:900},acceptDownloads:true,serviceWorkers:'block',
      httpCredentials:{username:credentials.basic_user,password:credentials.basic_password,origin:target.origin}});
    await context.route('**/*', async route => {
      const u=new URL(route.request().url());
      if (u.origin!==target.origin || !u.pathname.startsWith(prefix)) return route.abort('blockedbyclient');
      return route.continue();
    });
    page=await context.newPage(); page.setDefaultTimeout(20000);
    page.on('pageerror',e=>record.errors.push(scrub(e.message)));
    page.on('dialog',async dialog=>{record.errors.push('Unexpected dialog: '+scrub(dialog.message()));await dialog.dismiss();});
    page.on('response',r=>{
      const type=r.request().resourceType();
      if (['document','script','stylesheet','xhr','fetch'].includes(type)) {
        const key=r.request().method()+' '+r.status(); record.request_counts[key]=(record.request_counts[key]||0)+1;
        if (r.status()>=500 || (r.status()>=400 && ['script','stylesheet','xhr','fetch'].includes(type))) record.errors.push('HTTP '+r.status()+' '+scrub(r.url()));
      }
    });
    let response;
    try { response=await page.goto(target.href,{waitUntil:'networkidle'}); }
    catch (_) { throw new Blocked('HTTPS/Basic authentication/navigation could not be established.'); }
    if ([401,403].includes(response.status())) throw new Blocked('Basic authentication/access was not accepted.');
    expect('Initial login form',await page.locator('#loginbox').count(),1);
    expect('jQuery version',await page.evaluate(()=>jQuery.fn.jquery),'4.0.0');
    await login(credentials.app_password+'-incorrect-'+crypto.randomBytes(8).toString('hex'));
    expect('Incorrect app password rejected',await page.locator('#loginbox .error').count(),1);
    await login(credentials.app_password);
    if (!await page.locator('#filelist').count()) throw new Blocked('Supplied stage5 credentials were not accepted; confirm the password.');
    record.steps.push('通常フォームでのHTTPSログイン。セッション・内部値の注入なし');
    const cookies=(await context.cookies(target.href)).filter(c=>c.name.startsWith('FTSID'));
    expect('One application session cookie',cookies.length,1);
    expect('Secure cookie',cookies[0].secure,true); expect('HttpOnly cookie',cookies[0].httpOnly,true); expect('SameSite cookie',cookies[0].sameSite,'Lax');
    await create(rootName,'folder'); await enter(rootName); workingURL=page.url();
    record.steps.push('新規専用フォルダー内での操作開始');
    if (phase==='basic') await basic(); else await plugins();
    expect('Test directory emptied',await page.locator('#filelist tbody tr[data-filename]').count(),0);
    await page.goto(target.href,{waitUntil:'networkidle'}); await remove(rootName);
    record.fixture_cleanup='新規作成した検証用フォルダー・ファイルをUIから削除済み';
    await nav(() => page.locator('#logout button').click());
    expect('Final logout',await page.locator('#loginbox').count(),1);
    expect('No browser exceptions or server errors',record.errors,[]);
    record.verdict='PASS';
  } catch (error) {
    record.verdict=error instanceof Blocked || /net::ERR_/.test(error.message)?'BLOCKED':'FAIL'; record.error=scrub(error.stack || String(error));
    record.remaining_steps='NOT RUN';
    if (page) await page.screenshot({path:path.join(output,'failure.png'),fullPage:true}).catch(()=>{});
  } finally {
    if (browser) await browser.close();
    record.finished_at=new Date().toISOString();
    fs.writeFileSync(path.join(output,'result.json'),JSON.stringify(record,null,2)+'\n',{mode:0o600});
    console.log(JSON.stringify({case_id:record.case_id,scope:record.scope,verdict:record.verdict,checks:record.checks.length,error:record.error?.split('\n')[0]}));
    process.exitCode=record.verdict==='PASS'?0:1;
  }
})();
