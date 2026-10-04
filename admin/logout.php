<?php require __DIR__.'/bootstrap.php';log_activity('logout','Administrator logged out');$_SESSION=[];session_destroy();header('Location: login.php');
