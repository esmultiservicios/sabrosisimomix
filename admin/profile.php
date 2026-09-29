<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_login();
$pdo=db();
$me=current_admin();

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        $fullName=trim((string)($_POST['full_name']??''));
        $username=trim((string)($_POST['username']??''));
        $email=trim((string)($_POST['email']??''));
        $currentPassword=(string)($_POST['current_password']??'');
        $newPassword=(string)($_POST['new_password']??'');
        $confirmPassword=(string)($_POST['confirm_password']??'');

        if($fullName===''||$username===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){
            throw new RuntimeException('Completa correctamente tu nombre, usuario y correo.');
        }
        $dup=$pdo->prepare('SELECT id FROM admin_users WHERE (username=? OR email=?) AND id<>? LIMIT 1');
        $dup->execute([$username,$email,(int)$me['id']]);
        if($dup->fetch())throw new RuntimeException('El usuario o correo ya está siendo utilizado por otra cuenta.');

        if($newPassword!==''){
            if(strlen($newPassword)<8)throw new RuntimeException('La nueva contraseña debe contener al menos 8 caracteres.');
            if($newPassword!==$confirmPassword)throw new RuntimeException('La confirmación de la nueva contraseña no coincide.');
            if($currentPassword===''||!password_verify($currentPassword,(string)$me['password_hash'])){
                throw new RuntimeException('La contraseña actual no es correcta.');
            }
            $st=$pdo->prepare('UPDATE admin_users SET full_name=?,username=?,email=?,password_hash=? WHERE id=?');
            $st->execute([$fullName,$username,$email,password_hash($newPassword,PASSWORD_DEFAULT),(int)$me['id']]);
            log_activity('profile.password','Administrator updated profile and password');
        }else{
            $st=$pdo->prepare('UPDATE admin_users SET full_name=?,username=?,email=? WHERE id=?');
            $st->execute([$fullName,$username,$email,(int)$me['id']]);
            log_activity('profile.update','Administrator updated profile');
        }
        flash('success','Perfil actualizado correctamente.');
        header('Location: profile.php');exit;
    }catch(Throwable $e){
        flash('error',$e->getMessage());
        header('Location: profile.php');exit;
    }
}

$me=current_admin();
$pageTitle='Mi perfil';$active='';require __DIR__.'/_header.php';
?>
<div class="page-heading"><div><p class="eyebrow">CUENTA</p><h1>Mi perfil</h1><p class="muted">Administra tus datos de acceso sin salir del panel.</p></div></div>
<section class="panel profile-panel">
  <div class="profile-overview">
    <div class="profile-avatar-large"><?=h(strtoupper(substr((string)($me['full_name']??'A'),0,1)))?></div>
    <div><h2><?=h($me['full_name']??'Administrador')?></h2><p><?=h($me['role_name']??'Usuario')?> · <?=h($me['email']??'')?></p></div>
  </div>
  <form method="post" class="form-grid profile-form" autocomplete="off">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <label><span>Nombre completo</span><input name="full_name" value="<?=h($me['full_name']??'')?>" required></label>
    <label><span>Usuario</span><input name="username" value="<?=h($me['username']??'')?>" required></label>
    <label class="full"><span>Correo</span><input type="email" name="email" value="<?=h($me['email']??'')?>" required></label>
    <div class="full profile-password-title"><strong>Cambiar contraseña</strong><small>Déjala en blanco si no deseas cambiarla.</small></div>
    <label><span>Contraseña actual</span><input type="password" name="current_password" autocomplete="current-password"></label>
    <label><span>Nueva contraseña</span><input type="password" name="new_password" minlength="8" autocomplete="new-password"><small>Mínimo 8 caracteres.</small></label>
    <label class="full"><span>Confirmar nueva contraseña</span><input type="password" name="confirm_password" minlength="8" autocomplete="new-password"></label>
    <div class="full form-actions"><button type="submit">Guardar cambios</button><a class="button secondary" href="dashboard.php">Volver al dashboard</a></div>
  </form>
</section>
<?php require __DIR__.'/_footer.php'; ?>
