<?php
declare(strict_types=1);
define('INSTALLER_CONTEXT',true);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__).'/core/EmailService.php';
try {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Método no permitido.');
    $method=(string)($_POST['mail_method']??'none');$recipient=trim((string)($_POST['test_email']??$_POST['admin_email']??''));
    if($method==='smtp'){
        $cfg=['host'=>trim((string)($_POST['smtp_host']??'')),'port'=>(int)($_POST['smtp_port']??587),'encryption'=>(string)($_POST['smtp_encryption']??'tls'),'username'=>trim((string)($_POST['smtp_username']??'')),'password'=>(string)($_POST['smtp_password']??''),'from_email'=>trim((string)($_POST['smtp_from_email']??'')),'from_name'=>trim((string)($_POST['smtp_from_name']??'Sabrosísimo Mix'))];
    } elseif($method==='graph') {
        $cfg=['tenant_id'=>trim((string)($_POST['graph_tenant_id']??'')),'client_id'=>trim((string)($_POST['graph_client_id']??'')),'client_secret'=>(string)($_POST['graph_client_secret']??''),'sender_email'=>trim((string)($_POST['graph_sender_email']??''))];
    } else throw new RuntimeException('Selecciona SMTP o Microsoft Graph para realizar la prueba.');
    $r=(new EmailService())->test($method,$cfg,$recipient);echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) {http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
