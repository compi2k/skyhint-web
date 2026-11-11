<?php
declare(strict_types=1);

namespace PlanetAlerts;

/**
 * Turns calc results into plain sentences in the current language.
 * Used by the web pages and the emails.
 */
final class Format
{
    public static function planet(string $name): string
    {
        return I18n::t("planet.$name");
    }

    public static function planets(array $names): string
    {
        $names = array_map([self::class, 'planet'], $names);
        if (count($names) < 2) {
            return implode('', $names);
        }
        $last = array_pop($names);
        return implode(', ', $names) . ' ' . I18n::t('word.and') . ' ' . $last;
    }

    /** "21:43" from an ISO time with offset (already local). */
    public static function time(string $iso): string
    {
        return substr($iso, 11, 5);
    }

    /** "Mo, 10. Nov." / "Mon 10 Nov" from Y-m-d. */
    public static function date(string $ymd): string
    {
        $ts = strtotime($ymd . ' 12:00:00 UTC');
        return I18n::t('date.format', [
            'wd'  => I18n::t('weekday.' . gmdate('w', $ts)),
            'd'   => gmdate('j', $ts),
            'mon' => I18n::t('month.' . gmdate('n', $ts)),
        ]);
    }

    /** evening / morning / night, from a planet's visibility on one night. */
    public static function skyPart(array $vis): string
    {
        if (($vis['minutes'] ?? 0) >= 480) {
            return 'night';
        }
        $best = $vis['best']['time'] ?? null;
        if ($best === null) {
            return 'night';
        }
        $hour = (int) substr($best, 11, 2);
        return $hour >= 12 ? 'evening' : 'morning';
    }

    public static function height(float $altitude): string
    {
        $fists = (int) round($altitude / 10);
        if ($fists < 1) {
            return I18n::t('howto.height.low');
        }
        return $fists === 1 ? I18n::t('howto.height.one') : I18n::t('howto.height.fists', ['n' => $fists]);
    }

    public static function brightness(float $mag): string
    {
        return match (true) {
            $mag <= -3.0 => I18n::t('bright.extreme'),
            $mag <= -1.0 => I18n::t('bright.very'),
            $mag <= 1.0  => I18n::t('bright.bright'),
            default      => I18n::t('bright.normal'),
        };
    }

    /** One "how to find it" sentence for a planet on a night. */
    public static function howTo(string $planet, array $vis): string
    {
        $windows = $vis['windows'] ?? [];
        if (!$windows || empty($vis['best'])) {
            return '';
        }
        $first = $windows[0];
        $last = $windows[count($windows) - 1];
        return I18n::t('howto.line', [
            'planet'     => self::planet($planet),
            'dir'        => I18n::t('dir.' . $vis['best']['direction']),
            'height'     => self::height((float) $vis['best']['altitude']),
            'time'       => self::time($vis['best']['time']),
            'start'      => self::time($first['start']),
            'end'        => self::time($last['end']),
            'brightness' => self::brightness((float) ($vis['magnitude'] ?? 5)),
        ]);
    }

    /** Headline for an event from the calc service. */
    public static function eventTitle(array $e): string
    {
        $p = $e['planets'];
        $vis = $e['visibility'][$p[0]] ?? [];
        $d = $e['data'] ?? [];
        return match ($e['type']) {
            'comeback'    => I18n::t('event.comeback', ['planet' => self::planet($p[0]),
                                'sky' => I18n::t('sky.' . self::skyPart($vis))]),
            'last_chance' => I18n::t('event.last_chance', ['planet' => self::planet($p[0])]),
            'mercury_window' => ($d['phase'] ?? '') === 'greatest_elongation'
                ? I18n::t('event.mercury_best', ['sky' => I18n::t('sky.' . self::skyPart($vis))])
                : I18n::t('event.mercury_start', ['sky' => I18n::t('sky.' . self::skyPart($vis))]),
            'opposition'  => I18n::t('event.opposition', ['planet' => self::planet($p[0])]),
            'greatest_elongation' => I18n::t('event.greatest_elongation', ['planet' => self::planet($p[0]),
                                'sky' => I18n::t('sky.' . self::skyPart($vis))]),
            'planet_pair' => I18n::t('event.planet_pair', ['planets' => self::planets($p),
                                'deg' => self::degrees((float) ($d['separation'] ?? 0))]),
            'moon_nearby' => I18n::t('event.moon_nearby', ['planet' => self::planet($p[0]),
                                'deg' => self::degrees((float) ($d['separation'] ?? 0))]),
            'parade'      => I18n::t('event.parade', ['n' => count($p), 'planets' => self::planets($p)]),
            default       => $e['type'],
        };
    }

    /** "How to find it" sentences for every planet in an event. */
    public static function eventLines(array $e): array
    {
        $lines = [];
        foreach ($e['planets'] as $p) {
            $line = self::howTo($p, $e['visibility'][$p] ?? []);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        if ($e['type'] === 'moon_nearby' && !empty($e['data']['time'])) {
            $lines[] = I18n::t('howto.moon_time', ['time' => self::time($e['data']['time'])]);
        }
        return $lines;
    }

    public static function moon(array $moon): string
    {
        return I18n::t('moon.line', [
            'phase' => I18n::t('moon.' . $moon['phase']),
            'pct'   => (int) round(($moon['illumination'] ?? 0) * 100),
        ]);
    }

    public static function degrees(float $deg): string
    {
        $s = number_format($deg, 1, I18n::lang() === 'de' ? ',' : '.', '');
        return $s . '°';
    }

    /** "45.76° N · 4.84° E" */
    public static function coords(float $lat, float $lon): string
    {
        return sprintf('%s° %s · %s° %s',
            self::num(abs($lat), 2), I18n::t($lat >= 0 ? 'dir.short.N' : 'dir.short.S'),
            self::num(abs($lon), 2), I18n::t($lon >= 0 ? 'dir.short.E' : 'dir.short.W'));
    }

    private static function num(float $v, int $dec): string
    {
        return number_format($v, $dec, I18n::lang() === 'de' ? ',' : '.', '');
    }
}
