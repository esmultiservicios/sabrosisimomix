<?php
declare(strict_types=1);

require_once __DIR__ . '/EmailValidator.php';

final class PublicFormGuard
{
    public static function validateEmail(string $email, bool $checkDns = true, bool $checkExternal = true): array
    {
        $local = EmailValidator::validate($email, $checkDns);
        $result = [
            'ok' => (bool) ($local['valid'] ?? false),
            'email' => (string) ($local['email'] ?? ''),
            'status' => (string) ($local['reason'] ?? 'invalid'),
            'message' => 'Ingresa un correo electrónico válido. Ejemplo: nombre@empresa.com',
            'suggestion' => $local['suggestion'] ?? null,
            'domain' => (string) ($local['domain'] ?? ''),
            'dns_checked' => !empty($local['dns_checked']),
            'dns_mode' => $local['dns_mode'] ?? null,
            'external_checked' => false,
        ];

        if (!$result['ok']) {
            $result['message'] = match ($result['status']) {
                'domain_typo' => '¿Quisiste escribir ' . (string) $result['suggestion'] . '?',
                'disposable_domain' => 'Utiliza un correo electrónico permanente para continuar.',
                'domain_no_mail_dns' => 'El dominio de este correo no parece válido. Revisa la dirección e inténtalo nuevamente.',
                'obvious_fake' => 'Ingresa un correo electrónico real para continuar.',
                default => 'Ingresa un correo electrónico válido. Ejemplo: nombre@empresa.com',
            };
            return $result;
        }

        if ($checkExternal && self::externalValidationEnabled()) {
            $external = self::validateWithExternalService((string) $result['email']);
            $result['external_checked'] = (bool) ($external['checked'] ?? false);
            $result['external_provider'] = (string) ($external['external_provider'] ?? '');
            $result['external_latency_ms'] = isset($external['external_latency_ms']) ? (int) $external['external_latency_ms'] : null;
            if (($external['checked'] ?? false) && ($external['definitive_invalid'] ?? false)) {
                $result['ok'] = false;
                $result['status'] = 'mailbox';
                $result['message'] = 'Este correo no parece poder recibir mensajes. Revisa la dirección e inténtalo nuevamente.';
                return $result;
            }
        }

        $result['status'] = 'valid';
        $result['message'] = 'Correo válido';
        return $result;
    }

    public static function enforceSubmissionRateLimit(): void
    {
        self::enforceRateLimit('quote-submit', 5, 15 * 60);
        self::enforceSessionRateLimit('quote-submit-session', 4, 15 * 60);
    }

    public static function enforceValidationRateLimit(): void
    {
        self::enforceRateLimit('email-validation', 40, 60);
        self::enforceSessionRateLimit('email-validation-session', 30, 60);
    }

    public static function looksAutomated(string $name, string $phone, string $email, string $message): bool
    {
        $plain = strtolower(trim(rich_text_plain($message)));
        $combined = strtolower(trim($name . ' ' . $phone . ' ' . $email . ' ' . $plain));

        if (preg_match('/(.)\1{12,}/u', $combined)) {
            return true;
        }

        if (preg_match_all('~https?://|www\.~i', $plain, $links) > 3) {
            return true;
        }

        $spamSignals = [
            'buy followers', 'crypto investment', 'casino bonus', 'seo backlinks', 'guest post',
            'loan approval', 'viagra', 'porn', 'adult traffic', 'telegram me', 'whatsapp marketing',
        ];
        $hits = 0;
        foreach ($spamSignals as $signal) {
            if (str_contains($combined, $signal)) {
                $hits++;
            }
        }

        return $hits >= 2;
    }

    public static function normalizeEmail(string $email): string
    {
        return EmailValidator::normalize($email);
    }

    public static function suggestDomain(string $domain): ?string
    {
        return EmailValidator::suggestDomain($domain);
    }

    public static function isDisposableDomain(string $domain): bool
    {
        return EmailValidator::isDisposableDomain($domain);
    }

    public static function ensureSecurityTables(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        try {
            $pdo = db();
            $pdo->exec("CREATE TABLE IF NOT EXISTS public_rate_limits (
              bucket_key CHAR(64) PRIMARY KEY,
              bucket VARCHAR(80) NOT NULL,
              hits INT UNSIGNED NOT NULL DEFAULT 0,
              window_started_at DATETIME NOT NULL,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX idx_public_rate_limits_bucket(bucket),
              INDEX idx_public_rate_limits_window(window_started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS email_validation_events (
              id BIGINT AUTO_INCREMENT PRIMARY KEY,
              email_hash CHAR(64) NOT NULL,
              domain VARCHAR(253) NOT NULL DEFAULT '',
              result_status VARCHAR(40) NOT NULL,
              source VARCHAR(40) NOT NULL DEFAULT 'form',
              dns_checked TINYINT(1) NOT NULL DEFAULT 0,
              external_checked TINYINT(1) NOT NULL DEFAULT 0,
              external_provider VARCHAR(120) NULL,
              latency_ms INT UNSIGNED NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_email_validation_created(created_at),
              INDEX idx_email_validation_status(result_status),
              INDEX idx_email_validation_domain(domain),
              INDEX idx_email_validation_hash(email_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $ready = true;
        } catch (Throwable) {
            // Existing installations without CREATE permission still keep the file/session fallback.
        }
    }

    public static function recordValidationEvent(string $email, array $result, string $source = 'form'): void
    {
        try {
            self::ensureSecurityTables();
            $email = self::normalizeEmail($email);
            $domain = (string) ($result['domain'] ?? '');
            if ($domain === '' && str_contains($email, '@')) {
                $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));
            }
            $provider = trim((string) ($result['external_provider'] ?? setting('email_validation_api_name', '')));
            $latency = isset($result['external_latency_ms']) ? max(0, (int) $result['external_latency_ms']) : null;
            $st = db()->prepare('INSERT INTO email_validation_events(email_hash,domain,result_status,source,dns_checked,external_checked,external_provider,latency_ms) VALUES(?,?,?,?,?,?,?,?)');
            $st->execute([
                hash('sha256', $email),
                substr($domain, 0, 253),
                substr((string) ($result['status'] ?? 'unknown'), 0, 40),
                substr($source, 0, 40),
                !empty($result['dns_checked']) ? 1 : 0,
                !empty($result['external_checked']) ? 1 : 0,
                $provider !== '' ? substr($provider, 0, 120) : null,
                $latency,
            ]);
        } catch (Throwable) {
            // Logging is diagnostic only and never blocks a legitimate visitor.
        }
    }

    public static function clientIp(): string
    {
        // REMOTE_ADDR is authoritative unless the application is explicitly configured to trust a proxy.
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
    }

    private static function asciiDomain(string $domain): string
    {
        if (function_exists('idn_to_ascii')) {
            $flags = defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0;
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
            $converted = @idn_to_ascii($domain, $flags, $variant);
            if (is_string($converted) && $converted !== '') {
                return strtolower($converted);
            }
        }
        return $domain;
    }

    private static function enforceRateLimit(string $bucket, int $limit, int $windowSeconds): void
    {
        $key = hash('sha256', $bucket . '|' . self::clientIp());
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Tegucigalpa'));

        try {
            self::ensureSecurityTables();
            $pdo = db();
            $sql = "INSERT INTO public_rate_limits(bucket_key,bucket,hits,window_started_at)
                    VALUES(?,?,1,?)
                    ON DUPLICATE KEY UPDATE
                      hits = IF(TIMESTAMPDIFF(SECOND,window_started_at,?) >= ?,1,hits+1),
                      window_started_at = IF(TIMESTAMPDIFF(SECOND,window_started_at,?) >= ?,VALUES(window_started_at),window_started_at)";
            $stamp = $now->format('Y-m-d H:i:s');
            $st = $pdo->prepare($sql);
            $st->execute([$key, $bucket, $stamp, $stamp, $windowSeconds, $stamp, $windowSeconds]);

            $check = $pdo->prepare('SELECT hits FROM public_rate_limits WHERE bucket_key=?');
            $check->execute([$key]);
            $hits = (int) $check->fetchColumn();
            if ($hits > $limit) {
                throw new RuntimeException('Has realizado demasiados intentos. Espera unos minutos antes de volver a intentarlo.');
            }
            return;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            // Database rate limiting unavailable: continue with a local file fallback.
        }

        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sabrosisimomix-rate-limit';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        $nowTs = time();
        $data = ['start' => $nowTs, 'count' => 0];
        $handle = @fopen($path, 'c+');
        if (!$handle) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }
            $raw = stream_get_contents($handle);
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
            if (($nowTs - (int) $data['start']) >= $windowSeconds) {
                $data = ['start' => $nowTs, 'count' => 0];
            }
            $data['count'] = (int) $data['count'] + 1;
            if ($data['count'] > $limit) {
                throw new RuntimeException('Has realizado demasiados intentos. Espera unos minutos antes de volver a intentarlo.');
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_UNESCAPED_SLASHES));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function enforceSessionRateLimit(string $bucket, int $limit, int $windowSeconds): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $now = time();
        $entry = $_SESSION['rate_limits'][$bucket] ?? ['start' => $now, 'count' => 0];
        if (($now - (int) ($entry['start'] ?? $now)) >= $windowSeconds) {
            $entry = ['start' => $now, 'count' => 0];
        }
        $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
        $_SESSION['rate_limits'][$bucket] = $entry;
        if ($entry['count'] > $limit) {
            throw new RuntimeException('Has realizado demasiados intentos. Espera unos minutos antes de volver a intentarlo.');
        }
    }

    private static function externalValidationEnabled(): bool
    {
        return setting('email_validation_api_enabled', '0') === '1'
            && trim((string) setting('email_validation_api_url', '')) !== '';
    }

    private static function validateWithExternalService(string $email): array
    {
        $urlTemplate = trim((string) setting('email_validation_api_url', ''));
        $apiKey = trim((string) setting('email_validation_api_key', ''));
        $provider = trim((string) setting('email_validation_api_name', ''));
        $method = strtoupper(trim((string) setting('email_validation_api_method', 'GET')));
        $authMode = strtolower(trim((string) setting('email_validation_api_auth', 'bearer')));
        $keyName = trim((string) setting('email_validation_api_key_name', 'api_key'));
        $emailField = trim((string) setting('email_validation_api_email_field', 'email'));
        $timeout = max(2, min(8, (int) setting('email_validation_api_timeout', '4')));

        if ($urlTemplate === '') {
            return ['checked' => false, 'external_provider' => $provider];
        }
        if (!in_array($method, ['GET', 'POST'], true)) {
            $method = 'GET';
        }
        if (!in_array($authMode, ['none', 'bearer', 'x-api-key', 'query'], true)) {
            $authMode = 'bearer';
        }
        if ($keyName === '') {
            $keyName = 'api_key';
        }
        if ($emailField === '') {
            $emailField = 'email';
        }

        $started = microtime(true);
        try {
            $url = str_replace('{email}', rawurlencode($email), $urlTemplate);
            if ($apiKey !== '') {
                $url = str_replace('{key}', rawurlencode($apiKey), $url);
            }

            $params = [];
            if (!str_contains($urlTemplate, '{email}')) {
                $params[$emailField] = $email;
            }
            if ($apiKey !== '' && $authMode === 'query' && !str_contains($urlTemplate, '{key}')) {
                $params[$keyName] = $apiKey;
            }

            if ($method === 'GET' && $params) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            }

            $headers = ['Accept: application/json'];
            if ($apiKey !== '' && !str_contains($urlTemplate, '{key}')) {
                if ($authMode === 'bearer') {
                    $headers[] = 'Authorization: Bearer ' . $apiKey;
                } elseif ($authMode === 'x-api-key') {
                    $headers[] = 'X-API-Key: ' . $apiKey;
                }
            }

            $responseBody = '';
            $statusCode = 0;
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                $options = [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => $timeout,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_HTTPHEADER => $headers,
                ];
                if ($method === 'POST') {
                    $postData = $params;
                    if (!isset($postData[$emailField])) {
                        $postData[$emailField] = $email;
                    }
                    $options[CURLOPT_POST] = true;
                    $options[CURLOPT_POSTFIELDS] = http_build_query($postData);
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                    $options[CURLOPT_HTTPHEADER] = $headers;
                }
                curl_setopt_array($ch, $options);
                $response = curl_exec($ch);
                $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if (!is_string($response) || $response === '' || $statusCode < 200 || $statusCode >= 300) {
                    return [
                        'checked' => false,
                        'external_provider' => $provider,
                        'external_latency_ms' => (int) round((microtime(true) - $started) * 1000),
                    ];
                }
                $responseBody = $response;
            } else {
                $headerText = implode("\r\n", $headers) . "\r\n";
                $http = [
                    'method' => $method,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'header' => $headerText,
                ];
                if ($method === 'POST') {
                    $postData = $params;
                    if (!isset($postData[$emailField])) {
                        $postData[$emailField] = $email;
                    }
                    $http['content'] = http_build_query($postData);
                    $http['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
                }
                $context = stream_context_create(['http' => $http]);
                $response = @file_get_contents($url, false, $context);
                if (!is_string($response) || $response === '') {
                    return [
                        'checked' => false,
                        'external_provider' => $provider,
                        'external_latency_ms' => (int) round((microtime(true) - $started) * 1000),
                    ];
                }
                $responseBody = $response;
            }

            $json = json_decode($responseBody, true);
            if (!is_array($json)) {
                return [
                    'checked' => false,
                    'external_provider' => $provider,
                    'external_latency_ms' => (int) round((microtime(true) - $started) * 1000),
                ];
            }

            $flat = self::flattenArray($json);
            $status = strtolower((string) ($flat['status'] ?? $flat['result'] ?? $flat['deliverability'] ?? ''));
            $valid = $flat['is_valid'] ?? $flat['valid'] ?? $flat['deliverable'] ?? null;
            $invalidStatuses = ['invalid','undeliverable','do_not_send','failed','reject','rejected','not_deliverable'];
            $validStatuses = ['valid','deliverable','ok','safe','verified'];

            $definitiveInvalid = false;
            if (is_bool($valid)) {
                $definitiveInvalid = !$valid;
            } elseif (is_numeric($valid)) {
                $definitiveInvalid = ((int) $valid) === 0;
            } elseif (is_string($valid) && $valid !== '') {
                $normalized = strtolower($valid);
                if (in_array($normalized, ['false','0','no','invalid','undeliverable'], true)) {
                    $definitiveInvalid = true;
                }
            }
            if ($status !== '') {
                if (in_array($status, $invalidStatuses, true)) {
                    $definitiveInvalid = true;
                }
                if (in_array($status, $validStatuses, true)) {
                    $definitiveInvalid = false;
                }
            }

            return [
                'checked' => true,
                'definitive_invalid' => $definitiveInvalid,
                'external_provider' => $provider,
                'external_latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        } catch (Throwable) {
            return [
                'checked' => false,
                'external_provider' => $provider,
                'external_latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
    }

    private static function flattenArray(array $input): array
    {
        $flat = [];
        $walk = static function (array $items) use (&$flat, &$walk): void {
            foreach ($items as $key => $value) {
                if (is_array($value)) {
                    $walk($value);
                } else {
                    $flat[(string) $key] = $value;
                }
            }
        };
        $walk($input);
        return $flat;
    }
}
