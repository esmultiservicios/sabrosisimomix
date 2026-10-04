<?php
declare(strict_types=1);

final class EmailValidator
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

    private const OBVIOUS_FAKE_EMAILS = [
        'alguien@algo.com',
        'prueba@prueba.com',
        'test@test.com',
        'usuario@example.com',
        'correo@correo.com',
    ];

    private const RESERVED_EXAMPLE_DOMAINS = [
        'example.com',
        'example.net',
        'example.org',
        'test.com',
        'invalid',
        'localhost',
    ];

    public static function validate(string $email, bool $checkDns = true): array
    {
        $email = self::normalize($email);
        $result = [
            'valid' => false,
            'email' => $email,
            'reason' => 'invalid_format',
            'message' => 'El correo electrónico no tiene un formato válido.',
            'suggestion' => null,
            'domain' => '',
            'dns_checked' => false,
            'dns_mode' => null,
        ];

        if ($email === '' || strlen($email) > 254 || preg_match('/\s/u', $email)) {
            return $result;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $email)) {
            $result['reason'] = 'invalid_characters';
            $result['message'] = 'El correo electrónico contiene caracteres no permitidos.';
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
            $result['reason'] = 'domain_typo';
            $result['suggestion'] = $local . '@' . $suggestedDomain;
            $result['message'] = 'El dominio parece contener un error de escritura.';
            return $result;
        }

        if (self::isObviouslyFake($email, $local, $domain)) {
            $result['reason'] = 'obvious_fake';
            $result['message'] = 'El correo electrónico parece ser ficticio o de ejemplo.';
            return $result;
        }

        if (self::isDisposableDomain($domain)) {
            $result['reason'] = 'disposable_domain';
            $result['message'] = 'El dominio pertenece a un proveedor de correo temporal o desechable.';
            return $result;
        }

        if ($checkDns) {
            $dns = self::checkMailDns($domain);
            $result['dns_checked'] = $dns['checked'];
            $result['dns_mode'] = $dns['mode'];
            if (!$dns['valid']) {
                $result['reason'] = 'domain_no_mail_dns';
                $result['message'] = 'El dominio no tiene registros DNS válidos para recibir correo.';
                return $result;
            }
        }

        $result['valid'] = true;
        $result['reason'] = 'valid';
        $result['message'] = 'Correo válido.';
        return $result;
    }

    public static function normalize(string $email): string
    {
        $email = trim($email);
        $email = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{FEFF}]/u', '', $email) ?? $email;

        if (!str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        return $local . '@' . strtolower($domain);
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

    private static function isObviouslyFake(string $email, string $local, string $domain): bool
    {
        $normalized = strtolower($email);
        if (in_array($normalized, self::OBVIOUS_FAKE_EMAILS, true)) {
            return true;
        }

        if (in_array(strtolower($domain), self::RESERVED_EXAMPLE_DOMAINS, true)) {
            return true;
        }

        $localLower = strtolower($local);
        $domainLabel = strtolower((string) strtok($domain, '.'));
        if (in_array($localLower, ['test','prueba','correo','email','usuario','user','fake'], true)
            && $localLower === $domainLabel) {
            return true;
        }

        return false;
    }

    private static function checkMailDns(string $domain): array
    {
        $hasDnsTool = function_exists('checkdnsrr') || function_exists('dns_get_record');
        if (!$hasDnsTool) {
            return ['checked' => false, 'valid' => true, 'mode' => 'unavailable'];
        }

        try {
            if (function_exists('checkdnsrr')) {
                if (@checkdnsrr($domain, 'MX')) {
                    return ['checked' => true, 'valid' => true, 'mode' => 'mx'];
                }
                if (@checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA')) {
                    return ['checked' => true, 'valid' => true, 'mode' => 'address_fallback'];
                }
                return ['checked' => true, 'valid' => false, 'mode' => 'none'];
            }

            $mx = @dns_get_record($domain, DNS_MX);
            if (is_array($mx) && count($mx) > 0) {
                return ['checked' => true, 'valid' => true, 'mode' => 'mx'];
            }

            $addresses = @dns_get_record($domain, DNS_A | DNS_AAAA);
            if (is_array($addresses) && count($addresses) > 0) {
                return ['checked' => true, 'valid' => true, 'mode' => 'address_fallback'];
            }

            return ['checked' => true, 'valid' => false, 'mode' => 'none'];
        } catch (Throwable) {
            // DNS resolution itself failed. Fail open so a temporary resolver issue does not reject a valid address.
            return ['checked' => false, 'valid' => true, 'mode' => 'resolver_error'];
        }
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
}
