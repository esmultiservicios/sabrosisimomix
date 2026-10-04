<?php
declare(strict_types=1);

final class PublicFormGuard
{
    private const TYPO_DOMAINS = [
        'gmail.con' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmail.cm' => 'gmail.com',
        'gmial.com' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'gamil.com' => 'gmail.com',
        'hotmal.com' => 'hotmail.com',
        'hotmai.com' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',
        'outlook.con' => 'outlook.com',
        'outlok.com' => 'outlook.com',
        'outllok.com' => 'outlook.com',
        'yahoo.con' => 'yahoo.com',
        'yaho.com' => 'yahoo.com',
        'icloud.con' => 'icloud.com',
        'iclod.com' => 'icloud.com',
        'protonmail.con' => 'protonmail.com',
    ];

    private const DISPOSABLE_DOMAINS = [
        '10minutemail.com','10minutemail.net','20minutemail.com','33mail.com','anonbox.net',
        'bccto.me','burnermail.io','deadaddress.com','discard.email','discardmail.com','dispostable.com',
        'dropmail.me','emailondeck.com','fakeinbox.com','fakemail.net','getnada.com','guerrillamail.com',
        'guerrillamail.net','guerrillamail.org','guerrillamailblock.com','inboxbear.com','maildrop.cc',
        'mailinator.com','mailinator.net','mailnesia.com','mintemail.com','moakt.com','mohmal.com',
        'mytemp.email','nada.email','sharklasers.com','spam4.me','spamgourmet.com','temp-mail.org',
        'tempail.com','tempemail.net','tempinbox.com','tempmail.com','tempmail.net','tempmailo.com',
        'throwawaymail.com','trashmail.com','trashmail.net','yopmail.com','yopmail.fr','yopmail.net',
    ];

    public static function validateEmail(string $email, bool $checkDns = true, bool $checkExternal = true): array
    {
        $email = self::normalizeEmail($email);
        $result = [
            'ok' => false,
            'email' => $email,
            'status' => 'invalid',
            'message' => 'Ingresa un correo electrónico válido. Ejemplo: nombre@empresa.com',
            'suggestion' => null,
            'domain' => '',
            'dns_checked' => false,
            'external_checked' => false,
        ];

        if ($email === '' || strlen($email) > 254 || preg_match('/\s/u', $email)) {
            return $result;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $result;
        }

        [$local, $domain] = explode('@', $email, 2);
        $domain = self::asciiDomain(strtolower(rtrim($domain, '.')));
        $result['domain'] = $domain;

        if ($local === '' || $domain === '' || !str_contains($domain, '.') || strlen($domain) > 253) {
            return $result;
        }

        if (!preg_match('/^[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?$/i', $domain) || str_contains($domain, '..')) {
            return $result;
        }

        $suggestedDomain = self::suggestDomain($domain);
        if ($suggestedDomain !== null && $suggestedDomain !== $domain) {
            $suggestion = $local . '@' . $suggestedDomain;
            $result['status'] = 'suggestion';
            $result['suggestion'] = $suggestion;
            $result['message'] = '¿Quisiste escribir ' . $suggestion . '?';
            return $result;
        }

        if (self::isDisposableDomain($domain)) {
            $result['status'] = 'disposable';
            $result['message'] = 'Utiliza un correo electrónico permanente para continuar.';
            return $result;
        }

        if ($checkDns) {
            $result['dns_checked'] = true;
            if (!self::domainExists($domain) || !self::domainHasMx($domain)) {
                $result['status'] = 'domain';
                $result['message'] = 'El dominio de este correo no parece válido. Revisa la dirección e inténtalo nuevamente.';
                return $result;
            }
        }

        if ($checkExternal && self::externalValidationEnabled()) {
            $external = self::validateWithExternalService($email);
            $result['external_checked'] = (bool) ($external['checked'] ?? false);
            if (($external['checked'] ?? false) && ($external['definitive_invalid'] ?? false)) {
                $result['status'] = 'mailbox';
                $result['message'] = 'Este correo no parece poder recibir mensajes. Revisa la dirección e inténtalo nuevamente.';
                return $result;
            }
        }

        $result['ok'] = true;
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
        $email = trim($email);
        $email = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{FEFF}]/u', '', $email) ?? $email;
        return strtolower($email);
    }

    public static function suggestDomain(string $domain): ?string
    {
        $domain = strtolower($domain);
        if (isset(self::TYPO_DOMAINS[$domain])) {
            return self::TYPO_DOMAINS[$domain];
        }

        $known = ['gmail.com','hotmail.com','outlook.com','yahoo.com','icloud.com','protonmail.com'];
        foreach ($known as $candidate) {
            if (abs(strlen($candidate) - strlen($domain)) > 2) {
                continue;
            }
            $distance = levenshtein($domain, $candidate);
            if ($distance > 0 && $distance <= 2) {
                return $candidate;
            }
        }
        return null;
    }

    public static function isDisposableDomain(string $domain): bool
    {
        $domain = strtolower($domain);
        if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            return true;
        }

        foreach (['mailinator.','yopmail.','guerrillamail.','10minutemail.','tempmail.','temp-mail.','throwawaymail.','trashmail.'] as $fragment) {
            if (str_contains($domain, $fragment)) {
                return true;
            }
        }
        return false;
    }

    public static function domainExists(string $domain): bool
    {
        if (function_exists('checkdnsrr')) {
            try {
                if (@checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA') || @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'NS')) {
                    return true;
                }
                return false;
            } catch (Throwable) {
                return true;
            }
        }

        if (function_exists('dns_get_record')) {
            try {
                $records = @dns_get_record($domain, DNS_A | DNS_AAAA | DNS_MX | DNS_NS);
                return is_array($records) && count($records) > 0;
            } catch (Throwable) {
                return true;
            }
        }

        return true;
    }

    public static function domainHasMx(string $domain): bool
    {
        if (function_exists('checkdnsrr')) {
            try {
                if (@checkdnsrr($domain, 'MX')) {
                    return true;
                }
                return false;
            } catch (Throwable) {
                return true;
            }
        }

        if (function_exists('dns_get_record')) {
            try {
                $records = @dns_get_record($domain, DNS_MX);
                return is_array($records) && count($records) > 0;
            } catch (Throwable) {
                return true;
            }
        }

        // DNS tooling is unavailable on the server. Fail open rather than rejecting a legitimate lead.
        return true;
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
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sabrosisimomix-rate-limit';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        $now = time();
        $data = ['start' => $now, 'count' => 0];
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
            if (($now - (int) $data['start']) >= $windowSeconds) {
                $data = ['start' => $now, 'count' => 0];
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
        $timeout = max(2, min(8, (int) setting('email_validation_api_timeout', '4')));
        if ($urlTemplate === '') {
            return ['checked' => false];
        }

        $url = str_replace('{email}', rawurlencode($email), $urlTemplate);
        if (!str_contains($urlTemplate, '{email}')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'email=' . rawurlencode($email);
        }
        if ($apiKey !== '') {
            $url = str_replace('{key}', rawurlencode($apiKey), $url);
        }

        $body = '';
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                $headers = ['Accept: application/json'];
                if ($apiKey !== '' && !str_contains($urlTemplate, '{key}')) {
                    $headers[] = 'Authorization: Bearer ' . $apiKey;
                    $headers[] = 'X-API-Key: ' . $apiKey;
                }
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => $timeout,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_HTTPHEADER => $headers,
                ]);
                $response = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if (!is_string($response) || $response === '' || $status < 200 || $status >= 300) {
                    return ['checked' => false];
                }
                $body = $response;
            } else {
                $headers = "Accept: application/json\r\n";
                if ($apiKey !== '' && !str_contains($urlTemplate, '{key}')) {
                    $headers .= 'Authorization: Bearer ' . $apiKey . "\r\n";
                    $headers .= 'X-API-Key: ' . $apiKey . "\r\n";
                }
                $context = stream_context_create(['http' => [
                    'method' => 'GET',
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'header' => $headers,
                ]]);
                $response = @file_get_contents($url, false, $context);
                if (!is_string($response) || $response === '') {
                    return ['checked' => false];
                }
                $body = $response;
            }

            $json = json_decode($body, true);
            if (!is_array($json)) {
                return ['checked' => false];
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

            return ['checked' => true, 'definitive_invalid' => $definitiveInvalid];
        } catch (Throwable) {
            // Fail open: an unavailable third-party validator must not block a real customer.
            return ['checked' => false];
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
