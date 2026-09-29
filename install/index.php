<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
$lockFile = $root . '/config/install.lock';

/*
 * install.lock es la fuente de verdad de la instalación.
 * - Si existe: el CMS está instalado y el instalador queda bloqueado.
 * - Si se elimina manualmente: se habilita el asistente de reinstalación.
 * config.php se conserva para poder reutilizar de forma segura la conexión existente.
 */
if (is_file($lockFile)) {
    header('Location: ../admin/login.php');
    exit;
}

$existingConfig = [];
$existingConfigRaw = null;
if (is_file($configFile)) {
    $existingConfigRaw = file_get_contents($configFile);
    $loaded = require $configFile;
    if (is_array($loaded)) {
        $existingConfig = $loaded;
    }
}
$reinstallMode = !empty($existingConfig);

$error = '';
$pdo = null;
$configWritten = false;

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function splitSql(string $sql): array
{
    return array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])));
}

function detectedSiteUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $scheme = $https ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    if ($host === '') {
        $host = 'localhost';
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
    $installDir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $sitePath = rtrim(str_replace('\\', '/', dirname($installDir)), '/');
    if ($sitePath === '.' || $sitePath === '/') {
        $sitePath = '';
    }

    return rtrim($scheme . '://' . $host . $sitePath, '/');
}

$detectedSiteUrl = detectedSiteUrl();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $host = trim((string) ($_POST['db_host'] ?? ($existingConfig['db']['host'] ?? 'localhost')));
        $port = (int) ($_POST['db_port'] ?? ($existingConfig['db']['port'] ?? 3306));
        $dbPrefix = trim((string) ($_POST['db_prefix'] ?? ''));
        $dbBaseName = trim((string) ($_POST['db_base_name'] ?? ($existingConfig['db']['name'] ?? '')));
        $name = $dbPrefix . $dbBaseName;
        $user = trim((string) ($_POST['db_user'] ?? ($existingConfig['db']['user'] ?? '')));
        $pass = (string) ($_POST['db_pass'] ?? '');
        if ($pass === '' && $reinstallMode) {
            $pass = (string) ($existingConfig['db']['pass'] ?? '');
        }

        $adminName = trim((string) ($_POST['admin_name'] ?? ''));
        $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
        $adminUser = trim((string) ($_POST['admin_user'] ?? ''));
        $adminPass = (string) ($_POST['admin_password'] ?? '');
        $siteUrl = rtrim(trim((string) ($_POST['site_url'] ?? '')), '/');
        if ($siteUrl === '') {
            $siteUrl = $detectedSiteUrl;
        }

        if ($dbBaseName === '' || $user === '') {
            throw new RuntimeException('Completa la información de la base de datos.');
        }
        if ($dbPrefix !== '' && !preg_match('/^[A-Za-z0-9_$-]+$/', $dbPrefix)) {
            throw new RuntimeException('El prefijo de la base de datos solo puede contener letras, números, guion, guion bajo o $.');
        }
        if (!preg_match('/^[A-Za-z0-9_$-]+$/', $dbBaseName)) {
            throw new RuntimeException('El nombre de la base de datos solo puede contener letras, números, guion, guion bajo o $.');
        }
        if (strlen($name) > 64) {
            throw new RuntimeException('El nombre final de la base de datos supera el máximo de 64 caracteres permitido por MySQL.');
        }
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('El puerto de MySQL no es válido.');
        }
        if ($adminName === '' || $adminUser === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPass) < 8) {
            throw new RuntimeException('Completa correctamente la cuenta administradora. La contraseña debe tener al menos 8 caracteres.');
        }
        if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('La URL del sitio no es válida.');
        }

        $method = (string) ($_POST['mail_method'] ?? 'none');
        if ($method === 'smtp') {
            $mail = [
                'host' => trim((string) ($_POST['smtp_host'] ?? '')),
                'port' => (int) ($_POST['smtp_port'] ?? 587),
                'encryption' => (string) ($_POST['smtp_encryption'] ?? 'tls'),
                'username' => trim((string) ($_POST['smtp_username'] ?? '')),
                'password' => (string) ($_POST['smtp_password'] ?? ''),
                'from_email' => trim((string) ($_POST['smtp_from_email'] ?? '')),
                'from_name' => trim((string) ($_POST['smtp_from_name'] ?? 'Sabrosísimo Mix')),
            ];
            if ($mail['host'] === '' || $mail['port'] < 1 || $mail['port'] > 65535 || $mail['username'] === '' || $mail['password'] === '' || !filter_var($mail['from_email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Completa correctamente todos los campos SMTP o selecciona “Configurar después”.');
            }
        } elseif ($method === 'graph') {
            $mail = [
                'tenant_id' => trim((string) ($_POST['graph_tenant_id'] ?? '')),
                'client_id' => trim((string) ($_POST['graph_client_id'] ?? '')),
                'client_secret' => (string) ($_POST['graph_client_secret'] ?? ''),
                'sender_email' => trim((string) ($_POST['graph_sender_email'] ?? '')),
            ];
            if ($mail['tenant_id'] === '' || $mail['client_id'] === '' || $mail['client_secret'] === '' || !filter_var($mail['sender_email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Completa correctamente todos los campos de Microsoft Graph o selecciona “Configurar después”.');
            }
        } else {
            $method = 'none';
            $mail = [];
        }

        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $dbConnectError) {
            $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $safeDb = '`' . str_replace('`', '``', $name) . '`';
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $safeDb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE $safeDb");
        }

        if ($reinstallMode) {
            /*
             * Reinstalación limpia: elimina EXCLUSIVAMENTE las tablas que pertenecen a este CMS.
             * No elimina la base de datos, no toca otras bases y no borra tablas ajenas.
             */
            $cmsTables = [
                'estimate_reply_attachments',
                'estimate_replies',
                'estimate_notes',
                'estimate_attachments',
                'estimate_request_flags',
                'estimate_requests',
                'activity_log',
                'site_visits',
                'admin_role_permissions',
                'admin_password_resets',
                'admin_users',
                'admin_permissions',
                'admin_roles',
                'media_library',
                'videos',
                'tips',
                'service_areas',
                'projects',
                'services',
                'content_blocks',
                'settings',
            ];
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach ($cmsTables as $table) {
                    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
                }
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $schema = file_get_contents(__DIR__ . '/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('No se pudo leer la estructura de la base de datos.');
        }
        foreach (splitSql($schema) as $statement) {
            $pdo->exec($statement);
        }

        $pdo->beginTransaction();

        /* En una instalación nueva las tablas están vacías; en reinstalación se recrearon arriba. */


        $pdo->exec("INSERT IGNORE INTO admin_roles(id,role_name) VALUES(1,'Administrator')");
        $permissions = [
            ['dashboard.view', 'Dashboard'],
            ['content.manage', 'Content'],
            ['services.manage', 'Services'],
            ['projects.manage', 'Projects'],
            ['media.manage', 'Media'],
            ['settings.manage', 'Settings'],
            ['email.manage', 'Email'],
            ['health.view', 'Website Health'],
            ['analytics.view', 'Website analytics'],
            ['estimates.view', 'View estimate requests'],
            ['estimates.manage_assigned', 'Manage assigned estimate requests'],
            ['estimates.manage_all', 'Manage all estimate requests'],
        ];
        $permissionStmt = $pdo->prepare('INSERT IGNORE INTO admin_permissions(permission_key,label) VALUES(?,?)');
        foreach ($permissions as $row) {
            $permissionStmt->execute($row);
        }
        $pdo->exec('INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT 1,id FROM admin_permissions');

        $existingAdminStmt = $pdo->prepare('SELECT id FROM admin_users WHERE username=? OR email=? ORDER BY id LIMIT 1');
        $existingAdminStmt->execute([$adminUser, $adminEmail]);
        $existingAdminId = (int) ($existingAdminStmt->fetchColumn() ?: 0);
        if ($existingAdminId > 0) {
            $adminStmt = $pdo->prepare('UPDATE admin_users SET role_id=1,username=?,email=?,full_name=?,password_hash=?,active=1 WHERE id=?');
            $adminStmt->execute([$adminUser, $adminEmail, $adminName, password_hash($adminPass, PASSWORD_DEFAULT), $existingAdminId]);
        } else {
            $adminStmt = $pdo->prepare('INSERT INTO admin_users(role_id,username,email,full_name,password_hash,active) VALUES(1,?,?,?,?,1)');
            $adminStmt->execute([$adminUser, $adminEmail, $adminName, password_hash($adminPass, PASSWORD_DEFAULT)]);
        }

        $settings = [
            'site_name' => 'Sabrosísimo Mix',
            'site_tagline' => 'Sabor y Servicio es nuestra pasión',
            'site_url' => $siteUrl,
            'phone_primary' => '+504 3273-5251',
            'phone_secondary' => '+504 8809-9003',
            'whatsapp' => '50488099003',
            'location' => 'San Pedro Sula, Honduras',
            'facebook_url' => '',
            'tiktok_url' => 'https://www.tiktok.com/@sabrosisimomix',
            'instagram_url' => 'https://www.instagram.com/sabrosisimomix/',
            'facebook_url' => 'https://web.facebook.com/people/Sabros%C3%ADsimo-mix/61592916879862/',
            'social_networks_json' => '[{"enabled":1,"platform":"instagram","url":"https://www.instagram.com/sabrosisimomix/","order":1},{"enabled":1,"platform":"facebook","url":"https://web.facebook.com/people/Sabros%C3%ADsimo-mix/61592916879862/","order":2},{"enabled":1,"platform":"tiktok","url":"https://www.tiktok.com/@sabrosisimomix","order":3},{"enabled":0,"platform":"youtube","url":"","order":4},{"enabled":0,"platform":"linkedin","url":"","order":5}]',
            'social_display_location' => 'footer-floating-right',
            'social_icon_size' => 'medium',
            'social_display_style' => 'icons',
            'social_show_desktop' => '1',
            'social_show_mobile' => '1',
            'mail_method' => 'none',
            'mail_config' => '',
            'maintenance_mode' => '0',
            'floating_whatsapp_enabled' => '1',
            'floating_whatsapp_position' => 'bottom-right',
            'floating_whatsapp_order' => '1',
            'floating_widget_enabled' => '0',
            'floating_widget_position' => 'bottom-left',
            'floating_widget_order' => '2',
            'floating_widget_label' => 'Chat',
            'floating_widget_code' => '',
        ];
        $settingStmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach ($settings as $k => $v) {
            $settingStmt->execute([$k, $v]);
        }

        /* label es solo el nombre corto que se muestra en Administración. */
        $blocks = [
            ['hero_eyebrow', 'Etiqueta superior del inicio', 'EVENTOS · SABOR · EXPERIENCIAS'],
            ['hero_title', 'Título principal del inicio', 'Haz de tu evento una experiencia deliciosa e inolvidable'],
            ['hero_text', 'Texto principal del inicio', 'Ofrecemos taqueadas, pupusas, pastelitos, nieves, palomitas, algodones y saltarines para reuniones familiares, cumpleaños, ferias y eventos empresariales.'],
            ['about_title', 'Título de Nosotros', 'Todo lo que necesitas para compartir y celebrar'],
            ['about_text', 'Texto de Nosotros', 'Nos encargamos de la comida y del ambiente para que tus invitados disfruten. Trabajamos con atención cercana, preparación al momento y opciones ideales para distintos tipos de evento.'],
        ];
        $contentStmt = $pdo->prepare('INSERT INTO content_blocks(content_key,label,content_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label),content_value=VALUES(content_value)');
        foreach ($blocks as $row) {
            $contentStmt->execute($row);
        }

        $services = [
            ['Taqueadas en vivo', 'Preparación en el momento con atención para reuniones familiares, cumpleaños y eventos corporativos.', '🌮', 1],
            ['Pupusas recién hechas', 'Pupusas preparadas al momento para compartir una experiencia auténtica y sabrosa.', '🫓', 2],
            ['Pastelitos tradicionales', 'Opciones de pastelitos para complementar el menú de tu evento.', '🥟', 3],
            ['Saltarines para fiestas', 'Renta de saltarines para agregar diversión infantil a tus celebraciones.', '🏰', 4],
            ['Nieves, palomitas y algodones', 'Estaciones dulces e ideales para fiestas, convivios y actividades especiales.', '🍿', 5],
            ['Snacks y antojitos', 'Complementos ligeros para ampliar tu servicio según el tipo de evento.', '🍧', 6],
            ['Atención para eventos empresariales', 'Servicio adaptable a convivios, ferias internas y actividades corporativas.', '🏢', 7],
            ['Servicio a domicilio', 'Nos adaptamos al tamaño, lugar y presupuesto de tu evento.', '🚚', 8],
        ];
        $serviceStmt = $pdo->prepare('INSERT INTO services(title,description,icon,sort_order,active) VALUES(?,?,?,?,1)');
        foreach ($services as $row) {
            $serviceStmt->execute($row);
        }

        $areas = [
            ['San Pedro Sula', 'Cobertura principal para reuniones, cumpleaños, actividades familiares y empresariales.', 1],
            ['Zonas cercanas', 'Disponibilidad sujeta a coordinación previa para sectores cercanos a San Pedro Sula.', 2],
        ];
        $areaStmt = $pdo->prepare('INSERT INTO service_areas(name,description,sort_order,active) VALUES(?,?,?,1)');
        foreach ($areas as $row) {
            $areaStmt->execute($row);
        }

        $tips = [
            ['Reserva con anticipación', 'Comparte fecha, ubicación y cantidad aproximada de personas para recibir una cotización más precisa.', 1],
            ['Indica tus servicios clave', 'Cuéntanos si necesitas comida, saltarines, estación de dulces o una combinación personalizada.', 2],
            ['Adjunta referencias del evento', 'Puedes enviar imágenes del lugar o ideas del montaje desde el formulario de cotización.', 3],
        ];
        $tipStmt = $pdo->prepare('INSERT INTO tips(title,body,sort_order,active) VALUES(?,?,?,1)');
        foreach ($tips as $row) {
            $tipStmt->execute($row);
        }

        $projects = [
            ['Logo principal de marca', 'Branding', 'Logo base compartido por el cliente para la identidad visual del sitio.', 'assets/images/branding/logo-principal.jpg', 1],
            ['Banner de contacto', 'Branding', 'Pieza horizontal con teléfono, atributos del servicio y ubicación.', 'assets/images/branding/banner-contacto-horizontal.jpg', 2],
            ['Servicio en evento nocturno', 'Evento', 'Montaje real de atención al cliente en evento nocturno.', 'assets/images/gallery/servicio-en-evento-nocturno.jpg', 3],
            ['Taqueadas con saltarín', 'Evento', 'Servicio real con preparación en vivo y apoyo de saltarín.', 'assets/images/gallery/evento-con-saltarin-y-taqueadas.jpg', 4],
            ['Estación de cocina', 'Evento', 'Montaje de estación de cocina para servicio en vivo.', 'assets/images/gallery/estacion-de-cocina-nocturna.jpg', 5],
            ['Nieves y palomitas', 'Complementos', 'Mesa de nieves, palomitas y dulces para eventos.', 'assets/images/gallery/nieves-y-palomitas.jpg', 6],
            ['Saltarín modelo 1', 'Saltarines', 'Referencia visual de saltarín infantil.', 'assets/images/gallery/saltarin-modelo-1.jpg', 7],
            ['Saltarín modelo 2', 'Saltarines', 'Otra referencia de saltarín para el proyecto.', 'assets/images/gallery/saltarin-modelo-2.jpg', 8],
            ['Saltarín modelo 3', 'Saltarines', 'Modelo adicional de saltarín compartido como referencia.', 'assets/images/gallery/saltarin-modelo-3.jpg', 9],
            ['Arte promocional 1', 'Promocional', 'Pieza promocional vertical de servicios y menú.', 'assets/images/promos/arte-promocional-vertical-1.jpg', 10],
            ['Arte promocional 2', 'Promocional', 'Pieza promocional con lista de eventos ideales y servicios.', 'assets/images/promos/arte-promocional-vertical-2.jpg', 11],
            ['Arte de servicios 1', 'Promocional', 'Arte horizontal sobre banquetes, eventos y métodos de pago.', 'assets/images/promos/arte-servicios-eventos-1.jpg', 12],
            ['Arte de servicios 2', 'Promocional', 'Arte horizontal con saltarín y opciones de dulces.', 'assets/images/promos/arte-servicios-eventos-2.jpg', 13],
            ['Poster vintage de contacto', 'Promocional', 'Pieza gráfica con datos de contacto y lema de la marca.', 'assets/images/promos/poster-contacto-vintage.jpg', 14],
            ['Banner de servicios', 'Promocional', 'Resumen visual de servicios principales compartido por el cliente.', 'assets/images/promos/banner-servicios-horizontal.jpg', 15],
            ['Logo con fondo negro', 'Branding', 'Versión alternativa del logo con slogan sobre fondo oscuro.', 'assets/images/branding/logo-negro-slogan.jpg', 16],
        ];
        $projectStmt = $pdo->prepare('INSERT INTO projects(title,category,description,image_path,sort_order,active) VALUES(?,?,?,?,?,1)');
        foreach ($projects as $row) {
            $projectStmt->execute($row);
        }

        $mediaFiles = [
            ['assets/images/branding/logo-principal.jpg', 'Logo principal'],
            ['assets/images/branding/logo-negro-slogan.jpg', 'Logo con slogan sobre fondo negro'],
            ['assets/images/branding/banner-contacto-horizontal.jpg', 'Banner horizontal de contacto'],
            ['assets/images/gallery/evento-con-saltarin-y-taqueadas.jpg', 'Evento real con saltarín y taqueadas'],
            ['assets/images/gallery/servicio-en-evento-nocturno.jpg', 'Servicio en evento nocturno'],
            ['assets/images/gallery/estacion-de-cocina-nocturna.jpg', 'Estación de cocina nocturna'],
            ['assets/images/gallery/nieves-y-palomitas.jpg', 'Mesa de nieves y palomitas'],
            ['assets/images/gallery/saltarin-modelo-1.jpg', 'Saltarín modelo 1'],
            ['assets/images/gallery/saltarin-modelo-2.jpg', 'Saltarín modelo 2'],
            ['assets/images/gallery/saltarin-modelo-3.jpg', 'Saltarín modelo 3'],
            ['assets/images/promos/arte-servicios-eventos-1.jpg', 'Arte servicios y eventos 1'],
            ['assets/images/promos/arte-servicios-eventos-2.jpg', 'Arte servicios y eventos 2'],
            ['assets/images/promos/arte-promocional-vertical-1.jpg', 'Arte promocional vertical 1'],
            ['assets/images/promos/arte-promocional-vertical-2.jpg', 'Arte promocional vertical 2'],
            ['assets/images/promos/poster-contacto-vintage.jpg', 'Poster vintage de contacto'],
            ['assets/images/promos/banner-servicios-horizontal.jpg', 'Banner horizontal de servicios'],
        ];
        $mediaStmt = $pdo->prepare('INSERT INTO media_library(file_path,original_name,mime_type,file_size,alt_text) VALUES(?,?,?,?,?)');
        foreach ($mediaFiles as [$path, $alt]) {
            $absolute = $root . '/' . $path;
            if (!is_file($absolute)) {
                continue;
            }
            $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                default => 'application/octet-stream',
            };
            $mediaStmt->execute([$path, basename($absolute), $mime, (int) @filesize($absolute), $alt]);
        }

        $pdo->commit();

        $cfg = [
            'installed' => true,
            'app_key' => bin2hex(random_bytes(32)),
            'db' => [
                'host' => $host,
                'port' => $port,
                'name' => $name,
                'user' => $user,
                'pass' => $pass,
                'charset' => 'utf8mb4',
            ],
        ];
        $php = "<?php\nreturn " . var_export($cfg, true) . ";\n";
        if (file_put_contents($configFile, $php, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir config/config.php. Verifica los permisos de la carpeta config.');
        }
        $configWritten = true;
        @chmod($configFile, 0640);

        if (!defined('INSTALLER_CONTEXT')) {
            define('INSTALLER_CONTEXT', true);
        }
        require_once $root . '/core/bootstrap.php';
        require_once $root . '/core/EmailService.php';
        $emailService = new EmailService();
        $emailService->saveConfiguration($method, $mail);

        /* El correo de bienvenida nunca debe bloquear una instalación correcta. */
        if ($method !== 'none') {
            try {
                $welcome = $emailService->sendWithFallback(
                    ['system'],
                    $adminEmail,
                    'Bienvenido · ' . (string)setting('site_name', 'CMS'),
                    EmailTemplates::welcome($adminName, base_url('admin/login.php?email=' . rawurlencode($adminEmail)))
                );
                if (!(bool)($welcome['success'] ?? false)) {
                    $log = $pdo->prepare('INSERT INTO activity_log(admin_id,action,message,context_json,ip_address) VALUES(NULL,?,?,?,?)');
                    $log->execute(['welcome_email_failed','Welcome email could not be sent',json_encode(['email'=>$adminEmail,'error'=>$welcome['message']??'unknown'],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'']);
                }
            } catch (Throwable $welcomeError) {
                try {
                    $log = $pdo->prepare('INSERT INTO activity_log(admin_id,action,message,context_json,ip_address) VALUES(NULL,?,?,?,?)');
                    $log->execute(['welcome_email_failed','Welcome email could not be sent',json_encode(['email'=>$adminEmail,'error'=>$welcomeError->getMessage()],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'']);
                } catch (Throwable) {}
            }
        }

        $lockPayload = json_encode([
            'installed_at' => date(DATE_ATOM),
            'site_url' => $siteUrl,
            'version' => 1,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($lockPayload === false || file_put_contents($lockFile, $lockPayload . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo crear config/install.lock. Verifica los permisos de la carpeta config.');
        }
        @chmod($lockFile, 0640);

        header('Location: ../admin/login.php?installed=1&email='.rawurlencode($adminEmail));
        exit;
    } catch (Throwable $ex) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (is_file($lockFile)) {
            @unlink($lockFile);
        }
        if ($configWritten && is_file($configFile)) {
            if ($reinstallMode && is_string($existingConfigRaw)) {
                @file_put_contents($configFile, $existingConfigRaw, LOCK_EX);
            } else {
                @unlink($configFile);
            }
        }
        $message = $ex->getMessage();
        if (str_contains($message, "Data too long for column 'label'")) {
            $message = 'El contenido inicial no pudo guardarse correctamente. Esta versión ya corrige el tamaño de las etiquetas; vuelve a intentar la instalación.';
        }
        $error = $message;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#17110f">
    <title>Instalar Sabrosísimo Mix CMS</title>
    <link rel="stylesheet" href="../assets/css/install.css?v=<?= @filemtime($root . '/assets/css/install.css') ?>">
    <link rel="stylesheet" href="../assets/vendor/ui-feedback.css?v=<?= @filemtime($root . '/assets/vendor/ui-feedback.css') ?>">
</head>
<body>
<main class="wizard-shell">
    <section class="wizard-card" aria-labelledby="wizardTitle">
        <header class="wizard-header">
            <div class="wizard-brand">
                <div class="wizard-logo"><img src="../assets/images/sabrosisimo-logo-reference.png" alt="Sabrosísimo Mix"></div>
                <div>
                    <span>ASISTENTE DE INSTALACIÓN</span>
                    <h1 id="wizardTitle">Sabrosísimo Mix CMS</h1>
                    <p>Configuración inicial guiada</p>
                </div>
            </div>
            <div class="wizard-status"><span class="status-dot"></span><span><?= $reinstallMode ? 'Reinstalación limpia' : 'Instalación nueva' ?></span></div>
        </header>

        <div class="wizard-progress" aria-label="Progreso de instalación">
            <div class="progress-track"><div class="progress-bar" data-progress-bar></div></div>
            <div class="progress-meta"><strong data-step-label>Paso 1 de 4</strong><span data-step-percent>25%</span></div>
        </div>

        <?php if ($reinstallMode): ?>
        <div class="reinstall-notice" role="status">
            <strong>Reinstalación habilitada.</strong> Eliminaste <code>config/install.lock</code>. Al finalizar, el asistente recreará solamente las tablas de este CMS dentro de la misma base de datos y generará un nuevo <code>install.lock</code>.
        </div>
        <?php endif; ?>

        <nav class="wizard-steps" aria-label="Pasos del asistente">
            <button type="button" class="wizard-step is-active" data-step-nav="1" aria-current="step">
                <span>1</span><div><strong>Base de datos</strong><small>Conexión MySQL</small></div>
            </button>
            <button type="button" class="wizard-step" data-step-nav="2" disabled>
                <span>2</span><div><strong>Administrador</strong><small>Cuenta principal</small></div>
            </button>
            <button type="button" class="wizard-step" data-step-nav="3" disabled>
                <span>3</span><div><strong>Correo</strong><small>SMTP o Graph</small></div>
            </button>
            <button type="button" class="wizard-step" data-step-nav="4" disabled>
                <span>4</span><div><strong>Confirmar</strong><small>Revisión final</small></div>
            </button>
        </nav>

        <?php if ($error): ?>
            <div hidden data-install-notify data-type="error" data-message="<?= e($error) ?>"></div>
        <?php endif; ?>

        <form method="post" id="installForm" autocomplete="off" novalidate>
            <section class="wizard-panel is-active" data-step-panel="1" aria-labelledby="step1Title">
                <div class="panel-heading">
                    <span class="panel-kicker">PASO 1 DE 4</span>
                    <h2 id="step1Title">Conecta la base de datos</h2>
                    <p>Ingresa las credenciales de MySQL. Si la base indicada todavía no existe, el instalador intentará crearla automáticamente.</p>
                </div>
                <div class="form-grid two">
                    <label>Servidor
                        <input name="db_host" value="<?= e($_POST['db_host'] ?? ($existingConfig['db']['host'] ?? 'localhost')) ?>" required autocomplete="off">
                    </label>
                    <label>Puerto
                        <input type="number" name="db_port" value="<?= e($_POST['db_port'] ?? ($existingConfig['db']['port'] ?? '3306')) ?>" min="1" max="65535" required>
                    </label>
                    <label>Prefijo del hosting <span class="inline-note">· opcional</span>
                        <input name="db_prefix" value="<?= e($_POST['db_prefix'] ?? '') ?>" placeholder="ej. usuario123_" autocomplete="off" inputmode="latin">
                        <small>Algunos hostings anteponen automáticamente el usuario de la cuenta. Escríbelo aquí exactamente como aparece en tu panel.</small>
                    </label>
                    <label>Nombre de la base de datos
                        <input name="db_base_name" value="<?= e($_POST['db_base_name'] ?? ($existingConfig['db']['name'] ?? 'sabrosisimo_mix')) ?>" required autocomplete="off" inputmode="latin">
                        <small>Escribe el nombre que deseas después del prefijo. Si tu hosting no usa prefijo, este será el nombre completo.</small>
                    </label>
                    <label class="span-2 database-preview">
                        <span class="field-label">Nombre final que utilizará MySQL</span>
                        <div class="database-preview-box"><span data-db-prefix-preview></span><strong data-db-name-preview>sabrosisimo_mix</strong></div>
                        <small>Ejemplo: <code>usuario123_</code> + <code>sabrosisimo_mix</code> = <code>usuario123_sabrosisimo_mix</code>. El sistema no inventa ni fuerza el prefijo.</small>
                    </label>
                    <label>Usuario MySQL
                        <input name="db_user" value="<?= e($_POST['db_user'] ?? ($existingConfig['db']['user'] ?? '')) ?>" required autocomplete="off">
                    </label>
                    <label class="span-2"><span class="field-label">Contraseña MySQL</span>
                        <div class="password-field"><input type="password" name="db_pass" autocomplete="new-password" placeholder="<?= $reinstallMode ? 'Déjala vacía para usar la contraseña actual' : 'Contraseña MySQL' ?>"><button type="button" class="toggle-password" aria-label="Mostrar contraseña">Mostrar</button></div>
                        <?php if ($reinstallMode): ?><small>Si no cambió, puedes dejarla vacía y se reutilizará la conexión existente.</small><?php endif; ?>
                    </label>
                </div>
                <div class="info-box"><b>Consejo</b><span>En la mayoría de hostings, estos datos aparecen en la sección de bases de datos MySQL del panel de control.</span></div>
            </section>

            <section class="wizard-panel" data-step-panel="2" aria-labelledby="step2Title" hidden>
                <div class="panel-heading">
                    <span class="panel-kicker">PASO 2 DE 4</span>
                    <h2 id="step2Title">Crea la cuenta administradora</h2>
                    <p>Esta será la cuenta principal para entrar al panel y administrar contenido, solicitudes, imágenes y configuración.</p>
                </div>
                <div class="form-grid two">
                    <label>Nombre completo
                        <input name="admin_name" value="<?= e($_POST['admin_name'] ?? '') ?>" required autocomplete="name">
                    </label>
                    <label>Usuario
                        <input name="admin_user" value="<?= e($_POST['admin_user'] ?? 'admin') ?>" required autocomplete="username">
                    </label>
                    <label>Correo
                        <input type="email" name="admin_email" value="<?= e($_POST['admin_email'] ?? '') ?>" required autocomplete="email">
                    </label>
                    <label><span class="field-label">Contraseña</span>
                        <div class="password-field"><input type="password" name="admin_password" minlength="8" required autocomplete="new-password"><button type="button" class="toggle-password" aria-label="Mostrar contraseña">Mostrar</button></div>
                        <small>Mínimo 8 caracteres.</small>
                    </label>
                    <label class="span-2"><span class="field-label">URL del sitio <span class="inline-note">· detectada automáticamente</span></span>
                        <input type="url" name="site_url" placeholder="https://midominio.com" value="<?= e($_POST['site_url'] ?? $detectedSiteUrl) ?>" autocomplete="url">
                        <small>La detectamos según la dirección desde la que abriste este instalador. Puedes cambiarla si lo necesitas.</small>
                    </label>
                </div>
            </section>

            <section class="wizard-panel" data-step-panel="3" aria-labelledby="step3Title" hidden>
                <div class="panel-heading">
                    <span class="panel-kicker">PASO 3 DE 4</span>
                    <h2 id="step3Title">Configura el correo del sistema</h2>
                    <p>Elige cómo enviará correos el CMS. Puedes probar la conexión ahora o dejarla pendiente sin bloquear la instalación.</p>
                </div>

                <div class="mail-options">
                    <label class="mail-option">
                        <input type="radio" name="mail_method" value="none" <?= (($_POST['mail_method'] ?? 'none') === 'none') ? 'checked' : '' ?>>
                        <span class="mail-option-icon">→</span>
                        <span><strong>Configurar después</strong><small>Finaliza la instalación y configura el correo desde Administración → Correo.</small></span>
                    </label>
                    <label class="mail-option">
                        <input type="radio" name="mail_method" value="smtp" <?= (($_POST['mail_method'] ?? '') === 'smtp') ? 'checked' : '' ?>>
                        <span class="mail-option-icon">✉</span>
                        <span><strong>SMTP</strong><small>Hosting, Gmail, Microsoft 365 SMTP u otro proveedor compatible.</small></span>
                    </label>
                    <label class="mail-option">
                        <input type="radio" name="mail_method" value="graph" <?= (($_POST['mail_method'] ?? '') === 'graph') ? 'checked' : '' ?>>
                        <span class="mail-option-icon">G</span>
                        <span><strong>Microsoft Graph</strong><small>OAuth2 mediante Tenant ID, Client ID y Client Secret.</small></span>
                    </label>
                </div>

                <div class="mail-panel" data-method="smtp" hidden>
                    <div class="subpanel-title"><strong>Datos SMTP</strong><span>Completa únicamente los campos de tu proveedor.</span></div>
                    <div class="form-grid two">
                        <label>Servidor SMTP<input name="smtp_host" placeholder="smtp.office365.com" value="<?= e($_POST['smtp_host'] ?? '') ?>"></label>
                        <label>Puerto<input type="number" name="smtp_port" value="<?= e($_POST['smtp_port'] ?? '587') ?>"></label>
                        <label>Seguridad
                            <select name="smtp_encryption">
                                <option value="tls" <?= (($_POST['smtp_encryption'] ?? 'tls') === 'tls') ? 'selected' : '' ?>>TLS / STARTTLS</option>
                                <option value="ssl" <?= (($_POST['smtp_encryption'] ?? '') === 'ssl') ? 'selected' : '' ?>>SSL</option>
                                <option value="none" <?= (($_POST['smtp_encryption'] ?? '') === 'none') ? 'selected' : '' ?>>Sin cifrado</option>
                            </select>
                        </label>
                        <label>Usuario<input name="smtp_username" value="<?= e($_POST['smtp_username'] ?? '') ?>"></label>
                        <label>Contraseña<div class="password-field"><input type="password" name="smtp_password" autocomplete="new-password"><button type="button" class="toggle-password">Mostrar</button></div></label>
                        <label>Correo remitente<input type="email" name="smtp_from_email" value="<?= e($_POST['smtp_from_email'] ?? '') ?>"></label>
                        <label class="span-2">Nombre remitente<input name="smtp_from_name" value="<?= e($_POST['smtp_from_name'] ?? 'Sabrosísimo Mix') ?>"></label>
                    </div>
                </div>

                <div class="mail-panel" data-method="graph" hidden>
                    <div class="subpanel-title"><strong>Microsoft Graph</strong><span>Usa los datos de la aplicación registrada en Microsoft Entra.</span></div>
                    <div class="form-grid two">
                        <label>Tenant ID<input name="graph_tenant_id" value="<?= e($_POST['graph_tenant_id'] ?? '') ?>"></label>
                        <label>Client ID<input name="graph_client_id" value="<?= e($_POST['graph_client_id'] ?? '') ?>"></label>
                        <label>Client Secret<div class="password-field"><input type="password" name="graph_client_secret" autocomplete="new-password"><button type="button" class="toggle-password">Mostrar</button></div></label>
                        <label>Correo Microsoft 365<input type="email" name="graph_sender_email" placeholder="correo@empresa.com" value="<?= e($_POST['graph_sender_email'] ?? '') ?>"></label>
                    </div>
                </div>

                <div class="test-wrap" data-test-wrap hidden>
                    <div class="test-field">
                        <label>Enviar prueba a<input type="email" name="test_email" placeholder="correo@ejemplo.com" value="<?= e($_POST['test_email'] ?? '') ?>"></label>
                    </div>
                    <div class="test-action">
                        <span class="test-action-label" aria-hidden="true">Acción</span>
                        <button type="button" class="button secondary" data-test-button>Probar configuración</button>
                    </div>
                    <p>La prueba no guarda los datos; solo valida que la configuración pueda utilizarse.</p>
                </div>
                <div class="mail-test-result" data-test-result hidden aria-hidden="true"></div>
            </section>

            <section class="wizard-panel" data-step-panel="4" aria-labelledby="step4Title" hidden>
                <div class="panel-heading">
                    <span class="panel-kicker">PASO 4 DE 4</span>
                    <h2 id="step4Title">Revisa y confirma</h2>
                    <p>Verifica los datos principales. Las contraseñas y secretos nunca se muestran en este resumen.</p>
                </div>

                <div class="review-grid">
                    <article class="review-card">
                        <span>Base de datos</span>
                        <strong data-review-db>—</strong>
                        <small data-review-db-user>—</small>
                    </article>
                    <article class="review-card">
                        <span>Administrador</span>
                        <strong data-review-admin>—</strong>
                        <small data-review-email>—</small>
                    </article>
                    <article class="review-card">
                        <span>URL del sitio</span>
                        <strong data-review-url>—</strong>
                        <small>Dirección base detectada para enlaces del CMS.</small>
                    </article>
                    <article class="review-card">
                        <span>Correo</span>
                        <strong data-review-mail>Configurar después</strong>
                        <small data-review-mail-detail>Podrás cambiarlo desde el panel.</small>
                    </article>
                    <article class="review-card">
                        <span>Contenido inicial</span>
                        <strong>Sabrosísimo Mix</strong>
                        <small>Servicios, galería, imágenes, contacto y contenido base.</small>
                    </article>
                </div>

                <div class="ready-box">
                    <div class="ready-icon">✓</div>
                    <div><strong>Todo listo para instalar</strong><p>Al confirmar se crearán las tablas, el usuario administrador, el contenido inicial y la configuración seleccionada.</p></div>
                </div>
            </section>

            <footer class="wizard-actions">
                <button type="button" class="button ghost" data-prev hidden>← Atrás</button>
                <div class="actions-spacer"></div>
                <button type="button" class="button primary" data-next>Siguiente →</button>
                <button type="submit" name="install_only" value="1" class="button primary install-button" data-install hidden>Instalar Sabrosísimo Mix</button>
            </footer>
        </form>
    </section>

    <aside class="wizard-assistant">
        <div class="assistant-inner">
            <div class="assistant-brand"><img src="../assets/images/branding/logo-negro-slogan.jpg" alt="Sabrosísimo Mix"></div>
            <span class="assistant-kicker">ASISTENTE</span>
            <h2 data-assistant-title>Primero conectaremos la base de datos.</h2>
            <p data-assistant-copy>Necesitamos los datos de MySQL para crear la estructura del CMS y guardar toda la información del sitio.</p>
            <div class="assistant-tip">
                <span>TIP</span>
                <p data-assistant-tip>Si tu hosting ya creó la base, usa exactamente el nombre, usuario y contraseña que te proporcionó.</p>
            </div>
            <div class="assistant-checklist">
                <div data-check="1" class="is-current"><b>1</b><span>Base de datos</span></div>
                <div data-check="2"><b>2</b><span>Administrador</span></div>
                <div data-check="3"><b>3</b><span>Correo</span></div>
                <div data-check="4"><b>4</b><span>Confirmación</span></div>
            </div>
        </div>
    </aside>
</main>

<script src="../assets/vendor/ui-feedback.js?v=<?= @filemtime($root . '/assets/vendor/ui-feedback.js') ?>"></script>
<script>
(() => {
    const form = document.getElementById('installForm');
    const panels = [...document.querySelectorAll('[data-step-panel]')];
    const navItems = [...document.querySelectorAll('[data-step-nav]')];
    const checklist = [...document.querySelectorAll('[data-check]')];
    const nextBtn = document.querySelector('[data-next]');
    const prevBtn = document.querySelector('[data-prev]');
    const installBtn = document.querySelector('[data-install]');
    const progressBar = document.querySelector('[data-progress-bar]');
    const stepLabel = document.querySelector('[data-step-label]');
    const stepPercent = document.querySelector('[data-step-percent]');
    const assistantTitle = document.querySelector('[data-assistant-title]');
    const assistantCopy = document.querySelector('[data-assistant-copy]');
    const assistantTip = document.querySelector('[data-assistant-tip]');
    const radios = [...document.querySelectorAll('[name=mail_method]')];
    const mailPanels = [...document.querySelectorAll('[data-method]')];
    const testWrap = document.querySelector('[data-test-wrap]');
    const testBtn = document.querySelector('[data-test-button]');
    const testResult = document.querySelector('[data-test-result]');
    let currentStep = 1;
    let maxVisited = 1;

    const assistantContent = {
        1: {
            title: 'Primero conectaremos la base de datos.',
            copy: 'Necesitamos los datos de MySQL para crear la estructura del CMS y guardar toda la información del sitio.',
            tip: 'Si tu hosting ya creó la base, usa exactamente el nombre, usuario y contraseña que te proporcionó.'
        },
        2: {
            title: 'Ahora crearemos el acceso principal.',
            copy: 'Esta cuenta tendrá permisos de administrador y será la puerta de entrada al panel de Sabrosísimo Mix.',
            tip: 'Utiliza una contraseña de al menos 8 caracteres y un correo al que tengas acceso.'
        },
        3: {
            title: 'Elige cómo enviar correos.',
            copy: 'Puedes trabajar con SMTP, Microsoft Graph o dejar esta parte pendiente para configurarla después desde el panel.',
            tip: 'Si no tienes las credenciales todavía, selecciona “Configurar después”. La instalación continuará normalmente.'
        },
        4: {
            title: 'Última revisión antes de instalar.',
            copy: 'Confirma los datos principales. El CMS cargará también los servicios, imágenes y contenido inicial de Sabrosísimo Mix.',
            tip: 'Después de instalar podrás modificar el contenido, enlaces sociales y configuración del correo desde Administración.'
        }
    };

    function getField(name) {
        return form.elements.namedItem(name);
    }

    function markInvalid(el) {
        if (!el) return;
        el.classList.add('is-invalid');
        el.addEventListener('input', () => el.classList.remove('is-invalid'), { once: true });
        el.focus({ preventScroll: true });
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function validateStep(step) {
        const panel = panels.find(p => Number(p.dataset.stepPanel) === step);
        if (!panel) return true;
        const required = [...panel.querySelectorAll('[required]')];
        for (const el of required) {
            if (!el.checkValidity()) {
                el.reportValidity();
                markInvalid(el);
                return false;
            }
        }

        if (step === 3) {
            const method = document.querySelector('[name=mail_method]:checked')?.value || 'none';
            const names = method === 'smtp'
                ? ['smtp_host','smtp_port','smtp_username','smtp_password','smtp_from_email']
                : method === 'graph'
                    ? ['graph_tenant_id','graph_client_id','graph_client_secret','graph_sender_email']
                    : [];
            for (const name of names) {
                const el = getField(name);
                if (!el || !String(el.value || '').trim() || (el.type === 'email' && !el.checkValidity())) {
                    if (el) el.setCustomValidity('Completa este campo para continuar.');
                    if (el) el.reportValidity();
                    if (el) setTimeout(() => el.setCustomValidity(''), 0);
                    markInvalid(el);
                    return false;
                }
            }
        }
        return true;
    }

    function databaseName() {
        const prefix = getField('db_prefix')?.value.trim() || '';
        const base = getField('db_base_name')?.value.trim() || '';
        return `${prefix}${base}`;
    }

    function syncDatabasePreview() {
        const prefix = getField('db_prefix')?.value.trim() || '';
        const base = getField('db_base_name')?.value.trim() || '—';
        const prefixNode = document.querySelector('[data-db-prefix-preview]');
        const nameNode = document.querySelector('[data-db-name-preview]');
        if (prefixNode) prefixNode.textContent = prefix;
        if (nameNode) nameNode.textContent = base;
    }

    function updateReview() {
        const host = getField('db_host')?.value.trim() || 'localhost';
        const port = getField('db_port')?.value.trim() || '3306';
        const db = databaseName() || '—';
        const dbUser = getField('db_user')?.value.trim() || '—';
        const adminName = getField('admin_name')?.value.trim() || '—';
        const adminEmail = getField('admin_email')?.value.trim() || '—';
        const siteUrl = getField('site_url')?.value.trim() || '<?= e($detectedSiteUrl) ?>';
        const method = document.querySelector('[name=mail_method]:checked')?.value || 'none';

        document.querySelector('[data-review-db]').textContent = `${db} · ${host}:${port}`;
        document.querySelector('[data-review-db-user]').textContent = `Usuario: ${dbUser}`;
        document.querySelector('[data-review-admin]').textContent = adminName;
        document.querySelector('[data-review-email]').textContent = adminEmail;
        document.querySelector('[data-review-url]').textContent = siteUrl;

        const mailName = method === 'smtp' ? 'SMTP' : method === 'graph' ? 'Microsoft Graph' : 'Configurar después';
        let detail = 'Podrás cambiarlo desde el panel.';
        if (method === 'smtp') detail = getField('smtp_from_email')?.value.trim() || 'Configuración SMTP lista para guardar.';
        if (method === 'graph') detail = getField('graph_sender_email')?.value.trim() || 'Configuración Graph lista para guardar.';
        document.querySelector('[data-review-mail]').textContent = mailName;
        document.querySelector('[data-review-mail-detail]').textContent = detail;
    }

    function syncMail() {
        const method = document.querySelector('[name=mail_method]:checked')?.value || 'none';
        mailPanels.forEach(panel => {
            const active = panel.dataset.method === method;
            panel.hidden = !active;
            panel.querySelectorAll('input,select').forEach(el => el.disabled = !active);
        });
        const enabled = method !== 'none';
        testWrap.hidden = !enabled;
        if (testBtn) testBtn.hidden = !enabled;
        if (testResult) testResult.hidden = true;
        document.querySelectorAll('.mail-option').forEach(label => {
            label.classList.toggle('is-selected', label.querySelector('input')?.checked === true);
        });
    }

    function showStep(step) {
        currentStep = Math.max(1, Math.min(4, step));
        maxVisited = Math.max(maxVisited, currentStep);

        panels.forEach(panel => {
            const active = Number(panel.dataset.stepPanel) === currentStep;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });

        navItems.forEach(item => {
            const n = Number(item.dataset.stepNav);
            item.disabled = n > maxVisited;
            item.classList.toggle('is-active', n === currentStep);
            item.classList.toggle('is-complete', n < currentStep);
            if (n === currentStep) item.setAttribute('aria-current', 'step');
            else item.removeAttribute('aria-current');
        });

        checklist.forEach(item => {
            const n = Number(item.dataset.check);
            item.classList.toggle('is-current', n === currentStep);
            item.classList.toggle('is-complete', n < currentStep);
        });

        const percent = currentStep * 25;
        progressBar.style.width = `${percent}%`;
        stepLabel.textContent = `Paso ${currentStep} de 4`;
        stepPercent.textContent = `${percent}%`;

        const copy = assistantContent[currentStep];
        assistantTitle.textContent = copy.title;
        assistantCopy.textContent = copy.copy;
        assistantTip.textContent = copy.tip;

        prevBtn.hidden = currentStep === 1;
        nextBtn.hidden = currentStep === 4;
        installBtn.hidden = currentStep !== 4;

        if (currentStep === 4) updateReview();

        const first = panels.find(p => Number(p.dataset.stepPanel) === currentStep)?.querySelector('input:not([type=radio]):not([type=hidden]),select');
        if (first && !window.matchMedia('(max-width: 700px)').matches) setTimeout(() => first.focus({ preventScroll: true }), 80);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    nextBtn.addEventListener('click', () => {
        if (!validateStep(currentStep)) return;
        showStep(currentStep + 1);
    });
    prevBtn.addEventListener('click', () => showStep(currentStep - 1));
    navItems.forEach(item => item.addEventListener('click', () => {
        const target = Number(item.dataset.stepNav);
        if (target <= maxVisited) showStep(target);
    }));

    radios.forEach(r => r.addEventListener('change', syncMail));
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.closest('.password-field')?.querySelector('input');
            if (!input) return;
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.textContent = reveal ? 'Ocultar' : 'Mostrar';
        });
    });

    testBtn?.addEventListener('click', async () => {
        if (!validateStep(3)) return;
        const email = getField('test_email');
        if (!email || !email.value.trim() || !email.checkValidity()) {
            if (email) email.reportValidity();
            markInvalid(email);
            return;
        }
        testBtn.disabled = true;
        testBtn.textContent = 'Probando…';
        testResult.hidden = true;
        try {
            const fd = new FormData(form);
            const res = await fetch('test-email.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
            const data = await res.json();
            if (window.showNotify) showNotify(data.message, data.success ? 'success' : 'error', { duration: 6000, title: data.success ? 'Correo verificado' : 'No se pudo enviar' });
        } catch (e) {
            if (window.showNotify) showNotify('No se pudo completar la prueba. Revisa los datos y vuelve a intentarlo.', 'error', { duration: 6500 });
        } finally {
            testBtn.disabled = false;
            testBtn.textContent = 'Probar configuración';
        }
    });

    form.addEventListener('submit', e => {
        for (let step = 1; step <= 3; step++) {
            if (!validateStep(step)) {
                e.preventDefault();
                showStep(step);
                return;
            }
        }
        installBtn.disabled = true;
        installBtn.textContent = 'Instalando…';
    });


    document.querySelectorAll('[data-install-notify]').forEach(el => {
        if (window.showNotify) showNotify(el.dataset.message || '', el.dataset.type || 'error', { duration: 7000, title: 'Instalación' });
    });

    ['db_prefix','db_base_name'].forEach(name => getField(name)?.addEventListener('input', syncDatabasePreview));
    syncDatabasePreview();
    syncMail();
    showStep(<?= $error ? '4' : '1' ?>);
})();
</script>
</body>
</html>
