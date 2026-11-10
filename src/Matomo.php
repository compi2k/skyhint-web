<?php
declare(strict_types=1);

namespace PlanetAlerts;

/**
 * Optional visitor statistics with your own Matomo server.
 * Off unless `matomo.url` and `matomo.site_id` are set in the config.
 * Runs without cookies, skips visitors with Do Not Track or Global Privacy
 * Control, never sends query strings (they can hold tokens), and visitors can
 * switch it off on the privacy page.
 */
final class Matomo
{
    public static function enabled(): bool
    {
        return self::url() !== '' && (int) App::config('matomo.site_id', 0) > 0;
    }

    /** Base URL with trailing slash, e.g. https://stats.example.com/ */
    public static function url(): string
    {
        $url = trim((string) App::config('matomo.url', ''));
        if ($url === '' || !preg_match('~^https://[^/\s]+(/\S*)?$~', $url)) {
            return '';
        }
        return rtrim($url, '/') . '/';
    }

    /** Origin for the Content-Security-Policy, e.g. https://stats.example.com */
    public static function origin(): string
    {
        $p = parse_url(self::url());
        return 'https://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    public static function siteId(): int
    {
        return (int) App::config('matomo.site_id', 0);
    }

    public static function retentionDays(): int
    {
        return max(1, (int) App::config('matomo.retention_days', 180));
    }
}
