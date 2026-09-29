<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/Security.php';

final class EmailTemplates {
    private static function brandName(array $settings=[]): string {
        $name=(string)($settings['site_name']??'');
        if($name===''){
            try{$name=(string)setting('site_name','Sabrosísimo Mix');}catch(Throwable){$name='Sabrosísimo Mix';}
        }
        return $name!==''?$name:'Sabrosísimo Mix';
    }

    public static function layout(string $title,string $content,array $options=[]): string {
        $brand=h(self::brandName($options['settings']??[]));
        $safeTitle=h($title);
        $preheader=h((string)($options['preheader']??$title));
        $eyebrow=h((string)($options['eyebrow']??'COMUNICACIÓN DEL SISTEMA'));
        $footer=h((string)($options['footer']??'Este mensaje fue enviado automáticamente desde el sistema.'));
        $accent=h((string)($options['accent']??'#c93427'));
        $year=date('Y');
        return '<!doctype html><html lang="es" data-cms-email-template="1"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$safeTitle.'</title></head>'
            .'<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#172033">'
            .'<div style="display:none;max-height:0;overflow:hidden;opacity:0">'.$preheader.'</div>'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f3f4f6;padding:28px 12px"><tr><td align="center">'
            .'<table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:680px;background:#ffffff;border:1px solid #e5e7eb;border-radius:22px;overflow:hidden;box-shadow:0 12px 32px rgba(15,23,42,.08)">'
            .'<tr><td style="padding:0;background:#17110f"><div style="height:5px;background:'.$accent.'"></div><div style="padding:26px 30px">'
            .'<div style="font-size:11px;letter-spacing:2px;font-weight:700;color:#e8b854;margin-bottom:8px">'.$eyebrow.'</div>'
            .'<div style="font-size:24px;line-height:1.2;font-weight:800;color:#ffffff">'.$brand.'</div>'
            .'</div></td></tr>'
            .'<tr><td style="padding:30px">'
            .'<h1 style="margin:0 0 18px;font-size:25px;line-height:1.25;color:#111827">'.$safeTitle.'</h1>'
            .'<div style="font-size:15px;line-height:1.7;color:#4b5563">'.$content.'</div>'
            .'</td></tr>'
            .'<tr><td style="padding:20px 30px;background:#faf7f3;border-top:1px solid #eee7df">'
            .'<div style="font-size:12px;line-height:1.55;color:#7c716b">'.$footer.'</div>'
            .'<div style="font-size:11px;color:#a09893;margin-top:8px">© '.$year.' '.$brand.'</div>'
            .'</td></tr></table>'
            .'</td></tr></table></body></html>';
    }

    public static function test(string $method,string $recipient): string {
        $methodName=$method==='graph'?'Microsoft Graph':'SMTP';
        $content='<div style="padding:18px;border:1px solid #d7eadf;background:#f2fbf5;border-radius:14px;margin-bottom:20px">'
            .'<div style="font-size:16px;font-weight:800;color:#167647;margin-bottom:6px">✓ Conexión verificada correctamente</div>'
            .'<div style="color:#4b6358">La configuración de correo está funcionando y el sistema pudo entregar este mensaje mediante <strong>'.h($methodName).'</strong>.</div>'
            .'</div>'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;font-size:14px">'
            .'<tr><td style="padding:9px 0;color:#7b8491;width:150px;border-bottom:1px solid #eef0f2">Método</td><td style="padding:9px 0;font-weight:700;border-bottom:1px solid #eef0f2">'.h($methodName).'</td></tr>'
            .'<tr><td style="padding:9px 0;color:#7b8491;border-bottom:1px solid #eef0f2">Destinatario</td><td style="padding:9px 0;font-weight:700;border-bottom:1px solid #eef0f2">'.h($recipient).'</td></tr>'
            .'<tr><td style="padding:9px 0;color:#7b8491">Fecha de prueba</td><td style="padding:9px 0;font-weight:700">'.h(date('d/m/Y H:i')).'</td></tr>'
            .'</table>'
            .'<p style="margin:22px 0 0;color:#6b7280">No necesitas responder este mensaje. Su objetivo es confirmar que el servicio de correo está listo para utilizarse.</p>';
        return self::layout('Prueba de correo completada',$content,[
            'eyebrow'=>'CONFIGURACIÓN DE CORREO',
            'preheader'=>'La configuración de correo fue verificada correctamente.',
            'footer'=>'Prueba automática de configuración. No contiene credenciales ni información sensible.'
        ]);
    }

    public static function welcome(string $name,string $loginUrl): string {
        $content='<p style="margin-top:0">Hola <strong>'.h($name?:'Administrador').'</strong>,</p>'
            .'<p>Tu cuenta administrativa ya está lista. Desde el panel podrás gestionar el contenido, solicitudes, correo, widgets y configuración del sitio.</p>'
            .'<div style="padding:18px;border:1px solid #eadfd6;background:#fffaf2;border-radius:14px;margin:20px 0">'
            .'<div style="font-weight:800;color:#17110f;margin-bottom:5px">Acceso habilitado</div>'
            .'<div style="color:#6b625d">La instalación finalizó correctamente y tu usuario ya puede iniciar sesión.</div>'
            .'</div>'
            .'<p style="margin:24px 0 0"><a href="'.h($loginUrl).'" style="display:inline-block;background:#c93427;color:#fff;text-decoration:none;padding:12px 18px;border-radius:11px;font-weight:700">Abrir panel administrativo</a></p>'
            .'<p style="margin:22px 0 0;color:#6b7280;font-size:13px">Por seguridad, este correo nunca incluye tu contraseña.</p>';
        return self::layout('Bienvenido al panel administrativo',$content,[
            'eyebrow'=>'CUENTA ADMINISTRATIVA',
            'preheader'=>'Tu cuenta administrativa ya está lista.',
            'footer'=>'Mensaje automático de bienvenida. Si no reconoces esta instalación, contacta al responsable del sistema.'
        ]);
    }

    public static function passwordReset(string $name,string $resetUrl,int $minutes=60): string {
        $content='<p style="margin-top:0">Hola <strong>'.h($name?:'Usuario').'</strong>,</p>'
            .'<p>Recibimos una solicitud para restablecer la contraseña de tu cuenta administrativa.</p>'
            .'<p style="margin:24px 0"><a href="'.h($resetUrl).'" style="display:inline-block;background:#c93427;color:#fff;text-decoration:none;padding:12px 18px;border-radius:11px;font-weight:700">Restablecer contraseña</a></p>'
            .'<div style="padding:14px 16px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;color:#7c4a12">Este enlace vence en '.(int)$minutes.' minutos y solo puede utilizarse una vez.</div>'
            .'<p style="margin:20px 0 0;color:#6b7280;font-size:13px">Si tú no solicitaste este cambio, puedes ignorar este correo; tu contraseña actual seguirá funcionando.</p>';
        return self::layout('Recuperación de contraseña',$content,[
            'eyebrow'=>'SEGURIDAD DE CUENTA',
            'preheader'=>'Usa este enlace seguro para restablecer tu contraseña.',
            'footer'=>'Por seguridad, nunca compartas este enlace ni tu contraseña con otras personas.'
        ]);
    }

    public static function estimateResponse(string $name,string $html,array $settings): string {
        $content='<p style="margin-top:0">Hola <strong>'.h($name?:'cliente').'</strong>,</p>'.$html.'<p style="margin:26px 0 0;color:#6b7280">Gracias por contactarnos.</p>';
        return self::layout('Respuesta a tu solicitud',$content,['settings'=>$settings,'eyebrow'=>'ATENCIÓN AL CLIENTE']);
    }

    public static function estimateNotification(array $estimate,int $id,int $attachmentCount=0): string {
        $adminUrl=h(base_url('admin/estimates.php'));
        $row=function(string $label,string $value): string {
            $safe=$value!==''?h($value):'—';
            return '<tr><td style="padding:9px 10px;color:#6b7280;border-bottom:1px solid #eef0f2;width:145px">'.h($label).'</td><td style="padding:9px 10px;border-bottom:1px solid #eef0f2;font-weight:700">'.$safe.'</td></tr>';
        };
        $rows=$row('ID','#'.$id)
            .$row('Nombre',(string)($estimate['full_name']??''))
            .$row('Teléfono',(string)($estimate['phone']??''))
            .$row('Correo',(string)($estimate['email']??''))
            .$row('Servicio',(string)($estimate['service_needed']??''))
            .$row('Dirección',(string)($estimate['address']??''))
            .$row('Mensaje',(string)($estimate['message']??''))
            .$row('Adjuntos',$attachmentCount>0?$attachmentCount.' archivo(s)':'Sin adjuntos')
            .$row('Fecha',date('d/m/Y H:i'));
        $content='<p style="margin-top:0">Se recibió una nueva solicitud desde el sitio web.</p>'
            .'<table style="width:100%;border-collapse:collapse;font-size:14px">'.$rows.'</table>'
            .'<p style="margin:24px 0 0"><a href="'.$adminUrl.'" style="display:inline-block;background:#c93427;color:#fff;text-decoration:none;padding:12px 17px;border-radius:10px;font-weight:700">Abrir solicitudes en el CMS</a></p>';
        return self::layout('Nueva solicitud de cotización',$content,['eyebrow'=>'NUEVA SOLICITUD','preheader'=>'Se recibió una nueva solicitud desde el sitio web.']);
    }
}

final class EmailService {
    public function getConfiguration(): array {
        $method=(string)setting('mail_method','none');
        $raw=(string)setting('mail_config','');$cfg=[];
        if($raw!==''){try{$cfg=json_decode(Security::decrypt($raw),true,512,JSON_THROW_ON_ERROR)?:[];}catch(Throwable){$cfg=[];}}
        return ['method'=>$method,'config'=>$cfg];
    }
    public function saveConfiguration(string $method,array $config): void {
        if(!in_array($method,['none','smtp','graph'],true))throw new RuntimeException('Invalid email method.');
        save_setting('mail_method',$method);save_setting('mail_config',$method==='none'?'':Security::encrypt(json_encode($config,JSON_UNESCAPED_SLASHES)));
    }
    public function test(string $method,array $config,string $recipient): array {
        if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))return ['success'=>false,'message'=>'Ingresa un correo válido para realizar la prueba.'];
        $result=$this->sendUsing($method,$config,$recipient,'Prueba de correo · '.(string)setting('site_name','Sabrosísimo Mix'),EmailTemplates::test($method,$recipient),'La configuración de correo funciona correctamente.');
        if(($result['success']??false)===true){
            $result['message']='Conexión verificada. El correo de prueba fue enviado correctamente a '.$recipient.'.';
        }
        return $result;
    }
    public function sendWithFallback(array $purposes,string $to,string $subject,string $html,string $text='',array $headers=[],array $options=[]): array {
        $saved=$this->getConfiguration();if($saved['method']==='none')return ['success'=>false,'message'=>'Email is not configured yet.'];
        return $this->sendUsing($saved['method'],$saved['config'],$to,$subject,$html,$text,$options['attachments']??[]);
    }
    private function sendUsing(string $method,array $cfg,string $to,string $subject,string $html,string $text='',array $attachments=[]): array {
        try{
            if(!str_contains($html,'data-cms-email-template="1"')){
                $html=EmailTemplates::layout($subject,$html,['eyebrow'=>'NOTIFICACIÓN']);
            }
            if($method==='smtp')return $this->sendSmtp($cfg,$to,$subject,$html,$text,$attachments);
            if($method==='graph')return $this->sendGraph($cfg,$to,$subject,$html,$attachments);
            return ['success'=>false,'message'=>'No hay un método de correo configurado.'];
        }catch(Throwable $e){return ['success'=>false,'message'=>$e->getMessage()];}
    }
    private function sendGraph(array $c,string $to,string $subject,string $html,array $attachments): array {
        foreach(['tenant_id','client_id','client_secret','sender_email'] as $k)if(trim((string)($c[$k]??''))==='')throw new RuntimeException('Microsoft Graph configuration is incomplete.');
        if(!function_exists('curl_init'))throw new RuntimeException('cURL is required for Microsoft Graph.');
        $tokenUrl='https://login.microsoftonline.com/'.rawurlencode($c['tenant_id']).'/oauth2/v2.0/token';
        $ch=curl_init($tokenUrl);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'scope'=>'https://graph.microsoft.com/.default','grant_type'=>'client_credentials']),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>25]);
        $res=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);if($res===false||$code>=400)throw new RuntimeException('Graph authentication failed'.($err?': '.$err:'.'));
        $token=json_decode((string)$res,true)['access_token']??'';if($token==='')throw new RuntimeException('Graph did not return an access token.');
        $msg=['subject'=>$subject,'body'=>['contentType'=>'HTML','content'=>$html],'toRecipients'=>[['emailAddress'=>['address'=>$to]]]];
        if($attachments){$msg['attachments']=[];foreach($attachments as $a){$data=@file_get_contents($a['path']);if($data===false)continue;$msg['attachments'][]=['@odata.type'=>'#microsoft.graph.fileAttachment','name'=>$a['name']??basename($a['path']),'contentType'=>$a['mime']??'application/octet-stream','contentBytes'=>base64_encode($data)];}}
        $url='https://graph.microsoft.com/v1.0/users/'.rawurlencode($c['sender_email']).'/sendMail';$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POSTFIELDS=>json_encode(['message'=>$msg,'saveToSentItems'=>true]),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json'],CURLOPT_TIMEOUT=>30]);$body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);if($body===false||$code<200||$code>=300)throw new RuntimeException('Graph send failed'.($err?': '.$err:' (HTTP '.$code.').'));
        return ['success'=>true,'message'=>'Correo enviado correctamente mediante Microsoft Graph.'];
    }
    private function sendSmtp(array $c,string $to,string $subject,string $html,string $text,array $attachments): array {
        foreach(['host','port','username','password','from_email'] as $k)if(trim((string)($c[$k]??''))==='')throw new RuntimeException('SMTP configuration is incomplete.');
        $enc=strtolower((string)($c['encryption']??'tls'));$host=(string)$c['host'];$port=(int)$c['port'];$target=($enc==='ssl'?'ssl://':'').$host;
        $fp=@stream_socket_client($target.':'.$port,$errno,$errstr,20,STREAM_CLIENT_CONNECT);if(!$fp)throw new RuntimeException('SMTP connection failed: '.$errstr);
        stream_set_timeout($fp,20);$this->smtpExpect($fp,[220]);$this->smtpCmd($fp,'EHLO '.($_SERVER['SERVER_NAME']??'localhost'),[250]);
        if($enc==='tls'){$this->smtpCmd($fp,'STARTTLS',[220]);if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('Could not start TLS encryption.');$this->smtpCmd($fp,'EHLO '.($_SERVER['SERVER_NAME']??'localhost'),[250]);}
        $this->smtpCmd($fp,'AUTH LOGIN',[334]);$this->smtpCmd($fp,base64_encode((string)$c['username']),[334]);$this->smtpCmd($fp,base64_encode((string)$c['password']),[235]);
        $from=(string)$c['from_email'];$name=(string)($c['from_name']??setting('site_name','Sabrosísimo Mix'));$this->smtpCmd($fp,'MAIL FROM:<'.$from.'>',[250]);$this->smtpCmd($fp,'RCPT TO:<'.$to.'>',[250,251]);$this->smtpCmd($fp,'DATA',[354]);
        $boundary='b'.bin2hex(random_bytes(12));$headers=['From: '.$this->encodeHeader($name).' <'.$from.'>','To: <'.$to.'>','Subject: '.$this->encodeHeader($subject),'MIME-Version: 1.0','Content-Type: multipart/mixed; boundary="'.$boundary.'"'];
        $alt='a'.bin2hex(random_bytes(12));$body='--'.$boundary."\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n";
        $plain=$text!==''?$text:strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html));$body.='--'.$alt."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($plain));$body.='--'.$alt."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($html));$body.='--'.$alt."--\r\n";
        foreach($attachments as $a){$data=@file_get_contents($a['path']);if($data===false)continue;$nameA=preg_replace('/[^A-Za-z0-9._ -]/','_',(string)($a['name']??basename($a['path'])));$body.='--'.$boundary."\r\nContent-Type: ".($a['mime']??'application/octet-stream')."; name=\"$nameA\"\r\nContent-Disposition: attachment; filename=\"$nameA\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($data));}
        $body.='--'.$boundary."--\r\n";$payload=implode("\r\n",$headers)."\r\n\r\n".$body;
        fwrite($fp,preg_replace('/(?m)^\./','..',$payload)."\r\n.\r\n");$this->smtpExpect($fp,[250]);$this->smtpCmd($fp,'QUIT',[221]);fclose($fp);return ['success'=>true,'message'=>'Correo enviado correctamente mediante SMTP.'];
    }
    private function smtpCmd($fp,string $cmd,array $codes): void {fwrite($fp,$cmd."\r\n");$this->smtpExpect($fp,$codes);}
    private function smtpExpect($fp,array $codes): void {$resp='';while(($line=fgets($fp,515))!==false){$resp.=$line;if(strlen($line)>=4&&$line[3]===' ')break;}$code=(int)substr($resp,0,3);if(!in_array($code,$codes,true))throw new RuntimeException('SMTP error '.$code.': '.trim($resp));}
    private function encodeHeader(string $v): string {return '=?UTF-8?B?'.base64_encode($v).'?=';}
}
