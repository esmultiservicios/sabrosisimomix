<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');

$fields=[
    'site_name'=>'Nombre del sitio',
    'site_tagline'=>'Slogan',
    'site_url'=>'URL del sitio',
    'phone_primary'=>'Teléfono principal',
    'phone_secondary'=>'Teléfono secundario',
    'whatsapp'=>'WhatsApp (con código de país)',
    'location'=>'Ubicación'
];

function normalize_hn_phone_setting(string $phone): string {
    $phone=trim($phone);
    if($phone==='') return '';
    $digits=preg_replace('/\D+/','',$phone)??'';
    if(strlen($digits)===8) return '+504 '.substr($digits,0,4).'-'.substr($digits,4);
    if(strlen($digits)===11 && str_starts_with($digits,'504')) return '+504 '.substr($digits,3,4).'-'.substr($digits,7);
    return $phone;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        foreach($fields as $k=>$label){
            $value=trim((string)($_POST[$k]??''));
            if(in_array($k,['phone_primary','phone_secondary'],true)) $value=normalize_hn_phone_setting($value);
            if($k==='whatsapp'){
                $value=preg_replace('/\D+/','',$value)??'';
                if(strlen($value)===8) $value='504'.$value;
            }
            save_setting($k,$value);
        }
        save_setting('maintenance_mode',isset($_POST['maintenance_mode'])?'1':'0');
        log_activity('settings_update','Updated site settings');
        flash('success','Configuración guardada correctamente.');
        header('Location: settings.php');exit;
    }catch(Throwable $e){
        flash('error',$e->getMessage());header('Location: settings.php');exit;
    }
}

$values=[];
foreach($fields as $k=>$label){
    $values[$k]=(string)setting($k,'');
}
$values['phone_primary']=normalize_hn_phone_setting($values['phone_primary']!==''?$values['phone_primary']:'+504 3273-5251');
$values['phone_secondary']=normalize_hn_phone_setting($values['phone_secondary']!==''?$values['phone_secondary']:'+504 8809-9003');
if($values['whatsapp']==='') $values['whatsapp']='50488099003';

$pageTitle='Settings';$active='settings';require __DIR__.'/_header.php';
?>
<div class="page-heading"><div><p class="eyebrow">SITIO</p><h1>Configuración general</h1><p class="muted">Marca, contacto, redes sociales y mantenimiento.</p></div></div>
<form class="panel form-grid" method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<?php foreach($fields as $k=>$label):?>
<label>
  <span><?=h($label)?></span>
  <input name="<?=h($k)?>" value="<?=h($values[$k])?>" <?=in_array($k,['phone_primary','phone_secondary'],true)?'placeholder="+504 0000-0000"':''?>>
  <?php if(in_array($k,['phone_primary','phone_secondary'],true)):?><small>Incluye el código de país. Ejemplo: +504 3273-5251.</small><?php endif;?>
  <?php if($k==='whatsapp'):?><small>Usa solo números con código de país. Ejemplo: 50488099003.</small><?php endif;?>
</label>
<?php endforeach;?>
<label class="full status-field"><span class="field-label">Modo mantenimiento</span><span class="check-control"><input type="checkbox" name="maintenance_mode" <?=setting('maintenance_mode','0')==='1'?'checked':''?>><span>Ocultar temporalmente el sitio público</span></span></label>
<div class="full social-settings-link"><a class="button secondary" href="social.php">◎ Administrar redes sociales</a><small>Instagram, Facebook, TikTok y otros canales se configuran desde un módulo dedicado.</small></div>
<div class="full form-actions"><button>Guardar configuración</button></div>
</form>
<?php require __DIR__.'/_footer.php';?>
