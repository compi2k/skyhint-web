<?php
declare(strict_types=1);

namespace PlanetAlerts;

use DateTimeImmutable;
use DateTimeZone;

/**
 * City of the week for the start page.
 *
 * A curated list of well-known places, north and south taking turns, so the
 * start page shows a different sky every week (the same for everyone).
 *
 * The start page never talks to the calc server: the mail job (every 15
 * minutes) resolves the city and fills the cache with its night track ahead
 * of time (prepare()); the start page only reads what is there (forHome()).
 * If nothing is prepared yet, or the calc server was down all along, the
 * start page simply shows no sky.
 */
final class WeekCity
{
    /** [name, ISO country code], north and south taking turns. */
    public const CITIES = [
        ['Berlin', 'DE'], ['Sydney', 'AU'], ['Tokyo', 'JP'], ['Cape Town', 'ZA'],
        ['New York City', 'US'], ['Buenos Aires', 'AR'], ['Reykjavik', 'IS'], ['Auckland', 'NZ'],
        ['Cairo', 'EG'], ['Santiago', 'CL'], ['Mexico City', 'MX'], ['Rio de Janeiro', 'BR'],
        ['Mumbai', 'IN'], ['Perth', 'AU'], ['Paris', 'FR'], ['Lima', 'PE'],
        ['Vancouver', 'CA'], ['Johannesburg', 'ZA'], ['Istanbul', 'TR'], ['Melbourne', 'AU'],
        ['Honolulu', 'US'], ['Nairobi', 'KE'], ['Rome', 'IT'], ['Montevideo', 'UY'],
        ['Seoul', 'KR'], ['Queenstown', 'NZ'], ['London', 'GB'], ['La Paz', 'BO'],
        ['Los Angeles', 'US'], ['Hobart', 'AU'], ['Madrid', 'ES'], ['Windhoek', 'NA'],
        ['Tromsø', 'NO'], ['Brisbane', 'AU'], ['Dubai', 'AE'], ['São Paulo', 'BR'],
        ['Bangkok', 'TH'], ['Wellington', 'NZ'], ['Vienna', 'AT'], ['Antananarivo', 'MG'],
        ['Chicago', 'US'], ['Adelaide', 'AU'], ['Athens', 'GR'], ['Quito', 'EC'],
        ['Marrakesh', 'MA'], ['Durban', 'ZA'], ['Helsinki', 'FI'], ['Jakarta', 'ID'],
        ['Lisbon', 'PT'], ['Darwin', 'AU'], ['Kathmandu', 'NP'], ['Singapore', 'SG'],
    ];

    /** Weeks since 1970, starting Mondays 00:00 UTC. */
    public static function week(DateTimeImmutable $at): int
    {
        return intdiv($at->getTimestamp() + 3 * 86400, 7 * 86400);
    }

    /**
     * Run by the mail job: makes sure this week's city (and the next one,
     * shortly before the switch) is resolved and tonight's track is cached.
     * Returns log lines; never throws.
     */
    public static function prepare(DateTimeImmutable $now): array
    {
        $log = [];
        $done = [];
        foreach ([$now, $now->modify('+1 hour')] as $at) {
            $week = self::week($at);
            try {
                $city = self::resolve($week);
                if (!$city) {
                    $log[] = "week city: nothing found for week $week";
                    continue;
                }
                $night = Cities::currentNight($city, $at);
                $key = $city['id'] . ':' . $night;
                if (!isset($done[$key])) {
                    App::calc()->track((int) $city['id'], $night);
                    $done[$key] = true;
                }
            } catch (\Throwable $e) {
                $log[] = 'week city: ' . $e->getMessage();
            }
        }
        return $log;
    }

    /**
     * For the start page: the city and its prepared track, read from the
     * database only. Null if nothing is prepared.
     * @return array{city: array, track: array}|null
     */
    public static function forHome(?DateTimeImmutable $now = null): ?array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $db = App::db();
        foreach ([self::week($now), self::week($now) - 1] as $week) {   // last week's city if this one isn't ready
            $id = (int) $db->value('SELECT value FROM app_state WHERE name = ?', ["week_city:$week"]);
            if ($id <= 0) {
                continue;
            }
            $city = $db->one('SELECT * FROM cities WHERE id = ?', [$id]);
            if (!$city) {
                continue;
            }
            $track = App::calc()->peekTrack($id, Cities::currentNight($city, $now));
            if ($track) {
                return ['city' => $city, 'track' => $track];
            }
        }
        return null;
    }

    /** Finds the city for a week (skipping names the calc server doesn't know) and remembers it. */
    private static function resolve(int $week): ?array
    {
        $db = App::db();
        $id = (int) $db->value('SELECT value FROM app_state WHERE name = ?', ["week_city:$week"]);
        if ($id > 0 && ($city = Cities::ensure($id))) {
            return $city;
        }
        $n = count(self::CITIES);
        $city = null;
        for ($k = 0; $k < $n && !$city; $k++) {
            [$name, $country] = self::CITIES[($week + $k) % $n];
            $hits = App::calc()->searchCities($name, $country, 1);
            if ($hits) {
                $city = Cities::ensure((int) $hits[0]['id']);
            }
        }
        if (!$city) {
            $city = Cities::ensure((int) App::config('app.home_city', 2950159));
        }
        if ($city) {
            $db->run('REPLACE INTO app_state (name, value) VALUES (?, ?)', ["week_city:$week", (string) $city['id']]);
            $db->run("DELETE FROM app_state WHERE name LIKE 'week\\_city:%' AND name < ?", ['week_city:' . ($week - 2)]);
        }
        return $city;
    }
}
