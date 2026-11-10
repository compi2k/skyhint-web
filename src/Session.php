<?php
declare(strict_types=1);

namespace PlanetAlerts;

/** PHP session with safe cookie settings, CSRF tokens and flash messages. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('pa_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => Http::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '86400');
        session_start();
        // The cookie has no expiry date: closing the browser ends the login. On the
        // server a login also ends after 24 hours without activity, and after 30 days
        // at most (the privacy page says so).
        if (isset($_SESSION['login_at'])) {
            $idle = time() - (int) ($_SESSION['seen_at'] ?? $_SESSION['login_at']);
            if ($idle > 86400 || time() - $_SESSION['login_at'] > 30 * 86400) {
                self::logout();
            } else {
                $_SESSION['seen_at'] = time();
            }
        }
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function checkCsrf(): void
    {
        $sent = $_POST['csrf'] ?? '';
        if (!is_string($sent) || !hash_equals(self::csrfToken(), $sent)) {
            http_response_code(400);
            exit(I18n::t('error.csrf'));
        }
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['login_at'] = time();
        unset($_SESSION['csrf']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlash(): array
    {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }

    /** Keeps submitted form values for one redirect, so forms can be refilled. */
    public static function keepOld(array $values): void
    {
        $_SESSION['old'] = $values;
    }

    public static function takeOld(): array
    {
        $o = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);
        return $o;
    }
}
