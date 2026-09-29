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
function h(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
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
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    $sent=(string)($_POST['csrf']??'');
    if(!hash_equals((string)($_SESSION['csrf']??''),$sent))throw new RuntimeException('The session token expired. Refresh the page and try again.');
}
function flash(string $type,string $message): void {if(session_status()!==PHP_SESSION_ACTIVE)session_start();$_SESSION['flash']=['type'=>$type,'message'=>$message];}
function consume_flash(): ?array {if(session_status()!==PHP_SESSION_ACTIVE)session_start();$f=$_SESSION['flash']??null;unset($_SESSION['flash']);return $f;}
function current_admin(): ?array {
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    $id=(int)($_SESSION['admin_id']??0);if(!$id)return null;
    static $user=null;if($user&&isset($user['id'])&&(int)$user['id']===$id)return $user;
    $st=db()->prepare('SELECT u.*,r.role_name FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id WHERE u.id=? AND u.active=1');$st->execute([$id]);$user=$st->fetch()?:null;return $user;
}
function require_login(): void {if(!current_admin()){header('Location: login.php');exit;}}
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
