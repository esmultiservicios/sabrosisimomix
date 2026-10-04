<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/core/bootstrap.php';
app_session_start();
if(basename($_SERVER['SCRIPT_NAME']??'')!=='login.php')require_login();
