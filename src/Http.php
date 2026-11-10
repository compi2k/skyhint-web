<?php
declare(strict_types=1);

namespace PlanetAlerts;

/** Request and response helpers. */
final class Http
{
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return $path !== '/' ? rtrim($path, '/') : '/';
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function post(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function get(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function postArray(string $key): array
    {
        $v = $_POST[$key] ?? [];
        return is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
    }

    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        $stats = Matomo::enabled() ? ' ' . Matomo::origin() : '';   // optional own Matomo server
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:$stats; style-src 'self'; "
            . "script-src 'self'$stats; connect-src 'self'$stats; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    public static function redirect(string $path, int $status = 303): never
    {
        header('Location: ' . $path, true, $status);
        exit;
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        foreach ($headers as $h) {
            header($h);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
