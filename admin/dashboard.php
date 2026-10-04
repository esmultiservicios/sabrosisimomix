<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$pdo=db();
ensure_visit_tables();
$todayH=honduras_now()->format('Y-m-d');
$visitTodayStmt=$pdo->prepare('SELECT COUNT(*) FROM site_visits WHERE visit_date=?');$visitTodayStmt->execute([$todayH]);$visitsToday=(int)$visitTodayStmt->fetchColumn();
$counts=[
    ['label'=>'Solicitudes','value'=>(int)$pdo->query('SELECT COUNT(*) FROM estimate_requests')->fetchColumn(),'icon'=>'✉'],
    ['label'=>'Nuevas','value'=>(int)$pdo->query("SELECT COUNT(*) FROM estimate_requests WHERE status='new'")->fetchColumn(),'icon'=>'●'],
    ['label'=>'Servicios','value'=>(int)$pdo->query('SELECT COUNT(*) FROM services WHERE active=1')->fetchColumn(),'icon'=>'✦'],
    ['label'=>'Proyectos','value'=>(int)$pdo->query('SELECT COUNT(*) FROM projects WHERE active=1')->fetchColumn(),'icon'=>'▤'],
    ['label'=>'Visitas hoy','value'=>$visitsToday,'icon'=>'◷'],
];
$latest=$pdo->query('SELECT * FROM estimate_requests ORDER BY id DESC LIMIT 6')->fetchAll();
$pageTitle='Dashboard';$active='dashboard';require __DIR__.'/_header.php';
?>
<section class="dashboard-hero">
  <div><p class="eyebrow">CONTROL CENTER</p><h1>Dashboard</h1><p class="muted">Vista ejecutiva del sitio, contenido, solicitudes y accesos principales.</p></div>
  <div class="dashboard-actions"><a class="button secondary" href="../" target="_blank" rel="noopener">↗ Ver sitio</a><a class="button" href="content.php">✎ Editar portada</a></div>
</section>

<div class="stat-grid premium">
<?php foreach($counts as $item):?>
  <article class="stat-card premium">
    <span class="stat-icon"><?=h($item['icon'])?></span>
    <strong data-stat="<?=$item['value']?>"><?=$item['value']?></strong>
    <small><?=h($item['label'])?></small>
  </article>
<?php endforeach;?>
</div>

<section class="panel">
  <div class="section-heading"><div><p class="eyebrow">ACCESOS RÁPIDOS</p><h2>Gestiona lo importante</h2><p>Acciones frecuentes sin perder tiempo buscando módulos.</p></div></div>
  <div class="quick-action-grid">
    <a class="quick-action-card" href="analytics.php"><span class="qa-icon">◷</span><span><strong>Visitas</strong><small>Hoy, históricos y visitantes únicos</small></span></a>
    <a class="quick-action-card" href="estimates.php"><span class="qa-icon">✉</span><span><strong>Solicitudes</strong><small>Revisar cotizaciones recibidas</small></span></a>
    <a class="quick-action-card" href="media.php"><span class="qa-icon">▧</span><span><strong>Media Library</strong><small>Administrar imágenes y archivos</small></span></a>
    <a class="quick-action-card" href="widgets.php"><span class="qa-icon">◉</span><span><strong>Widgets flotantes</strong><small>WhatsApp, chat y posiciones</small></span></a>
    <a class="quick-action-card" href="seo.php"><span class="qa-icon">⌕</span><span><strong>SEO Manager</strong><small>Google, sitemap y anti-spam</small></span></a>
    <a class="quick-action-card" href="social.php"><span class="qa-icon">◎</span><span><strong>Redes sociales</strong><small>Canales, orden y ubicación</small></span></a>
    <a class="quick-action-card" href="email.php"><span class="qa-icon">@</span><span><strong>Correo</strong><small>SMTP, Graph y prueba</small></span></a>
  </div>
</section>

<section class="panel">
  <div class="section-heading"><div><p class="eyebrow">RECIENTES</p><h2>Solicitudes de cotización</h2></div><a class="button secondary small" href="estimates.php">Ver todas</a></div>
  <?php if($latest):?>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Cliente</th><th>Servicio</th><th>Contacto</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>
    <?php foreach($latest as $r):?><tr><td><?=h($r['full_name']?:'Visitante')?></td><td><?=h($r['service_needed'])?></td><td><?=h($r['phone']?:$r['email'])?></td><td><span class="badge <?=h($r['status'])?>"><?=h($r['status'])?></span></td><td><?=h($r['created_at'])?></td></tr><?php endforeach;?>
    </tbody></table></div>
  <?php else:?><div class="empty-state">Todavía no hay solicitudes.</div><?php endif;?>
</section>
<?php require __DIR__.'/_footer.php';?>
