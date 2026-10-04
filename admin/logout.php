<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

if(current_admin()){
    log_activity('logout','Administrator logged out');
}
AuthSessionManager::logout(false);
header('Location: login.php?logged_out=1');
exit;
