<?php
declare(strict_types=1);

namespace PlanetAlerts;

use DateTimeImmutable;
use DateTimeZone;

/** Local copy of the cities people use, so the mail job knows their time zones. */
final class Cities
{
    /** Returns the city row, fetching it from the calc service the first time. */
    public static function ensure(int $id): ?array
    {
        $db = App::db();
        $row = $db->one('SELECT * FROM cities WHERE id = ?', [$id]);
        if ($row) {
            return $row;
        }
        try {
            $c = App::calc()->city($id);
        } catch (CalcException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
        $db->run('REPLACE INTO cities (id, name, region, country, timezone, latitude, longitude, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$c['id'], $c['name'], $c['region'], $c['country'], $c['timezone'],
             $c['latitude'], $c['longitude'], Db::now()]);
        return $db->one('SELECT * FROM cities WHERE id = ?', [$id]);
    }

    public static function label(array $city): string
    {
        $parts = [$city['name']];
        if (($city['region'] ?? '') !== '') {
            $parts[] = $city['region'];
        }
        $parts[] = $city['country'];
        return implode(', ', $parts);
    }

    public static function now(array $city, ?DateTimeImmutable $at = null): DateTimeImmutable
    {
        return ($at ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone($city['timezone']));
    }

    /** Date of the night currently running (noon to noon, local). */
    public static function currentNight(array $city, ?DateTimeImmutable $at = null): string
    {
        return self::now($city, $at)->modify('-12 hours')->format('Y-m-d');
    }
}
