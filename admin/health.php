<?php
require __DIR__.'/bootstrap.php';
require_permission('health.view');
$pdo=db();
$s=settings();
$runtimeRequirements=server_runtime_requirements();
$checks=[];
$checks[]=['Logo configured',
!empty($s['admin_logo_path']),
'Upload the admin logo in Settings.',
'settings.php'];
$checks[]=['Favicon configured',
!empty($s['favicon_path']),
'Upload a browser tab icon.',
'settings.php'];
$checks[]=['WhatsApp enabled',
($s['whatsapp_enabled']??'0')==='1',
'Enable floating WhatsApp.',
'settings.php'];
$checks[]=['Public email configured',
filter_var($s['email']??'',FILTER_VALIDATE_EMAIL)!==false,
'Add a valid public email.',
'settings.php'];
$emailOk=(int)$pdo->query('SELECT COUNT(*) FROM correo WHERE estado=1')->fetchColumn()>0;
$checks[]=['Email delivery configured',
$emailOk,
'Configure and test SMTP or Microsoft Graph.',
'email.php'];
$areas=(int)$pdo->query('SELECT COUNT(*) FROM service_areas WHERE active=1')->fetchColumn();
$checks[]=['Service areas published',
$areas>0,
'Add at least one service area.',
'areas.php'];
$missing=(int)$pdo->query("SELECT COUNT(*) FROM gallery WHERE active=1 AND (image_path IS NULL OR image_path='')")->fetchColumn();
$checks[]=['Gallery uses real images',
$missing===0,
$missing.' gallery item(s) still use fallback images.',
'gallery.php'];
$seoOk=strlen(trim($s['seo_title']??''))>10&&strlen(trim($s['seo_description']??''))>40;
$checks[]=['SEO basics configured',
$seoOk,
'Review title and meta description.',
'seo.php'];
$https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
$checks[]=['HTTPS detected',
$https,
'Production should run over HTTPS.',
'#'];
$runtimePassed=count(array_filter($runtimeRequirements,static fn($requirement)=>!empty($requirement['available'])));
$websitePassed=count(array_filter($checks,static fn($check)=>!empty($check[1])));
$passed=$runtimePassed+$websitePassed;
$checkCount=count($runtimeRequirements)+count($checks);
$score=(int)round($passed/$checkCount*100);
$pageTitle='Website Health';
$active='health';
require __DIR__.'/_header.php';
?>
<div class="page-heading">
<div>
<p class="eyebrow">WEBSITE HEALTH</p>
<h1>Site readiness check</h1>
<p class="muted">A practical checklist showing what is ready and what needs attention.</p>
</div>
<a class="button" href="health.php?recheck=<?=time()?>#server-requirements">↻ Recheck server</a>
</div>
<div class="health-hero">
<div class="health-score">
<strong><?=$score?>
%</strong>
<span><?=$passed?>
 of <?=$checkCount?>
 checks passed</span>
</div>
<div class="health-meter">
<i style="width:<?=$score?>
%">
</i>
</div>
</div>

<section class="panel" id="server-requirements">
<div class="panel-heading">
<div class="panel-icon"><?=icon('gear')?>
</div>
<div>
<h2>PHP & server requirements</h2>
<p>These checks are read directly from the active PHP environment every time this page loads. Install an extension, reload this page and its status will update automatically.</p>
</div>
</div>
<div class="health-grid"><?php
foreach($runtimeRequirements as $requirement):
?>
<article class="health-card <?=$requirement['available']?'ok':'warn'?>">
<span><?=$requirement['available']?'✓':'!'?></span>
<div>
<strong><?=h($requirement['name'])?> · <?=$requirement['available']?'Available':'Missing'?></strong>
<p><?=h($requirement['purpose'])?></p>
<small><?=h($requirement['available']?'Detected in PHP '.PHP_VERSION.'.':$requirement['install'])?></small>
</div>
</article><?php
endforeach;
?>
</div>
</section>

<div class="page-heading health-section-heading">
<div>
<p class="eyebrow">WEBSITE CONTENT</p>
<h2>Configuration checks</h2>
</div>
</div>
<div class="health-grid"><?php
foreach($checks as $c):
?>
<article class="health-card <?=$c[1]?'ok':'warn'?>
">
<span><?=$c[1]?'✓':'!'?>
</span>
<div>
<strong><?=h($c[0])?>
</strong>
<p><?=h($c[2])?>
</p>
</div><?php
if($c[3]!=='#'):
?>
<a href="<?=h($c[3])?>
">Fix →</a><?php
endif;
?>
</article><?php
endforeach;
?>
</div><?php
require __DIR__.'/_footer.php';
