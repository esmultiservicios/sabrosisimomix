<?php
require __DIR__.'/bootstrap.php';
require_permission('email.manage');
require_once __DIR__.'/../core/EmailService.php';
$pdo=db();
$set=settings();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    try {
        if($action==='recipients') {
            $notification=trim((string)($_POST['estimate_notification_email']??''));
            $copy=trim((string)($_POST['estimate_copy_email']??''));
            if(!filter_var($notification,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email that will receive estimate requests.');
            if($copy!==''&&!filter_var($copy,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid optional copy email or leave it blank.');
            save_setting('estimate_notification_email',$notification);
            save_setting('estimate_copy_email',$copy);
            flash('success','Estimate notification recipients saved.');
        } elseif($action==='save') {
            $type=(int)($_POST['correo_tipo_id']??1);
            $copySourceId=(int)($_POST['copy_source_id']??0);
            $method=in_array($_POST['metodo_envio']??'SMTP',['SMTP','GRAPH'],true)?$_POST['metodo_envio']:'SMTP';
            $server=trim((string)($_POST['server']??''));
            $email=trim((string)($_POST['correo']??''));
            $port=(int)($_POST['port']??587);
            $secure=trim((string)($_POST['smtp_secure']??'tls'));
            $tenant=trim((string)($_POST['tenant_id']??''));
            $client=trim((string)($_POST['client_id']??''));
            $graph=trim((string)($_POST['graph_user']??''));
            $sent=isset($_POST['save_to_sent_items'])?1:0;
            $estado=isset($_POST['estado'])?1:2;
            if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid sender email.');
            $typeCheck=$pdo->prepare('SELECT COUNT(*) FROM correo_tipo WHERE correo_tipo_id=?');
            $typeCheck->execute([$type]);
            if((int)$typeCheck->fetchColumn()!==1)throw new RuntimeException('Select a valid destination email purpose.');
            $mailer=new EmailService();
            $old=$id?$mailer->configById($id):($copySourceId?$mailer->configById($copySourceId):null);
            if(($id||$copySourceId)&&!$old)throw new RuntimeException('The source email configuration was not found.');
            if($copySourceId&&(int)$old['correo_tipo_id']===$type)throw new RuntimeException('Choose a different email purpose for the copied connection.');
            $pass=trim((string)($_POST['password']??''));
            $secret=trim((string)($_POST['client_secret']??''));
            $passEnc=$pass!==''?secret_encrypt($pass):($old['password']??'');
            $secretEnc=$secret!==''?secret_encrypt($secret):($old['client_secret']??'');
            if($method==='GRAPH') {
                $server='graph.microsoft.com';
                $port=0;
                $secure='';
                if($graph==='')$graph=$email;
                if($estado===1&&($tenant===''||$client===''||$secretEnc===''||!filter_var($graph,FILTER_VALIDATE_EMAIL)))throw new RuntimeException('Complete Tenant ID, Client ID, Client Secret VALUE and Graph mailbox before activating Microsoft Graph.');
            } else {
                if($port<1||$port>65535)throw new RuntimeException('Enter a valid SMTP port.');
                if(!in_array($secure,['tls','ssl'],true))throw new RuntimeException('Select TLS or SSL for SMTP security.');
                if($estado===1&&($server===''||$passEnc===''))throw new RuntimeException('Complete the SMTP server and app password before activating SMTP.');
            }
            if($id) {
                $st=$pdo->prepare('UPDATE correo SET correo_tipo_id=?,metodo_envio=?,server=?,correo=?,password=?,port=?,smtp_secure=?,tenant_id=?,client_id=?,client_secret=?,graph_user=?,save_to_sent_items=?,estado=? WHERE correo_id=?');
                $st->execute([$type,$method,$server,$email,$passEnc,$port,$secure,$tenant?:null,$client?:null,$secretEnc?:null,$graph?:null,$sent,$estado,$id]);
                $savedId=$id;
            } else {
                $st=$pdo->prepare('INSERT INTO correo(correo_tipo_id,metodo_envio,server,correo,password,port,smtp_secure,tenant_id,client_id,client_secret,graph_user,save_to_sent_items,estado,fecha_registro) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                $st->execute([$type,$method,$server,$email,$passEnc,$port,$secure,$tenant?:null,$client?:null,$secretEnc?:null,$graph?:null,$sent,$estado]);
                $savedId=(int)$pdo->lastInsertId();
            }
            if($estado===1) {
                $off=$pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=? AND correo_id<>?');
                $off->execute([$type,$savedId]);
            }
            flash('success',$copySourceId?'Email configuration copied to the selected purpose.':'Email configuration saved.');
        } elseif($action==='delete'&&$id) {
            $pdo->prepare('DELETE FROM correo WHERE correo_id=?')->execute([$id]);
            flash('success','Email configuration deleted.');
        } elseif($action==='toggle'&&$id) {
            $row=(new EmailService())->configById($id);
            if(!$row)throw new RuntimeException('Email configuration not found.');
            $newStatus=(int)$row['estado']===1?2:1;
            if($newStatus===1) {
                validate_email_configuration($row);
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=? AND correo_id<>?')->execute([(int)$row['correo_tipo_id'],$id]);
                $pdo->prepare('UPDATE correo SET estado=1 WHERE correo_id=?')->execute([$id]);
                $pdo->commit();
            } else {
                $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_id=?')->execute([$id]);
            }
            flash('success','Email status updated.');
        } elseif($action==='test'&&$id) {
            $res=(new EmailService())->test($id,trim((string)($_POST['test_to']??'')));
            flash($res['success']?'success':'error',$res['message']);
        }
        header('Location: email.php'.($id?'?edit='.$id:''));
        exit;
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        $error=$e->getMessage();
    }
}
$set=settings();
$types=$pdo->query('SELECT * FROM correo_tipo ORDER BY correo_tipo_id')->fetchAll();
$rows=$pdo->query('SELECT c.*,t.nombre tipo_nombre FROM correo c JOIN correo_tipo t ON t.correo_tipo_id=c.correo_tipo_id ORDER BY c.correo_id DESC')->fetchAll();
$edit=null;
$isCopy=false;
$copySource=null;
if(isset($_GET['edit'])) {
    $edit=(new EmailService())->configById((int)$_GET['edit']);
} elseif(isset($_GET['copy'])) {
    $copySource=(new EmailService())->configById((int)$_GET['copy']);
    if($copySource) {
        $edit=$copySource;
        $isCopy=true;
        $sourceTypeId=(int)$copySource['correo_tipo_id'];
        $availableTypeIds=array_map(static fn($type)=>(int)$type['correo_tipo_id'],$types);
        $configuredTypeIds=array_map(static fn($row)=>(int)$row['correo_tipo_id'],$rows);
        $destinationPriority=[3,4,2,1];
        $destinationTypeId=0;

        foreach($destinationPriority as $candidateTypeId) {
            if($candidateTypeId!==$sourceTypeId
                &&in_array($candidateTypeId,$availableTypeIds,true)
                &&!in_array($candidateTypeId,$configuredTypeIds,true)) {
                $destinationTypeId=$candidateTypeId;
                break;
            }
        }

        if($destinationTypeId===0) {
            foreach($destinationPriority as $candidateTypeId) {
                if($candidateTypeId!==$sourceTypeId&&in_array($candidateTypeId,$availableTypeIds,true)) {
                    $destinationTypeId=$candidateTypeId;
                    break;
                }
            }
        }

        $edit['correo_tipo_id']=$destinationTypeId;
    }
}
$pageTitle='Email Configuration';
$active='email';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">EMAIL</p>
<h1>SMTP & Microsoft Graph</h1>
<p class="muted">Configure purpose-specific senders for website alerts, administrator security, estimate requests and automatic customer replies.</p>
</div>
<a class="button secondary" href="email.php">New configuration</a>
</div><?php
if($error):
?>
<div class="alert error"><?=h($error)?>
</div><?php
endif;
?>

<section class="panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('mail')?>
</div>
<div>
<h2>Estimate notification recipients</h2>
<p>The first address receives every website request. The optional copy is sent as BCC, so it stays hidden from the customer and other recipients.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="recipients">
<div class="two-col">
<label>Send new requests to<input type="email" name="estimate_notification_email" required value="<?=h($set['estimate_notification_email']??($set['email']??'castrosreadycompany@gmail.com'))?>" placeholder="castrosreadycompany@gmail.com">
<small>This is the internal Castro's Ready inbox that receives the complete request.</small>
</label>
<label>Optional copy to (BCC)<input type="email" name="estimate_copy_email" value="<?=h($set['estimate_copy_email']??'')?>" placeholder="Optional supervision email">
<small>This address is hidden from the customer and from the primary recipient. Leave blank when no additional copy is needed.</small>
</label>
</div>
<div class="form-actions"><button>Save notification recipients</button></div>
</form>
</section>

<section class="panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('mail')?>
</div>
<div>
<h2><?=$isCopy?'Copy configuration':($edit?'Edit configuration':'Add email configuration')?>
</h2>
<p><?=$isCopy?'A destination purpose is selected automatically. The connection data and encrypted credentials will be reused.':'Secrets are encrypted before they are stored in MySQL.'?></p>
</div>
</div>
<?php if($isCopy): ?>
<div class="info-strip">
<strong>Copying <?=h($copySource['metodo_envio'])?> connection</strong>
<span>Select where else this connection should be used. The original connection will remain unchanged.</span>
</div>
<?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?=h((string)($isCopy?0:($edit['correo_id']??0)))?>
">
<input type="hidden" name="copy_source_id" value="<?=h((string)($isCopy?($copySource['correo_id']??0):0))?>">
<div class="three-col">
<label>Email purpose<select name="correo_tipo_id" required><?php
if($isCopy&&empty($edit['correo_tipo_id'])):
?>
<option value="" selected disabled>Choose destination purpose</option><?php
endif;
foreach($types as $t):
if($isCopy&&(int)$t['correo_tipo_id']===(int)$copySource['correo_tipo_id'])continue;
?>
<option value="<?=$t['correo_tipo_id']?>
" <?=($edit['correo_tipo_id']??1)==$t['correo_tipo_id']?'selected':''?>
><?=h($t['nombre'])?>
</option><?php
endforeach;
?>
</select>
</label>
<label>Method<select name="metodo_envio" data-method-select>
<option value="SMTP" <?=($edit['metodo_envio']??'SMTP')==='SMTP'?'selected':''?>
>SMTP</option>
<option value="GRAPH" <?=($edit['metodo_envio']??'')==='GRAPH'?'selected':''?>
>Microsoft Graph</option>
</select>
</label>
<label class="premium-switch">
<input type="checkbox" name="estado" <?=!$edit||($edit['estado']??1)==1?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Active configuration</b>
<small>Use this sender for the selected email purpose.</small>
</span>
</label>
</div>
<label>Sender email<input type="email" name="correo" required value="<?=h($edit['correo']??($set['email']??'castrosreadycompany@gmail.com'))?>
">
<small>The messages are sent under Castro's Ready branding from this account.</small>
</label>
<div class="email-method-fields" data-method="SMTP">
<div class="three-col">
<label>SMTP server<input name="server" value="<?=h(($edit['metodo_envio']??'SMTP')==='SMTP'?($edit['server']??'smtp.gmail.com'):'')?>
">
</label>
<label>Port<input type="number" name="port" value="<?=h((string)($edit['port']??587))?>
">
</label>
<label>Security<select name="smtp_secure">
<option value="tls" <?=($edit['smtp_secure']??'tls')==='tls'?'selected':''?>
>TLS</option>
<option value="ssl" <?=($edit['smtp_secure']??'')==='ssl'?'selected':''?>
>SSL</option>
</select>
</label>
</div>
<label>SMTP password<input type="password" name="password" autocomplete="new-password" placeholder="<?=$edit?'Leave blank to keep saved password':'SMTP password'?>
">
<small>For Gmail, use an App Password generated after enabling two-step verification. Do not enter the normal Gmail password.</small>
</label>
</div>
<div class="email-method-fields" data-method="GRAPH">
<div class="two-col">
<label>Tenant ID<input name="tenant_id" value="<?=h($edit['tenant_id']??'')?>
">
</label>
<label>Client ID<input name="client_id" value="<?=h($edit['client_id']??'')?>
">
</label>
</div>
<label>Client Secret VALUE<input type="password" name="client_secret" placeholder="<?=$edit?'Leave blank to keep saved secret':'Microsoft Entra client secret'?>
">
<small>Graph uses the Microsoft Entra application secret; it never requires the mailbox password.</small>
</label>
<label>Graph User / mailbox<input type="email" name="graph_user" value="<?=h($edit['graph_user']??'')?>
">
</label>
<label class="premium-switch">
<input type="checkbox" name="save_to_sent_items" <?=!$edit||!empty($edit['save_to_sent_items'])?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Save a copy in Sent Items</b>
<small>Microsoft Graph will keep a copy in the sender mailbox.</small>
</span>
</label>
</div>
<div class="form-actions">
<button>Save email configuration</button><?php
if($edit):
?>
<a class="button secondary" href="email.php"><?=$isCopy?'Cancel copy':'Cancel edit'?></a><?php
endif;
?>
</div>
</form>
</section>
<?php
if($edit&&!$isCopy):
?>
<section class="panel email-test-panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('mail')?>
</div>
<div>
<h2>Test this configuration</h2>
<p>Send a real test message before using this sender on the website.</p>
</div>
</div>
<form method="post" class="test-email-form">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="test">
<input type="hidden" name="id" value="<?=$edit['correo_id']?>
">
<label>Test destination email<input type="email" name="test_to" required placeholder="you@example.com" value="<?=h($edit['correo']??'')?>
">
</label>
<div class="form-actions">
<button class="button">Send test email</button>
</div>
</form>
</section><?php
else:
?>
<div class="info-strip">
<strong>Want to test it?</strong>
<span>Save the configuration first. The test tool will appear here immediately after saving.</span>
</div><?php
endif;
?>

<div class="section-heading">
<div>
<p class="eyebrow">CONFIGURED SENDERS</p>
<h2>Email connections</h2>
</div>
</div><?php
if(!$rows):
?>
<div class="empty-state">
<strong>No email configured</strong>
<p>Add SMTP or Microsoft Graph above.</p>
</div><?php
else:
?>
<div class="email-grid"><?php
foreach($rows as $r):
?>
<article class="email-card animate-in">
<div class="list-head">
<div>
<span class="email-method"><?=h($r['metodo_envio'])?>
</span>
<h3><?=h($r['tipo_nombre'])?>
</h3>
<small><?=h($r['correo'])?>
</small>
</div>
<span class="badge <?=$r['estado']==1?'contacted':'closed'?>
"><?=$r['estado']==1?'Active':'Inactive'?>
</span>
</div>
<p class="muted"><?=$r['metodo_envio']==='GRAPH'?'Microsoft 365 / Graph mailbox: '.h($r['graph_user']?:$r['correo']):'SMTP: '.h($r['server']).':'.(int)$r['port']?>
</p>
<div class="actions">
<form method="post" class="actions">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="test">
<input type="hidden" name="id" value="<?=$r['correo_id']?>
">
<input style="max-width:230px" type="email" name="test_to" placeholder="Test destination">
<button class="button small">Send test</button>
</form>
<details class="action-menu">
<summary>Actions ▾</summary>
<nav>
<a href="?edit=<?=$r['correo_id']?>
">Edit</a>
<a href="?copy=<?=$r['correo_id']?>">Copy to another purpose</a>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="toggle">
<input type="hidden" name="id" value="<?=$r['correo_id']?>
">
<button><?=$r['estado']==1?'Deactivate':'Activate'?>
</button>
</form>
<form method="post" data-swal-confirm="Delete this email configuration?" data-swal-text="Email delivery using this configuration will stop.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$r['correo_id']?>
">
<button class="danger-text">Delete</button>
</form>
</nav>
</details>
</div>
</article><?php
endforeach;
?>
</div><?php
endif;
?>
<?php
require __DIR__.'/_footer.php';
