<?php
// CLI-only: build a fresh Basic-authenticated rehearsal using a completed backup.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/sakura-backup.php';

function ftr_capture($pattern, $source) {
    ftb_need(preg_match($pattern,$source,$m)===1,'Display customization differs from the reviewed format.');
    return $m[1];
}
function ftr_presentation($index,$css) {
    // Only the reviewed literal display fragments. No include, eval or PHP execution.
    $title=ftr_capture('~<title>([^<\r\n]+)</title>~u',$index);
    $label=ftr_capture('~ft_make_link\(t\("([^"\\\\\r\n]+)"\),~u',$index);
    $heading=ftr_capture('~<h3><a href="\./"><u>([^<]+)</u></a></h3>~u',$index);
    $notice=ftr_capture('~\$str \.= \'<p>\';(.*?)\$str \.= \'</p>\';~su',$index);
    $lines=[];
    foreach (preg_split('/\R/u',trim($notice)) as $line) {
        $text=ftr_capture('~^\s*\$str \.= \'([^\'\\\\]*)<br />\';\s*$~u',$line);
        ftb_need(!str_contains($text,'<'),'Unexpected notice markup.');$lines[]=html_entity_decode($text,ENT_QUOTES,'UTF-8');
    }
    ftb_need(count($lines)===4 && $lines[0]==='同じファイル名の登録は、上書きされます。','Notice requires individual review.');
    $lines[0]='同じファイル名の登録は、上書きしません。';
    // Ignore the legacy invalid "#background" pseudo-comment when finding the active rule.
    $accent=ftr_capture('~^\s*background:(#[0-9a-fA-F]{6}) !important;~m',$css);
    $widths=[];
    foreach (['filelist'=>"id='filelist' style='width: (\\d+)px'",'sidebar'=>'id="sidebar" style="width: (\d+)px"','upload'=>'size="12" style="width: (\d+)px"'] as $name=>$pattern) {
        $widths[$name]=(int)ftr_capture('~'.$pattern.'~',$index);
        ftb_need($widths[$name]>=100 && $widths[$name]<=1600,'Unsupported presentation width.');
    }
    foreach (['return "<div id=\\"footer\\"></div>";', '<link rel="icon" href="favicon.ico">', "duplicate_link: \"<?php echo t('');?>\"", "unzip_link: \"<?php echo t('');?>\"", '<!-- <input type="radio" name="type" value="file"', '<!-- <input type="radio" name="type" value="url"'] as $expected) {
        ftb_need(str_contains($index,$expected),'Display menu or footer requires individual review.');
    }
    return ['title'=>html_entity_decode($title,ENT_QUOTES,'UTF-8'),'home_label'=>$label,
        'notice_heading'=>html_entity_decode($heading,ENT_QUOTES,'UTF-8'),'notice_text'=>implode("\n",$lines),
        'accent'=>$accent,'filelist_width'=>$widths['filelist'],'sidebar_width'=>$widths['sidebar'],'upload_width'=>$widths['upload'],
        'favicon'=>true,'hide_footer'=>true,'hide_create_file'=>true,'hide_create_url'=>true,'hide_duplicate'=>true,'hide_unzip'=>true];
}
function ftr_config($source,$presentation,$private) {
    $parsed=ft_inv_config($source);
    ftb_need($parsed['unknown']===0 && !$parsed['dynamic'] && $parsed['otherCalls']===0 && !$parsed['users'] && !$parsed['groups'], 'Configuration requires individual review.');
    foreach (['LOGIN'=>false,'HIDEFILEPATHS'=>false] as $key=>$expected) {
        ftb_need(($parsed['settings'][$key]['literal']??false) && $parsed['settings'][$key]['value']===$expected,'Rehearsal requires literal '.$key.'=false.');
    }
    // Both reviewed relative spellings refer to the copied files directory.
    // Keep the original setting; do not accept arbitrary paths or coerce booleans.
    $dir=$parsed['settings']['DIR']??[];
    ftb_need(($dir['literal']??false) && in_array($dir['value'],['files','./files'],true),
        'Rehearsal DIR must be the literal relative path files or ./files.');
    ftb_need(!array_diff(array_keys($parsed['plugins']),['edit','search']),'Only the reviewed edit/search plugins are supported.');
    ftb_need(($parsed['settings']['PLUGINDIR']['value']??'plugins')==='plugins'
        && ($parsed['settings']['REQUEST_URI']['value']??false)===false,'Plugin or request paths require review.');
    // Insert into the unique return statement using tokens, not a match in comments.
    $tokens=token_get_all($source);$offset=0;$returns=[];
    foreach ($tokens as $i=>$t) {
        $text=is_array($t)?$t[1]:$t;
        if (is_array($t) && $t[0]===T_RETURN) {
            $tail=substr($source,$offset);ftb_need(preg_match('/^return\s+\$ft\s*;/',$tail)===1,'Unsupported configuration return.');$returns[]=$offset;
        }
        $offset+=strlen($text);
    }
    ftb_need(count($returns)===1,'Unique settings return required.');
    $insertion='$ft[\'presentation\'] = '.var_export($presentation,true).";\n  ";
    $insertion.='$ft["settings"]["FILEBLACKLIST"] = trim(($ft["settings"]["FILEBLACKLIST"] ?? "")." .htaccess");'."\n  ";
    $source=substr_replace($source,$insertion,$returns[0],0);
    ftb_need(str_starts_with($source,'<?php'),'Plain PHP configuration required.');
    $logging="\nerror_reporting(E_ALL);\nini_set('display_errors','0');\nini_set('log_errors','1');\nini_set('error_log',".var_export($private.'/php-error.log',true).");\n";
    return substr_replace($source,$logging,5,0);
}
function ftr_auth($source,$home) {
    // Fail closed on unreviewed directives instead of guessing their authentication semantics.
    $required=[];
    foreach (preg_split('/\R/',$source) as $line) {
        $line=trim($line);if ($line==='' || $line[0]==='#') {continue;}
        ftb_need(preg_match('/^(AuthType|AuthName|AuthUserFile|Require)\s+(.+)$/iD',$line,$m)===1,'Additional .htaccess directives require review before publication.');
        $key=strtolower($m[1]);ftb_need(!isset($required[$key]),'Repeated authentication directives require review.');$required[$key]=$m[2];
    }
    ftb_need(strtolower($required['authtype']??'')==='basic' && strtolower($required['require']??'')==='valid-user'
        && isset($required['authname'],$required['authuserfile']),'Basic valid-user authentication required.');
    $ref=trim($required['authuserfile'],'"');$resolved=realpath($ref);
    ftb_need($resolved && $resolved===$ref && ftb_inside($resolved,$home) && !is_link($ref) && is_file($ref),'Existing authentication reference required.');
    // Preserve the existing realm and live credential file; shared Apache can keep reading it.
    return rtrim($source)."\nOptions -Indexes\n<FilesMatch \"^(?:config(?:\\.sample)?\\.php|php\\.ini.*|\\.user\\.ini|\\..*)$\">\n  Require all denied\n</FilesMatch>\n";
}
function ftr_run($backupArg,$homeArg,$webArg,$baseUrl,&$created) {
    [$home,$web]=ftb_context($homeArg,$webArg);[$backup,$m]=ftb_load_backup($backupArg,$home,$web);
    $app=$m['application'];
    ftb_need(realpath($app)===$app && is_dir($app) && ftb_inside($app,$web),'Original application parent is required.');
    ftb_need(filter_var($baseUrl,FILTER_VALIDATE_URL) && parse_url($baseUrl,PHP_URL_SCHEME)==='https'
        && parse_url($baseUrl,PHP_URL_QUERY)===null && parse_url($baseUrl,PHP_URL_FRAGMENT)===null
        && parse_url($baseUrl,PHP_URL_USER)===null,'HTTPS application base URL required.');
    ftb_need($m['managed_relative']==='files','Reviewed direct files directory required.');
    // Verify kit files before any destination exists.
    $manifest=json_decode(ftb_read(__DIR__.'/rehearsal-manifest.json'),true,512,JSON_THROW_ON_ERROR);
    foreach ($manifest['files'] as $rel=>$sha) { ftb_path($rel);ftb_need(hash('sha256',ftb_read(__DIR__.'/app/'.$rel))===$sha,'Rehearsal application checksum mismatch.'); }
    $index=ftb_read($backup.'/app/index.php');$css=ftb_read($backup.'/app/css/ft.css');
    $presentation=ftr_presentation(str_replace("\r\n","\n",$index),str_replace("\r\n","\n",$css));
    $auth=ftr_auth(ftb_read($backup.'/app/.htaccess'),$home);
    ftb_need(hash_file('sha256',$app.'/.htaccess')===$m['entries']['.htaccess']['sha256'],'Production authentication changed since the backup; review required.');
    $entries=[];
    foreach ($m['entries'] as $rel=>$entry) {
        if ($rel!=='files' && !str_starts_with($rel,'files/')) {continue;}
        // Server-control files could override Basic authentication or execute content.
        if ($rel!=='files') { ftb_need(!in_array(strtolower(basename($rel)),['.htaccess','.user.ini','php.ini'],true),'Managed server-control files require review.'); }
        $entries[$rel==='files'?'.':substr($rel,6)]=$entry;
    }
    ftb_need(isset($entries['.']),'Missing managed directory.');
    ftb_need(disk_free_space($home)>array_sum(array_column($entries,'bytes'))+16777216,'Insufficient filesystem space; account quota is separate.');
    $created=ftb_new($home,$web,'rehearsal');$stage=$created.'/public';ftb_mkdir($stage);
    $config=ftr_config(ftb_read($backup.'/app/config.php'),$presentation,$created);
    ftb_write($created.'/config.php',$config);ftb_write($created.'/php-error.log','');
    foreach ($manifest['files'] as $rel=>$sha) {
        $target=$stage.'/'.$rel;if(!is_dir(dirname($target))) {ftb_need(mkdir(dirname($target),0755,true),'Could not create asset directory.');}
        ftb_write($target,ftb_read(__DIR__.'/app/'.$rel));ftb_need(chmod($target,0644),'Asset permission failed.');
    }
    ftb_write($stage.'/config.php',"<?php\nrequire ".var_export($created.'/config.php',true).";\n");
    ftb_write($stage.'/.htaccess',$auth);ftb_need(chmod($stage.'/.htaccess',0644),'Authentication permission failed.');
    ftb_copy($backup.'/app/favicon.ico',$stage.'/favicon.ico',$m['entries']['favicon.ico'],true);
    ftb_copy_tree($backup.'/app/files',$stage.'/files',$entries,true);
    ftb_need(ftb_tree($stage.'/files')===$entries,'Managed copy mismatch.');
    $policy="Options -Indexes -ExecCGI\n<FilesMatch \"(?i:\\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|shtml)(?:\\.|$))|^\\.\">\n Require all denied\n</FilesMatch>\n";
    ftb_write($stage.'/files/.htaccess',$policy);ftb_need(chmod($stage.'/files/.htaccess',0644),'Managed policy permission failed.');
    ftb_need(chmod($stage,0755),'Public directory permission failed.');
    $id=substr(basename($created),1);$public=$app.'/'.$id;
    ftb_need(!file_exists($public) && !is_link($public),'New public destination required.');
    ftb_write($created.'/deployment.json',ftb_json(['backup'=>basename($backup),'private'=>$created,'public'=>$public,
        'application'=>$manifest,'managed_entries'=>$entries,'presentation'=>$presentation,'credential_reference'=>'Existing unchanged Basic AuthUserFile']));
    // Only a fully assembled, Basic-protected directory is moved into the web tree.
    ftb_need(rename($stage,$public),'Could not publish new rehearsal directory. Private preparation retained.');
    $result=['scope'=>'段階6 保全コピーからのBasic認証だけの移行リハーサル配置。既存アプリの切替なし',
        'checked_at_utc'=>gmdate('c'),'php'=>PHP_VERSION,'verdict'=>'PREPARED','rehearsal_id'=>$id,
        'preview_url'=>rtrim($baseUrl,'/').'/'.$id.'/index.php','managed_files'=>count(array_filter($entries,fn($v)=>$v['type']==='file')),
        'managed_copy_hashes_modes_mtime_match'=>true,'configuration_executed_during_setup'=>false,
        'production_existing_files_modified'=>false,'basic_credentials_reused'=>true,'application_login'=>false,
        'display_customization_imported'=>true,'same_name_notice_corrected'=>true,'web_acceptance'=>'NOT RUN'];
    ftb_write($created.'/summary.json',ftb_json($result));return $result;
}
if (realpath($_SERVER['SCRIPT_FILENAME']??'')!==__FILE__) { return; }
$created=null;
try {
    ftb_need($argc===5,'Usage: php sakura-rehearsal.php BACKUP HOME WEB_ROOT HTTPS_BASE_URL');
    echo ftb_json(ftr_run($argv[1],$argv[2],$argv[3],$argv[4],$created));
} catch (Throwable $error) {
    echo ftb_json(['verdict'=>'INCOMPLETE','message'=>get_class($error)===RuntimeException::class?$error->getMessage():'Metadata or runtime error.',
        'private_directory_id'=>$created?basename($created):null,'production_existing_files_modified'=>false]);exit(2);
}
