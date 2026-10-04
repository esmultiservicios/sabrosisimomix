<?php
declare(strict_types=1);
if (!is_file(__DIR__ . '/config/install.lock') || !is_file(__DIR__ . '/config/config.php')) {
    header('Location: install/');
    exit;
}
require_once __DIR__ . '/core/public.php';
app_session_start();

if (setting('maintenance_mode', '0') === '1' && !isset($_GET['preview'])) {
    http_response_code(503);
    ?>
    <!doctype html>
    <html lang="es">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/x-icon" href="assets/favicon/favicon.ico?v=1">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon/favicon-32x32.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon/favicon-16x16.png?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-touch-icon.png?v=1">
    <link rel="manifest" href="assets/favicon/site.webmanifest?v=1">
    <title>Mantenimiento</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#17110f;color:#fff;font:16px system-ui;text-align:center}
        .box{max-width:600px;padding:30px}
        .box img{max-width:340px;width:80%}
    </style>
    <div class="box">
        <img src="assets/images/branding/logo-negro-slogan.jpg" alt="Sabrosísimo Mix">
        <h1>Volvemos en un momento</h1>
        <p>Estamos preparando algo sabroso para ti.</p>
    </div>
    </html>
    <?php
    exit;
}

$services = public_list('services');
$projects = public_list('projects');
$areas = public_list('service_areas');
$tips = public_list('tips');
$videos = public_list('videos');
function display_hn_phone(string $phone): string {
    $phone=trim($phone);
    if($phone==='') return '';
    $digits=preg_replace('/\D+/','',$phone)??'';
    if(strlen($digits)===8) return '+504 '.substr($digits,0,4).'-'.substr($digits,4);
    if(strlen($digits)===11 && str_starts_with($digits,'504')) return '+504 '.substr($digits,3,4).'-'.substr($digits,7);
    return $phone;
}
function public_display_image_path(string $path): string {
    $normalized = ltrim(str_replace('\\', '/', trim($path)), './');
    $basename = strtolower(basename($normalized));
    if (in_array($basename, ['logo-principal.jpg', 'sabrosisimo-logo-reference.png'], true)) {
        return 'assets/images/brand-reference-banner.jpg';
    }
    return $normalized;
}
$phone1 = display_hn_phone((string)setting('phone_primary', '+504 3273-5251'));
$phone2 = display_hn_phone((string)setting('phone_secondary', '+504 8809-9003'));
$wa = preg_replace('/\D+/', '', (string) setting('whatsapp', '50488099003'));
$allowedWidgetPositions = ['bottom-right','bottom-left','top-right','top-left'];
$waWidgetEnabled = setting('floating_whatsapp_enabled','1') === '1';
$waWidgetPosition = (string) setting('floating_whatsapp_position','bottom-right');
if (!in_array($waWidgetPosition,$allowedWidgetPositions,true)) $waWidgetPosition='bottom-right';
$waWidgetOrder = max(1,min(20,(int)setting('floating_whatsapp_order','1')));
$waWidgetOffset = ($waWidgetOrder - 1) * 70;
$externalWidgetsDecoded = json_decode((string)setting('floating_widgets_json',''), true);
$externalWidgets = is_array($externalWidgetsDecoded) ? array_values(array_filter($externalWidgetsDecoded, 'is_array')) : [];
if (!$externalWidgets && trim((string)setting('floating_widget_code','')) !== '') {
    $externalWidgets[] = [
        'enabled' => setting('floating_widget_enabled','0') === '1',
        'name' => (string)setting('floating_widget_label','Chat'),
        'install_type' => 'code',
        'code' => (string)setting('floating_widget_code',''),
        'url' => '',
        'position' => (string)setting('floating_widget_position','bottom-left'),
        'order' => (int)setting('floating_widget_order','2'),
        'desktop' => true,
        'mobile' => true,
    ];
}
foreach ($externalWidgets as &$widget) {
    $position = (string)($widget['position'] ?? 'bottom-left');
    if (!in_array($position, $allowedWidgetPositions, true)) $position = 'bottom-left';
    $sameSide = (str_ends_with($position,'left') && str_ends_with($waWidgetPosition,'left')) || (str_ends_with($position,'right') && str_ends_with($waWidgetPosition,'right'));
    if ($sameSide) $position = str_ends_with($position,'left') ? str_replace('left','right',$position) : str_replace('right','left',$position);
    $widget['position'] = $position;
    $widget['order'] = max(1,min(99,(int)($widget['order'] ?? 20)));
}
unset($widget);
$seoTitle = trim((string)setting('seo_title','')) ?: setting('site_name','Sabrosísimo Mix').' · '.setting('site_tagline','Sabor y Servicio es nuestra pasión');
$seoDescription = trim((string)setting('seo_description','')) ?: 'Sabrosísimo Mix: taqueadas, pupusas, pastelitos, saltarines y servicios para eventos en San Pedro Sula.';
$seoIndexing = (string)setting('seo_indexing','index-follow');
$seoRobots = $seoIndexing === 'index-follow' ? 'index,follow' : ($seoIndexing === 'noindex-follow' ? 'noindex,follow' : 'noindex,nofollow');
$seoVerification = trim((string)setting('google_site_verification',''));
$seoSocialImage = trim((string)setting('seo_social_image',''));
$turnstileEnabled = setting('turnstile_enabled','0') === '1' && trim((string)setting('turnstile_site_key','')) !== '' && trim((string)setting('turnstile_secret_key','')) !== '';
$turnstileSiteKey = trim((string)setting('turnstile_site_key',''));
$socialDefaults = [
    ['enabled'=>1,'platform'=>'instagram','url'=>'https://www.instagram.com/sabrosisimomix/','order'=>1],
    ['enabled'=>1,'platform'=>'facebook','url'=>'https://web.facebook.com/people/Sabros%C3%ADsimo-mix/61592916879862/','order'=>2],
    ['enabled'=>1,'platform'=>'tiktok','url'=>'https://www.tiktok.com/@sabrosisimomix','order'=>3],
    ['enabled'=>0,'platform'=>'youtube','url'=>'','order'=>4],
    ['enabled'=>0,'platform'=>'linkedin','url'=>'','order'=>5],
];
$socialDecoded=json_decode((string)setting('social_networks_json',''),true);
$socialNetworks=is_array($socialDecoded)?$socialDecoded:$socialDefaults;
$socialNetworks=array_values(array_filter($socialNetworks,fn($item)=>is_array($item)&&!empty($item['enabled'])&&!empty($item['url'])));
usort($socialNetworks,fn($a,$b)=>(int)($a['order']??99)<=>(int)($b['order']??99));
$socialLocation=(string)setting('social_display_location','footer-floating-right');
$socialSize=(string)setting('social_icon_size','medium');
$socialStyle=(string)setting('social_display_style','icons');
$socialShowDesktop=setting('social_show_desktop','1')==='1';
$socialShowMobile=setting('social_show_mobile','1')==='1';
function social_label(string $platform): string {return ['instagram'=>'Instagram','facebook'=>'Facebook','tiktok'=>'TikTok','youtube'=>'YouTube','linkedin'=>'LinkedIn'][$platform]??ucfirst($platform);}
function social_icon_svg(string $platform): string {
    return match($platform){
        'instagram'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2Zm-.2 2A3.6 3.6 0 0 0 4 7.6v8.8A3.6 3.6 0 0 0 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6A3.6 3.6 0 0 0 16.4 4H7.6Zm9.65 1.55a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>',
        'facebook'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M13.6 22v-8h2.7l.4-3.1h-3.1V8.9c0-.9.3-1.5 1.6-1.5h1.7V4.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.4V14h2.8v8h3.4Z"/></svg>',
        'tiktok'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M15.4 3c.4 2.2 1.7 3.5 3.9 3.7v3.1a7.4 7.4 0 0 1-3.9-1.1v6.1a6.1 6.1 0 1 1-5.3-6V12a2.9 2.9 0 1 0 2.1 2.8V3h3.2Z"/></svg>',
        'youtube'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21.6 7.2a2.8 2.8 0 0 0-2-2C17.8 4.7 12 4.7 12 4.7s-5.8 0-7.6.5a2.8 2.8 0 0 0-2 2A29 29 0 0 0 2 12a29 29 0 0 0 .4 4.8 2.8 2.8 0 0 0 2 2c1.8.5 7.6.5 7.6.5s5.8 0 7.6-.5a2.8 2.8 0 0 0 2-2A29 29 0 0 0 22 12a29 29 0 0 0-.4-4.8ZM10 15.3V8.7l5.7 3.3-5.7 3.3Z"/></svg>',
        'linkedin'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.6 8.3H3.3V19h3.3V8.3ZM5 3a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm3.6 5.3V19h3.3v-5.3c0-1.4.3-2.8 2.1-2.8 1.8 0 1.8 1.7 1.8 2.9V19h3.3v-5.9c0-2.9-.6-5.1-4-5.1-1.6 0-2.7.9-3.2 1.7h-.1V8.3H8.6Z"/></svg>',
        default=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="currentColor"/></svg>'
    };
}
function render_social_links(array $items,string $style='icons'): string {
    $html='';
    foreach($items as $item){$platform=(string)($item['platform']??'');$url=(string)($item['url']??'');if($url==='')continue;$label=social_label($platform);$html.='<a class="social-link social-'.$platform.'" href="'.h($url).'" target="_blank" rel="noopener noreferrer" aria-label="'.h($label).'">'.social_icon_svg($platform).($style==='labels'?'<span>'.h($label).'</span>':'').'</a>';}
    return $html;
}
$quoteError = $_SESSION['quote_error'] ?? '';
$quoteOld = $_SESSION['quote_old'] ?? [];
unset($_SESSION['quote_error'], $_SESSION['quote_old']);
record_public_visit((string)($_SERVER['REQUEST_URI'] ?? '/'));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/x-icon" href="assets/favicon/favicon.ico?v=1">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon/favicon-32x32.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon/favicon-16x16.png?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-touch-icon.png?v=1">
    <link rel="manifest" href="assets/favicon/site.webmanifest?v=1">
    <meta name="theme-color" content="#18110f">
    <meta name="description" content="<?= h($seoDescription) ?>">
    <meta name="robots" content="<?= h($seoRobots) ?>">
    <?php if($seoVerification!==''): ?><meta name="google-site-verification" content="<?= h($seoVerification) ?>"><?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= h($seoTitle) ?>">
    <meta property="og:description" content="<?= h($seoDescription) ?>">
    <meta property="og:url" content="<?= h(rtrim(base_url(),'/')) ?>">
    <?php if($seoSocialImage!=='' && is_file(ROOT_DIR.'/'.$seoSocialImage)): ?><meta property="og:image" content="<?= h(base_url($seoSocialImage)) ?>"><?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <title><?= h($seoTitle) ?></title>
    <link rel="stylesheet" href="assets/vendor/ui-feedback.css?v=<?= @filemtime(__DIR__ . '/assets/vendor/ui-feedback.css') ?>"><link rel="stylesheet" href="assets/vendor/select2-local.css?v=<?= @filemtime(__DIR__ . '/assets/vendor/select2-local.css') ?>"><link rel="stylesheet" href="assets/vendor/richtext-local.css?v=<?= @filemtime(__DIR__ . '/assets/vendor/richtext-local.css') ?>"><link rel="stylesheet" href="assets/css/site.css?v=<?= @filemtime(__DIR__ . '/assets/css/site.css') ?>">
</head>
<body>
<header class="site-header">
    <a class="site-logo" href="#inicio"><img src="assets/images/branding/logo-negro-slogan.jpg" alt="Sabrosísimo Mix"></a>
    <button class="nav-toggle" aria-label="Abrir menú" aria-expanded="false">☰</button>
    <nav>
        <a class="nav-section-link" href="#servicios">Servicios</a>
        <a class="nav-section-link" href="#nosotros">Nosotros</a>
        <?php if ($projects): ?><a class="nav-section-link" href="#galeria">Galería</a><?php endif; ?>
        <a class="nav-section-link" href="#cotizar">Cotizar</a>
    </nav>
    <a class="header-cta" href="https://wa.me/<?= $wa ?>" target="_blank" rel="noopener" aria-label="Contactar por WhatsApp"><svg viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M16 3.2A12.5 12.5 0 0 0 5.1 21.8L3.4 28.5l6.9-1.8A12.5 12.5 0 1 0 16 3.2Zm0 22.7c-2 0-4-.6-5.6-1.6l-.4-.2-4 .9 1-3.8-.3-.4A10.2 10.2 0 1 1 16 25.9Zm5.6-7.7c-.3-.2-1.8-.9-2.1-1-.3-.1-.5-.2-.7.2-.2.3-.8 1-1 1.2-.2.2-.4.2-.7.1-1.9-.9-3.2-1.7-4.5-3.8-.3-.6.3-.6.9-1.8.1-.2 0-.5-.1-.7-.1-.2-.7-1.7-1-2.4-.3-.7-.6-.6-.8-.6h-.7c-.2 0-.6.1-.9.5-.3.3-1.2 1.2-1.2 2.9 0 1.7 1.3 3.4 1.5 3.6.2.2 2.5 3.9 6.1 5.4 2.3 1 3.2 1.1 4.3.9.7-.1 1.8-.7 2.1-1.5.3-.7.3-1.4.2-1.5-.1-.2-.4-.3-.7-.4Z"/></svg><span>WhatsApp</span></a>
</header>

<main id="inicio">
    <section class="hero">
        <div class="hero-noise"></div>
        <div class="hero-visual" data-reveal>
            <button
                class="brand-frame hero-image-button"
                type="button"
                data-site-lightbox
                data-lightbox-src="assets/images/branding/banner-contacto-horizontal.jpg"
                data-lightbox-title="Sabrosísimo Mix"
                data-lightbox-caption="Sabor, servicio y experiencias para tus celebraciones."
                aria-label="Ampliar imagen principal de Sabrosísimo Mix"
            >
                <img src="assets/images/branding/banner-contacto-horizontal.jpg" alt="Presentación de Sabrosísimo Mix con sus servicios y contactos">
                <span class="hero-image-zoom" aria-hidden="true">⌕</span>
            </button>
            <div class="hero-visual-meta">
                <div class="hero-mini-card hero-mini-card--service">
                    <span class="hero-mini-icon" aria-hidden="true">✦</span>
                    <div>
                        <small>TODO PARA TU EVENTO</small>
                        <strong>Comida + diversión + atención</strong>
                    </div>
                </div>
                <div class="floating-card">
                    <small>ENCUÉNTRANOS EN</small>
                    <strong><?= h(setting('location', 'San Pedro Sula, Honduras')) ?></strong>
                </div>
            </div>
        </div>
        <div class="hero-copy" data-reveal>
            <span class="eyebrow"><?= h(content_block('hero_eyebrow', 'EVENTOS · SABOR · EXPERIENCIAS')) ?></span>
            <h1><?= h(content_block('hero_title', 'Haz de tu evento una experiencia deliciosa e inolvidable')) ?></h1>
            <div class="rich-content"><?= rich_text_render(content_block('hero_text', 'Ofrecemos taqueadas, pupusas, pastelitos, nieves, palomitas, algodones y saltarines para reuniones familiares, cumpleaños, ferias y eventos empresariales.')) ?></div>
            <div class="hero-actions">
                <a class="btn primary" href="#cotizar">Solicitar cotización</a>
                <a class="btn ghost" href="https://wa.me/<?= $wa ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a>
            </div>
            <div class="trust-row">
                <span>✓ Ingredientes de calidad</span>
                <span>✓ Preparación al momento</span>
                <span>✓ Atención personalizada</span>
                <span>✓ Servicio para eventos</span>
            </div>
        </div>
    </section>

    <?php if($socialNetworks && $socialLocation==='after-hero'):?><div class="social-hero-strip social-size-<?=h($socialSize)?> <?=!$socialShowDesktop?'hide-social-desktop':''?> <?=!$socialShowMobile?'hide-social-mobile':''?>"><span>Síguenos</span><div class="social-links"><?=render_social_links($socialNetworks,$socialStyle)?></div></div><?php endif;?>
<section class="contact-strip">
        <div>
            <small>CONTACTOS</small>
            <strong><?= h($phone1) ?> · <?= h($phone2) ?></strong>
        </div>
        <div class="separator"></div>
        <div>
            <small>SERVICIOS</small>
            <strong>Taqueadas · Pupusas · Pastelitos · Saltarines</strong>
        </div>
        <a href="https://wa.me/<?= $wa ?>" target="_blank" rel="noopener">Cotízanos vía WhatsApp →</a>
    </section>

    <section class="experience-strip" aria-label="Experiencia Sabrosísimo Mix">
        <article class="experience-card" data-reveal>
            <span class="experience-number">01</span>
            <div>
                <small>COMIDA AL MOMENTO</small>
                <strong>Sabores que hacen que tus invitados quieran repetir</strong>
                <p>Taqueadas, pupusas y pastelitos preparados para acompañar celebraciones de distintos tamaños.</p>
            </div>
        </article>
        <article class="experience-card" data-reveal>
            <span class="experience-number">02</span>
            <div>
                <small>ANTOJOS Y SNACKS</small>
                <strong>Detalles que mantienen el ambiente activo</strong>
                <p>Nieves, palomitas y opciones dulces para sumar variedad y crear una experiencia más completa.</p>
            </div>
        </article>
        <article class="experience-card" data-reveal>
            <span class="experience-number">03</span>
            <div>
                <small>DIVERSIÓN PARA EL EVENTO</small>
                <strong>Más que comida: una celebración que se siente</strong>
                <p>Saltarines y servicios complementarios para que grandes y pequeños disfruten cada momento.</p>
            </div>
        </article>
    </section>

    <section class="event-story section" aria-labelledby="eventStoryTitle">
        <div class="event-story-copy" data-reveal>
            <span class="eyebrow">TU EVENTO, MÁS COMPLETO</span>
            <h2 id="eventStoryTitle">Nos ocupamos de los detalles para que tú disfrutes la celebración</h2>
            <p>Sabrosísimo Mix combina comida preparada al momento, atención cercana y opciones de entretenimiento para ayudarte a resolver más de tu evento con un solo equipo.</p>
            <div class="event-story-points">
                <div><strong>01</strong><span>Cuéntanos fecha, ubicación y cantidad de invitados.</span></div>
                <div><strong>02</strong><span>Selecciona los servicios que mejor encajan con tu celebración.</span></div>
                <div><strong>03</strong><span>Recibe atención personalizada para coordinar cada detalle.</span></div>
            </div>
            <a class="btn primary" href="#cotizar">Quiero preparar mi evento →</a>
        </div>
        <div class="event-story-gallery" data-reveal>
            <button type="button" class="story-photo story-photo--main" data-site-lightbox data-lightbox-src="assets/images/gallery/servicio-en-evento-nocturno.jpg" data-lightbox-title="Servicio para eventos" data-lightbox-caption="Atención y preparación para acompañar tu celebración." aria-label="Ampliar fotografía de servicio en evento">
                <img src="assets/images/gallery/servicio-en-evento-nocturno.jpg" alt="Servicio de Sabrosísimo Mix durante un evento">
                <span>Servicio en tu evento</span>
            </button>
            <button type="button" class="story-photo" data-site-lightbox data-lightbox-src="assets/images/gallery/nieves-y-palomitas.jpg" data-lightbox-title="Snacks y antojos" data-lightbox-caption="Opciones para complementar la experiencia de tus invitados." aria-label="Ampliar fotografía de snacks">
                <img src="assets/images/gallery/nieves-y-palomitas.jpg" alt="Nieves y palomitas para eventos">
                <span>Snacks y antojos</span>
            </button>
            <button type="button" class="story-photo" data-site-lightbox data-lightbox-src="assets/images/gallery/saltarin-modelo-1.jpg" data-lightbox-title="Diversión para celebrar" data-lightbox-caption="Saltarines como complemento para celebraciones familiares." aria-label="Ampliar fotografía de saltarín">
                <img src="assets/images/gallery/saltarin-modelo-1.jpg" alt="Saltarín para celebraciones">
                <span>Diversión incluida</span>
            </button>
        </div>
    </section>

    <section class="section services" id="servicios">
        <div class="section-head">
            <div>
                <span class="eyebrow">LO QUE HACEMOS</span>
                <h2>Soluciones sabrosas para tu evento</h2>
            </div>
            <p>Atendemos desde celebraciones en casa hasta reuniones familiares y eventos corporativos, con menús flexibles y servicios complementarios.</p>
        </div>
        <div class="service-grid">
            <?php foreach ($services as $i => $s): ?>
                <article class="service-card">
                    <span class="service-index"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <div class="service-icon"><?= h($s['icon'] ?: '✦') ?></div>
                    <h3><?= h($s['title']) ?></h3>
                    <div class="rich-content"><?= rich_text_render((string)$s['description']) ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section about" id="nosotros">
        <div class="about-media">
            <button class="about-image-button" type="button" data-site-lightbox data-lightbox-src="assets/images/gallery/evento-con-saltarin-y-taqueadas.jpg" data-lightbox-title="Sabrosísimo Mix en acción" data-lightbox-caption="Servicio en evento con preparación al momento y saltarín." aria-label="Ampliar fotografía"><img src="assets/images/gallery/evento-con-saltarin-y-taqueadas.jpg" alt="Sabrosísimo Mix atendiendo un evento con comida y saltarín"><span class="zoom-hint" aria-hidden="true">⌕</span></button>
            <div class="quality-badge"><b>4</b><span>líneas de<br>servicio base</span></div>
        </div>
        <div class="about-copy">
            <span class="eyebrow">SABROSÍSIMO MIX</span>
            <h2><?= h(content_block('about_title', 'Todo lo que necesitas para compartir y celebrar')) ?></h2>
            <div class="rich-content"><?= rich_text_render(content_block('about_text', 'Nos encargamos de la comida y del ambiente para que tus invitados disfruten. Trabajamos con atención cercana, preparación al momento y opciones ideales para distintos tipos de evento.')) ?></div>
            <ul>
                <li><b>01</b> Taqueadas, pupusas y pastelitos</li>
                <li><b>02</b> Nieves, palomitas y algodones</li>
                <li><b>03</b> Saltarines para fiestas</li>
                <li><b>04</b> Servicio a domicilio y eventos</li>
            </ul>
        </div>
    </section>

    <?php if ($projects): ?>
        <section class="section portfolio" id="galeria">
            <div class="section-head">
                <div>
                    <span class="eyebrow">GALERÍA</span>
                    <h2>Montajes, promociones y experiencias</h2>
                </div>
                <p>Se cargó al proyecto el material visual compartido por el cliente: fotografías reales, imágenes de referencia y artes promocionales.</p>
            </div>
            <div class="project-grid">
                <?php foreach ($projects as $p): ?>
                    <article class="project-card">
                        <?php if ($p['image_path']): $displayImage = public_display_image_path((string)$p['image_path']); ?>
                            <button class="project-image-button" type="button" data-site-lightbox data-lightbox-src="<?= h($displayImage) ?>" data-lightbox-title="<?= h($p['title']) ?>" data-lightbox-caption="<?= h(rich_text_plain((string)$p['description'])) ?>" aria-label="Ampliar imagen: <?= h($p['title']) ?>"><img src="<?= h($displayImage) ?>" alt="<?= h($p['title']) ?>"><span class="zoom-hint" aria-hidden="true">⌕</span></button>
                        <?php else: ?>
                            <div class="project-placeholder">SM</div>
                        <?php endif; ?>
                        <div>
                            <small><?= h($p['category']) ?></small>
                            <h3><?= h($p['title']) ?></h3>
                            <div class="rich-content"><?= rich_text_render((string)$p['description']) ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($areas || $tips): ?>
        <section class="section detail-grid">
            <?php if ($areas): ?>
                <div>
                    <span class="eyebrow">COBERTURA</span>
                    <h2>Áreas de servicio</h2>
                    <div class="detail-list">
                        <?php foreach ($areas as $a): ?>
                            <article>
                                <strong><?= h($a['name']) ?></strong>
                                <div class="rich-content"><?= rich_text_render((string)$a['description']) ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($tips): ?>
                <div>
                    <span class="eyebrow">PARA TU EVENTO</span>
                    <h2>Tips útiles</h2>
                    <div class="detail-list">
                        <?php foreach ($tips as $t): ?>
                            <article>
                                <strong><?= h($t['title']) ?></strong>
                                <div class="rich-content"><?= rich_text_render((string)$t['body']) ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="quote-section" id="cotizar">
        <div class="quote-intro">
            <span class="eyebrow">COTIZA TU EVENTO</span>
            <h2>Cuéntanos qué estás preparando.</h2>
            <p>Envíanos fecha, zona, cantidad aproximada de personas y los servicios que necesitas. El equipo podrá darle seguimiento desde el panel administrativo.</p>
            <div class="direct-contact">
                <a href="tel:<?= h(preg_replace('/\D+/', '', $phone1)) ?>"><?= h($phone1) ?></a>
                <a href="tel:<?= h(preg_replace('/\D+/', '', $phone2)) ?>"><?= h($phone2) ?></a>
            </div>
        </div>
        <form class="quote-form" method="post" action="quote.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="form_started_at" value="<?= time() ?>">
            <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
            <?php if (isset($_GET['sent'])): ?><div hidden data-public-notify data-type="success" data-message="¡Gracias! Recibimos tu solicitud y podremos darle seguimiento."></div><?php endif; ?><?php if ($quoteError): ?><div hidden data-public-notify data-type="error" data-message="<?= h($quoteError) ?>"></div><?php endif; ?>

            <div class="field-row">
                <label>Nombre completo<input name="full_name" value="<?= h($quoteOld['full_name'] ?? '') ?>" required></label>
                <label>Teléfono<input name="phone" value="<?= h($quoteOld['phone'] ?? '') ?>" required></label>
            </div>
            <div class="field-row">
                <div class="quote-field email-validation-field" data-email-validation>
                    <label for="quote-email">Correo</label>
                    <div class="email-input-wrap">
                        <input id="quote-email" type="email" name="email" value="<?= h($quoteOld['email'] ?? '') ?>" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required aria-describedby="quote-email-status">
                        <span class="email-validation-indicator" data-email-indicator aria-hidden="true"></span>
                    </div>
                    <div class="email-validation-feedback" id="quote-email-status" data-email-status aria-live="polite"></div>
                    <button class="email-suggestion" type="button" data-email-suggestion hidden></button>
                </div>
                <label>Servicio
                    <select name="service_needed">
                        <option value="">Selecciona una opción</option>
                        <?php foreach ($services as $s): ?>
                            <option <?= ($quoteOld['service_needed'] ?? '') === $s['title'] ? 'selected' : '' ?>><?= h($s['title']) ?></option>
                        <?php endforeach; ?>
                        <option <?= ($quoteOld['service_needed'] ?? '') === 'Evento / paquete personalizado' ? 'selected' : '' ?>>Evento / paquete personalizado</option>
                    </select>
                </label>
            </div>
            <label>Dirección o zona del evento<input name="address" value="<?= h($quoteOld['address'] ?? '') ?>"></label>
            <label>Cuéntanos sobre el evento<textarea name="message" placeholder="Fecha, cantidad aproximada de personas, horario, servicios que te interesan…"><?= h($quoteOld['message'] ?? '') ?></textarea></label>
            <div class="upload-field">
                <div class="upload-heading"><strong>Adjuntos opcionales</strong><small>JPG, PNG o WEBP · máximo 3 archivos de 3 MB cada uno</small></div>
                <div class="public-dropzone" data-public-dropzone tabindex="0" role="button" aria-label="Adjuntar archivos">
                    <input class="public-file-input" type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp" data-public-file-input>
                    <div class="dropzone-icon" aria-hidden="true">⇧</div>
                    <div class="dropzone-copy"><strong>Arrastra y suelta tus imágenes aquí</strong><span>También puedes pegar con Ctrl + V o seleccionar desde tu equipo.</span></div>
                    <button class="btn file-select-btn" type="button" data-public-file-select>Seleccionar archivos</button>
                </div>
                <div class="public-file-list" data-public-file-list aria-live="polite"></div>
            </div>
            <?php if($turnstileEnabled): ?><div class="turnstile-wrap"><div class="cf-turnstile" data-sitekey="<?=h($turnstileSiteKey)?>" data-theme="light" data-language="es"></div><small>Protegido por Cloudflare Turnstile.</small></div><?php endif; ?>
            <button class="btn primary" type="submit">Enviar solicitud</button>
        </form>
    </section>
</main>

<footer>
    <div>
        <img src="assets/images/branding/logo-negro-slogan.jpg" alt="Sabrosísimo Mix">
        <p><?= h(setting('site_tagline', 'Sabor y Servicio es nuestra pasión')) ?></p>
    </div>
    <div>
        <strong>Contacto</strong>
        <a href="tel:<?= h(preg_replace('/\D+/', '', $phone1)) ?>"><?= h($phone1) ?></a>
        <a href="tel:<?= h(preg_replace('/\D+/', '', $phone2)) ?>"><?= h($phone2) ?></a>
        <span><?= h(setting('location', 'San Pedro Sula, Honduras')) ?></span>
    </div>
    <?php if($socialNetworks && in_array($socialLocation,['footer','footer-floating-left','footer-floating-right'],true)):?><div class="footer-socials <?=!$socialShowDesktop?'hide-social-desktop':''?> <?=!$socialShowMobile?'hide-social-mobile':''?>"><strong>Síguenos</strong><div class="social-links social-links--footer social-size-<?=h($socialSize)?>"><?=render_social_links($socialNetworks,$socialStyle)?></div></div><?php else:?><div class="footer-socials"><strong>Síguenos</strong><span>Conecta con nosotros</span></div><?php endif;?><small class="copyright">© <?= date('Y') ?> Sabrosísimo Mix. Todos los derechos reservados.</small>
</footer>

<div class="floating-widget-layer" aria-label="Accesos flotantes">
<?php if($socialNetworks && in_array($socialLocation,['floating-left','floating-right','footer-floating-left','footer-floating-right'],true)): $socialSide=str_contains($socialLocation,'left')?'left':'right';?><div class="social-floating social-floating--<?=h($socialSide)?> social-size-<?=h($socialSize)?> <?=!$socialShowDesktop?'hide-social-desktop':''?> <?=!$socialShowMobile?'hide-social-mobile':''?>"><div class="social-links"><?=render_social_links($socialNetworks,$socialStyle)?></div></div><?php endif;?>
<?php if($waWidgetEnabled && $wa !== ''): ?>
    <a class="wa-float floating-slot pos-<?=h($waWidgetPosition)?>" style="--float-offset:<?=$waWidgetOffset?>px" href="https://wa.me/<?= $wa ?>" target="_blank" rel="noopener" aria-label="Abrir WhatsApp">
        <svg viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M16 3.2A12.5 12.5 0 0 0 5.1 21.8L3.4 28.5l6.9-1.8A12.5 12.5 0 1 0 16 3.2Zm0 22.7c-2 0-4-.6-5.6-1.6l-.4-.2-4 .9 1-3.8-.3-.4A10.2 10.2 0 1 1 16 25.9Zm5.6-7.7c-.3-.2-1.8-.9-2.1-1-.3-.1-.5-.2-.7.2-.2.3-.8 1-1 1.2-.2.2-.4.2-.7.1-1.9-.9-3.2-1.7-4.5-3.8-.3-.6.3-.6.9-1.8.1-.2 0-.5-.1-.7-.1-.2-.7-1.7-1-2.4-.3-.7-.6-.6-.8-.6h-.7c-.2 0-.6.1-.9.5-.3.3-1.2 1.2-1.2 2.9 0 1.7 1.3 3.4 1.5 3.6.2.2 2.5 3.9 6.1 5.4 2.3 1 3.2 1.1 4.3.9.7-.1 1.8-.7 2.1-1.5.3-.7.3-1.4.2-1.5-.1-.2-.4-.3-.7-.4Z"/></svg>
    </a>
<?php endif; ?>
<?php foreach($externalWidgets as $widget):
    if(empty($widget['enabled'])) continue;
    $widgetPosition=(string)($widget['position']??'bottom-left');
    $widgetOffset=((int)($widget['order']??20)-1)*70;
    $showDesktop=!array_key_exists('desktop',$widget)||!empty($widget['desktop']);
    $showMobile=!array_key_exists('mobile',$widget)||!empty($widget['mobile']);
    $visibilityClass=(!$showDesktop?' widget-hide-desktop':'').(!$showMobile?' widget-hide-mobile':'');
    $installType=(string)($widget['install_type']??'code');
?>
    <div class="external-widget-slot floating-slot pos-<?=h($widgetPosition)?><?=h($visibilityClass)?>" style="--float-offset:<?=$widgetOffset?>px" aria-label="<?=h((string)($widget['name']??'Widget'))?>">
      <?php if($installType==='url' && filter_var((string)($widget['url']??''),FILTER_VALIDATE_URL)): ?>
        <iframe class="floating-widget-frame" src="<?=h((string)$widget['url'])?>" title="<?=h((string)($widget['name']??'Widget'))?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
      <?php elseif(trim((string)($widget['code']??''))!==''): ?>
        <?= (string)$widget['code'] ?>
      <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<?php if($turnstileEnabled): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
<script src="assets/vendor/ui-feedback.js?v=<?= @filemtime(__DIR__ . '/assets/vendor/ui-feedback.js') ?>"></script>
<div class="site-lightbox" data-site-lightbox-modal hidden aria-hidden="true">
  <div class="site-lightbox-backdrop" data-site-lightbox-close></div>
  <section class="site-lightbox-dialog" role="dialog" aria-modal="true" aria-labelledby="siteLightboxTitle">
    <button class="site-lightbox-close" type="button" data-site-lightbox-close aria-label="Cerrar visor"><span aria-hidden="true">×</span><strong>Cerrar</strong></button>
    <div class="site-lightbox-stage"><img data-site-lightbox-image alt=""></div>
    <div class="site-lightbox-copy"><h2 id="siteLightboxTitle" data-site-lightbox-title></h2><p data-site-lightbox-caption></p></div>
  </section>
</div>
<script src="assets/vendor/select2-local.js?v=<?= @filemtime(__DIR__ . '/assets/vendor/select2-local.js') ?>"></script>
<script src="assets/vendor/richtext-local.js?v=<?= @filemtime(__DIR__ . '/assets/vendor/richtext-local.js') ?>"></script>
<script src="assets/js/site.js?v=<?= @filemtime(__DIR__ . '/assets/js/site.js') ?>"></script>
</body>
</html>
