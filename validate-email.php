<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/PublicFormGuard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'status' => 'method', 'message' => 'Método no permitido.']);
    exit;
}

try {
    PublicFormGuard::enforceValidationRateLimit();
    $email = (string) ($_POST['email'] ?? '');
    $result = PublicFormGuard::validateEmail($email, true, true);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'status' => 'rate_limit',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
