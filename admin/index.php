<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';
app_session_start();

if (current_admin()) {
    header('Location: dashboard.php');
    exit;
}

$reason = AuthSessionManager::lastExpiryReason();
$target = 'login.php';
if ($reason) {
    $target .= '?expired=' . rawurlencode($reason);
}
header('Location: ' . $target);
exit;
