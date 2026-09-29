<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');

$platforms = [
    'instagram' => 'Instagram',
    'facebook' => 'Facebook',
    'tiktok' => 'TikTok',
    'youtube' => 'YouTube',
    'linkedin' => 'LinkedIn',
];
$locations = [
    'footer' => 'Footer',
    'after-hero' => 'Debajo del hero',
    'floating-left' => 'Flotante · izquierda',
    'floating-right' => 'Flotante · derecha',
    'footer-floating-left' => 'Footer + flotante izquierda',
    'footer-floating-right' => 'Footer + flotante derecha',
];
$sizes = ['small'=>'Pequeño','medium'=>'Mediano','large'=>'Grande'];
$styles = ['icons'=>'Solo iconos','labels'=>'Icono + nombre'];

function social_defaults(): array {
    return [
        ['enabled'=>1,'platform'=>'instagram','url'=>'https://www.instagram.com/sabrosisimomix/','order'=>1],
        ['enabled'=>1,'platform'=>'facebook','url'=>'https://web.facebook.com/people/Sabros%C3%ADsimo-mix/61592916879862/','order'=>2],
        ['enabled'=>1,'platform'=>'tiktok','url'=>'https://www.tiktok.com/@sabrosisimomix','order'=>3],
        ['enabled'=>0,'platform'=>'youtube','url'=>'','order'=>4],
        ['enabled'=>0,'platform'=>'linkedin','url'=>'','order'=>5],
    ];
}
function social_rows(): array {
    $raw=(string)setting('social_networks_json','');
    $decoded=json_decode($raw,true);
    if(!is_array($decoded)) return social_defaults();
    $rows=[];
    for($i=0;$i<5;$i++){
        $rows[] = is_array($decoded[$i]??null) ? array_merge(social_defaults()[$i],$decoded[$i]) : social_defaults()[$i];
    }
    return $rows;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        $rows=[];
        for($i=0;$i<5;$i++){
            $platform=(string)($_POST['platform'][$i]??array_keys($platforms)[$i]);
            if(!isset($platforms[$platform])) $platform='instagram';
            $url=trim((string)($_POST['url'][$i]??''));
            if($url!=='' && !filter_var($url,FILTER_VALIDATE_URL)) throw new RuntimeException('Revisa las URLs de las redes sociales. Hay una dirección no válida.');
            $rows[]=[
                'enabled'=>isset($_POST['enabled'][$i])?1:0,
                'platform'=>$platform,
                'url'=>$url,
                'order'=>max(1,min(99,(int)($_POST['order'][$i]??($i+1)))),
            ];
        }
        $location=(string)($_POST['social_display_location']??'footer-floating-right');
        $size=(string)($_POST['social_icon_size']??'medium');
        $style=(string)($_POST['social_display_style']??'icons');
        if(!isset($locations[$location]))$location='footer-floating-right';
        if(!isset($sizes[$size]))$size='medium';
        if(!isset($styles[$style]))$style='icons';
        save_setting('social_networks_json',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        save_setting('social_display_location',$location);
        save_setting('social_icon_size',$size);
        save_setting('social_display_style',$style);
        save_setting('social_show_desktop',isset($_POST['social_show_desktop'])?'1':'0');
        save_setting('social_show_mobile',isset($_POST['social_show_mobile'])?'1':'0');
        log_activity('social_settings_update','Updated social network settings');
        flash('success','Redes sociales actualizadas correctamente.');
        header('Location: social.php');exit;
    }catch(Throwable $e){
        flash('error',$e->getMessage());header('Location: social.php');exit;
    }
}

$rows=social_rows();
$pageTitle='Redes sociales';$active='social';require __DIR__.'/_header.php';
?>
<div class="page-heading">
  <div><p class="eyebrow">PRESENCIA DIGITAL</p><h1>Redes sociales</h1><p class="muted">Configura hasta cinco canales, su orden, tamaño y dónde aparecerán en el sitio publicado.</p></div>
  <div class="actions"><a class="button secondary" href="../" target="_blank" rel="noopener">↗ Ver sitio</a></div>
</div>
<form method="post" class="social-admin-layout">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<section class="panel social-display-panel">
  <div class="section-heading"><div><p class="eyebrow">VISUALIZACIÓN</p><h2>Cómo quieres mostrarlas</h2><p>El sitio las coloca en una zona estratégica sin interferir con WhatsApp ni otros widgets.</p></div></div>
  <div class="form-grid social-display-grid">
    <label>Ubicación<select name="social_display_location"><?php foreach($locations as $k=>$v):?><option value="<?=h($k)?>" <?=setting('social_display_location','footer-floating-right')===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></label>
    <label>Tamaño<select name="social_icon_size"><?php foreach($sizes as $k=>$v):?><option value="<?=h($k)?>" <?=setting('social_icon_size','medium')===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></label>
    <label>Estilo<select name="social_display_style"><?php foreach($styles as $k=>$v):?><option value="<?=h($k)?>" <?=setting('social_display_style','icons')===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></label>
  </div>
  <div class="social-device-toggles">
    <label class="toggle-row"><input type="checkbox" name="social_show_desktop" <?=setting('social_show_desktop','1')==='1'?'checked':''?>><span>Mostrar en escritorio y tablet</span></label>
    <label class="toggle-row"><input type="checkbox" name="social_show_mobile" <?=setting('social_show_mobile','1')==='1'?'checked':''?>><span>Mostrar en móvil</span></label>
  </div>
</section>
<section class="panel">
  <div class="section-heading"><div><p class="eyebrow">CANALES</p><h2>Redes configuradas</h2><p>Activa solo las que quieras publicar. Las demás pueden quedar preparadas para usarlas después.</p></div></div>
  <div class="social-network-list">
  <?php foreach($rows as $i=>$row):?>
    <article class="social-network-row">
      <div class="social-network-number"><?=($i+1)?></div>
      <label class="social-enable"><input type="checkbox" name="enabled[<?=$i?>]" <?=$row['enabled']?'checked':''?>><span>Visible</span></label>
      <label>Red<select name="platform[<?=$i?>]"><?php foreach($platforms as $k=>$v):?><option value="<?=h($k)?>" <?=$row['platform']===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></label>
      <label class="social-url-field">URL<input type="url" name="url[<?=$i?>]" value="<?=h($row['url'])?>" placeholder="https://..."></label>
      <label>Orden<input type="number" min="1" max="99" name="order[<?=$i?>]" value="<?=h((string)$row['order'])?>"></label>
    </article>
  <?php endforeach;?>
  </div>
</section>
<div class="social-savebar"><button type="submit">Guardar redes sociales</button><span class="muted">Los cambios se reflejan inmediatamente en la vista publicada.</span></div>
</form>
<?php require __DIR__.'/_footer.php';?>
