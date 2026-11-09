<?php
declare(strict_types=1);

namespace PlanetAlerts;

/** What one alert watches: which planets, which event types. */
final class Prefs
{
    public const PLANETS = ['mercury', 'venus', 'mars', 'jupiter', 'saturn'];

    /** Event type => on by default (project plan, "Event rules"). */
    public const EVENTS = [
        'comeback'            => true,
        'last_chance'         => true,
        'mercury_window'      => true,
        'opposition'          => true,
        'greatest_elongation' => true,
        'planet_pair'         => true,
        'moon_nearby'         => false,
        'parade'              => true,
    ];

    /** Mail style is per account: event alerts, the Sunday overview, or both. */
    public const STYLES = ['alerts', 'digest', 'both'];

    public static function defaults(): array
    {
        return [
            'planets' => self::PLANETS,
            'events'  => array_keys(array_filter(self::EVENTS)),
        ];
    }

    public static function decode(?string $json): array
    {
        $p = json_decode((string) $json, true);
        return self::normalize(is_array($p) ? $p : []);
    }

    /** Keeps only known values; missing keys fall back to defaults. */
    public static function normalize(array $p): array
    {
        $d = self::defaults();
        return [
            'planets' => array_values(array_intersect(self::PLANETS, (array) ($p['planets'] ?? $d['planets']))),
            'events'  => array_values(array_intersect(array_keys(self::EVENTS), (array) ($p['events'] ?? $d['events']))),
        ];
    }

    public static function style(?string $s): string
    {
        return in_array($s, self::STYLES, true) ? $s : 'alerts';
    }

    public static function wantsAlerts(string $style): bool
    {
        return in_array($style, ['alerts', 'both'], true);
    }

    public static function wantsDigest(string $style): bool
    {
        return in_array($style, ['digest', 'both'], true);
    }

    /** An event matches if its type is chosen and every planet in it is chosen. */
    public static function matches(array $p, array $event): bool
    {
        if (!in_array($event['type'], $p['events'], true)) {
            return false;
        }
        if ($event['type'] === 'parade') {
            return count(array_intersect($event['planets'], $p['planets'])) >= 2;
        }
        return !array_diff($event['planets'], $p['planets']);
    }

    public static function filter(array $p, array $events): array
    {
        return array_values(array_filter($events, static fn ($e) => self::matches($p, $e)));
    }
}
