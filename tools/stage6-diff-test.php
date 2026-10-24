<?php
// Disposable CLI fixture; never uses a real installation or credentials.
if (PHP_SAPI!=='cli' || $argc!==2) { exit(2); }
$kit=$argv[1];$home=sys_get_temp_dir().'/ft-diff-test-'.bin2hex(random_bytes(6));
$backup=$home.'/.ft-stage6-backup-20261024-000000-0123456789';$checks=[];
function ftt_check($name,$actual,$expected) {
    global $checks;$pass=$actual===$expected;
    $checks[]=['name'=>$name,'actual'=>$actual,'expected'=>$expected,'verdict'=>$pass?'PASS':'FAIL'];
    if (!$pass) { throw new RuntimeException($name); }
}
function ftt_manifest() {
    global $backup,$home;
    $m=['format'=>1,'home'=>$home,'web_root'=>$home.'/www','entries'=>[]];
    foreach(['index.php','css/ft.css'] as $p) { $m['entries'][$p]=['type'=>'file','sha256'=>hash_file('sha256',$backup.'/app/'.$p)]; }
    $encoded=json_encode($m);file_put_contents($backup.'/manifest.json',$encoded);
    file_put_contents($backup.'/COMPLETE.json',json_encode(['manifest_sha256'=>hash('sha256',$encoded)]));
}
function ftt_run() {
    global $kit,$home,$backup;
    $p=proc_open([PHP_BINARY,$kit.'/sakura-display-diff.php',$backup,$home,$home.'/www'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($p),json_decode($out,true,512,JSON_THROW_ON_ERROR),$err,$out];
}
function ftt_remove($path) {
    if(is_file($path)||is_link($path)) { unlink($path);return; }
    foreach(scandir($path) as $p) { if($p!=='.'&&$p!=='..') { ftt_remove($path.'/'.$p); } }rmdir($path);
}
$record=['case_id'=>str_starts_with(PHP_VERSION,'8.3.')?'TS-606':'TS-607','name'=>'既存表示差分の抽出','php'=>PHP_VERSION,'verdict'=>'INCONCLUSIVE'];
try {
    mkdir($home,0700);mkdir($home.'/www');mkdir($backup);mkdir($backup.'/app');mkdir($backup.'/app/css');
    $originalIndex=file_get_contents($kit.'/upstream/index.php');$originalCss=file_get_contents($kit.'/upstream/ft.css');
    $index=str_replace('<title>File Thingie ','<title>Custom design ',$originalIndex);
    $css=preg_replace('/background:#fff;/','background:#abc;',$originalCss,1)."\n/* $home/private-image */\n";
    file_put_contents($backup.'/app/index.php',str_replace("\n","\r\n",$index));
    file_put_contents($backup.'/app/css/ft.css',str_replace("\n","\r\n",$css));
    $config="<?php file_put_contents(".var_export($home.'/executed',true).", 'fixture-secret-must-not-appear');";
    file_put_contents($backup.'/app/config.php',$config);ftt_manifest();$before=hash_file('sha256',$backup.'/app/index.php');
    [$status,$r,$stderr,$out]=ftt_run();
    ftt_check('差分ありをエラーにしない終了状態',$status,0);ftt_check('PHP診断なし',$stderr,'');
    ftt_check('比較完了',$r['comparison_completed'],true);
    foreach(['index.php','css/ft.css'] as $p) {
        ftt_check($p.'の差分検出',$r['files'][$p]['changed'],true);
        ftt_check($p.'の通常表示差分の出力',$r['files'][$p]['withheld_sensitive_marker'],false);
    }
    ftt_check('タイトル変更の抽出',str_contains($out,'Custom design'),true);
    ftt_check('色変更の抽出',str_contains($out,'background:#abc'),true);
    ftt_check('ホームパスの非出力',str_contains($out,$home),false);
    ftt_check('設定本文の非出力',str_contains($out,'fixture-secret-must-not-appear'),false);
    ftt_check('設定の非実行',file_exists($home.'/executed'),false);
    ftt_check('比較元内容の不変',hash_file('sha256',$backup.'/app/index.php'),$before);
    ftt_check('自作した一時ファイルの後片付け',glob($home.'/.ft-stage6-diff-*'),[]);
    file_put_contents($backup.'/app/index.php',str_replace("\n","\r\n",$originalIndex));
    file_put_contents($backup.'/app/css/ft.css',str_replace("\n","\r\n",$originalCss));ftt_manifest();
    [$status,$r,$stderr]=ftt_run();
    ftt_check('CRLFだけの差を無視',$r['files']['index.php']['changed'],false);
    ftt_check('CSSのCRLFだけの差を無視',$r['files']['css/ft.css']['changed'],false);
    file_put_contents($backup.'/app/css/ft.css',$originalCss."\n/* password=fixture-password-must-not-leak */\n");ftt_manifest();
    [$status,$r,$stderr,$out]=ftt_run();
    ftt_check('認証語検出時の差分保留',$r['files']['css/ft.css']['withheld_sensitive_marker'],true);
    ftt_check('保留した差分の非出力',$r['files']['css/ft.css']['diff_lines'],[]);
    ftt_check('検出した秘密文字列の非出力',str_contains($out,'fixture-password-must-not-leak'),false);
    file_put_contents($backup.'/app/index.php',$originalIndex.'tampered');
    [$status,$r,$stderr]=ftt_run();ftt_check('保全コピーの改変拒否',$status,2);
    ftt_check('改変時に比較完了を主張しない結果',$r['comparison_completed'],false);
    file_put_contents($backup.'/app/index.php',$originalIndex);ftt_manifest();
    rename($backup.'/app/index.php',$backup.'/app/linked-source');symlink($backup.'/app/linked-source',$backup.'/app/index.php');
    [$status,$r,$stderr]=ftt_run();ftt_check('比較元リンクの拒否',$status,2);
    $record['verdict']='PASS';
} catch(Throwable $error) {
    $record['verdict']='FAIL';$record['error']=$error->getMessage();
} finally {
    if(is_dir($home)) { ftt_remove($home); }
    $record['checks']=$checks;echo json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
}
exit($record['verdict']==='PASS'?0:1);
