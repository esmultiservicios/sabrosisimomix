<?php
require __DIR__.'/bootstrap.php';
if(admin_count()>0) {
    header('Location: login.php');
    exit;
}
$error='';
$set=settings();
$favicon=$set['favicon_path']??($set['admin_logo_path']??'assets/logo.jpg');
$brand=$set['admin_brand_name']??"Castro's Ready Admin";
$logo=$set['admin_logo_path']??'assets/logo.jpg';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $u=trim((string)($_POST['username']??''));
    $full=trim((string)($_POST['full_name']??''));
    $email=trim((string)($_POST['email']??''));
    $p=(string)($_POST['password']??'');
    $p2=(string)($_POST['password2']??'');
    if(strlen($u)<4)$error='Username must have at least 4 characters.';
    elseif($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Enter a valid email address.';
    elseif(strlen($p)<10)$error='Password must have at least 10 characters.';
    elseif($p!==$p2)$error='Passwords do not match.';
    else {
        $role=(int)db()->query("SELECT id FROM admin_roles WHERE role_key='owner' LIMIT 1")->fetchColumn();
        $st=db()->prepare('INSERT INTO admin_users(username,full_name,email,password_hash,role_id,active) VALUES(?,?,?,?,?,1)');
        $st->execute([$u,$full,$email,password_hash($p,PASSWORD_DEFAULT),$role]);
        header('Location: login.php?setup=1');
        exit;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" href="../<?=h($favicon)?>">
<link rel="shortcut icon" href="../<?=h($favicon)?>">
<title>Admin Setup</title>
<link rel="stylesheet" href="<?=h(versioned_asset('../assets/vendor/sweetalert2/sweetalert2.min.css', 'assets/vendor/sweetalert2/sweetalert2.min.css'))?>">
<link rel="stylesheet" href="<?=h(versioned_asset('../assets/vendor/show-notify/showNotify.css', 'assets/vendor/show-notify/showNotify.css'))?>">
<link rel="stylesheet" href="<?=h(versioned_asset('admin.css', 'admin/admin.css'))?>">
<style>
.setup-wizard-status{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:0 0 22px}.setup-wizard-step{display:flex;align-items:center;gap:9px;min-width:0;padding:10px;border:1px solid #d9e4df;border-radius:13px;background:#f8fbfa}.setup-wizard-step.done,.setup-wizard-step.active{border-color:#9fd0c7;background:#edf8f5}.setup-wizard-num{width:30px;height:30px;flex:0 0 30px;display:grid;place-items:center;border-radius:50%;background:#e5ebe8;color:#667170;font-size:12px;font-weight:900}.setup-wizard-step.done .setup-wizard-num,.setup-wizard-step.active .setup-wizard-num{background:#0f7777;color:#fff}.setup-wizard-copy{min-width:0;display:grid;gap:1px}.setup-wizard-copy strong{font-size:12px;line-height:1.2}.setup-wizard-copy small{font-size:10px;color:#667170;line-height:1.25}.setup-step-count{display:inline-flex;margin:0 0 12px;padding:7px 10px;border-radius:999px;background:#eef8f5;border:1px solid #cfe1dc;color:#0b5f60;font-size:12px;font-weight:800}.auth-card button,.auth-card a{cursor:pointer}.setup-submit{display:flex!important;align-items:center!important;justify-content:center!important;gap:9px!important}.setup-submit .setup-arrow{display:inline-grid;place-items:center;width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.16);font-size:17px}@media(max-width:620px){.setup-wizard-status{grid-template-columns:1fr}.setup-wizard-step{padding:9px 10px}}@media(prefers-reduced-motion:reduce){.auth-card button,.auth-card a{transition:none!important}}
</style>
</head>
<body>
<main class="auth-wrap">
<div class="auth-card">
<?php if(!isset($_GET['reset'])): ?>
<div class="setup-step-count">Step 3 of 3</div>
<div class="setup-wizard-status" aria-label="Installation progress">
  <div class="setup-wizard-step done"><span class="setup-wizard-num">✓</span><span class="setup-wizard-copy"><strong>Database</strong><small>Connected</small></span></div>
  <div class="setup-wizard-step done"><span class="setup-wizard-num">✓</span><span class="setup-wizard-copy"><strong>CMS ready</strong><small>Configured</small></span></div>
  <div class="setup-wizard-step active"><span class="setup-wizard-num">3</span><span class="setup-wizard-copy"><strong>Administrator</strong><small>Create Owner</small></span></div>
</div>
<?php endif; ?>
<img src="../<?=h($logo)?>" alt="">
<p class="eyebrow">FIRST-TIME ADMIN SETUP</p>
<h1>Create administrator</h1>
<p>Set the account that will manage <?=h($brand)?>

.</p><?php
if(isset($_GET['reset'])):
?>

<div class="alert success">Administrator ownership was reset. Create the new owner's account below.</div><?php
endif;
?>
<?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>

<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<label>Full name<input name="full_name" autocomplete="name">
</label>
<label>Email<input type="email" name="email" autocomplete="email">
</label>
<label>Username<input name="username" required autocomplete="username">
</label>
<label>Password<input type="password" name="password" required minlength="10" autocomplete="new-password">
</label>
<label>Repeat password<input type="password" name="password2" required minlength="10" autocomplete="new-password">
</label>
<button type="submit" class="setup-submit"><span>Create administrator & finish</span><span class="setup-arrow" aria-hidden="true">→</span></button>
</form>
</div>
</main>
<script src="<?=h(versioned_asset('../assets/vendor/sweetalert2/sweetalert2.all.min.js', 'assets/vendor/sweetalert2/sweetalert2.all.min.js'))?>">
</script>
<script src="<?=h(versioned_asset('../assets/vendor/show-notify/showNotify.js', 'assets/vendor/show-notify/showNotify.js'))?>">
</script>
<script>document.querySelectorAll(".alert.success,.alert.error,.alert.info,.alert.warning").forEach(function(el){var t=el.classList.contains("error")?"error":el.classList.contains("warning")?"warning":el.classList.contains("success")?"success":"info";if(window.showNotify){showNotify(el.textContent.trim(),t);el.hidden=true;}});</script>
</body>
</html>
