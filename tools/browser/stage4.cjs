/* Local-only browser functional tests. No injected sessions or mocked API responses. */
const {chromium} = require('playwright-core');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const cp = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const [service, outputArg] = process.argv.slice(2);
if (!['php83','php85'].includes(service) || !outputArg) throw Error('Usage: node tools/browser/stage4.cjs php83|php85 OUTPUT');
const output = path.resolve(outputArg);
fs.mkdirSync(output,{recursive:false});
const record = {case_id: service === 'php83' ? 'TS-413' : 'TS-414',case:'Chromeブラウザー互換', service, started_at:new Date().toISOString(), verdict:'INCONCLUSIVE', checks:[], steps:[], errors:[], responses:[]};
function hash(value) { return crypto.createHash('sha256').update(value).digest('hex'); }
function command(args) { return cp.execFileSync(args[0],args.slice(1),{cwd:root,encoding:'utf8'}).trim(); }
function expect(label,actual,expected) {
  const pass = JSON.stringify(actual) === JSON.stringify(expected);
  record.checks.push({label,actual,expected,verdict:pass?'PASS':'FAIL'});
  if (!pass) throw Error(label);
}
async function main() {
  let browser, page;
  try {
    const id = command(['docker','compose','ps','-q',service]);
    const inspect = JSON.parse(command(['docker','inspect',id]))[0];
    const bind = inspect.HostConfig.PortBindings['80/tcp'][0];
    if (bind.HostIp !== '127.0.0.1') throw Error('Localhost binding required');
    const base = `http://127.0.0.1:${bind.HostPort}`, url = base+'/ft2.php';
    record.image_id = inspect.Image;
    const files = ['ft2.php','config.sample.php','hooks.php','dev/config.test.php','js/filethingie.js','js/jquery-4.0.0.min.js','css/ft.css','locales/ja.php',...fs.readdirSync(path.join(root,'plugins')).filter(name=>name.endsWith('.php')).map(name=>'plugins/'+name)];
    record.source_sha256 = Object.fromEntries(files.map(file=>[file,hash(fs.readFileSync(path.join(root,file)))]));
    record.harness_sha256 = hash(fs.readFileSync(__filename));
    const php = '$r=[]; foreach(json_decode($argv[1],true) as $p) {$r[$p]=hash_file("sha256",$p === "dev/config.test.php" ? "config.php" : $p);} echo json_encode($r);';
    const actual = JSON.parse(command(['docker','compose','exec','-T',service,'php','-r',php,JSON.stringify(files)]));
    expect('Container source matches checkout',actual,record.source_sha256);
    try { browser = await chromium.launch({executablePath:process.env.FT_CHROME || '/usr/bin/google-chrome',headless:true}); }
    catch(error) { record.verdict='BLOCKED';throw error; }
    record.browser_version=browser.version();
    const context=await browser.newContext({locale:'ja-JP',viewport:{width:1280,height:900},acceptDownloads:true});
    page=await context.newPage();
    page.on('pageerror',error=>record.errors.push(error.message));
    page.on('dialog',async dialog=>{record.errors.push('Unexpected dialog: '+dialog.message());await dialog.dismiss();});
    page.on('response', response=>{
      const kind=response.request().resourceType();
      if(['document','script','stylesheet','xhr','fetch'].includes(kind)) {
        record.responses.push({url:response.url(),status:response.status(),kind});
        if(response.status()>=400) record.errors.push(`${kind} ${response.status()}: ${response.url()}`);
      }
    });
    async function navigate(action) { await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),action()]); }
    await page.goto(url,{waitUntil:'networkidle'});
    record.runtime=await (await context.request.get(base+'/__environment.php')).json();
    expect('Japanese login label',await page.locator('#login_button').inputValue(),'ログイン');
    expect('jQuery version',await page.evaluate(()=>jQuery.fn.jquery),'4.0.0');
    record.steps.push('UI login using public fixture credentials');
    await page.locator('#ft_user').fill('stage2');await page.locator('#ft_pass').fill('local-only-filethingie');
    await navigate(()=>page.locator('#login_button').click());
    expect('Authenticated file list',await page.locator('#filelist').count(),1);
    const name='stage4-ブラウザー\'"&<img src=x>.txt', name2='stage4-browser empty.txt';
    const content=Buffer.from('ブラウザー UTF-8\n    preserve spaces\tTAB\n');
    record.steps.push({operation:'select two files and submit upload',files:[{name,sha256:hash(content)},{name:name2,sha256:hash(Buffer.alloc(0))}]});
    await page.locator('#uploadsection input[type=file]:not(.safarihide)').setInputFiles({name,mimeType:'text/plain',buffer:content});
    await page.locator('#uploadsection input[type=file]:not(.safarihide)').setInputFiles({name:name2,mimeType:'text/plain',buffer:Buffer.alloc(0)});
    expect('Two selected files',await page.locator('#files_list li').count(),2);
    expect('Uploaded names rendered as text',await page.locator('#files_list img').count(),0);
    await navigate(()=>page.locator('#uploadbutton input').click());
    function row(filename) { return page.locator('tr[data-filename='+JSON.stringify(filename)+']'); }
    expect('Long filename preserved',await row(name).count(),1);
    await row(name).locator('td.details span.show').click();
    expect('Full name in action form',await page.locator('.filedetails input[name=file]').inputValue(),name);
    expect('No filename HTML injection',await page.locator('.filedetails img').count(),0);
    expect('CSRF in dynamic form', (await page.locator('.filedetails input[name=csrf_token]').inputValue()).length,64);
    await page.locator('.filedetails li.edit').click();
    await navigate(()=>page.locator('.filedetails input[type=submit]').click());
    expect('Editor original content',await page.locator('#filecontent').inputValue(),content.toString());
    const edited=content.toString()+'    AJAX保存\n';
    await page.locator('#filecontent').fill(edited);
    await Promise.all([page.waitForResponse(response=>response.request().method()==='POST' && response.request().postData()?.includes('act=saveedit')),page.locator('#save').click()]);
    await page.locator('#savestatus .ok').waitFor();
    expect('Spaces preserved with unchecked option',(await (await context.request.get(url+'?method=getfile&file='+encodeURIComponent(name))).body()).toString(),edited);
    await page.locator('#convertspaces').check();
    await navigate(()=>page.locator('#edit input[type=submit]').first().click());
    const converted=edited.replaceAll('    ','\t');
    const downloadPromise=page.waitForEvent('download');await row(name).locator('td.name a').click();
    const download=await downloadPromise;await download.saveAs(path.join(output,'download.txt'));
    expect('UI downloaded content',hash(fs.readFileSync(path.join(output,'download.txt'))),hash(converted));
    record.steps.push('Normal save with spaces-to-tabs, then UI download');
    await page.locator('#q').fill('ブラウザー');
    await Promise.all([page.waitForResponse(response=>response.request().postData()?.includes('act=search')),page.locator('#dosearch').click()]);
    await page.locator('#searchlist a').first().waitFor();
    expect('Search result full filename in URL',(await page.locator('#searchlist a').first().getAttribute('href')).includes(encodeURIComponent(name).replaceAll("'",'%27')),true);
    await page.screenshot({path:path.join(output,'files.png'),fullPage:true});
    record.steps.push('Search Japanese name and inspect logs');
    await navigate(()=>page.locator('#secondary_menu a[href*="act=log"]').click());
    await page.locator('#log_table th button').first().click();
    const ascending=await page.locator('#log_table tbody tr td:first-child').allTextContents();
    expect('Log ascending sort',ascending,[...ascending].sort((a,b)=>a.localeCompare(b)));
    await page.locator('#log_table th button').first().click();
    const descending=await page.locator('#log_table tbody tr td:first-child').allTextContents();
    expect('Log descending sort',descending,[...descending].sort((a,b)=>b.localeCompare(a)));
    await page.screenshot({path:path.join(output,'logs.png'),fullPage:true});
    await navigate(()=>page.locator('#logout button').click());
    expect('Logout form',await page.locator('#loginbox').count(),1);
    expect('No JavaScript or required asset errors',record.errors,[]);
    record.verdict='PASS';
  } catch(error) {
    if(record.verdict!=='BLOCKED') record.verdict='FAIL';
    record.error=error.stack;
    if(page) {await page.screenshot({path:path.join(output,'failure.png'),fullPage:true}).catch(()=>{});fs.writeFileSync(path.join(output,'failure.html'),await page.content());}
  } finally {
    if(browser) await browser.close();
    try {
      const logs=command(['docker','compose','logs','--no-color','--timestamps','--since',record.started_at,service]);
      fs.writeFileSync(path.join(output,'container.log'),logs);
      record.php_diagnostics=logs.match(/.*PHP (?:Fatal error|Parse error|Warning|Deprecated|Notice):.*/g)||[];
      if(record.php_diagnostics.length) record.verdict='FAIL';
    } catch(error) {record.observation_error=String(error);if(record.verdict==='PASS')record.verdict='INCONCLUSIVE';}
    record.finished_at=new Date().toISOString();fs.writeFileSync(path.join(output,'result.json'),JSON.stringify(record,null,2)+'\n');
    console.log(JSON.stringify({case_id:record.case_id,verdict:record.verdict,error:record.error?.split('\n')[0],errors:record.errors}));
    process.exitCode=record.verdict==='PASS'?0:1;
  }
}
main();
