<?php
declare(strict_types=1);

final class AuthSessionManager
{
    public const IDLE_TIMEOUT_SECONDS = 3600;
    public const ABSOLUTE_TIMEOUT_SECONDS = 43200;
    public const SESSION_COOKIE_NAME = 'SMXSESSID';
    public const REMEMBER_LOGIN_COOKIE = 'cms_remember_login';

    private static bool $tableReady = false;
    private static ?string $lastExpiryReason = null;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = self::isHttps();

        if (function_exists('ini_set')) {
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_samesite', 'Lax');
            @ini_set('session.cookie_secure', $secure ? '1' : '0');
        }

        session_name(self::SESSION_COOKIE_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function establish(int $adminId): void
    {
        self::start();
        self::ensureTable();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $now = time();
        $_SESSION['admin_id'] = $adminId;
        $_SESSION['auth_issued_at'] = $now;
        $_SESSION['auth_last_activity_at'] = $now;
        $_SESSION['auth_absolute_expires_at'] = $now + self::ABSOLUTE_TIMEOUT_SECONDS;

        $sessionHash = self::sessionHash();
        $userAgentHash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

        $sql = 'INSERT INTO admin_sessions '
            . '(admin_id, session_hash, issued_at, last_activity_at, absolute_expires_at, user_agent_hash, revoked_at, revoke_reason) '
            . 'VALUES (?, ?, ?, ?, ?, ?, NULL, NULL) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'admin_id=VALUES(admin_id), issued_at=VALUES(issued_at), last_activity_at=VALUES(last_activity_at), '
            . 'absolute_expires_at=VALUES(absolute_expires_at), user_agent_hash=VALUES(user_agent_hash), '
            . 'revoked_at=NULL, revoke_reason=NULL';

        db()->prepare($sql)->execute([
            $adminId,
            $sessionHash,
            $now,
            $now,
            $now + self::ABSOLUTE_TIMEOUT_SECONDS,
            $userAgentHash,
        ]);

        self::purgeOldRows();
    }

    public static function validateCurrent(): bool
    {
        self::start();

        $adminId = (int)($_SESSION['admin_id'] ?? 0);
        if ($adminId <= 0) {
            return false;
        }

        self::ensureTable();
        $sessionHash = self::sessionHash();

        $st = db()->prepare(
            'SELECT id, admin_id, issued_at, last_activity_at, absolute_expires_at, revoked_at '
            . 'FROM admin_sessions WHERE session_hash=? AND admin_id=? LIMIT 1'
        );
        $st->execute([$sessionHash, $adminId]);
        $row = $st->fetch();

        if (!$row) {
            self::expire('security');
            return false;
        }

        if (!empty($row['revoked_at'])) {
            self::expire('revoked', false);
            return false;
        }

        $now = time();
        $issuedAt = (int)$row['issued_at'];
        $lastActivityAt = (int)$row['last_activity_at'];
        $absoluteExpiresAt = (int)$row['absolute_expires_at'];

        if ($absoluteExpiresAt <= $now || ($issuedAt > 0 && ($now - $issuedAt) >= self::ABSOLUTE_TIMEOUT_SECONDS)) {
            self::expire('absolute');
            return false;
        }

        if ($lastActivityAt > 0 && ($now - $lastActivityAt) >= self::IDLE_TIMEOUT_SECONDS) {
            self::expire('idle');
            return false;
        }

        $sessionIssuedAt = (int)($_SESSION['auth_issued_at'] ?? 0);
        $sessionAbsolute = (int)($_SESSION['auth_absolute_expires_at'] ?? 0);
        if ($sessionIssuedAt <= 0 || $sessionAbsolute <= 0 || $sessionAbsolute <= $now) {
            self::expire('security');
            return false;
        }

        $_SESSION['auth_last_activity_at'] = $now;
        db()->prepare('UPDATE admin_sessions SET last_activity_at=? WHERE id=?')->execute([$now, (int)$row['id']]);
        return true;
    }

    public static function expire(string $reason, bool $revokeCurrent = true): void
    {
        self::start();
        self::$lastExpiryReason = $reason;

        if ($revokeCurrent && !empty($_SESSION['admin_id'])) {
            try {
                self::ensureTable();
                db()->prepare(
                    'UPDATE admin_sessions SET revoked_at=?, revoke_reason=? '
                    . 'WHERE session_hash=? AND revoked_at IS NULL'
                )->execute([time(), substr($reason, 0, 60), self::sessionHash()]);
            } catch (Throwable) {
            }
        }

        self::destroyPhpSession();
    }

    public static function logout(bool $clearRememberLogin = true): void
    {
        self::start();
        $adminId = (int)($_SESSION['admin_id'] ?? 0);

        try {
            if ($adminId > 0) {
                self::ensureTable();
                db()->prepare(
                    'UPDATE admin_sessions SET revoked_at=?, revoke_reason=? '
                    . 'WHERE session_hash=? AND revoked_at IS NULL'
                )->execute([time(), 'logout', self::sessionHash()]);
            }
        } catch (Throwable) {
        }

        self::destroyPhpSession();
        if ($clearRememberLogin) {
            self::deleteCookie(self::REMEMBER_LOGIN_COOKIE);
        }
    }

    public static function forceFreshLogin(bool $clearRememberLogin = false): void
    {
        self::start();
        if (!empty($_SESSION['admin_id'])) {
            try {
                self::ensureTable();
                db()->prepare(
                    'UPDATE admin_sessions SET revoked_at=?, revoke_reason=? '
                    . 'WHERE session_hash=? AND revoked_at IS NULL'
                )->execute([time(), 'fresh_login', self::sessionHash()]);
            } catch (Throwable) {
            }
        }

        self::destroyPhpSession();
        if ($clearRememberLogin) {
            self::deleteCookie(self::REMEMBER_LOGIN_COOKIE);
        }
    }

    public static function revokeAllForAdmin(int $adminId, string $reason = 'security_change'): void
    {
        if ($adminId <= 0) {
            return;
        }

        try {
            self::ensureTable();
            db()->prepare(
                'UPDATE admin_sessions SET revoked_at=?, revoke_reason=? '
                . 'WHERE admin_id=? AND revoked_at IS NULL'
            )->execute([time(), substr($reason, 0, 60), $adminId]);
        } catch (Throwable) {
        }
    }

    public static function setRememberLogin(string $login, bool $remember): void
    {
        $value = trim($login);

        // Remove legacy variants first so an older cookie on / or /admin/
        // cannot shadow the current remembered identifier.
        self::deleteRememberCookieVariants();

        if (!$remember || $value === '') {
            unset($_COOKIE[self::REMEMBER_LOGIN_COOKIE]);
            return;
        }

        $path = self::rememberCookiePath();
        setcookie(self::REMEMBER_LOGIN_COOKIE, $value, [
            'expires' => time() + (30 * 86400),
            'path' => $path,
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Keep the request state coherent. The browser receives the cookie
        // in the response, but PHP does not populate $_COOKIE automatically.
        $_COOKIE[self::REMEMBER_LOGIN_COOKIE] = $value;
    }

    public static function rememberedLogin(): string
    {
        $value = trim((string)($_COOKIE[self::REMEMBER_LOGIN_COOKIE] ?? ''));
        if ($value === '' || preg_match('/[\r\n\0]/', $value)) {
            return '';
        }

        return substr($value, 0, 190);
    }

    public static function lastExpiryReason(): ?string
    {
        return self::$lastExpiryReason;
    }

    public static function isAjaxOrApiRequest(): bool
    {
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        $uri = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));

        return $requestedWith === 'xmlhttprequest'
            || str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || str_contains($uri, '/api/');
    }

    public static function expiryMessage(string $reason): string
    {
        return match ($reason) {
            'idle' => 'Tu sesión venció por inactividad. Inicia sesión nuevamente por seguridad.',
            'absolute' => 'Tu sesión alcanzó su duración máxima de seguridad. Inicia sesión nuevamente.',
            'revoked' => 'Tu sesión fue cerrada por seguridad. Inicia sesión nuevamente.',
            'security' => 'La sesión anterior ya no es válida. Inicia sesión nuevamente por seguridad.',
            default => 'Tu sesión ya no está disponible. Inicia sesión nuevamente.',
        };
    }

    private static function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }

        db()->exec(
            "CREATE TABLE IF NOT EXISTS admin_sessions (\n"
            . "  id BIGINT AUTO_INCREMENT PRIMARY KEY,\n"
            . "  admin_id INT NOT NULL,\n"
            . "  session_hash CHAR(64) NOT NULL UNIQUE,\n"
            . "  issued_at BIGINT UNSIGNED NOT NULL,\n"
            . "  last_activity_at BIGINT UNSIGNED NOT NULL,\n"
            . "  absolute_expires_at BIGINT UNSIGNED NOT NULL,\n"
            . "  user_agent_hash CHAR(64) NULL,\n"
            . "  revoked_at BIGINT UNSIGNED NULL,\n"
            . "  revoke_reason VARCHAR(60) NULL,\n"
            . "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
            . "  INDEX idx_admin_sessions_admin(admin_id),\n"
            . "  INDEX idx_admin_sessions_activity(last_activity_at),\n"
            . "  INDEX idx_admin_sessions_absolute(absolute_expires_at),\n"
            . "  CONSTRAINT fk_admin_sessions_admin FOREIGN KEY(admin_id) REFERENCES admin_users(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        self::$tableReady = true;
    }

    private static function destroyPhpSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        $params = session_get_cookie_params();
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    private static function deleteCookie(string $name): void
    {
        if ($name === self::REMEMBER_LOGIN_COOKIE) {
            self::deleteRememberCookieVariants();
            unset($_COOKIE[$name]);
            return;
        }

        setcookie($name, '', [
            'expires' => time() - 42000,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[$name]);
    }

    private static function rememberCookiePath(): string
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/admin/login.php'));
        $marker = '/admin/';
        $position = strrpos($script, $marker);

        if ($position === false) {
            return '/';
        }

        return substr($script, 0, $position) . '/admin/';
    }

    private static function deleteRememberCookieVariants(): void
    {
        $paths = array_values(array_unique(['/', self::rememberCookiePath()]));

        foreach ($paths as $path) {
            setcookie(self::REMEMBER_LOGIN_COOKIE, '', [
                'expires' => time() - 42000,
                'path' => $path,
                'secure' => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    private static function sessionHash(): string
    {
        return hash('sha256', session_id());
    }

    private static function isHttps(): bool
    {
        return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    }

    private static function purgeOldRows(): void
    {
        try {
            $cutoff = time() - (30 * 86400);
            db()->prepare(
                'DELETE FROM admin_sessions WHERE '
                . '(revoked_at IS NOT NULL AND revoked_at < ?) OR absolute_expires_at < ?'
            )->execute([$cutoff, $cutoff]);
        } catch (Throwable) {
        }
    }
}

function app_session_start(): void
{
    AuthSessionManager::start();
}
