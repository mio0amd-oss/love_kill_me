<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) { http_response_code(500); exit('config.php is missing.'); }
$config = require $configFile;

ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Strict');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure','1');
session_name($config['session_name'] ?? 'subkazani_admin');
session_start();

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function cfg(string $key, $default=null){ global $config; return $config[$key] ?? $default; }
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if(!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('درخواست نامعتبر است.'); } }
function github_request(string $method, string $path, ?array $body=null): array {
    $url='https://api.github.com'.$path;
    $ch=curl_init($url);
    $headers=['Accept: application/vnd.github+json','Authorization: Bearer '.cfg('github_token'),'X-GitHub-Api-Version: 2022-11-28','User-Agent: subkazani-admin'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10]);
    if($body!==null){$headers[]='Content-Type: application/json';curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    $raw=curl_exec($ch);$errno=curl_errno($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($errno) throw new RuntimeException('خطای ارتباط با GitHub: '.$error);
    $data=json_decode((string)$raw,true); if(!is_array($data)) $data=[];
    if($status<200||$status>=300) throw new RuntimeException('GitHub API '.$status.': '.($data['message']??'خطای نامشخص'));
    return $data;
}
function repoPath(string $path): string { return '/repos/'.rawurlencode(cfg('github_owner')).'/'.rawurlencode(cfg('github_repo')).'/contents/'.str_replace('%2F','/',rawurlencode($path)); }
function read_content(): array {
    $r=github_request('GET',repoPath('data/content.json').'?ref='.rawurlencode(cfg('github_branch')));
    $decoded=base64_decode(preg_replace('/\s+/','',$r['content']??''),true); if($decoded===false) throw new RuntimeException('content.json قابل خواندن نیست.');
    $data=json_decode($decoded,true); if(!is_array($data)) throw new RuntimeException('ساختار content.json خراب است.');
    $data['music']=is_array($data['music']??null)?$data['music']:[]; $data['texts']=is_array($data['texts']??null)?$data['texts']:[];
    return [$data,$r['sha']??''];
}
function write_content(array $data,string $sha,string $message): array {
    $json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
    return github_request('PUT',repoPath('data/content.json'),['message'=>$message,'content'=>base64_encode($json),'branch'=>cfg('github_branch'),'sha'=>$sha]);
}
function github_file_sha(string $path): ?string {
    try{$r=github_request('GET',repoPath($path).'?ref='.rawurlencode(cfg('github_branch')));return $r['sha']??null;}catch(Throwable $e){if(str_contains($e->getMessage(),'GitHub API 404')) return null;throw $e;}
}
function upload_audio(string $tmp,string $name): string {
    $bytes=file_get_contents($tmp); if($bytes===false) throw new RuntimeException('خواندن فایل صوتی ناموفق بود.');
    $safe=preg_replace('/[^A-Za-z0-9._-]+/','_',basename($name));
    if(!preg_match('/\.mp3$/i',$safe)) throw new RuntimeException('فقط فایل MP3 مجاز است.');
    $path='music/'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'-'.$safe;
    github_request('PUT',repoPath($path),['message'=>'Add music '.$safe,'content'=>base64_encode($bytes),'branch'=>cfg('github_branch')]);
    return $path;
}
function delete_repo_file(string $path): void {
    $sha=github_file_sha($path); if(!$sha) return;
    github_request('DELETE',repoPath($path),['message'=>'Delete music file','sha'=>$sha,'branch'=>cfg('github_branch')]);
}
function redirect_self(): never { header('Location: index.php'); exit; }

if(isset($_GET['logout'])){ session_unset();session_destroy();header('Location: index.php');exit; }
$error='';$notice='';
if(empty($_SESSION['admin'])){
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        $now=time(); $last=(int)($_SESSION['login_window']??0); $attempts=(int)($_SESSION['login_attempts']??0);
        if($now-$last>900){$attempts=0;$_SESSION['login_window']=$now;}
        if($attempts>=5){$error='تلاش‌های ورود زیاد است؛ ۱۵ دقیقه بعد دوباره امتحان کنید.';}
        else {$_SESSION['login_attempts']=$attempts+1;if(password_verify((string)($_POST['password']??''),(string)cfg('admin_password_hash',''))){session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['login_attempts']=0;$_SESSION['csrf']=bin2hex(random_bytes(32));redirect_self();}else{$error='رمز عبور اشتباه است.';}}
    }
    ?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ورود مدیریت</title><style>body{font-family:Tahoma,sans-serif;background:#090202;color:#fff;display:grid;place-items:center;min-height:100vh}.box{width:min(420px,90vw);background:#160606;border:1px solid #542020;border-radius:16px;padding:28px;box-shadow:0 20px 60px #000}input,button{width:100%;padding:13px;margin-top:10px;border-radius:10px;border:1px solid #633;background:#0b0202;color:#fff}button{background:#721313;cursor:pointer}.err{color:#ff8d8d;margin-top:12px}</style></head><body><form class="box" method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><h2>پنل مدیریت subkazani</h2><p>ورود امن مدیریت</p><input type="password" name="password" autocomplete="current-password" placeholder="رمز عبور" required><button>ورود</button><?php if($error):?><div class="err"><?=h($error)?></div><?php endif;?></form></body></html><?php exit;
}

try {
    [$data,$sha]=read_content();
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf(); $action=$_POST['action']??'';
        if($action==='save_settings'){
            $data['settings']['quote']=['fa'=>trim((string)$_POST['quote_fa']),'en'=>trim((string)$_POST['quote_en'])];$data['settings']['footer']=trim((string)$_POST['footer']);write_content($data,$sha,'Update site settings');$notice='تنظیمات ذخیره شد.';
        } elseif($action==='save_text'){
            $id=trim((string)$_POST['id']);if(!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$id)) throw new RuntimeException('شناسه نامعتبر است.');$item=['id'=>$id,'fa'=>trim((string)$_POST['fa']),'en'=>trim((string)$_POST['en']),'position'=>(int)$_POST['position'],'active'=>isset($_POST['active'])];$found=false;foreach($data['texts'] as &$x){if(($x['id']??'')===$id){$x=$item;$found=true;break;}}unset($x);if(!$found)$data['texts'][]=$item;write_content($data,$sha,$found?'Update text '.$id:'Add text '.$id);$notice='متن ذخیره شد.';
        } elseif($action==='delete_text'){
            $id=(string)$_POST['id'];$data['texts']=array_values(array_filter($data['texts'],fn($x)=>($x['id']??'')!==$id));write_content($data,$sha,'Delete text '.$id);$notice='متن حذف شد.';
        } elseif($action==='save_music'){
            $id=trim((string)$_POST['id']);if(!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$id)) throw new RuntimeException('شناسه نامعتبر است.');$existing=null;foreach($data['music'] as $x)if(($x['id']??'')===$id)$existing=$x;
            $file=$existing['file']??'';
            if(isset($_FILES['audio']) && $_FILES['audio']['error']!==UPLOAD_ERR_NO_FILE){if($_FILES['audio']['error']!==UPLOAD_ERR_OK)throw new RuntimeException('آپلود فایل ناموفق بود.');if((int)$_FILES['audio']['size']>(int)cfg('max_audio_bytes',31457280))throw new RuntimeException('حجم فایل بیش از حد مجاز است.');$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($_FILES['audio']['tmp_name']);if($mime!=='audio/mpeg')throw new RuntimeException('فایل انتخابی MP3 معتبر نیست.');$file=upload_audio($_FILES['audio']['tmp_name'],$_FILES['audio']['name']);}
            if($file==='')throw new RuntimeException('برای موسیقی فایل MP3 لازم است.');$item=['id'=>$id,'file'=>$file,'fa'=>trim((string)$_POST['fa']),'en'=>trim((string)$_POST['en']),'position'=>(int)$_POST['position'],'side'=>($_POST['side']??'left')==='right'?'right':'left','active'=>isset($_POST['active'])];$found=false;foreach($data['music'] as &$x){if(($x['id']??'')===$id){$old=$x;$x=$item;$found=true;break;}}unset($x);if(!$found)$data['music'][]=$item;
            try{write_content($data,$sha,$found?'Update music '.$id:'Add music '.$id);}catch(Throwable $e){if(!$found && isset($file)&&$file!=='')try{delete_repo_file($file);}catch(Throwable $ignore){}throw $e;}
            if($found && isset($old['file']) && $old['file']!==$file && $old['file']!=='')try{delete_repo_file($old['file']);}catch(Throwable $ignore){}
            $notice='موسیقی ذخیره شد.';
        } elseif($action==='delete_music'){
            $id=(string)$_POST['id'];$removed=null;$new=[];foreach($data['music'] as $x){if(($x['id']??'')===$id)$removed=$x;else$new[]=$x;}$data['music']=$new;write_content($data,$sha,'Delete music '.$id);if($removed && !empty($removed['file']))try{delete_repo_file((string)$removed['file']);}catch(Throwable $ignore){}$notice='موسیقی حذف شد.';
        } else { throw new RuntimeException('عملیات نامعتبر است.'); }
        [$data,$sha]=read_content();
    }
} catch(Throwable $e) { $error=$e->getMessage(); }
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>مدیریت subkazani</title><style>
body{margin:0;background:#080202;color:#eee;font-family:Tahoma,Arial,sans-serif}.wrap{max-width:1100px;margin:auto;padding:24px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.card{background:#140606;border:1px solid #4b1c1c;border-radius:16px;padding:18px;margin-top:18px}.full{grid-column:1/-1}input,textarea,select,button{width:100%;box-sizing:border-box;background:#090303;color:#fff;border:1px solid #5b2525;border-radius:9px;padding:10px;margin:5px 0 10px}textarea{min-height:100px;resize:vertical}button{background:#741616;cursor:pointer}button.danger{background:#3d0d0d}.row{display:flex;gap:8px;align-items:end}.row>*{flex:1}.item{border-top:1px solid #351313;padding:12px 0}.muted{color:#aaa;font-size:12px}.ok{color:#9cffb0}.err{color:#ff9191}.tag{display:inline-block;padding:4px 8px;border-radius:20px;background:#2c1010}.link{color:#fff}.check{width:auto}.small{width:auto;padding:8px 14px} @media(max-width:800px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.top{align-items:flex-start;flex-direction:column}}
</style></head><body><div class="wrap"><div class="top"><div><h1>پنل مدیریت subkazani</h1><div class="muted">محتوا مستقیماً در مخزن GitHub ذخیره می‌شود.</div></div><div><a class="link" href="https://subkazani.ir" target="_blank">مشاهده سایت</a> · <a class="link" href="?logout=1">خروج</a></div></div>
<?php if($notice):?><p class="ok"><?=h($notice)?></p><?php endif;?><?php if($error):?><p class="err"><?=h($error)?></p><?php endif;?>
<div class="grid">
<div class="card"><h2>تنظیمات</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="save_settings"><label>متن بالای سایت</label><input name="quote_fa" value="<?=h($data['settings']['quote']['fa']??'')?>"><label>ترجمه</label><input name="quote_en" value="<?=h($data['settings']['quote']['en']??'')?>"><label>فوتر</label><input name="footer" value="<?=h($data['settings']['footer']??'')?>"><button>ذخیره تنظیمات</button></form></div>
<div class="card"><h2>افزودن/ویرایش متن</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="save_text"><label>شناسه</label><input name="id" placeholder="text-001" required><label>متن فارسی</label><textarea name="fa" required></textarea><label>متن انگلیسی</label><textarea name="en"></textarea><div class="row"><div><label>ترتیب</label><input type="number" name="position" value="1"></div><div><label>وضعیت</label><select name="active"><option value="1">فعال</option><option value="0">غیرفعال</option></select></div></div><button>ذخیره متن</button></form></div>
<div class="card full"><h2>افزودن/ویرایش موسیقی</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="save_music"><div class="row"><div><label>شناسه</label><input name="id" placeholder="music-001" required></div><div><label>ترتیب</label><input type="number" name="position" value="1"></div><div><label>سمت متن</label><select name="side"><option value="left">چپ</option><option value="right">راست</option></select></div></div><label>متن فارسی</label><textarea name="fa" required></textarea><label>متن انگلیسی</label><textarea name="en"></textarea><label>فایل MP3</label><input type="file" name="audio" accept="audio/mpeg,.mp3" required><label><input class="check" type="checkbox" name="active" checked> فعال</label><button>ذخیره موسیقی</button><div class="muted">برای ویرایش، همان شناسه را وارد کن. فایل جدید جای فایل قبلی را می‌گیرد.</div></form></div>
<div class="card full"><h2>موسیقی‌های فعلی</h2><?php foreach($data['music'] as $x):?><div class="item"><b><?=h($x['id']??'')?></b> <span class="tag"><?=!empty($x['active'])?'فعال':'غیرفعال'?></span><div class="muted"><?=h($x['fa']??'')?> · فایل: <?=h($x['file']??'')?></div><form method="post" onsubmit="return confirm('این موسیقی حذف شود؟')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="delete_music"><input type="hidden" name="id" value="<?=h($x['id']??'')?>"><button class="danger small">حذف کامل</button></form></div><?php endforeach;if(!$data['music']):?><div class="muted">هنوز موسیقی ثبت نشده است.</div><?php endif;?></div>
<div class="card full"><h2>متن‌های فعلی</h2><?php foreach($data['texts'] as $x):?><div class="item"><b><?=h($x['id']??'')?></b> <span class="tag"><?=!empty($x['active'])?'فعال':'غیرفعال'?></span><div><?=nl2br(h($x['fa']??''))?></div><form method="post" onsubmit="return confirm('این متن حذف شود؟')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="delete_text"><input type="hidden" name="id" value="<?=h($x['id']??'')?>"><button class="danger small">حذف</button></form></div><?php endforeach;if(!$data['texts']):?><div class="muted">هنوز متنی ثبت نشده است.</div><?php endif;?></div>
</div></div></body></html>
