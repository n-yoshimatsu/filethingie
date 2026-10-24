// Local synthetic Basic-only migration rehearsal through normal Chrome operations.
const {chromium,request}=require('playwright-core');
const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process'),crypto=require('node:crypto');
const [service,outArg,variant]=process.argv.slice(2),out=path.resolve(outArg),container='ft-stage6-'+service;
if(variant && variant!=='relative-dir') throw Error('Unsupported case variant');
if(!['php83','php85'].includes(service)) throw Error('Local fixture service required');
fs.mkdirSync(out,{recursive:false});
const state=JSON.parse(cp.execFileSync('docker',['exec',container,'cat','/tmp/ft-rehearsal-local/state.json'],{encoding:'utf8'}));
const url=state.url,base=new URL('./',url).href;
const rec={case_id:variant?(service==='php83'?'TS-614':'TS-615'):(service==='php83'?'TS-610':'TS-611'),name:variant?'相対保存先 ./files の画面・通常操作':'Basic認証だけの画面・通常操作',service,verdict:'INCONCLUSIVE',checks:[],errors:[]};
const hash=b=>crypto.createHash('sha256').update(b).digest('hex');
function eq(label,actual,expected){const ok=JSON.stringify(actual)===JSON.stringify(expected);rec.checks.push({label,actual,expected,verdict:ok?'PASS':'FAIL'});if(!ok)throw Error(label);}
(async()=>{let browser;
try{
 const anon=await request.newContext({ignoreHTTPSErrors:true});
 for(const rel of ['index.php','files/既存.txt','css/ft.css'])eq('未認証401 '+rel,(await anon.get(new URL(rel,base).href)).status(),401);
 await anon.dispose();
 browser=await chromium.launch({executablePath:'/usr/bin/google-chrome',headless:true});rec.browser=browser.version();
 const ctx=await browser.newContext({ignoreHTTPSErrors:true,httpCredentials:{username:'fixture',password:'local-rehearsal-only'},viewport:{width:1280,height:900}});
 const page=await ctx.newPage();page.on('pageerror',e=>rec.errors.push(e.message));
 async function nav(f){await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),f()]);}
 function row(name){return page.locator('tr[data-filename='+JSON.stringify(name)+']');}
 async function actions(name,action){await row(name).locator('td.details span.show').click();await page.locator('.filedetails li.'+action).click();}
 async function send(){await nav(()=>page.locator('.filedetails input[type=submit]').click());}
 await page.goto(url,{waitUntil:'networkidle'});
 eq('アプリログインなし',await page.locator('#login').count(),0);eq('一覧表示',await page.locator('#filelist').count(),1);
 eq('管理設定ファイルの非表示',await row('.htaccess').count(),0);
 eq('ページタイトル',await page.title(),'Fixture & files');eq('見出し文字列',await page.locator('#title a').first().textContent(),'会議 <&>');
 eq('案内文字列・HTML非実行',await page.locator('#site-notice').textContent(),'同じファイル名の登録は、上書きしません。\nファイル名の案内。\n日付と登録者名の追加。\n引用符 " & <script>。');
 eq('案内内スクリプトなし',await page.locator('#site-notice script').count(),0);
 eq('配色',await page.locator('h1').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(141, 147, 200)');
 eq('3箇所の幅',await page.evaluate(()=>['#filelist','#sidebar','#uploadsection input.upload'].map(s=>getComputedStyle(document.querySelector(s)).width)),['420px','280px','260px']);
 eq('作成メニュー',await page.locator('#new input[type=radio]').evaluateAll(es=>es.map(e=>e.value)),['folder']);
 eq('空のフッター',await page.locator('#footer').textContent(),'');eq('favicon',await page.locator('link[rel=icon]').getAttribute('href'),'favicon.ico');
 for(const rel of ['config.php','config.sample.php','.htaccess','files/'])eq('直接取得拒否 '+rel,(await ctx.request.get(new URL(rel,base).href)).status(),403);
 const names=['試験-日本語.txt','試験-empty.txt'],data=[Buffer.from('転送確認 日本語\n'),Buffer.alloc(0)];
 async function upload(name,bytes){await page.locator('#uploadsection input[type=file]:not(.safarihide)').setInputFiles({name,mimeType:'text/plain',buffer:bytes});}
 await upload(names[0],data[0]);await upload(names[1],data[1]);await nav(()=>page.locator('#uploadbutton input').click());
 for(let i=0;i<2;i++){
  eq('アップロード一覧 '+names[i],await row(names[i]).count(),1);
  const link=row(names[i]).locator('td.name a');const href=await link.getAttribute('href');
  eq('直接filesリンク '+names[i],new URL(href,url).pathname.includes('/files/'),true);
  const [download]=await Promise.all([page.waitForEvent('download'),link.click({modifiers:['Alt']})]);
  const target=path.join(out,'download-'+i+'.txt');await download.saveAs(target);
  eq('保存ファイルSHA256 '+names[i],hash(fs.readFileSync(target)),hash(data[i]));

 }
 await upload(names[0],Buffer.from('overwrite attempt'));await nav(()=>page.locator('#uploadbutton input').click());
 eq('同名エラー表示',(await page.locator('body').textContent()).includes('File already exists'),true);
 eq('同名で内容不変',hash(await(await ctx.request.get(base+'files/'+encodeURIComponent(names[0]))).body()),hash(data[0]));
 await row(names[0]).locator('td.details span.show').click();
 eq('複製・解凍項目なし',await page.locator('.filedetails li.duplicate,.filedetails li.unzip').count(),0);
 eq('空メニュー項目なし',await page.locator('.filedetails li').evaluateAll(es=>es.some(e=>e.textContent.trim()==='')),false);
 await page.goto(url,{waitUntil:'networkidle'});await page.locator('#newdir').fill('試験-folder');await nav(()=>page.locator('#mkdirsubmit').click());eq('フォルダー作成',await row('試験-folder').count(),1);
 await actions(names[0],'rename');await page.locator('.filedetails input.newvalue').fill('試験-renamed.txt');await send();eq('名前変更',await row('試験-renamed.txt').count(),1);
 await actions('試験-renamed.txt','edit');await send();eq('編集元内容',await page.locator('#filecontent').inputValue(),data[0].toString());
 await page.locator('#filecontent').fill('編集後 日本語\n');await nav(()=>page.locator('#edit input[type=submit]').first().click());
 eq('編集保存内容',(await(await ctx.request.get(base+'files/'+encodeURIComponent('試験-renamed.txt'))).body()).toString(),'編集後 日本語\n');
 await page.locator('#q').fill('試験-renamed');await Promise.all([page.waitForResponse(r=>r.request().postData()?.includes('act=search')),page.locator('#dosearch').click()]);await page.locator('#searchlist a').first().waitFor();eq('検索結果',(await page.locator('#searchlist').textContent()).includes('試験-renamed'),true);
 await page.goto(url,{waitUntil:'networkidle'});await actions('試験-renamed.txt','move');await page.locator('.filedetails input.newvalue').fill('/試験-folder');await send();eq('移動元から除去',await row('試験-renamed.txt').count(),0);
 await nav(()=>row('試験-folder').locator('td.name a').click());eq('移動先表示',await row('試験-renamed.txt').count(),1);await actions('試験-renamed.txt','del');await send();eq('ファイル削除',await row('試験-renamed.txt').count(),0);
 await page.goto(url,{waitUntil:'networkidle'});await actions('試験-folder','del');await send();eq('空フォルダー削除',await row('試験-folder').count(),0);
 await actions(names[1],'del');await send();eq('空ファイル削除',await row(names[1]).count(),0);
 eq('CSRFなし変更403',(await ctx.request.post(url,{form:{act:'createdir',type:'folder',newdir:'must-not-create',dir:''}})).status(),403);
 await page.goto(url,{waitUntil:'networkidle'});eq('CSRF拒否で未作成',await row('must-not-create').count(),0);
 const csrf=await page.locator('meta[name=csrf-token]').getAttribute('content');
 eq('管理設定への削除要求拒否',(await ctx.request.post(url,{form:{act:'delete',file:'.htaccess',dir:'',csrf_token:csrf}})).status(),400);
 eq('管理設定ファイルの保持',cp.execFileSync('docker',['exec',container,'test','-f',state.public+'/files/.htaccess']).length,0);
 await page.screenshot({path:path.join(out,'screen.png'),fullPage:true});
 eq('PHPログ空',cp.execFileSync('docker',['exec',container,'cat',state.private+'/php-error.log'],{encoding:'utf8'}),'');
 eq('元の日本語内容不変',cp.execFileSync('docker',['exec',container,'cat',state.original+'/files/既存.txt'],{encoding:'utf8'}),'既存の日本語ファイル\n');
 eq('ブラウザーエラーなし',rec.errors,[]);rec.verdict='PASS';await ctx.close();
}catch(e){rec.verdict='FAIL';rec.error=e.message;}finally{if(browser)await browser.close();rec.harness_sha256=hash(fs.readFileSync(__filename));fs.writeFileSync(path.join(out,'result.json'),JSON.stringify(rec,null,2)+'\n');console.log(JSON.stringify({case_id:rec.case_id,verdict:rec.verdict,checks:rec.checks.length,error:rec.error}));process.exitCode=rec.verdict==='PASS'?0:1;}
})();
