<?php
declare(strict_types=1);

namespace PlanetAlerts;

/**
 * Strings from lang/*.php, with {placeholders}. Every file there is a language:
 * the file name is its two-letter code, 'lang.name' its name in that language.
 * Adding a language = copying en.php to xx.php and translating it.
 */
final class I18n
{
    private static string $lang = 'de';
    private static array $strings = [];
    private static ?array $names = null;

    /** @return array<string,string> code => own name, e.g. ['de' => 'Deutsch', 'en' => 'English'] */
    public static function languages(): array
    {
        if (self::$names === null) {
            self::$names = [];
            foreach (glob(APP_ROOT . '/lang/*.php') ?: [] as $file) {
                $code = basename($file, '.php');
                if (preg_match('/^[a-z]{2}$/', $code)) {
                    self::$strings[$code] ??= require $file;
                    self::$names[$code] = (string) (self::$strings[$code]['lang.name'] ?? $code);
                }
            }
            ksort(self::$names);
        }
        return self::$names;
    }

    public static function valid(mixed $lang): bool
    {
        return is_string($lang) && isset(self::languages()[$lang]);
    }

    public static function set(string $lang): void
    {
        self::$lang = self::valid($lang)
            ? $lang : (string) App::config('app.default_lang', 'de');
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    public static function t(string $key, array $vars = [], ?string $lang = null): string
    {
        $lang ??= self::$lang;
        self::$strings[$lang] ??= require APP_ROOT . "/lang/$lang.php";
        $text = self::$strings[$lang][$key] ?? self::fallback($key);
        foreach ($vars as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }

    private static function fallback(string $key): string
    {
        self::$strings['en'] ??= require APP_ROOT . '/lang/en.php';
        return self::$strings['en'][$key] ?? $key;
    }

    /** Picks a language from ?lang, cookie, then Accept-Language. */
    public static function detect(): string
    {
        $q = $_GET['lang'] ?? null;
        if (self::valid($q)) {
            setcookie('lang', $q, ['expires' => time() + 31536000, 'path' => '/',
                'secure' => Http::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
            return $q;
        }
        $c = $_COOKIE['lang'] ?? null;
        if (self::valid($c)) {
            return $c;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        foreach (explode(',', $accept) as $part) {
            $code = substr(trim($part), 0, 2);
            if (self::valid($code)) {
                return $code;
            }
        }
        return (string) App::config('app.default_lang', 'de');
    }
}
