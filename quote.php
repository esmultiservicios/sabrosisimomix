<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/EmailService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./');
    exit;
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$movedFiles = [];
$pdo = null;

try {
    verify_csrf();

    $name = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $service = trim((string) ($_POST['service_needed'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));

    if ($name === '' || $phone === '') {
        throw new RuntimeException('Completa tu nombre y teléfono.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Escribe un correo válido.');
    }

    // Honeypot anti-spam.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        header('Location: ./?sent=1#cotizar');
        exit;
    }

    $files = normalized_files('attachments');
    if (count($files) > 3) {
        throw new RuntimeException('Puedes adjuntar máximo 3 archivos.');
    }
    foreach ($files as $f) {
        if ((int) $f['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        if ((int) $f['size'] > 3 * 1024 * 1024) {
            throw new RuntimeException('Cada archivo debe ser menor de 3 MB.');
        }
        $mime = secure_file_mime_type($f['tmp_name']);
        if (!isset(['image/jpeg' => 1, 'image/png' => 1, 'image/webp' => 1][$mime])) {
            throw new RuntimeException('Adjuntos permitidos: JPG, PNG o WEBP.');
        }
    }

    $pdo = db();
    $pdo->beginTransaction();

    $st = $pdo->prepare("INSERT INTO estimate_requests(full_name,phone,email,address,service_needed,message,status,priority) VALUES(?,?,?,?,?,?,'new','normal')");
    $st->execute([$name, $phone, $email, $address, $service, $message]);
    $id = (int) $pdo->lastInsertId();

    $attachmentCount = 0;
    if ($files) {
        $dir = UPLOAD_DIR . '/estimates/' . $id;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo preparar la carpeta de adjuntos.');
        }
        $ins = $pdo->prepare('INSERT INTO estimate_attachments(estimate_id,file_path,original_name,mime_type,file_size) VALUES(?,?,?,?,?)');
        foreach ($files as $f) {
            if ((int) $f['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            $mime = secure_file_mime_type($f['tmp_name']);
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
            $physical = bin2hex(random_bytes(12)) . '.' . $ext;
            $relative = 'uploads/estimates/' . $id . '/' . $physical;
            $absolute = ROOT_DIR . '/' . $relative;
            if (!move_uploaded_file($f['tmp_name'], $absolute)) {
                throw new RuntimeException('No se pudo guardar un adjunto.');
            }
            $movedFiles[] = $absolute;
            $ins->execute([$id, $relative, basename((string) $f['name']), $mime, (int) $f['size']]);
            $attachmentCount++;
        }
    }

    $pdo->commit();
    log_activity('estimate_public', 'New estimate request', ['estimate_id' => $id]);

    // La solicitud ya está guardada. El correo es una notificación adicional y nunca bloquea al visitante.
    try {
        $emailService = new EmailService();
        $mailCfg = $emailService->getConfiguration();
        if (($mailCfg['method'] ?? 'none') !== 'none') {
            $recipientStmt = $pdo->query("SELECT u.email FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id WHERE u.active=1 AND u.email<>'' ORDER BY (r.role_name='Administrator') DESC,u.id ASC LIMIT 1");
            $recipient = trim((string) ($recipientStmt->fetchColumn() ?: ''));
            if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $estimate = [
                    'full_name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'service_needed' => $service,
                    'address' => $address,
                    'message' => $message,
                ];
                $subject = 'Nueva solicitud #' . $id . ' · ' . setting('site_name', 'Sabrosísimo Mix');
                $html = EmailTemplates::estimateNotification($estimate, $id, $attachmentCount);
                $result = $emailService->sendWithFallback(['estimate_notification'], $recipient, $subject, $html);
                log_activity(
                    $result['success'] ? 'estimate_email_sent' : 'estimate_email_failed',
                    $result['success'] ? 'Estimate notification email sent' : 'Estimate notification email failed',
                    ['estimate_id' => $id, 'recipient' => $recipient, 'message' => (string) ($result['message'] ?? '')]
                );
            }
        }
    } catch (Throwable $mailError) {
        log_activity('estimate_email_failed', 'Estimate notification email failed', ['estimate_id' => $id, 'message' => $mailError->getMessage()]);
    }

    header('Location: ./?sent=1#cotizar');
    exit;
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($movedFiles as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    $_SESSION['quote_error'] = $e->getMessage();
    $_SESSION['quote_old'] = $_POST;
    header('Location: ./?error=1#cotizar');
    exit;
}
