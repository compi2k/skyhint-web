<?php
declare(strict_types=1);

namespace PlanetAlerts;

/** Holds configuration and lazily created services. */
final class App
{
    private static array $config = [];
    private static ?Db $db = null;
    private static ?Calc $calc = null;

    public static function boot(array $config): void
    {
        self::$config = $config;
        $debug = (bool) self::config('app.debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);
        ini_set('log_errors', '1');
        ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
    }

    /** Dot notation lookup: App::config('mail.from'). */
    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function db(): Db
    {
        return self::$db ??= Db::connect(self::config('db'));
    }

    public static function calc(): Calc
    {
        return self::$calc ??= new Calc(self::config('calc'), self::db());
    }

    /** Absolute URL for links in emails and redirects. */
    public static function url(string $path = '/', array $query = []): string
    {
        $base = rtrim((string) self::config('app.url'), '/');
        return $base . $path . ($query ? '?' . http_build_query($query) : '');
    }

    public static function name(): string
    {
        return (string) self::config('app.name', 'Skyhint.app');
    }

    public static function log(string $message): void
    {
        $line = sprintf("[%s] %s\n", gmdate('Y-m-d H:i:s'), $message);
        @file_put_contents(APP_ROOT . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
