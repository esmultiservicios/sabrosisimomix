<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/core/PublicFormProtection.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'valid' => false,
        'status' => 'request',
        'message' => 'Invalid request.',
        'suggestion' => '',
    ]);
    exit;
}

if (!PublicFormProtection::consumeRateLimit('email_validation', 30, 600, 600)) {
    http_response_code(429);
    echo json_encode([
        'valid' => false,
        'status' => 'rate_limit',
        'message' => 'Please wait a moment before checking another email address.',
        'suggestion' => '',
    ]);
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$result = PublicFormProtection::validateEmail($email);

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
