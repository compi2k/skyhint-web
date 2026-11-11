<?php
declare(strict_types=1);

namespace PlanetAlerts;

/**
 * Turns the night track from the calc service into what the city page needs:
 * rows for the night planner, and the data for the sky view script.
 */
final class SkyTrack
{
    /** Below this, the Moon is too thin to bother drawing. */
    public const MOON_MIN_ILLUMINATION = 0.03;
    private const AFTER_DARK_STEPS = 6;   // 6 x 10 min = one hour

    /** Same rule as the calc service: height a planet needs in a dark sky. */
    public static function minAltitude(string $planet): float
    {
        return $planet === 'mercury' ? 5.0 : 10.0;
    }

    public static function prepare(array $track, array $city, ?\DateTimeImmutable $now = null): array
    {
        $times = $track['times'];
        $n = count($times);
        $sun = $track['sun'];

        $firstDark = null;
        $lastDark = null;
        foreach ($sun as $i => $alt) {
            if ($alt <= -6) {
                $firstDark ??= $i;
                $lastDark = $i;
            }
        }
        $evening = $firstDark === null ? intdiv($n, 2) : min($firstDark + self::AFTER_DARK_STEPS, $lastDark);

        // start at "now" when the page is opened during the night, otherwise an hour after dark
        $start = $evening;
        $nowTs = ($now ?? new \DateTimeImmutable())->getTimestamp();
        $startTs = strtotime($times[0]);
        $endTs = strtotime($times[$n - 1]);
        if ($nowTs >= $startTs && $nowTs <= $endTs) {
            $start = max(0, min($n - 1, (int) round(($nowTs - $startTs) / ($track['step_minutes'] * 60))));
        }

        $rows = self::plannerRows($track);
        return [
            'rows'  => $rows,
            'times' => array_map(static fn ($t) => substr($t, 11, 5), $times),
            'sun'   => $sun,
            'start' => $start,
            'data'  => [
                'times'   => array_map(static fn ($t) => substr($t, 11, 5), $times),
                'iso'     => $times,
                'sun'     => $sun,
                'sunAz'   => $track['sun_az'] ?? array_fill(0, $n, 270),
                'bodies'  => $track['bodies'],
                'lat'     => (float) $city['latitude'],
                'lon'     => (float) $city['longitude'],
                'start'   => $start,
                'best'    => (object) array_filter(array_column($rows, 'best', 'name'), static fn ($v) => $v !== null),
                'moonMin' => self::MOON_MIN_ILLUMINATION,
                'labels'  => self::labels(),
            ],
        ];
    }

    /**
     * One row per body that is above the horizon at some point of the night.
     * Segments: 'up' (above the horizon) and 'good' (high enough in a dark sky).
     */
    public static function plannerRows(array $track): array
    {
        $rows = [];
        $sun = $track['sun'];
        foreach (array_merge(Prefs::PLANETS, ['moon']) as $name) {
            $b = $track['bodies'][$name] ?? null;
            if (!$b || max($b['alt']) <= 0) {
                continue;
            }
            if ($name === 'moon' && ($b['illumination'] ?? 0) < self::MOON_MIN_ILLUMINATION) {
                continue;
            }
            $min = $name === 'moon' ? 5.0 : self::minAltitude($name);
            $up = [];
            $good = [];
            $best = null;
            foreach ($b['alt'] as $i => $alt) {
                $up[$i] = $alt > 0;
                $good[$i] = $alt >= $min && $sun[$i] <= -6;
                if ($good[$i] && ($best === null || $alt > $b['alt'][$best])) {
                    $best = $i;
                }
            }
            $rows[] = ['name' => $name, 'up' => self::runs($up), 'good' => self::runs($good), 'best' => $best];
        }
        return $rows;
    }

    /**
     * "Tonight at a glance" from a track: darkness, Moon, visible planets and
     * the best moment (the visible planet standing highest at its best time).
     * @return array{dark: ?array{0:string,1:string}, moon: ?int, waxing: bool, visible: list<string>, best: ?array{name:string,time:string}}
     */
    public static function glance(array $track): array
    {
        $times = $track['times'];
        $dark = array_keys(array_filter($track['sun'], static fn ($alt) => $alt <= -6));
        $visible = [];
        $best = null;
        foreach (self::plannerRows($track) as $row) {
            if ($row['name'] === 'moon' || !$row['good'] || $row['best'] === null) {
                continue;
            }
            $visible[] = $row['name'];
            $alt = $track['bodies'][$row['name']]['alt'][$row['best']];
            if ($best === null || $alt > $best['alt']) {
                $best = ['name' => $row['name'], 'time' => $times[$row['best']], 'alt' => $alt];
            }
        }
        $moon = $track['bodies']['moon'] ?? [];
        return [
            'dark'    => $dark ? [$times[$dark[0]], $times[end($dark)]] : null,
            'moon'    => isset($moon['illumination']) ? (int) round($moon['illumination'] * 100) : null,
            'waxing'  => (bool) ($moon['waxing'] ?? true),
            'visible' => $visible,
            'best'    => $best,
        ];
    }

    /** [[from, to], ...] index ranges where the flags are true. */
    private static function runs(array $flags): array
    {
        $out = [];
        $start = null;
        foreach ($flags as $i => $f) {
            if ($f && $start === null) {
                $start = $i;
            } elseif (!$f && $start !== null) {
                $out[] = [$start, $i - 1];
                $start = null;
            }
        }
        if ($start !== null) {
            $out[] = [$start, count($flags) - 1];
        }
        return $out;
    }

    private static function labels(): array
    {
        $l = [];
        foreach (array_merge(Prefs::PLANETS, ['moon']) as $p) {
            $l['planet'][$p] = I18n::t("planet.$p");
        }
        foreach (['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'] as $d) {
            $l['dir'][$d] = I18n::t("dir.short.$d");
            $l['dirLong'][$d] = I18n::t("dir.$d");
        }
        foreach (['at', 'none', 'day', 'twilight', 'faint', 'fists', 'fist_one', 'fist_low', 'play', 'pause',
                  'item', 'and', 'facing', 'ruler'] as $k) {
            $l['text'][$k] = I18n::t("sky.live.$k");
        }
        foreach (['orion', 'ursa_major', 'cassiopeia', 'summer_triangle', 'cygnus', 'leo', 'scorpius', 'crux',
                  'gemini', 'pleiades', 'great_square', 'perseus'] as $c) {
            $l['const'][$c] = I18n::t("const.$c");
        }
        foreach (['sirius', 'vega', 'arcturus', 'capella', 'rigel', 'procyon', 'betelgeuse', 'altair',
                  'aldebaran', 'antares', 'spica', 'pollux', 'deneb', 'regulus', 'polaris', 'canopus', 'fomalhaut'] as $s) {
            $l['star'][$s] = I18n::t("star.$s");
        }
        return $l;
    }
}
