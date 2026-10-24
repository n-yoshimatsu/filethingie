<?php
// Synthetic, disposable local fixture. Never uses production paths or credentials.
if(PHP_SAPI!=='cli'||$argc!==4) {exit(2);}
[$kit,$base,$url]=array_slice($argv,1);require $kit.'/sakura-rehearsal.php';
$home=$base.'/home';$app=$home.'/www/app';$checks=[];
function check_r($label,$actual,$expected) {
    global $checks;$ok=$actual===$expected;$checks[]=compact('label','actual','expected')+['verdict'=>$ok?'PASS':'FAIL'];
    if(!$ok){throw new RuntimeException($label);}
}
$record=['case_id'=>str_starts_with(PHP_VERSION,'8.3.')?'TS-608':'TS-609','name'=>'Basic認証だけのリハーサル配置','php'=>PHP_VERSION,'verdict'=>'INCONCLUSIVE'];
try {
    mkdir($base,0700);mkdir($home);mkdir($home.'/www');mkdir($app);mkdir($app.'/files');mkdir($app.'/files/existing');
    file_put_contents($app.'/files/既存.txt',"既存の日本語ファイル\n");file_put_contents($app.'/files/empty.txt','');
    file_put_contents($app.'/files/existing/retained.txt','preserve');chmod($app.'/files/existing',0751);
    $index= <<<'OLD'
<?php
// Nonexecuted legacy display excerpts for a synthetic fixture.
ft_make_link(t("会議 <&>"), '', t("Go to home folder"));
<table id='filelist' style='width: 420px'>
<div id="sidebar" style="width: 280px">
<input size="12" style="width: 260px" />
<title>Fixture &amp; files</title>
<link rel="icon" href="favicon.ico">
return "<div id=\"footer\"></div>";
<!-- <input type="radio" name="type" value="file" /> -->
<!-- <input type="radio" name="type" value="url" /> -->
duplicate_link: "<?php echo t('');?>"
unzip_link: "<?php echo t('');?>"
$str .= '<h3><a href="./"><u>&lt;Fixture files&gt;</u></a></h3>';
$str .= '<p>';
$str .= '同じファイル名の登録は、上書きされます。<br />';
$str .= 'ファイル名の案内。<br />';
$str .= '日付と登録者名の追加。<br />';
$str .= '引用符 &quot; &amp; &lt;script&gt;。<br />';
$str .= '</p>';
OLD;
    file_put_contents($app.'/index.php',$index);mkdir($app.'/css');file_put_contents($app.'/css/ft.css',"#background:#326532 !important;\nbackground:#8d93c8 !important;\n");
    file_put_contents($app.'/favicon.ico',hex2bin('0000010001000101000001002000300000001600000028000000010000000200000001002000000000000400000000000000000000000000000000000000c8938dff00000000'));
    $config=ftb_read($kit.'/app/config.sample.php');
    foreach (['DIR'=>'files','LOGIN'=>false,'HIDEFILEPATHS'=>false,'HTTPS'=>true,'MAXSIZE'=>52428800] as $key=>$value) {
        $pattern='/(\$ft\["settings"\]\["'.$key.'"\]\s*=\s*)[^;]+;/';
        $config=preg_replace_callback($pattern,fn($m)=>$m[1].var_export($value,true).';',$config,1,$count);
        ftb_need($count===1,'Complete fixture setting not found.');
    }
    file_put_contents($app.'/config.php',$config);
    file_put_contents($app.'/.htpasswd','fixture:'.password_hash('local-rehearsal-only',PASSWORD_BCRYPT)."\n");
    file_put_contents($app.'/.htaccess',"AuthType Basic\nAuthName \"Local rehearsal\"\nAuthUserFile \"$app/.htpasswd\"\nRequire valid-user\n");
    $before=ftb_tree($app);$backup=ftb_new($home,$home.'/www','backup');ftb_copy_tree($app,$backup.'/app',$before);ftb_mkdir($backup.'/extra');
    $m=['format'=>1,'home'=>$home,'web_root'=>$home.'/www','application'=>$app,'managed_relative'=>'files','entries'=>$before,'extra'=>[]];
    $encoded=ftb_json($m);ftb_write($backup.'/manifest.json',$encoded);ftb_write($backup.'/COMPLETE.json',ftb_json(['manifest_sha256'=>hash('sha256',$encoded)]));
    $sourceBackup=ftb_tree($backup);$created=null;
    $result=ftr_run($backup,$home,$home.'/www',$url,$created);$public=$app.'/'.$result['rehearsal_id'];
    check_r('配置状態',$result['verdict'],'PREPARED');
    check_r('設定非実行',defined('USERNAME'),false);
    check_r('保全コピー不変',ftb_tree($backup)===$sourceBackup,true);
    $unchanged=true;foreach($before as $rel=>$entry) {if($rel!=='.' && ftb_entry($app.'/'.$rel)!==$entry){$unchanged=false;}}
    check_r('既存ファイル・子フォルダー不変',$unchanged,true);
    check_r('複製ファイル内容',file_get_contents($public.'/files/既存.txt'),"既存の日本語ファイル\n");
    check_r('複製空ファイル',filesize($public.'/files/empty.txt'),0);
    check_r('複製権限',fileperms($public.'/files/existing')&0777,0751);
    check_r('複製更新時刻',filemtime($public.'/files/既存.txt'),$before['files/既存.txt']['mtime']);
    check_r('別inode',fileinode($public.'/files/既存.txt')!==fileinode($app.'/files/既存.txt'),true);
    check_r('非公開親0700',fileperms($created)&0777,0700);
    check_r('非公開設定0600',fileperms($created.'/config.php')&0777,0600);
    check_r('認証情報の公開複製なし',file_exists($public.'/.htpasswd'),false);
    check_r('既存のBasic認証参照',str_contains(file_get_contents($public.'/.htaccess'),$app.'/.htpasswd'),true);
    $deployment=json_decode(file_get_contents($created.'/deployment.json'),true);
    check_r('タイトル復号',$deployment['presentation']['title'],'Fixture & files');
    check_r('案内文修正',str_starts_with($deployment['presentation']['notice_text'],'同じファイル名の登録は、上書きしません。'),true);
    check_r('配色復元',$deployment['presentation']['accent'],'#8d93c8');
    check_r('幅復元',array_intersect_key($deployment['presentation'],array_flip(['filelist_width','sidebar_width','upload_width'])),['filelist_width'=>420,'sidebar_width'=>280,'upload_width'=>260]);
    foreach (['Require all granted','AuthType Digest','RewriteEngine On'] as $extra) {
        $rejected=false;try{ftr_auth(file_get_contents($app.'/.htaccess')."\n".$extra,$home);}catch(RuntimeException $e){$rejected=true;}
        check_r('未対応認証の停止 '.$extra,$rejected,true);
    }
    $bad=ftb_tree($backup);file_put_contents($backup.'/app/files/empty.txt','tamper');$rejected=false;
    try{$unused=null;ftr_run($backup,$home,$home.'/www',$url,$unused);}catch(RuntimeException $e){$rejected=true;}
    check_r('改変バックアップ拒否',$rejected,true);check_r('拒否時の新規先なし',$unused,null);
    file_put_contents($backup.'/app/files/empty.txt','');touch($backup.'/app/files/empty.txt',$bad['app/files/empty.txt']['mtime']);
    check_r('バックアップ復旧',ftb_tree($backup)===$sourceBackup,true);
    $record['verdict']='PASS';$record['fixture_url']=$result['preview_url'];
    // Local-only state for the following browser case. Not part of public evidence.
    file_put_contents($base.'/state.json',ftb_json(['public'=>$public,'private'=>$created,'original'=>$app,'url'=>$result['preview_url']]));
} catch(Throwable $e) {$record['verdict']='FAIL';$record['error']=$e->getMessage();}
$record['checks']=$checks;echo ftb_json($record);exit($record['verdict']==='PASS'?0:1);
