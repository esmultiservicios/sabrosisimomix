<?php
declare(strict_types=1);

final class PublicFormProtection
{
    private const COMMON_DOMAIN_CORRECTIONS = [
        'gmail.con' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmail.cmo' => 'gmail.com',
        'gmial.com' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gamil.com' => 'gmail.com',
        'hotmal.com' => 'hotmail.com',
        'hotmai.com' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',
        'outlook.con' => 'outlook.com',
        'outlok.com' => 'outlook.com',
        'outloo.com' => 'outlook.com',
        'yahoo.con' => 'yahoo.com',
        'yaho.com' => 'yahoo.com',
        'icloud.con' => 'icloud.com',
    ];

    public static function validateEmail(string $email, bool $checkExternal = true): array
    {
        $email = trim($email);
        $formatMessage = 'Enter a valid email address. Example: name@company.com';

        if (
            $email === ''
            || strlen($email) > 254
            || preg_match('/[\s\x00-\x1F\x7F]/', $email)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            return self::emailResult(false, 'format', $formatMessage);
        }

        [$localPart, $domain] = explode('@', $email, 2);
        $domain = strtolower(rtrim($domain, '.'));

        if ($localPart === '' || $domain === '' || !str_contains($domain, '.')) {
            return self::emailResult(false, 'format', $formatMessage);
        }

        $suggestedDomain = self::COMMON_DOMAIN_CORRECTIONS[$domain] ?? '';
        if ($suggestedDomain !== '') {
            $suggestion = $localPart . '@' . $suggestedDomain;
            return self::emailResult(
                false,
                'suggestion',
                'Did you mean ' . $suggestion . '?',
                $suggestion
            );
        }

        if (self::isDisposableDomain($domain)) {
            return self::emailResult(
                false,
                'temporary',
                'Use a permanent email address to continue.'
            );
        }

        $dnsResult = self::domainAcceptsEmail($domain);
        if ($dnsResult === false) {
            return self::emailResult(
                false,
                'domain',
                'The domain for this email does not appear to be valid. Review the address and try again.'
            );
        }

        if ($checkExternal) {
            $externalResult = self::externalEmailVerification($email);
            if (is_array($externalResult) && ($externalResult['valid'] ?? true) === false) {
                return $externalResult;
            }
        }

        return self::emailResult(true, 'valid', 'Valid email');
    }

    public static function consumeRateLimit(
        string $action,
        int $limit,
        int $windowSeconds,
        int $blockSeconds
    ): bool {
        try {
            self::startPublicSession();

            $identities = [
                'ip:' . self::clientIp(),
                'session:' . session_id(),
            ];

            foreach ($identities as $identity) {
                if (!self::consumeRateLimitIdentity(
                    $action,
                    hash_hmac('sha256', $identity, app_key()),
                    $limit,
                    $windowSeconds,
                    $blockSeconds
                )) {
                    return false;
                }
            }

            if (random_int(1, 100) === 1) {
                db()->exec(
                    'DELETE FROM public_form_rate_limits '
                    . 'WHERE updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
                );
            }
        } catch (Throwable $error) {
            // Rate limiting must never make the form unavailable because of a
            // transient database or session-storage problem.
            return true;
        }

        return true;
    }

    public static function isHoneypotFilled(array $input): bool
    {
        return trim((string) ($input['website_url'] ?? '')) !== '';
    }

    public static function assertHumanContent(array $input): void
    {
        $limits = [
            'name' => 150,
            'phone' => 80,
            'email' => 180,
            'address' => 255,
            'service' => 150,
            'date' => 20,
            'message' => 5000,
        ];

        foreach ($limits as $field => $maximumLength) {
            $value = trim((string) ($input[$field] ?? ''));
            if (strlen($value) > $maximumLength) {
                throw new RuntimeException('One or more fields contain too much information. Please review the form.');
            }
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
                throw new RuntimeException('The form contains invalid characters. Please review the information.');
            }
        }

        $name = trim((string) ($input['name'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));
        $combined = implode(' ', [
            $name,
            (string) ($input['address'] ?? ''),
            $message,
        ]);

        $spamScore = 0;
        $urlCount = preg_match_all('~(?:https?://|www\.)~i', $combined);
        if ($urlCount !== false && $urlCount > 2) {
            $spamScore += 3;
        }
        if (preg_match('~(?:https?://|www\.)~i', $name)) {
            $spamScore += 3;
        }
        if (preg_match('/(.)\1{14,}/u', $combined)) {
            $spamScore += 2;
        }
        if (preg_match('/\[(?:url|link)=|<a\s+href=/i', $combined)) {
            $spamScore += 3;
        }
        if (preg_match('/\b(?:crypto\s+investment|casino\s+bonus|buy\s+followers|seo\s+backlinks|viagra|loan\s+approval)\b/i', $combined)) {
            $spamScore += 3;
        }

        if ($spamScore >= 3) {
            throw new RuntimeException('We could not accept this message. Please remove links or automated content and try again.');
        }
    }

    public static function turnstileSiteKey(): string
    {
        $config = self::activeIntegration('turnstile');
        return trim((string) ($config['public_key'] ?? ''));
    }

    public static function verifyTurnstile(string $token): bool
    {
        $config = self::activeIntegration('turnstile');
        if (!$config) {
            return true;
        }

        $secret = secret_decrypt((string) ($config['secret_key'] ?? ''));
        if ($secret === '' || $token === '') {
            return false;
        }

        if (!function_exists('curl_init')) {
            return true;
        }

        $handle = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => self::clientIp(),
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);
        $response = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($handle);
        curl_close($handle);

        if ($response === false || $curlError !== 0 || $statusCode >= 500) {
            return true;
        }

        $payload = json_decode((string) $response, true);
        return is_array($payload) && !empty($payload['success']);
    }

    private static function emailResult(
        bool $valid,
        string $status,
        string $message,
        string $suggestion = ''
    ): array {
        return [
            'valid' => $valid,
            'status' => $status,
            'message' => $message,
            'suggestion' => $suggestion,
        ];
    }

    private static function isDisposableDomain(string $domain): bool
    {
        static $domains = null;

        if ($domains === null) {
            $configuredDomains = require ROOT_DIR . '/config/disposable-email-domains.php';
            $domains = array_fill_keys(array_map('strtolower', $configuredDomains), true);
        }

        if (isset($domains[$domain])) {
            return true;
        }

        foreach ($domains as $disposableDomain => $_unused) {
            if (str_ends_with($domain, '.' . $disposableDomain)) {
                return true;
            }
        }

        return false;
    }

    private static function domainAcceptsEmail(string $domain): ?bool
    {
        if (!function_exists('dns_get_record')) {
            return null;
        }

        set_error_handler(static fn (): bool => true);
        try {
            $records = dns_get_record($domain, DNS_MX);
        } finally {
            restore_error_handler();
        }

        if ($records === false) {
            return null;
        }

        return count($records) > 0;
    }

    private static function externalEmailVerification(string $email): ?array
    {
        $config = self::activeIntegration('email_validation');
        if (!$config || !function_exists('curl_init')) {
            return null;
        }

        $baseUrl = trim((string) ($config['base_url'] ?? ''));
        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $secret = secret_decrypt((string) ($config['secret_key'] ?? ''));
        $publicKey = trim((string) ($config['public_key'] ?? ''));
        $provider = strtolower((string) ($config['provider_name'] ?? ''));
        $headers = ['Accept: application/json'];

        if (str_contains($baseUrl, '{email}')) {
            $url = str_replace('{email}', rawurlencode($email), $baseUrl);
        } else {
            $separator = str_contains($baseUrl, '?') ? '&' : '?';
            $url = $baseUrl . $separator . 'email=' . rawurlencode($email);
        }

        if (str_contains($provider, 'abstract')) {
            $key = $secret !== '' ? $secret : $publicKey;
            if ($key !== '') {
                $url .= '&api_key=' . rawurlencode($key);
            }
        } elseif ($secret !== '') {
            $headers[] = 'Authorization: Bearer ' . $secret;
        } elseif ($publicKey !== '') {
            $headers[] = 'X-API-Key: ' . $publicKey;
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);
        $response = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($handle);
        curl_close($handle);

        if ($response === false || $curlError !== 0 || $statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        $payload = json_decode((string) $response, true);
        if (!is_array($payload)) {
            return null;
        }

        $isDisposable = self::nestedBoolean($payload, [
            'is_disposable_email.value',
            'is_disposable.value',
            'disposable',
            'is_disposable',
        ]);
        if ($isDisposable === true) {
            return self::emailResult(false, 'temporary', 'Use a permanent email address to continue.');
        }

        $deliverable = self::nestedBoolean($payload, [
            'is_smtp_valid.value',
            'smtp_check',
            'deliverable',
            'is_deliverable',
            'valid',
            'is_valid',
        ]);
        $deliverability = strtolower((string) ($payload['deliverability'] ?? $payload['status'] ?? ''));

        if ($deliverable === false || in_array($deliverability, ['undeliverable', 'invalid', 'rejected'], true)) {
            return self::emailResult(
                false,
                'mailbox',
                'This email address does not appear to be deliverable. Review it and try again.'
            );
        }

        return null;
    }

    private static function nestedBoolean(array $payload, array $paths): ?bool
    {
        foreach ($paths as $path) {
            $value = $payload;
            foreach (explode('.', $path) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    continue 2;
                }
                $value = $value[$segment];
            }

            if (is_bool($value)) {
                return $value;
            }
            if ($value === 1 || $value === '1' || strtolower((string) $value) === 'true') {
                return true;
            }
            if ($value === 0 || $value === '0' || strtolower((string) $value) === 'false') {
                return false;
            }
        }

        return null;
    }

    private static function activeIntegration(string $type): ?array
    {
        try {
            $statement = db()->prepare(
                'SELECT * FROM api_integrations WHERE api_type = ? AND active = 1 ORDER BY id DESC LIMIT 1'
            );
            $statement->execute([$type]);
            $config = $statement->fetch();
            return is_array($config) ? $config : null;
        } catch (Throwable $error) {
            return null;
        }
    }

    private static function startPublicSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        session_name('castros_ready_public');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    private static function clientIp(): string
    {
        $cloudflareIp = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if (filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
            return $cloudflareIp;
        }

        $remoteIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($remoteIp, FILTER_VALIDATE_IP) ? $remoteIp : 'unknown';
    }

    private static function consumeRateLimitIdentity(
        string $action,
        string $identityHash,
        int $limit,
        int $windowSeconds,
        int $blockSeconds
    ): bool {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $insert = $pdo->prepare(
                'INSERT IGNORE INTO public_form_rate_limits '
                . '(action_key, identity_hash, window_started_at, attempts, blocked_until) '
                . 'VALUES (?, ?, NOW(), 0, NULL)'
            );
            $insert->execute([$action, $identityHash]);

            $select = $pdo->prepare(
                'SELECT window_started_at, attempts, blocked_until '
                . 'FROM public_form_rate_limits '
                . 'WHERE action_key = ? AND identity_hash = ? FOR UPDATE'
            );
            $select->execute([$action, $identityHash]);
            $row = $select->fetch();

            $now = time();
            $windowStartedAt = strtotime((string) ($row['window_started_at'] ?? '')) ?: $now;
            $blockedUntil = strtotime((string) ($row['blocked_until'] ?? '')) ?: 0;

            if ($blockedUntil > $now) {
                $pdo->commit();
                return false;
            }

            $attempts = (int) ($row['attempts'] ?? 0);
            if (($now - $windowStartedAt) >= $windowSeconds) {
                $attempts = 0;
                $windowStartedAt = $now;
            }

            $attempts++;
            $newBlockedUntil = null;
            if ($attempts > $limit) {
                $newBlockedUntil = date('Y-m-d H:i:s', $now + $blockSeconds);
            }

            $update = $pdo->prepare(
                'UPDATE public_form_rate_limits '
                . 'SET window_started_at = ?, attempts = ?, blocked_until = ?, updated_at = NOW() '
                . 'WHERE action_key = ? AND identity_hash = ?'
            );
            $update->execute([
                date('Y-m-d H:i:s', $windowStartedAt),
                $attempts,
                $newBlockedUntil,
                $action,
                $identityHash,
            ]);
            $pdo->commit();

            return $newBlockedUntil === null;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}
