<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_permission('analytics.view');
ensure_visit_tables();
$pdo=db();
$tz=new DateTimeZone('America/Tegucigalpa');
$now=new DateTimeImmutable('now',$tz);
$today=$now->format('Y-m-d');
$yesterday=$now->modify('-1 day')->format('Y-m-d');
$weekStart=$now->modify('-6 days')->format('Y-m-d');

$q=$pdo->prepare('SELECT COUNT(*) FROM site_visits WHERE visit_date=?');$q->execute([$today]);$todayViews=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT COUNT(DISTINCT visitor_key) FROM site_visits WHERE visit_date=?');$q->execute([$today]);$todayUnique=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT COUNT(*) FROM site_visits WHERE visit_date=?');$q->execute([$yesterday]);$yesterdayViews=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT COUNT(*) FROM site_visits WHERE visit_date BETWEEN ? AND ?');$q->execute([$weekStart,$today]);$weekViews=(int)$q->fetchColumn();
$totalViews=(int)$pdo->query('SELECT COUNT(*) FROM site_visits')->fetchColumn();
$totalUnique=(int)$pdo->query('SELECT COUNT(DISTINCT visitor_key) FROM site_visits')->fetchColumn();
$daily=$pdo->query("SELECT visit_date,COUNT(*) visits,COUNT(DISTINCT visitor_key) unique_visitors,MIN(visited_at) first_visit,MAX(visited_at) last_visit FROM site_visits GROUP BY visit_date ORDER BY visit_date DESC LIMIT 30")->fetchAll();
$recent=$pdo->query("SELECT id,path,visited_at,visitor_key FROM site_visits ORDER BY id DESC LIMIT 20")->fetchAll();
$maxDaily=max(array_map(fn($r)=>(int)$r['visits'],$daily)?:[1]);
$pageTitle='Visitas';$active='analytics';require __DIR__.'/_header.php';
?>
<section class="dashboard-hero analytics-hero"><div><p class="eyebrow">ANALÍTICA</p><h1>Visitas del sitio</h1><p class="muted">Conteo del sitio publicado con fecha y hora de Honduras. Las visitas realizadas mientras un administrador está autenticado no se contabilizan.</p></div><div class="analytics-clock"><small>HORA DE HONDURAS</small><strong><?=h($now->format('d/m/Y · h:i A'))?></strong></div></section>
<div class="stat-grid premium analytics-stats">
 <article class="stat-card premium"><span class="stat-icon">◷</span><strong data-stat="<?=$todayViews?>"><?=$todayViews?></strong><small>Visitas hoy</small></article>
 <article class="stat-card premium"><span class="stat-icon">♙</span><strong data-stat="<?=$todayUnique?>"><?=$todayUnique?></strong><small>Visitantes únicos hoy</small></article>
 <article class="stat-card premium"><span class="stat-icon">7</span><strong data-stat="<?=$weekViews?>"><?=$weekViews?></strong><small>Últimos 7 días</small></article>
 <article class="stat-card premium"><span class="stat-icon">∞</span><strong data-stat="<?=$totalViews?>"><?=$totalViews?></strong><small>Visitas acumuladas</small></article>
</div>
<section class="panel"><div class="section-heading"><div><p class="eyebrow">ÚLTIMOS 30 DÍAS</p><h2>Actividad diaria</h2><p>Compara visitas y visitantes únicos por día.</p></div><div class="analytics-summary"><span>Ayer <b><?=$yesterdayViews?></b></span><span>Únicos acumulados <b><?=$totalUnique?></b></span></div></div>
<?php if($daily):?><div class="analytics-chart" aria-label="Gráfico de visitas diarias"><?php foreach(array_reverse($daily) as $row):$height=max(8,round(((int)$row['visits']/$maxDaily)*100));?><div class="analytics-bar-wrap" title="<?=h($row['visit_date'])?> · <?=h($row['visits'])?> visitas"><div class="analytics-bar" style="height:<?=$height?>%"></div><small><?=h(substr($row['visit_date'],5))?></small></div><?php endforeach;?></div><?php else:?><div class="empty-state">Todavía no hay visitas públicas registradas.</div><?php endif;?>
</section>
<section class="panel"><div class="section-heading"><div><p class="eyebrow">HISTÓRICO</p><h2>Resumen por fecha</h2></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha</th><th>Visitas</th><th>Visitantes únicos</th><th>Primera visita</th><th>Última visita</th></tr></thead><tbody><?php foreach($daily as $row):?><tr><td><strong><?=h((new DateTimeImmutable($row['visit_date']))->format('d/m/Y'))?></strong></td><td><?=h($row['visits'])?></td><td><?=h($row['unique_visitors'])?></td><td><?=h((new DateTimeImmutable($row['first_visit']))->format('h:i A'))?></td><td><?=h((new DateTimeImmutable($row['last_visit']))->format('h:i A'))?></td></tr><?php endforeach;?></tbody></table></div></section>
<section class="panel"><div class="section-heading"><div><p class="eyebrow">RECIENTES</p><h2>Últimas visitas</h2><p>Identificador anónimo de sesión; no se almacenan direcciones IP para este conteo.</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha y hora</th><th>Ruta</th><th>Visitante</th></tr></thead><tbody><?php foreach($recent as $row):?><tr><td><?=h((new DateTimeImmutable($row['visited_at']))->format('d/m/Y · h:i:s A'))?></td><td><?=h($row['path'])?></td><td><code><?=h(substr($row['visitor_key'],0,10))?>…</code></td></tr><?php endforeach;?></tbody></table></div></section>
<?php require __DIR__.'/_footer.php';?>
