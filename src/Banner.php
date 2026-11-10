<?php
declare(strict_types=1);

namespace PlanetAlerts;

/**
 * Optional banner across the very top of every page, set in config.php:
 *
 *   'banner' => [
 *       'text'  => ['de' => 'Prototyp …', 'en' => 'Prototype …'],   // or one string for all languages
 *       'style' => 'construction',        // info | warning | danger | construction
 *       'link'  => ['url' => '/status', 'text' => ['de' => 'Status', 'en' => 'Status']],   // optional
 *   ],
 *
 * Empty or missing text: no banner.
 */
final class Banner
{
    public const STYLES = ['info', 'warning', 'danger', 'construction'];

    /** @return array{text: string, style: string, url: ?string, link: ?string}|null */
    public static function current(): ?array
    {
        $cfg = App::config('banner', []);
        $text = self::localized($cfg['text'] ?? '');
        if ($text === '') {
            return null;
        }
        $url = (string) ($cfg['link']['url'] ?? '');
        $safeUrl = preg_match('~^(https://|/)~', $url) ? $url : null;   // no javascript: and friends
        $link = self::localized($cfg['link']['text'] ?? '');
        return [
            'text'  => $text,
            'style' => in_array($cfg['style'] ?? '', self::STYLES, true) ? $cfg['style'] : 'info',
            'url'   => $safeUrl,
            'link'  => $safeUrl ? ($link !== '' ? $link : $url) : null,
        ];
    }

    private static function localized(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[I18n::lang()] ?? $value['en'] ?? reset($value) ?: '';
        }
        return trim((string) $value);
    }
}
