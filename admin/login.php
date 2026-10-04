<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

$freshLogin = $_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['fresh']);
$implicitExpiredReason = null;
if($freshLogin){
    AuthSessionManager::forceFreshLogin(false);
    app_session_start();
}

// Visiting login.php is an authentication screen, not an automatic session restore endpoint.
// /admin/ remains the route that resumes a still-valid administrative session.
if($_SERVER['REQUEST_METHOD']==='GET' && !$freshLogin){
    $activeAdmin = current_admin();
    $implicitExpiredReason = AuthSessionManager::lastExpiryReason();
    if($activeAdmin){
        AuthSessionManager::forceFreshLogin(false);
        app_session_start();
        $freshLogin = true;
    }
}

$error='';
$prefill=trim((string)($_GET['email']??''));
if($prefill==='')$prefill=AuthSessionManager::rememberedLogin();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $prefill=trim((string)($_POST['login']??''));
    try{
        verify_csrf();
        $login=$prefill;$pass=(string)($_POST['password']??'');
        $st=db()->prepare('SELECT * FROM admin_users WHERE active=1 AND (username=? OR email=?) LIMIT 1');
        $st->execute([$login,$login]);$u=$st->fetch();
        if(!$u||!password_verify($pass,$u['password_hash']))throw new RuntimeException('Usuario o contraseña incorrectos.');
        $remember=isset($_POST['remember_login']);
        AuthSessionManager::setRememberLogin($login,$remember);
        AuthSessionManager::establish((int)$u['id']);
        log_activity('login','Administrator logged in');
        header('Location: dashboard.php');exit;
    }catch(Throwable $e){$error=$e->getMessage();}
}
$installed=isset($_GET['installed']);
$resetDone=isset($_GET['reset']);
$expiredReason=trim((string)($_GET['expired']??($implicitExpiredReason??'')));
$passwordChanged=isset($_GET['password_changed']);
$expiredMessage=$expiredReason!==''?AuthSessionManager::expiryMessage($expiredReason):'';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><link rel="icon" type="image/x-icon" href="../assets/favicon/favicon.ico?v=1"><link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32x32.png?v=1"><link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon/favicon-16x16.png?v=1"><link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png?v=1"><link rel="manifest" href="../assets/favicon/site.webmanifest?v=1"><title>Administración · Sabrosísimo Mix</title><link rel="stylesheet" href="../assets/css/auth.css?v=<?=@filemtime(ROOT_DIR.'/assets/css/auth.css')?>"><link rel="stylesheet" href="../assets/vendor/ui-feedback.css?v=<?=@filemtime(ROOT_DIR.'/assets/vendor/ui-feedback.css')?>"></head>
<body class="auth-page">
<form class="auth-card" method="post" autocomplete="on">
  <div class="auth-brand"><span class="auth-mark">SM</span><div class="auth-brand-copy"><strong>Sabrosísimo Mix</strong><small>CMS Premium</small></div></div>
  <h1>Bienvenido</h1><p class="auth-subtitle">Ingresa para administrar el sitio.</p>
  <?php if($installed):?><div hidden data-auth-notify data-type="success" data-title="Instalación completada" data-message="Tu cuenta ya está lista. Inicia sesión para entrar al panel."></div><?php endif;?>
  <?php if($resetDone):?><div hidden data-auth-notify data-type="success" data-title="Contraseña actualizada" data-message="Tu contraseña fue cambiada correctamente. Ya puedes iniciar sesión."></div><?php endif;?>
  <?php if($passwordChanged):?><div hidden data-auth-notify data-type="success" data-title="Seguridad actualizada" data-message="Tu contraseña fue actualizada y las sesiones administrativas anteriores fueron cerradas. Inicia sesión nuevamente."></div><?php endif;?>
  <?php if($expiredMessage!==''):?><div hidden data-auth-notify data-type="warning" data-title="Sesión vencida" data-message="<?=h($expiredMessage)?>"></div><?php endif;?>
  <?php if($freshLogin):?><div hidden data-auth-notify data-type="info" data-title="Autenticación nueva" data-message="La sesión administrativa anterior fue cerrada. Ingresa tus credenciales para continuar."></div><?php endif;?>
  <?php if($error):?><div hidden data-auth-notify data-type="error" data-title="No se pudo iniciar sesión" data-message="<?=h($error)?>"></div><?php endif;?>
  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
  <div class="auth-form">
    <label class="auth-field"><span>Usuario o correo</span><input class="auth-input" name="login" value="<?=h($prefill)?>" autocomplete="username" autofocus required></label>
    <label class="auth-field"><span>Contraseña</span><span class="auth-input-wrap"><input class="auth-input has-toggle" type="password" name="password" autocomplete="current-password" required><button class="auth-toggle-password" type="button" data-password-toggle>Mostrar</button></span></label>
    <div class="auth-row">
      <label class="remember-control"><input type="checkbox" name="remember_login" value="1" <?=AuthSessionManager::rememberedLogin()!==''?'checked':''?>><span class="remember-dot" aria-hidden="true"></span><span>Recordar usuario</span></label>
      <a class="auth-link" href="forgot-password.php">¿Olvidaste tu contraseña?</a>
    </div>
    <button class="auth-button" type="submit">Ingresar</button>
  </div>
  <a class="auth-back" href="../">← Volver al sitio</a>
</form>
<script src="../assets/vendor/ui-feedback.js?v=<?=@filemtime(ROOT_DIR.'/assets/vendor/ui-feedback.js')?>"></script>
<script>
document.querySelector('[data-password-toggle]')?.addEventListener('click',e=>{const b=e.currentTarget,i=b.previousElementSibling,show=i.type==='password';i.type=show?'text':'password';b.textContent=show?'Ocultar':'Mostrar';});

(() => {
  const form = document.querySelector('.auth-card');
  const login = form?.querySelector('input[name="login"]');
  const remember = form?.querySelector('input[name="remember_login"]');
  const storageKey = 'smx_remember_login';

  if (!form || !login || !remember) return;

  try {
    const localRemembered = (window.localStorage.getItem(storageKey) || '').trim();
    if (!login.value.trim() && localRemembered) {
      login.value = localRemembered;
      remember.checked = true;
    }
  } catch (_) {}

  form.addEventListener('submit', () => {
    try {
      if (remember.checked && login.value.trim()) {
        window.localStorage.setItem(storageKey, login.value.trim());
      } else {
        window.localStorage.removeItem(storageKey);
      }
    } catch (_) {}
  });
})();

document.querySelectorAll('[data-auth-notify]').forEach(el=>window.showNotify?.(el.dataset.message,el.dataset.type,{title:el.dataset.title,duration:5600}));
</script>
</body></html>
