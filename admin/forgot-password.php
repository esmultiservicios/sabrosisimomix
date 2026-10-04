<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/core/bootstrap.php';
require_once dirname(__DIR__).'/core/EmailService.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(current_admin()){header('Location: dashboard.php');exit;}

$message='';$type='info';$title='';$email=trim((string)($_POST['email']??''));
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Ingresa un correo válido.');
        ensure_password_reset_table();
        $st=db()->prepare('SELECT id,full_name,email FROM admin_users WHERE active=1 AND email=? LIMIT 1');$st->execute([$email]);$u=$st->fetch();
        if($u){
            $svc=new EmailService();$cfg=$svc->getConfiguration();
            if(($cfg['method']??'none')!=='none'){
                $raw=bin2hex(random_bytes(32));$hash=hash('sha256',$raw);$minutes=60;
                db()->prepare('UPDATE admin_password_resets SET used_at=NOW() WHERE admin_id=? AND used_at IS NULL')->execute([(int)$u['id']]);
                $ins=db()->prepare('INSERT INTO admin_password_resets(admin_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL ? MINUTE))');
                $ins->execute([(int)$u['id'],$hash,$minutes]);
                $url=base_url('admin/reset-password.php?token='.rawurlencode($raw));
                $result=$svc->sendWithFallback(['system'],(string)$u['email'],'Recuperación de contraseña · '.setting('site_name','CMS'),EmailTemplates::passwordReset((string)$u['full_name'],$url,$minutes));
                if(!($result['success']??false)){
                    db()->prepare('UPDATE admin_password_resets SET used_at=NOW() WHERE token_hash=?')->execute([$hash]);
                    try{db()->prepare('INSERT INTO activity_log(admin_id,action,message,context_json,ip_address) VALUES(NULL,?,?,?,?)')->execute(['password_reset_email_failed','Password reset email failed',json_encode(['email'=>$email,'error'=>$result['message']??'unknown'],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'']);}catch(Throwable){}
                }
            }
        }
        $message='Si el correo pertenece a una cuenta activa y el servicio de correo está configurado, recibirás un enlace seguro para restablecer la contraseña.';$type='success';$title='Solicitud recibida';
    }catch(Throwable $e){$message=$e->getMessage();$type='error';$title='Revisa el correo';}
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><link rel="icon" type="image/x-icon" href="../assets/favicon/favicon.ico?v=1"><link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32x32.png?v=1"><link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon/favicon-16x16.png?v=1"><link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png?v=1"><link rel="manifest" href="../assets/favicon/site.webmanifest?v=1"><title>Recuperar contraseña · Sabrosísimo Mix</title><link rel="stylesheet" href="../assets/css/auth.css?v=<?=@filemtime(ROOT_DIR.'/assets/css/auth.css')?>"><link rel="stylesheet" href="../assets/vendor/ui-feedback.css?v=<?=@filemtime(ROOT_DIR.'/assets/vendor/ui-feedback.css')?>"></head><body class="auth-page">
<form class="auth-card" method="post">
<div class="auth-brand"><span class="auth-mark">SM</span><div class="auth-brand-copy"><strong>Sabrosísimo Mix</strong><small>Seguridad de cuenta</small></div></div>
<h1>Recuperar contraseña</h1><p class="auth-subtitle">Te enviaremos un enlace de uso único al correo registrado.</p>
<?php if($message):?><div hidden data-auth-notify data-type="<?=h($type)?>" data-title="<?=h($title)?>" data-message="<?=h($message)?>"></div><?php endif;?>
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<div class="auth-form"><label class="auth-field"><span>Correo de tu cuenta</span><input class="auth-input" type="email" name="email" value="<?=h($email)?>" autocomplete="email" autofocus required></label><div class="auth-security"><span>✓</span><div><b>Enlace seguro</b><br>El enlace vence en 60 minutos y solo puede utilizarse una vez.</div></div><button class="auth-button" type="submit">Enviar enlace de recuperación</button></div>
<a class="auth-back" href="login.php">← Volver al inicio de sesión</a>
</form><script src="../assets/vendor/ui-feedback.js?v=<?=@filemtime(ROOT_DIR.'/assets/vendor/ui-feedback.js')?>"></script><script>document.querySelectorAll('[data-auth-notify]').forEach(el=>window.showNotify?.(el.dataset.message,el.dataset.type,{title:el.dataset.title,duration:6000}));</script></body></html>
