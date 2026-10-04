<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');

function seo_public_url(string $path=''): string {
    $base=rtrim((string)setting('site_url',''),'/');
    if($base==='') $base=rtrim(base_url(),'/');
    return $base.'/'.ltrim($path,'/');
}

function seo_generate_files(): array {
    $base=rtrim(seo_public_url(),'/');
    $robots="User-agent: *\n";
    $indexing=(string)setting('seo_indexing','index-follow');
    if($indexing==='noindex-nofollow') {
        $robots.="Disallow: /\n";
    } else {
        $robots.="Disallow: /admin/\nDisallow: /install/\nDisallow: /config/\nDisallow: /core/\n";
    }
    $robots.="Sitemap: {$base}/sitemap.xml\n";

    $now=date('Y-m-d');
    $urls=[
        ['loc'=>$base.'/', 'priority'=>'1.0'],
        ['loc'=>$base.'/#servicios', 'priority'=>'0.9'],
        ['loc'=>$base.'/#nosotros', 'priority'=>'0.8'],
        ['loc'=>$base.'/#galeria', 'priority'=>'0.8'],
        ['loc'=>$base.'/#cotizar', 'priority'=>'0.9'],
    ];
    $xml="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach($urls as $item){
        $xml.="  <url><loc>".htmlspecialchars($item['loc'],ENT_XML1|ENT_QUOTES,'UTF-8')."</loc><lastmod>{$now}</lastmod><priority>{$item['priority']}</priority></url>\n";
    }
    $xml.="</urlset>\n";

    $robotsOk=@file_put_contents(ROOT_DIR.'/robots.txt',$robots)!==false;
    $sitemapOk=@file_put_contents(ROOT_DIR.'/sitemap.xml',$xml)!==false;
    return [$robotsOk,$sitemapOk];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verify_csrf();
        $action=(string)($_POST['action']??'save');
        if($action==='regenerate'){
            [$r,$s]=seo_generate_files();
            if(!$r||!$s) throw new RuntimeException('No se pudieron escribir robots.txt o sitemap.xml. Revisa permisos de escritura en la raíz del sitio.');
            log_activity('seo_regenerate','Regenerated robots.txt and sitemap.xml');
            flash('success','robots.txt y sitemap.xml fueron regenerados correctamente.');
            header('Location: seo.php');exit;
        }

        $title=trim((string)($_POST['seo_title']??''));
        $description=trim(strip_tags((string)($_POST['seo_description']??'')));
        $verification=trim((string)($_POST['google_site_verification']??''));
        $indexing=(string)($_POST['seo_indexing']??'index-follow');
        if(!in_array($indexing,['index-follow','noindex-follow','noindex-nofollow'],true)) $indexing='index-follow';
        if(strlen($title)>70) $title=substr($title,0,70);
        if(strlen($description)>180) $description=substr($description,0,180);

        save_setting('seo_title',$title);
        save_setting('seo_description',$description);
        save_setting('google_site_verification',$verification);
        save_setting('seo_indexing',$indexing);
        save_setting('turnstile_enabled',isset($_POST['turnstile_enabled'])?'1':'0');
        save_setting('turnstile_site_key',trim((string)($_POST['turnstile_site_key']??'')));
        $turnstileSecret=trim((string)($_POST['turnstile_secret_key']??''));
        if($turnstileSecret!=='') save_setting('turnstile_secret_key',$turnstileSecret);

        save_setting('email_validation_api_enabled',isset($_POST['email_validation_api_enabled'])?'1':'0');
        save_setting('email_validation_api_url',trim((string)($_POST['email_validation_api_url']??'')));
        save_setting('email_validation_api_timeout',(string)max(2,min(8,(int)($_POST['email_validation_api_timeout']??4))));
        $emailValidationApiKey=trim((string)($_POST['email_validation_api_key']??''));
        if($emailValidationApiKey!=='') save_setting('email_validation_api_key',$emailValidationApiKey);

        if(isset($_FILES['seo_social_image']) && (int)$_FILES['seo_social_image']['error']!==UPLOAD_ERR_NO_FILE){
            $file=$_FILES['seo_social_image'];
            if((int)$file['error']!==UPLOAD_ERR_OK) throw new RuntimeException('No se pudo recibir la imagen social.');
            if((int)$file['size']>5*1024*1024) throw new RuntimeException('La imagen social no puede superar 5 MB.');
            $mime=secure_file_mime_type((string)$file['tmp_name']);
            $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
            if(!$ext) throw new RuntimeException('La imagen social debe ser JPG, PNG o WEBP.');
            $dir=UPLOAD_DIR.'/seo';
            if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar uploads/seo.');
            foreach(glob($dir.'/social.*')?:[] as $old) @unlink($old);
            $relative='uploads/seo/social.'.$ext;
            if(!move_uploaded_file((string)$file['tmp_name'],ROOT_DIR.'/'.$relative)) throw new RuntimeException('No se pudo guardar la imagen social.');
            save_setting('seo_social_image',$relative);
        }

        [$r,$s]=seo_generate_files();
        log_activity('seo_update','Updated SEO and anti-spam settings',['robots'=>$r,'sitemap'=>$s]);
        flash('success','Configuración SEO y protección anti-spam guardadas.');
        header('Location: seo.php');exit;
    }catch(Throwable $e){
        flash('error',$e->getMessage());header('Location: seo.php');exit;
    }
}

$siteName=(string)setting('site_name','Sabrosísimo Mix');
$siteTagline=(string)setting('site_tagline','Sabor y Servicio es nuestra pasión');
$seoTitle=(string)setting('seo_title',$siteName.' | Eventos, comida y diversión');
$seoDescription=(string)setting('seo_description','Taqueadas, pupusas, pastelitos, snacks, saltarines y atención para eventos en San Pedro Sula.');
$verification=(string)setting('google_site_verification','');
$indexing=(string)setting('seo_indexing','index-follow');
$socialImage=(string)setting('seo_social_image','');
$turnstileEnabled=setting('turnstile_enabled','0')==='1';
$turnstileSiteKey=(string)setting('turnstile_site_key','');
$turnstileHasSecret=(string)setting('turnstile_secret_key','')!=='';
$emailValidationApiEnabled=setting('email_validation_api_enabled','0')==='1';
$emailValidationApiUrl=(string)setting('email_validation_api_url','');
$emailValidationApiHasKey=(string)setting('email_validation_api_key','')!=='';
$emailValidationApiTimeout=max(2,min(8,(int)setting('email_validation_api_timeout','4')));
$robotsExists=is_file(ROOT_DIR.'/robots.txt');
$sitemapExists=is_file(ROOT_DIR.'/sitemap.xml');
$titleLen=strlen($seoTitle);$descLen=strlen($seoDescription);
$score=0;
$score+=($titleLen>=35&&$titleLen<=70)?20:0;
$score+=($descLen>=100&&$descLen<=180)?20:0;
$score+=($indexing==='index-follow')?20:0;
$score+=($socialImage!==''&&is_file(ROOT_DIR.'/'.$socialImage))?20:0;
$score+=($verification!=='')?20:0;

$pageTitle='SEO Manager';$active='seo';require __DIR__.'/_header.php';
?>
<div class="seo-hero panel">
  <div><p class="eyebrow">SEO MANAGER</p><h1>Visibilidad en buscadores</h1><p class="muted">Controla cómo aparece Sabrosísimo Mix en Google, redes sociales y protege el formulario público contra spam.</p></div>
  <div class="seo-score"><strong><?=$score?>%</strong><span>Estado SEO<small><?=intdiv($score,20)?> de 5 controles completos</small></span></div>
</div>

<form method="post" enctype="multipart/form-data" class="seo-layout">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="save">
<section class="panel seo-main-card">
  <div class="section-heading"><div><p class="eyebrow">CONFIGURACIÓN PRINCIPAL</p><h2>Cómo se presenta tu sitio</h2><p>Define títulos, descripción, indexación e imagen social.</p></div></div>
  <div class="seo-form-grid">
    <label class="full"><span>Título para navegador y Google</span><input name="seo_title" maxlength="70" value="<?=h($seoTitle)?>" data-seo-title><small>Recomendado: 50–60 caracteres. <b data-seo-title-count><?=$titleLen?></b>/70</small></label>
    <label class="full"><span>Meta descripción</span><textarea name="seo_description" maxlength="180" data-rte-off data-seo-description><?=h($seoDescription)?></textarea><small>Resume qué ofreces y qué problema resuelves. <b data-seo-description-count><?=$descLen?></b>/180</small></label>
    <label><span>Google Site Verification</span><input name="google_site_verification" value="<?=h($verification)?>" placeholder="Código de Google Search Console"><small>Pega solamente el valor del atributo content.</small></label>
    <label><span>Indexación</span><select name="seo_indexing"><option value="index-follow" <?=$indexing==='index-follow'?'selected':''?>>Indexar y seguir enlaces</option><option value="noindex-follow" <?=$indexing==='noindex-follow'?'selected':''?>>No indexar, seguir enlaces</option><option value="noindex-nofollow" <?=$indexing==='noindex-nofollow'?'selected':''?>>No indexar ni seguir enlaces</option></select><small>Para producción usa “Indexar y seguir enlaces”.</small></label>
  </div>
  <div class="seo-upload-card">
    <div><h3>Imagen para compartir</h3><p>Se usa al compartir el sitio en WhatsApp, Facebook y otras plataformas.</p></div>
    <?php if($socialImage!==''&&is_file(ROOT_DIR.'/'.$socialImage)):?><button type="button" class="seo-social-preview" data-preview-src="../<?=h($socialImage)?>" data-preview-caption="Imagen social"><img src="../<?=h($socialImage)?>" alt="Imagen social actual"></button><?php endif;?>
    <label class="premium-file-button"><input type="file" name="seo_social_image" accept="image/jpeg,image/png,image/webp"><span>▧ Elegir imagen</span><small>JPG, PNG o WEBP · máximo 5 MB</small></label>
  </div>
  <div class="form-actions"><button type="submit">Guardar configuración SEO</button></div>
</section>

<aside class="seo-side">
  <section class="panel seo-preview-card">
    <p class="eyebrow">VISTA PREVIA EN GOOGLE</p><h2>Resultado aproximado</h2>
    <div class="google-preview"><small><?=h(parse_url(seo_public_url(),PHP_URL_HOST)?:'sitio')?></small><strong data-seo-preview-title><?=h($seoTitle)?></strong><p data-seo-preview-description><?=h($seoDescription)?></p></div>
  </section>
  <section class="panel seo-health-card">
    <p class="eyebrow">SALUD SEO</p><h2>Lista de revisión</h2>
    <?php $checks=[['Título SEO definido',$titleLen>0],['Descripción optimizada',$descLen>=100],['Indexación habilitada',$indexing==='index-follow'],['Imagen social configurada',$socialImage!==''&&is_file(ROOT_DIR.'/'.$socialImage)],['Google Search Console',$verification!=='']];foreach($checks as [$label,$ok]):?><div class="seo-check <?=$ok?'ok':'pending'?>"><span><?=$ok?'✓':'•'?></span><strong><?=h($label)?></strong><small><?=$ok?'Correcto':'Pendiente'?></small></div><?php endforeach;?>
  </section>
</aside>

<section class="panel seo-social-card">
  <p class="eyebrow">SOCIAL</p><h2>Vista previa al compartir</h2><p class="muted">Así puede verse el enlace cuando alguien comparte el sitio.</p>
  <div class="share-preview"><?php if($socialImage!==''&&is_file(ROOT_DIR.'/'.$socialImage)):?><img src="../<?=h($socialImage)?>" alt="Imagen social"><?php else:?><div class="share-preview-placeholder">SM</div><?php endif;?><div><small><?=h(strtoupper(parse_url(seo_public_url(),PHP_URL_HOST)?:'SITIO'))?></small><strong><?=h($seoTitle)?></strong><p><?=h($seoDescription)?></p></div></div>
</section>

<section class="panel seo-tech-card">
  <p class="eyebrow">SEO TÉCNICO</p><h2>Robots y Sitemap</h2><p class="muted">Archivos públicos para rastreo e indexación.</p>
  <div class="seo-file-row"><div><span class="seo-file-icon">✓</span><strong>robots.txt</strong><small><?=$robotsExists?'Listo':'Pendiente de generar'?></small></div><code><?=h(seo_public_url('robots.txt'))?></code></div>
  <div class="seo-file-row"><div><span class="seo-file-icon">⌘</span><strong>sitemap.xml</strong><small><?=$sitemapExists?'Listo':'Pendiente de generar'?></small></div><code><?=h(seo_public_url('sitemap.xml'))?></code></div>
  <button type="submit" name="action" value="regenerate" class="button secondary">⚙ Regenerar robots y sitemap</button>
</section>


<section class="panel seo-turnstile-card full">
  <div class="section-heading"><div><p class="eyebrow">VALIDACIÓN DE CORREO</p><h2>Filtro avanzado contra correos falsos</h2><p>El formulario valida formato, errores frecuentes, dominio, MX y correos temporales antes de aceptar una solicitud.</p></div><span class="status-pill is-on">Integrado</span></div>
  <div class="seo-check ok"><span>✓</span><strong>Formato + sugerencias</strong><small>Activo</small></div>
  <div class="seo-check ok"><span>✓</span><strong>Dominio + registros MX/DNS</strong><small>Activo</small></div>
  <div class="seo-check ok"><span>✓</span><strong>Bloqueo de correo temporal + rate limit + honeypot</strong><small>Activo</small></div>
  <div class="email-api-settings">
    <label class="toggle-row"><input type="checkbox" name="email_validation_api_enabled" <?=$emailValidationApiEnabled?'checked':''?>><span>Usar también un servicio/API externo de verificación de buzón</span></label>
    <div class="form-grid">
      <label>URL del servicio<input type="url" name="email_validation_api_url" value="<?=h($emailValidationApiUrl)?>" placeholder="https://api.ejemplo.com/verify?email={email}&key={key}"><small>Usa <code>{email}</code> para el correo y, si aplica, <code>{key}</code> para la clave. Si el proveedor está caído, el formulario continúa con las validaciones locales.</small></label>
      <label>API Key<input type="password" name="email_validation_api_key" value="" autocomplete="new-password" placeholder="<?=$emailValidationApiHasKey?'Guardada · escribe solo para reemplazarla':'Clave opcional del proveedor'?>"><small><?=$emailValidationApiHasKey?'La clave ya está guardada.':'Solo se utiliza del lado del servidor.'?></small></label>
      <label>Timeout<select name="email_validation_api_timeout"><option value="2" <?=$emailValidationApiTimeout===2?'selected':''?>>2 segundos</option><option value="4" <?=$emailValidationApiTimeout===4?'selected':''?>>4 segundos</option><option value="6" <?=$emailValidationApiTimeout===6?'selected':''?>>6 segundos</option><option value="8" <?=$emailValidationApiTimeout===8?'selected':''?>>8 segundos</option></select><small>Recomendado: 4 segundos para no hacer lento el formulario.</small></label>
    </div>
    <div class="seo-api-note"><strong>Fallback seguro:</strong> si la API externa no responde, no se bloquea automáticamente a un cliente legítimo. El servidor conserva formato, dominio, MX/DNS, correo temporal, rate limit, honeypot y Turnstile.</div>
  </div>
</section>
<section class="panel seo-turnstile-card full">
  <div class="section-heading"><div><p class="eyebrow">CLOUDFLARE TURNSTILE</p><h2>Protección anti-spam del formulario</h2><p>Agrega una validación invisible/ligera antes de aceptar solicitudes públicas.</p></div><span class="status-pill <?=$turnstileEnabled?'is-on':''?>"><?=$turnstileEnabled?'Activo':'Desactivado'?></span></div>
  <label class="toggle-row"><input type="checkbox" name="turnstile_enabled" <?=$turnstileEnabled?'checked':''?>><span>Activar Cloudflare Turnstile en el formulario de cotización</span></label>
  <div class="form-grid">
    <label>Site Key<input name="turnstile_site_key" value="<?=h($turnstileSiteKey)?>" autocomplete="off" placeholder="0x4AAAA..."><small>Clave pública que Cloudflare permite mostrar en el frontend.</small></label>
    <label>Secret Key<input type="password" name="turnstile_secret_key" value="" autocomplete="new-password" placeholder="<?=$turnstileHasSecret?'Guardada · escribe solo para reemplazarla':'0x4AAAA...'?>"><small><?=$turnstileHasSecret?'La clave secreta ya está guardada.':'Se valida únicamente desde el servidor.'?></small></label>
  </div>
</section>
</form>
<script>
(()=>{const t=document.querySelector('[data-seo-title]'),d=document.querySelector('[data-seo-description]');const update=()=>{document.querySelector('[data-seo-title-count]').textContent=t?.value.length||0;document.querySelector('[data-seo-description-count]').textContent=d?.value.length||0;document.querySelector('[data-seo-preview-title]').textContent=t?.value||'Título del sitio';document.querySelector('[data-seo-preview-description]').textContent=d?.value||'Descripción del sitio';};t?.addEventListener('input',update);d?.addEventListener('input',update);update();})();
</script>
<?php require __DIR__.'/_footer.php';?>
