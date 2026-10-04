<?php
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_DIR.'/uploads');
$configFile = ROOT_DIR.'/config/config.php';
$installLockFile = ROOT_DIR.'/config/install.lock';
$installerContext = defined('INSTALLER_CONTEXT') && INSTALLER_CONTEXT === true;

if (!$installerContext && (!is_file($configFile) || !is_file($installLockFile))) {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $prefix = str_contains($script, '/admin/') ? '../' : '';
    header('Location: '.$prefix.'install/');
    exit;
}

if (!is_file($configFile)) {
    if ($installerContext) {
        $GLOBALS['app_config'] = ['app_key'=>bin2hex(random_bytes(32)),'db'=>[]];
        return;
    }
    return;
}
$GLOBALS['app_config'] = require $configFile;


// Application timezone: all business timestamps shown/stored by this CMS use Honduras time.
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('America/Tegucigalpa');
}

function ensure_visit_tables(): void {
    static $ready=false;
    if($ready)return;
    $pdo=db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_visits (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      visitor_key CHAR(64) NOT NULL,
      path VARCHAR(255) NOT NULL DEFAULT '/',
      visited_at DATETIME NOT NULL,
      visit_date DATE NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_site_visits_date(visit_date),
      INDEX idx_site_visits_visited(visited_at),
      INDEX idx_site_visits_visitor(visitor_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try {
        $pdo->prepare('INSERT IGNORE INTO admin_permissions(permission_key,label) VALUES(?,?)')
            ->execute(['analytics.view','Website analytics']);
        $pdo->exec("INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT 1,id FROM admin_permissions WHERE permission_key='analytics.view'");
    } catch(Throwable) {}
    $ready=true;
}

function record_public_visit(string $path='/'): bool {
    try {
        // Any authenticated administrator previewing the published site is excluded from analytics.
        if(current_admin()) return false;
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET') return false;
        ensure_visit_tables();
        app_session_start();
        if(empty($_SESSION['public_visitor_key'])) {
            $_SESSION['public_visitor_key']=hash('sha256',random_bytes(32));
        }
        $key=(string)$_SESSION['public_visitor_key'];
        $now=new DateTimeImmutable('now',new DateTimeZone('America/Tegucigalpa'));
        $cleanPath=parse_url($path,PHP_URL_PATH)?:'/';
        $cleanPath=substr((string)$cleanPath,0,255);
        $st=db()->prepare('INSERT INTO site_visits(visitor_key,path,visited_at,visit_date) VALUES(?,?,?,?)');
        $st->execute([$key,$cleanPath,$now->format('Y-m-d H:i:s'),$now->format('Y-m-d')]);
        return true;
    } catch(Throwable) { return false; }
}

function honduras_now(): DateTimeImmutable {
    return new DateTimeImmutable('now',new DateTimeZone('America/Tegucigalpa'));
}

function app_config(?string $key=null, mixed $default=null): mixed {
    $cfg=$GLOBALS['app_config']??[];
    if($key===null)return $cfg;
    foreach(explode('.',$key) as $part){if(!is_array($cfg)||!array_key_exists($part,$cfg))return $default;$cfg=$cfg[$part];}
    return $cfg;
}
function db(): PDO {
    static $pdo=null;
    if($pdo instanceof PDO)return $pdo;
    $c=app_config('db',[]);
    $dsn='mysql:host='.$c['host'].';port='.(int)($c['port']??3306).';dbname='.$c['name'].';charset='.($c['charset']??'utf8mb4');
    $pdo=new PDO($dsn,$c['user'],$c['pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    return $pdo;
}

require_once __DIR__.'/AuthSession.php';
function h(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

function rich_text_sanitize(string $html): string {
    $html=str_replace("\0",'',$html);
    do {
        $before=$html;
        $html=preg_replace('~<(script|style|iframe|object|embed|svg|math|form|input|button|textarea|select|option)\b[^>]*>.*?</\1\s*>~is','',$html)??'';
    } while($before!==$html);
    $html=preg_replace('/<!--.*?-->/s','',$html)??'';
    $html=strip_tags($html,'<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h2><h3><a>');
    $html=preg_replace_callback(
        '~<\s*(/?)\s*(p|br|strong|b|em|i|u|s|ul|ol|li|blockquote|h2|h3|a)\b([^>]*)>~i',
        static function(array $m): string {
            $closing=$m[1]==='/';
            $tag=strtolower($m[2]);
            if($closing)return $tag==='br'?'':'</'.$tag.'>';
            if($tag!=='a')return '<'.$tag.'>';
            $attrs=(string)($m[3]??'');
            $href='';
            if(preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is',$attrs,$hm))$href=html_entity_decode(trim($hm[2]),ENT_QUOTES|ENT_HTML5,'UTF-8');
            elseif(preg_match('~\bhref\s*=\s*([^\s>]+)~i',$attrs,$hm))$href=html_entity_decode(trim($hm[1],"\"'"),ENT_QUOTES|ENT_HTML5,'UTF-8');
            if($href===''||!preg_match('~^(?:https?://|mailto:|tel:|/|#)~i',$href))return '<a>';
            return '<a href="'.h($href).'" target="_blank" rel="noopener noreferrer">';
        },
        $html
    )??'';
    return trim($html);
}
function rich_text_has_text(string $html): bool {
    $text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
    return (preg_replace('/[\s\x{00A0}]+/u','',$text)??'')!=='';
}
function rich_text_plain(string $html): string {
    $safe=rich_text_sanitize($html);
    $safe=preg_replace('~<(?:br|/p|/li|/blockquote|/h2|/h3)>~i',"\n",$safe)??$safe;
    $text=html_entity_decode(strip_tags($safe),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $text=preg_replace('/[\t ]+/u',' ',$text)??$text;
    $text=preg_replace('/\h*\R\h*/u',"\n",$text)??$text;
    return trim(preg_replace('/\R{3,}/u',"\n\n",$text)??$text);
}
function rich_text_render(string $html): string {
    $html=trim($html);
    if($html==='')return '';
    if(!preg_match('~</?(?:p|br|strong|b|em|i|u|s|ul|ol|li|blockquote|h2|h3|a)\b~i',$html))return nl2br(h($html));
    return rich_text_sanitize($html);
}
function base_url(string $path=''): string {
    $base=rtrim((string)settings('site_url',''),'/');
    if($base===''){
        $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
        $host=$_SERVER['HTTP_HOST']??'localhost';
        $script=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/'));
        $script=preg_replace('~/(admin|install)$~','',$script)??$script;
        $base=$scheme.'://'.$host.rtrim($script,'/');
    }
    return $base.'/'.ltrim($path,'/');
}
function setting(string $key,mixed $default=''): mixed {return settings($key,$default);}
function settings(?string $key=null,mixed $default=null): mixed {
    static $all=null;
    if($all===null){
        try{$rows=db()->query('SELECT setting_key,setting_value FROM settings')->fetchAll();$all=[];foreach($rows as $r)$all[$r['setting_key']]=$r['setting_value'];}
        catch(Throwable){$all=[];}
    }
    if($key===null)return $all;
    return $all[$key]??$default;
}
function clear_settings_cache(): void { /* request-local cache naturally resets */ }
function save_setting(string $key,string $value): void {
    $st=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $st->execute([$key,$value]);
}
function csrf_token(): string {
    app_session_start();
    if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    app_session_start();
    $sent=(string)($_POST['csrf']??'');
    if(!hash_equals((string)($_SESSION['csrf']??''),$sent))throw new RuntimeException('The session token expired. Refresh the page and try again.');
}
function flash(string $type,string $message): void {app_session_start();$_SESSION['flash']=['type'=>$type,'message'=>$message];}
function consume_flash(): ?array {app_session_start();$f=$_SESSION['flash']??null;unset($_SESSION['flash']);return $f;}
function current_admin(): ?array {
    app_session_start();
    $id=(int)($_SESSION['admin_id']??0);
    if($id<=0)return null;
    if(!AuthSessionManager::validateCurrent())return null;
    static $user=null;
    if($user&&isset($user['id'])&&(int)$user['id']===$id)return $user;
    $st=db()->prepare('SELECT u.*,r.role_name FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id WHERE u.id=? AND u.active=1');
    $st->execute([$id]);
    $user=$st->fetch()?:null;
    if(!$user){
        AuthSessionManager::expire('security');
        return null;
    }
    return $user;
}
function require_login(): void {
    if(current_admin())return;
    $reason=AuthSessionManager::lastExpiryReason();
    if(AuthSessionManager::isAjaxOrApiRequest()){
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'=>false,
            'error'=>'unauthorized',
            'reason'=>$reason?:'authentication_required',
            'message'=>$reason?AuthSessionManager::expiryMessage($reason):'Authentication required.'
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    $target='login.php';
    if($reason)$target.='?expired='.rawurlencode($reason);
    header('Location: '.$target);
    exit;
}
function user_can(string $permission): bool {
    $u=current_admin();if(!$u)return false;
    if(($u['role_name']??'')==='Administrator')return true;
    $st=db()->prepare('SELECT COUNT(*) FROM admin_role_permissions rp JOIN admin_permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.permission_key=?');$st->execute([(int)$u['role_id'],$permission]);return (int)$st->fetchColumn()>0;
}
function require_permission(string $permission): void {require_login();if(!user_can($permission)){http_response_code(403);exit('Access denied.');}}
function log_activity(string $action,string $message,array $context=[]): void {
    try{$u=current_admin();$st=db()->prepare('INSERT INTO activity_log(admin_id,action,message,context_json,ip_address) VALUES(?,?,?,?,?)');$st->execute([$u['id']??null,$action,$message,json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$_SERVER['REMOTE_ADDR']??'']);}catch(Throwable){}
}
function icon(string $name): string {
    $map=['mail'=>'✉','home'=>'⌂','settings'=>'⚙','image'=>'▧','folder'=>'▤','users'=>'♙','activity'=>'◷','heart'=>'♥','check'=>'✓','phone'=>'☎','map'=>'⌖','edit'=>'✎','trash'=>'×','external'=>'↗','eye'=>'⌕'];
    return '<span class="ui-icon" aria-hidden="true">'.h($map[$name]??'•').'</span>';
}
function normalized_files(string $field): array {
    $f=$_FILES[$field]??null;if(!$f)return [];
    if(!is_array($f['name']))return [$f];$out=[];
    foreach($f['name'] as $i=>$name)$out[]=['name'=>$name,'type'=>$f['type'][$i]??'','tmp_name'=>$f['tmp_name'][$i]??'','error'=>$f['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$f['size'][$i]??0];
    return array_values(array_filter($out,fn($x)=>(int)$x['error']!==UPLOAD_ERR_NO_FILE));
}
function secure_file_mime_type(string $file): string {
    if(class_exists('finfo')){$f=new finfo(FILEINFO_MIME_TYPE);return (string)$f->file($file);}return mime_content_type($file)?:'application/octet-stream';
}

function ensure_password_reset_table(): void {
    static $ready=false;
    if($ready)return;
    db()->exec("CREATE TABLE IF NOT EXISTS admin_password_resets (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      admin_id INT NOT NULL,
      token_hash CHAR(64) NOT NULL UNIQUE,
      expires_at DATETIME NOT NULL,
      used_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_password_reset_admin(admin_id),
      INDEX idx_password_reset_expiry(expires_at),
      CONSTRAINT fk_password_reset_admin FOREIGN KEY(admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready=true;
}
