<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (current_admin()) {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;
