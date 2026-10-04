<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/core/EmailService.php';
require_once __DIR__.'/core/PublicFormProtection.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

function estimate_public_base_url(array $settings): string {
    $configured=trim((string)($settings['website']??''));
    if($configured!=='') {
        if(!preg_match('~^https?://~i',$configured))$configured='https://'.$configured;
        if(filter_var($configured,FILTER_VALIDATE_URL))return rtrim($configured,'/');
    }

    $host=preg_replace('/[^a-z0-9.:-]/i','',(string)($_SERVER['HTTP_HOST']??''));
    if($host==='')return '';
    $forwarded=strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??'')));
    $https=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||$forwarded==='https';
    $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/estimate-submit.php'));
    $dir=rtrim(str_replace('\\','/',dirname($script)),'/');
    if($dir==='.'||$dir==='/')$dir='';
    return ($https?'https':'http').'://'.$host.$dir;
}

try {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Invalid request.');

    if(PublicFormProtection::isHoneypotFilled($_POST)) {
        echo json_encode([
            'ok'=>true,
            'message'=>'Thank you. Your free estimate request has been received.'
        ]);
        exit;
    }

    if(!PublicFormProtection::consumeRateLimit('estimate_submission',5,900,1800)) {
        http_response_code(429);
        throw new RuntimeException('Too many requests were submitted. Please wait a few minutes and try again.');
    }

    $name=trim((string)($_POST['name']??''));
    $phone=trim((string)($_POST['phone']??''));
    $email=trim((string)($_POST['email']??''));
    $address=trim((string)($_POST['address']??''));
    $service=trim((string)($_POST['service']??''));
    $date=trim((string)($_POST['date']??''));
    $message=trim((string)($_POST['message']??''));
    if($name===''&&$phone===''&&$email===''&&$message==='')throw new RuntimeException('Please provide at least your name or contact information.');

    PublicFormProtection::assertHumanContent([
        'name'=>$name,
        'phone'=>$phone,
        'email'=>$email,
        'address'=>$address,
        'service'=>$service,
        'date'=>$date,
        'message'=>$message,
    ]);

    if($email!=='') {
        $emailValidation=PublicFormProtection::validateEmail($email);
        if(!$emailValidation['valid'])throw new RuntimeException((string)$emailValidation['message']);
    }

    if(!PublicFormProtection::verifyTurnstile(trim((string)($_POST['cf-turnstile-response']??'')))) {
        throw new RuntimeException('Please complete the security verification and try again.');
    }

    $files=normalized_files('photos');
    if(count($files)>8)throw new RuntimeException('Please upload no more than 8 images.');

    $pdo=db();
    $pdo->beginTransaction();
    $st=$pdo->prepare('INSERT INTO estimate_requests(full_name,phone,email,address,service_needed,desired_date,message,photo_path) VALUES(?,?,?,?,?,?,?,NULL)');
    $st->execute([$name,$phone,$email,$address,$service,$date!==''?$date:null,$message]);
    $id=(int)$pdo->lastInsertId();
    $first=null;
    $mailAttachments=[];
    $attachmentMeta=[];

    foreach($files as $f) {
        $path=upload_image($f,'estimates','estimate-'.$id,8);
        if($first===null)$first=$path;
        $mime=detect_file_mime_type(ROOT_DIR.'/'.$path);
        $original=basename(trim((string)($f['name']??'')));
        if($original==='')$original=basename($path);
        $ins=$pdo->prepare('INSERT INTO estimate_attachments(estimate_id,file_path,original_name,mime_type,file_size) VALUES(?,?,?,?,?)');
        $ins->execute([$id,$path,$original,$mime,(int)($f['size']??0)]);
        $mailAttachments[]=[
            'path'=>ROOT_DIR.'/'.$path,
            'name'=>$original,
            'mime'=>$mime
        ];
        $attachmentMeta[]=[
            'path'=>$path,
            'name'=>$original,
            'mime'=>$mime
        ];
    }

    if($first)$pdo->prepare('UPDATE estimate_requests SET photo_path=? WHERE id=?')->execute([$first,$id]);
    $pdo->commit();

    admin_notify('info','New estimate request',($name!==''?$name:'A website visitor').' submitted a free estimate request.','estimates.php?view='.$id);
    log_activity('estimate_received','New website estimate request',['estimate_id'=>$id]);

    $set=settings();
    $baseUrl=estimate_public_base_url($set);
    $request=[
        'full_name'=>$name,
        'phone'=>$phone,
        'email'=>$email,
        'address'=>$address,
        'service_needed'=>$service,
        'desired_date'=>$date,
        'message'=>$message,
        'attachments'=>array_map(static function(array $attachment) use($baseUrl): array {
            $attachment['url']=$baseUrl!==''?$baseUrl.'/'.ltrim((string)$attachment['path'],'/'):'';
            unset($attachment['path']);
            return $attachment;
        },$attachmentMeta)
    ];

    try {
        $mailer=new EmailService();
        $adminTo=trim((string)($set['estimate_notification_email']??($set['email']??'')));
        $copyTo=trim((string)($set['estimate_copy_email']??''));
        $bcc=[];
        if(filter_var($copyTo,FILTER_VALIDATE_EMAIL)&&strcasecmp($copyTo,$adminTo)!==0)$bcc[]=$copyTo;

        $adminResult=['success'=>false,'message'=>'No valid estimate notification email is configured.'];
        if(filter_var($adminTo,FILTER_VALIDATE_EMAIL)) {
            $adminResult=$mailer->sendWithFallback(
                [3,1],
                $adminTo,
                "New Castro's Ready estimate request",
                EmailTemplates::estimateAdmin($request,$set),
                $email,
                $bcc,
                $mailAttachments
            );
        }

        if(!$adminResult['success'])admin_notify('warning','Estimate email not sent','Estimate #'.$id.' was saved, but the notification email could not be delivered.','email.php');
    } catch(Throwable $mailError) {
        admin_notify('warning','Estimate email error','Estimate #'.$id.' was saved, but email delivery raised an error.','email.php');
    }

    echo json_encode(['ok'=>true,'message'=>'Thank you. Your free estimate request has been received.','request_id'=>$id]);
} catch(Throwable $e) {
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    if(http_response_code()<400)http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
