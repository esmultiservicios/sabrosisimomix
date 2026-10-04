<?php
declare(strict_types=1);
session_start();
require __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/core/PublicFormProtection.php';
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
if(!config_ready()) {
    header('Location: install/');
    exit;
}
try {
    $content=site_content();
    $settings=settings();
    $services=db()->query('SELECT * FROM services WHERE active=1 ORDER BY sort_order,id')->fetchAll();
    $gallery=db()->query('SELECT * FROM gallery WHERE active=1 ORDER BY sort_order,id')->fetchAll();
    $videos=[];
    try { $videos=db()->query('SELECT * FROM videos WHERE active=1 ORDER BY sort_order,id')->fetchAll(); } catch(Throwable $ignored) { $videos=[]; }
    $aboutArtworks=[];
    try { $aboutArtworks=db()->query('SELECT * FROM about_artworks WHERE active=1 ORDER BY sort_order,id')->fetchAll(); } catch(Throwable $ignored) { $aboutArtworks=[]; }
    $areas=db()->query('SELECT * FROM service_areas WHERE active=1 ORDER BY sort_order,id')->fetchAll();
    $tips=db()->query('SELECT * FROM tips WHERE active=1 ORDER BY sort_order,id LIMIT 5')->fetchAll();
} catch(Throwable $e) {
    http_response_code(500);
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<title>Castro's Ready Setup</title>
<style>
body {
  font-family:Arial;
  padding:40px;
  max-width:760px;
  margin:auto;
  color:#183d39
}
code {
  background:#eee;
  padding:2px 6px
}
</style>
</head>
<body>
<h1>Castro's Ready needs database configuration</h1>
<p>Import <code>database.sql</code>, copy <code>config/database.example.php</code> to <code>config/database.php</code>, and enter the MySQL credentials.</p>
<p><?=h($e->getMessage())?>

</p>
</body>
</html><?php
exit;
}
$draftPreview=!empty($_SESSION['cr_admin_id'])&&($_GET['draft']??'')==='1';
if($draftPreview) {
    $draft=draft_content();
    if($draft)$content=array_replace($content,$draft);
}
function c(string $k,string $f=''):string {
    global $content;
    return $content[$k]??$f;
}
function public_video_embed(array $video): string {
    $type=(string)($video['video_type']??'');
    $url=trim((string)($video['video_url']??''));
    if($type==='youtube') {
        if(preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~',$url,$m)) {
            return 'https://www.youtube-nocookie.com/embed/'.rawurlencode($m[1]).'?rel=0';
        }
    }
    if($type==='vimeo' && preg_match('~vimeo\.com/(?:video/)?([0-9]+)~',$url,$m)) {
        return 'https://player.vimeo.com/video/'.rawurlencode($m[1]);
    }
    return '';
}
$phone=$settings['phone']??'+1 202-644-2717';
$digits=preg_replace('/\D+/','',$settings['phone_digits']??'12026442717');
$email=$settings['email']??'castrosreadycompany@gmail.com';
$turnstileSiteKey=PublicFormProtection::turnstileSiteKey();
$favicon=$settings['favicon_path']??'assets/logo.jpg';
$maintenance=($settings['maintenance_mode']??'0')==='1';
$adminPreview=!empty($_SESSION['cr_admin_id'])&&($_GET['preview']??'')==='1';
if($maintenance&&!$adminPreview) {
    $mt=$settings['maintenance_title']??'We are improving our website.';
    $mx=$settings['maintenance_text']??'We will be back shortly.';
    $mi=trim((string)($settings['maintenance_image_path']??''));
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Castro's Ready</title>
<link rel="icon" href="<?=h($favicon)?>">
<link rel="shortcut icon" href="<?=h($favicon)?>">
<style>
* {
  box-sizing:border-box
}
body {
  margin:0;
  background:#f3f5f3;
  color:#173f3d;
  font-family:Inter,Arial,sans-serif
}
.m {
  min-height:100vh;
  display:grid;
  place-items:center;
  padding:24px
}
.c {
  width:min(760px,100%);
  background:#fff;
  border:1px solid #dce4df;
  border-radius:30px;
  padding:clamp(28px,6vw,58px);
  text-align:center;
  box-shadow:0 24px 70px rgba(16,60,57,.13);
  position:relative;
  overflow:hidden
}
.c:before {
  content:"";
  position:absolute;
  inset:0 0 auto;
  height:6px;
  background:#f2d45c
}
.brand {
  width:88px;
  height:88px;
  border-radius:22px;
  object-fit:cover;
  border:1px solid #dce4df
}
.hero {
  width:100%;
  max-height:310px;
  object-fit:cover;
  border-radius:22px;
  margin:20px 0 4px;
  border:1px solid #dce4df
}
h1 {
  font-size:clamp(32px,6vw,54px);
  margin:20px 0 10px;
  letter-spacing:-.04em;
  line-height:1.05
}
p {
  color:#687574;
  line-height:1.75;
  font-size:clamp(15px,2vw,18px)
}
.a {
  display:inline-flex;
  margin-top:14px;
  padding:14px 20px;
  border-radius:13px;
  background:#0f7777;
  color:#fff;
  text-decoration:none;
  font-weight:850;
  box-shadow:0 10px 24px rgba(15,119,119,.18)
}
@media(max-width:520px) {
  .m {
    padding:14px
  }
  .c {
    padding:26px 18px;
    border-radius:22px
  }
  .hero {
    border-radius:16px
  }
}
</style>
</head>
<body>
<main class="m">
<section class="c">
<img class="brand" src="assets/logo.jpg" alt="Castro's Ready"><?php
if($mi!==''):
?>

<img class="hero" src="<?=h($mi)?>" alt="Website maintenance"><?php
endif;
?>

<h1><?=h($mt)?>

</h1>
<p><?=h($mx)?>

</p>
<a class="a" href="https://wa.me/<?=$digits?>">Contact us on WhatsApp</a>
</section>
</main>
</body>
</html><?php
exit;
}
$fontCatalog=[ 'Manrope'=>"'Manrope',sans-serif",
'DM Sans'=>"'DM Sans',sans-serif",
'Montserrat'=>"'Montserrat',sans-serif",
'Poppins'=>"'Poppins',sans-serif",
'Inter'=>"'Inter',sans-serif",
'Roboto'=>"'Roboto',sans-serif",
'Open Sans'=>"'Open Sans',sans-serif",
'Lato'=>"'Lato',sans-serif",
'Nunito'=>"'Nunito',sans-serif",
'Merriweather'=>"'Merriweather',serif",
'Playfair Display'=>"'Playfair Display',serif",
'System'=>"system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif" ];
$headingFont=$settings['font_heading_family']??'Manrope';
$bodyFont=$settings['font_body_family']??'DM Sans';
if(!isset($fontCatalog[$headingFont]))$headingFont='Manrope';
if(!isset($fontCatalog[$bodyFont]))$bodyFont='DM Sans';
$webFonts=array_values(array_unique(array_filter([$headingFont,$bodyFont],fn($f)=>$f!=='System')));
$fontHref='';
if($webFonts) {
    $families=[];
    foreach($webFonts as $f)$families[]='family='.str_replace('%20','+',rawurlencode($f)).':wght@300;400;500;600;700;800;900';
    $fontHref='https://fonts.googleapis.com/css2?'.implode('&',$families).'&display=swap';
}
$bannerEnabled=($settings['banner_enabled']??'1')==='1';
$bannerType=$settings['banner_type']??'image';
$bannerImage=$settings['banner_image_path']??'assets/hero-banner.png';
$bannerVideo=$settings['banner_video_path']??'';
$bannerEmbed=trim((string)($settings['banner_embed_url']??''));
$bannerAlt=$settings['banner_alt']??"Castro's Ready services";
$bannerDisplay=$settings['banner_display']??'full';
$bannerHeight=$settings['banner_height']??'auto';
$navPosition=$settings['nav_position']??'below_banner';
$navBehavior=$settings['nav_behavior']??'sticky_after';
$navLogo=($settings['nav_logo_enabled']??'0')==='1';
$navAlign=$settings['nav_alignment']??'center';
$embedUrl='';
if($bannerEmbed!=='') {
    if(preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,})~',$bannerEmbed,$m))$embedUrl='https://www.youtube.com/embed/'.$m[1].'?rel=0&modestbranding=1';
    elseif(preg_match('~vimeo\.com/(\d+)~',$bannerEmbed,$m))$embedUrl='https://player.vimeo.com/video/'.$m[1];
}
$renderBanner=function() use($bannerEnabled,$bannerType,$bannerImage,$bannerVideo,$embedUrl,$bannerAlt,$bannerDisplay,$bannerHeight) {
    if(!$bannerEnabled)return;
    echo '<section class="site-banner banner-'.$bannerDisplay.' banner-'.$bannerHeight.'" aria-label="Website banner"><div class="site-banner-inner">';
    if($bannerType==='video_upload'&&$bannerVideo!=='')echo '<video src="'.h($bannerVideo).'" autoplay muted loop playsinline controls></video>';
    elseif($bannerType==='video_embed'&&$embedUrl!=='')echo '<div class="banner-embed"><iframe src="'.h($embedUrl).'" title="Website banner video" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>';
    else echo '<img src="'.h($bannerImage).'" alt="'.h($bannerAlt).'">';
    echo '</div></section>';
}
;
$renderNav=function() use($navBehavior,$navLogo,$navAlign,$videos) {
    echo '<header class="site-header nav-'.h($navBehavior).' '.(!$navLogo?'no-brand ':'').'align-'.h($navAlign).'" id="top"><div class="container nav-wrap">';
    if($navLogo)echo '<a class="brand" href="#home" data-scroll><img src="assets/logo.jpg" alt="Castro\'s Ready logo"><span class="brand-copy"><strong>CASTRO\'S READY</strong><small>PAINTING · REPAIRS · MAINTENANCE</small></span></a>';
    $videoLink=!empty($videos)?'<a href="#videos" data-scroll>Videos</a>':'';
    echo '<button class="menu-btn" type="button" aria-label="Open menu" aria-expanded="false">☰</button><nav class="main-nav"><a href="#home" data-scroll>Home</a><a href="#about" data-scroll>About</a><a href="#services" data-scroll>Services</a>'.$videoLink.'<a href="#gallery" data-scroll>Gallery</a><a href="#areas" data-scroll>Service Areas</a><a href="#contact" data-scroll>Contact</a><a class="nav-cta" href="#estimate" data-scroll>Free Estimate</a></nav></div><div class="scroll-progress"><span></span></div></header>';
}
;
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=h($settings['seo_description']??'Professional home improvement services.')?>">
<meta name="robots" content="<?=h($settings['seo_robots']??'index,follow')?>">
<meta property="og:title" content="<?=h($settings['seo_title']??"Castro's Ready | Home Improvement")?>">
<meta property="og:description" content="<?=h($settings['seo_description']??'Professional home improvement services.')?>"><?php
if(!empty($settings['seo_social_image'])):
?>

<meta property="og:image" content="<?=h($settings['seo_social_image'])?>"><?php
endif;
?>

<title><?=h($settings['seo_title']??"Castro's Ready | Home Improvement")?>

</title>
<link rel="icon" href="<?=h($favicon)?>">
<link rel="shortcut icon" href="<?=h($favicon)?>"><?php
if($fontHref!==''):
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?=h($fontHref)?>" rel="stylesheet"><?php
endif;
?>

<link rel="stylesheet" href="<?=h(versioned_asset('assets/site.css', 'assets/site.css'))?>">
<style>
:root {
  --font-heading: <?= $fontCatalog[$headingFont] ?>;
  --font-body: <?= $fontCatalog[$bodyFont] ?>;
  --size-h1-desktop: <?= h($settings['font_h1_desktop'] ?? '40') ?>px;
  --size-h1-tablet: <?= h($settings['font_h1_tablet'] ?? '34') ?>px;
  --size-h1-mobile: <?= h($settings['font_h1_mobile'] ?? '30') ?>px;
  --size-h2-desktop: <?= h($settings['font_h2_desktop'] ?? '32') ?>px;
  --size-h2-mobile: <?= h($settings['font_h2_mobile'] ?? '28') ?>px;
  --size-h3: <?= h($settings['font_h3'] ?? '22') ?>px;
  --size-body: <?= h($settings['font_body_size'] ?? '16') ?>px;
  --size-small: <?= h($settings['font_small'] ?? '14') ?>px;
  --size-nav: <?= h($settings['font_nav'] ?? '15') ?>px;
  --size-button: <?= h($settings['font_button'] ?? '15') ?>px;
  --line-body: <?= h($settings['line_height_body'] ?? '1.7') ?>;
  --weight-heading: <?= h($settings['heading_weight'] ?? '800') ?>;
  --weight-body: <?= h($settings['body_weight'] ?? '400') ?>;
  --teal: <?= h($settings['color_primary'] ?? '#0f7777') ?>;
  --teal-dark: <?= h($settings['color_primary_dark'] ?? '#0b5f60') ?>;
  --yellow: <?= h($settings['color_secondary'] ?? '#f2d45c') ?>;
  --paper: <?= h($settings['color_background'] ?? '#f7f6f1') ?>;
  --white: <?= h($settings['color_surface'] ?? '#ffffff') ?>;
  --ink: <?= h($settings['color_text'] ?? '#1c2a2a') ?>;
  --muted: <?= h($settings['color_muted'] ?? '#667170') ?>;
  --radius: <?= h($settings['theme_radius'] ?? '24') ?>px;
  --shadow: 0 18px 50px rgba(22, 49, 45, <?= h(((float) ($settings['theme_shadow_strength'] ?? 10)) / 100) ?>);
}
</style>
</head>
<body class="<?= $navBehavior === 'fixed' ? 'nav-fixed-layout' : '' ?>">
<style>
main {
  display:flex;
  flex-direction:column
}
</style><?php
if($navPosition==='above_banner') {
    $renderNav();
    $renderBanner();
} else {
    $renderBanner();
    $renderNav();
}
?>

<main>
<?php
if(section_enabled('home')):
?>

<section class="hero section-anchor" id="home" style="order:<?=section_order('home')?>">
<div class="container hero-grid">
<div class="reveal">
<span class="eyebrow" data-content-key="hero_eyebrow"><?=h(c('hero_eyebrow'))?>

</span>
<h1 data-content-key="hero_title"><?=h(c('hero_title'))?>

</h1>
<p class="hero-text" data-content-key="hero_text"><?=h(c('hero_text'))?>

</p>
<div class="hero-actions">
<a class="btn btn-primary" href="#estimate" data-scroll>Request a Free Estimate</a>
<a class="btn btn-secondary" href="tel:<?=$digits?>">Call <?=h($phone)?>

</a>
</div>
<div class="trust-row">
<span>Reliable service</span>
<span>Quality workmanship</span>
<span>Home-focused care</span>
</div>
</div>
<div class="hero-visual reveal">
<div class="hero-badge">
<strong>One trusted team.</strong>
<span>From touch-ups to full transformations.</span>
</div>
</div>
</div>
</section>
<?php
endif;
?>
<?php
if(section_enabled('intro')):
?>

<section class="intro-strip" style="order:<?=section_order('intro')?>">
<div class="container intro-grid reveal">
<div>
<span class="kicker">WHAT WE DO</span>
<h2 data-content-key="intro_title"><?=h(c('intro_title'))?>

</h2>
</div>
<p data-content-key="intro_text"><?=h(c('intro_text'))?>

</p>
</div>
</section>
<?php
endif;
?>
<?php
if(section_enabled('about')):
?>

<section class="section section-anchor" id="about" style="order:<?=section_order('about')?>">
<div class="container about-grid">
<div class="photo-stack reveal">
<div class="photo photo-main">
</div>
<div class="photo-note">
<span>Built around one standard</span>
<strong>Do the work right.</strong>
</div>
</div>
<div class="section-copy reveal">
<span class="kicker">ABOUT CASTRO'S READY</span>
<h2 data-content-key="about_title"><?=h(c('about_title'))?>

</h2>
<p class="lead" data-content-key="about_text"><?=h(c('about_text'))?>

</p>
<p data-content-key="about_text_2"><?=h(c('about_text_2'))?>

</p>
<?php if(!empty($aboutArtworks)): ?>
<div class="mission-art-grid about-artwork-gallery <?=count($aboutArtworks)===1?'single-artwork':''?>">
<?php foreach($aboutArtworks as $artwork): ?>
<article class="mission-art-card">
<img src="<?=h($artwork['image_path'])?>" alt="<?=h(trim((string)$artwork['title'])!==''?$artwork['title'].' artwork':'Castro\'s Ready Mission and Vision artwork')?>" loading="lazy">
</article>
<?php endforeach; ?>
</div>
<p class="visually-hidden" data-content-key="mission"><?=h(c('mission'))?></p>
<p class="visually-hidden" data-content-key="vision"><?=h(c('vision'))?></p>
<?php else: ?>
<div class="mission-grid mission-text-fallback">
<article><span>Mission</span><p data-content-key="mission"><?=h(c('mission'))?></p></article>
<article><span>Vision</span><p data-content-key="vision"><?=h(c('vision'))?></p></article>
</div>
<?php endif; ?>
<div class="values">
<span>Integrity</span>
<span>Respect</span>
<span>Quality</span>
<span>Reliability</span>
<span>Accountability</span>
<span>Customer Focus</span>
</div>
</div>
</div>
</section>
<?php
endif;
?>
<?php
if(section_enabled('services')):
?>

<section class="section services-section section-anchor" id="services" style="order:<?=section_order('services')?>">
<div class="container">
<div class="section-head reveal">
<div>
<span class="kicker">OUR SERVICES</span>
<h2>From maintenance to complete transformations.</h2>
</div>
<p>Select a category to explore the work Castro's Ready can help with.</p>
</div>
<div class="services-grid reveal"><?php
foreach($services as $i=>$s):
?>

<details>
<summary>
<span class="service-icon service-badge-wrap">
<?php if(!empty($s['icon_path'])): ?><img src="<?=h($s['icon_path'])?>" alt="<?=h($s['title'])?> service badge" loading="lazy"><?php else: ?><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?><?php endif; ?>
</span>
<strong><?=h($s['title'])?>

</strong>
<i>+</i>
</summary>
<div class="service-body">
<p><?=h($s['details'])?>

</p>
</div>
</details><?php
endforeach;
?>

</div>
</div>
</section>
<?php
endif;
?>
<?php
if(section_enabled('videos')&&!empty($videos)):
?>
<section class="section video-section section-anchor" id="videos" style="order:<?=section_order('videos')?>">
<div class="container">
<div class="section-head reveal">
<div><span class="kicker">SEE OUR WORK</span><h2>Watch the craftsmanship behind the finished result.</h2></div>
<p>Real project videos, presented in a clean and consistent showcase.</p>
</div>
<div class="video-grid reveal">
<?php foreach($videos as $video): $embed=public_video_embed($video); ?>
<article class="video-card">
<div class="video-frame">
<?php if($video['video_type']==='upload'&&!empty($video['file_path'])): ?>
<video controls preload="metadata" playsinline <?php if(!empty($video['poster_path'])): ?>poster="<?=h($video['poster_path'])?>"<?php endif; ?>><source src="<?=h($video['file_path'])?>"></video>
<?php elseif($embed!==''): ?>
<iframe src="<?=h($embed)?>" title="<?=h($video['title'])?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
<?php endif; ?>
</div>
<div class="video-card-copy"><strong><?=h($video['title'])?></strong><?php if(trim((string)$video['description'])!==''): ?><p><?=h($video['description'])?></p><?php endif; ?></div>
</article>
<?php endforeach; ?>
</div>
</div>
</section>
<?php endif; ?>

<?php
if(section_enabled('gallery')):
?>

<section class="section gallery-section section-anchor" id="gallery" style="order:<?=section_order('gallery')?>">
<div class="container">
<div class="section-head reveal">
<div>
<span class="kicker">PROJECT GALLERY</span>
<h2>Craftsmanship is easier to trust when you can see it.</h2>
</div>
<a class="text-link" href="#estimate" data-scroll>Start your project →</a>
</div>
<div class="gallery-grid reveal"><?php
foreach($gallery as $i=>$g):$img=$g['image_path']?:gallery_fallback($i);
?>

<article class="gallery-card g<?=($i%6)+1?>" style="background-image:url('<?=h($img)?>')">
<span><?=h($g['title'])?>

</span>
<button type="button" class="gallery-zoom" data-gallery-src="<?=h($img)?>" data-gallery-title="<?=h($g['title'])?>" aria-label="View <?=h($g['title'])?>

 large">⌕</button>
</article><?php
endforeach;
?>

</div>
</div>
</section>
<?php
endif;
?>
<?php
if(section_enabled('areas')):
?>

<section class="section areas-section section-anchor" id="areas" style="order:<?=section_order('areas')?>">
<div class="container areas-grid">
<div class="reveal">
<span class="kicker">SERVICE AREAS</span>
<h2 data-content-key="areas_title"><?=h(c('areas_title'))?>

</h2>
<p data-content-key="areas_text"><?=h(c('areas_text'))?>

</p>
<div class="area-tags"><?php
if($areas):foreach($areas as $a):
?>

<span><?=h($a['area_name'])?>

</span><?php
endforeach;
else:
?>

<span>Coverage locations coming soon</span><?php
endif;
?>

</div>
<a class="btn btn-secondary" href="tel:<?=$digits?>">Ask if we serve your area</a>
</div>
<?php
$serviceMapEnabled=($settings['service_map_enabled']??'1')==='1';
$serviceMapQuery=trim((string)($settings['service_map_query']??''));
if($serviceMapQuery===''&&!empty($areas)) {
    $serviceMapQuery=(string)$areas[0]['area_name'];
}
if($serviceMapQuery==='') {
    $serviceMapQuery='United States';
}
$serviceMapLabel=trim((string)($settings['service_map_label']??'Service Area Map'));
if($serviceMapLabel==='') {
    $serviceMapLabel='Service Area Map';
}
?>
<?php if($serviceMapEnabled): ?>
<div class="service-map reveal">
<iframe
    title="<?=h($serviceMapLabel)?>"
    src="https://www.google.com/maps?q=<?=rawurlencode($serviceMapQuery)?>&output=embed"
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade"
></iframe>
<div class="map-card">
<strong><?=h($serviceMapLabel)?></strong>
<span><?=h($serviceMapQuery)?></span>
</div>
</div>
<?php endif; ?>
</div>
</section>
<?php
endif;
?>
<?php
if(section_enabled('tips')):
?>

<section class="section tips-section section-anchor" id="tips" style="order:<?=section_order('tips')?>">
<div class="container">
<div class="section-head reveal">
<div>
<span class="kicker">HOME IMPROVEMENT TIPS</span>
<h2>Useful advice for protecting your home.</h2>
</div>
<p>Practical guidance for keeping your property looking and performing its best.</p>
</div>
<div class="tips-grid reveal"><?php
foreach($tips as $i=>$tip):
?>

<article>
<span><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?>

</span>
<h3><?=h($tip['title'])?>

</h3>
<a href="<?=h($tip['url']?:'#')?>">Read guide →</a>
</article><?php
endforeach;
?>

</div>
</div>
</section>
<?php
endif;
?>
<?php
if(section_enabled('estimate')):
?>

<section class="section estimate-section section-anchor" id="estimate" style="order:<?=section_order('estimate')?>">
<div class="container estimate-grid">
<div class="estimate-copy reveal">
<span class="kicker">FREE ESTIMATE</span>
<h2 data-content-key="estimate_title"><?=h(c('estimate_title'))?>

</h2>
<p data-content-key="estimate_text"><?=h(c('estimate_text'))?>

</p>
<div class="estimate-contact">
<a href="tel:<?=$digits?>">
<b>Call</b>
<span><?=h($phone)?>

</span>
</a>
<a href="mailto:<?=h($email)?>">
<b>Email</b>
<span><?=h($email)?>

</span>
</a>
</div>
</div>
<form class="estimate-form reveal" id="estimateForm" enctype="multipart/form-data">
<div class="form-honeypot" aria-hidden="true">
<label>Website URL<input type="text" name="website_url" tabindex="-1" autocomplete="off">
</label>
</div>
<div class="field-row">
<label>Full Name<input type="text" name="name" placeholder="Your name">
</label>
<label>Phone<input type="tel" name="phone" placeholder="(000) 000-0000">
</label>
</div>
<div class="field-row">
<label>Email<input type="email" name="email" placeholder="you@email.com" autocomplete="email" aria-describedby="estimateEmailStatus">
<span class="email-validation-status" id="estimateEmailStatus" data-email-status aria-live="polite"></span>
</label>
<label>Desired Date<input type="date" name="date">
</label>
</div>
<label>Address<input type="text" name="address" placeholder="Project address">
</label>
<label>Service Needed<select name="service">
<option value="">Select a service</option><?php
foreach($services as $s):
?>

<option><?=h($s['title'])?>

</option><?php
endforeach;
?>

</select>
</label>
<label>Tell us about your project<textarea name="message" rows="4" placeholder="A short description is enough to get started.">
</textarea>
</label>
<div class="public-upload" data-public-upload tabindex="0">
<div class="public-upload-icon">＋</div>
<strong>Add project photos</strong>
<span>Drag & drop, paste from clipboard, or choose files</span>
<small>Optional · JPG, PNG or WebP · up to 8 images</small>
<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
<div class="public-upload-preview" data-public-upload-preview>
</div>
</div>
<?php if($turnstileSiteKey!==''): ?>
<div class="turnstile-wrap">
<div class="cf-turnstile" data-sitekey="<?=h($turnstileSiteKey)?>" data-theme="light"></div>
</div>
<?php endif; ?>
<button type="submit" class="btn btn-primary btn-full">Send Free Estimate Request</button>
<a class="whatsapp-btn" target="_blank" rel="noopener" href="https://wa.me/<?=$digits?>

?text=Hello%2C%20I%20just%20submitted%20a%20free%20estimate%20request%20on%20your%20website.%20I%20would%20like%20to%20receive%20more%20information%20about%20my%20project.">Continue on WhatsApp</a>
<p class="form-note">You can submit the form even if you do not attach a photo.</p>
</form>
</div>
</section>
<?php
endif;
?>
<?php
if(section_enabled('contact')):
?>

<section class="section contact-section section-anchor" id="contact" style="order:<?=section_order('contact')?>">
<div class="container contact-grid">
<div class="reveal contact-intro">
<span class="kicker">CONTACT</span>
<h2 data-content-key="contact_title"><?=h(c('contact_title'))?>

</h2>
<p>Choose the easiest way to reach Castro's Ready.</p>
<div class="contact-accent">
<span>
</span>
<b>Fast, direct, professional contact.</b>
</div>
</div>
<div class="contact-links reveal">
<a class="contact-card" href="tel:<?=$digits?>">
<i class="contact-icon">☎</i>
<div>
<span>Phone</span>
<strong><?=h($phone)?>

</strong>
<small>Call our team directly</small>
</div>
<b class="contact-arrow">→</b>
</a>
<a class="contact-card" target="_blank" rel="noopener" href="https://wa.me/<?=$digits?>">
<i class="contact-icon">WA</i>
<div>
<span>WhatsApp</span>
<strong>Start a conversation</strong>
<small>Quick project questions</small>
</div>
<b class="contact-arrow">→</b>
</a>
<a class="contact-card" href="mailto:<?=h($email)?>">
<i class="contact-icon">✉</i>
<div>
<span>Email</span>
<strong><?=h($email)?>

</strong>
<small>Send project information</small>
</div>
<b class="contact-arrow">→</b>
</a>
<a class="contact-card" target="_blank" rel="noopener" href="<?=h($settings['youtube']??'#')?>">
<i class="contact-icon">▶</i>
<div>
<span>YouTube</span>
<strong>@CastrosReady</strong>
<small>See more of our work</small>
</div>
<b class="contact-arrow">→</b>
</a>
</div>
</div>
</section><?php
endif;
?>

</main>
<footer>
<div class="container footer-grid">
<div class="footer-brand">
<img src="assets/logo.jpg" alt="Castro's Ready logo">
<div>
<strong>CASTRO'S READY</strong>
<span>Painting · Repairs · Maintenance</span>
</div>
</div>
<div>
<strong>Explore</strong>
<a href="#about" data-scroll>About</a>
<a href="#services" data-scroll>Services</a>
<?php if(!empty($videos)): ?><a href="#videos" data-scroll>Videos</a><?php endif; ?>
<a href="#gallery" data-scroll>Gallery</a>
</div>
<div>
<strong>Contact</strong>
<a href="tel:<?=$digits?>"><?=h($phone)?>

</a>
<a href="mailto:<?=h($email)?>">Email us</a>
<a href="#estimate" data-scroll>Free Estimate</a>
</div>
<div>
<strong>Website</strong>
<span><?=h($settings['website']??'castrosready.us')?>

</span>
<span>© 2026 Castro's Ready</span>
</div>
</div><?php
if(($settings['developer_credit_enabled']??'0')==='1'):
?>

<div class="developer-credit"><?=h($settings['developer_credit_text']??'Website by ES MULTISERVICIOS')?>

</div><?php
endif;
?>

</footer>
<div class="toast" id="toast">Thank you.</div>
<div class="site-lightbox" data-site-lightbox aria-hidden="true">
<button type="button" data-site-lightbox-close aria-label="Close">×</button>
<figure>
<img src="" alt="Project preview" data-site-lightbox-img>
<figcaption data-site-lightbox-caption>
</figcaption>
</figure>
</div><?php
if(($settings['whatsapp_enabled']??'1')==='1'):
?>

<a class="floating-whatsapp <?=($settings['whatsapp_position']??'right')==='left'?'left':''?>" href="https://wa.me/<?=$digits?>

?text=<?=rawurlencode($settings['whatsapp_message']??"Hello, I would like more information about Castro's Ready services.")?>" target="_blank" rel="noopener" aria-label="Contact Castro's Ready on WhatsApp">
<span>WA</span>
<b>WhatsApp</b>
</a><?php
endif;
?>

<?php if($turnstileSiteKey!==''): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<script src="<?=h(versioned_asset('assets/site.js', 'assets/site.js'))?>">
</script>
</body>
</html>
