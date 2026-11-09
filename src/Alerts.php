<?php
declare(strict_types=1);

namespace PlanetAlerts;

use DateTimeImmutable;
use DateTimeZone;

/** A user's alerts: each watches one city with its own planets and event types. */
final class Alerts
{
    public const MAX_PER_USER = 10;

    private const SELECT = 'SELECT a.*, c.name AS city_name, c.region AS city_region, c.country AS city_country,
                                   c.timezone AS city_timezone
                            FROM alerts a JOIN cities c ON c.id = a.city_id';

    /** Oldest first, so the first alert is the "home" one. */
    public static function forUser(int $userId, bool $enabledOnly = false): array
    {
        return array_map([self::class, 'hydrate'], App::db()->all(
            self::SELECT . ' WHERE a.user_id = ?' . ($enabledOnly ? ' AND a.enabled = 1' : '')
            . ' ORDER BY a.created_at, a.id', [$userId]));
    }

    public static function find(int $userId, int $id): ?array
    {
        $row = App::db()->one(self::SELECT . ' WHERE a.user_id = ? AND a.id = ?', [$userId, $id]);
        return $row ? self::hydrate($row) : null;
    }

    public static function count(int $userId): int
    {
        return (int) App::db()->value('SELECT COUNT(*) FROM alerts WHERE user_id = ?', [$userId]);
    }

    public static function create(int $userId, int $cityId, array $prefs, string $name = ''): int
    {
        $db = App::db();
        $db->run('INSERT INTO alerts (user_id, name, city_id, prefs, enabled, created_at, updated_at)
                  VALUES (?, ?, ?, ?, 1, ?, ?)',
            [$userId, mb_substr(trim($name), 0, 80), $cityId, json_encode(Prefs::normalize($prefs)), Db::now(), Db::now()]);
        return $db->lastId();
    }

    public static function update(int $userId, int $id, int $cityId, array $prefs, string $name): void
    {
        App::db()->run('UPDATE alerts SET city_id = ?, prefs = ?, name = ?, updated_at = ? WHERE id = ? AND user_id = ?',
            [$cityId, json_encode(Prefs::normalize($prefs)), mb_substr(trim($name), 0, 80), Db::now(), $id, $userId]);
    }

    public static function setEnabled(int $userId, int $id, bool $on): void
    {
        App::db()->run('UPDATE alerts SET enabled = ?, updated_at = ? WHERE id = ? AND user_id = ?',
            [(int) $on, Db::now(), $id, $userId]);
    }

    public static function delete(int $userId, int $id): void
    {
        $db = App::db();
        $db->run('DELETE FROM sent_log WHERE user_id = ? AND event_key LIKE ?', [$userId, "a$id:%"]);
        $db->run('DELETE FROM alerts WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /** "Home", or the city name when the alert has no name of its own. */
    public static function label(array $alert): string
    {
        return $alert['name'] !== '' ? $alert['name'] : $alert['city_name'];
    }

    public static function city(array $alert): array
    {
        return ['id' => $alert['city_id'], 'name' => $alert['city_name'], 'region' => $alert['city_region'],
                'country' => $alert['city_country'], 'timezone' => $alert['city_timezone']];
    }

    /**
     * Mails go out at 15:00 in the time zone of the user's first enabled alert.
     * Returns null when the user has no alert at all.
     */
    public static function mailTimezone(array $alerts): ?string
    {
        foreach ($alerts as $a) {
            if ($a['enabled']) {
                return $a['city_timezone'];
            }
        }
        return $alerts[0]['city_timezone'] ?? null;
    }

    /**
     * Date of "tonight" in a city, seen from a moment that may be morning elsewhere.
     * Until 06:00 local the night that started yesterday is still running.
     */
    public static function nightFor(array $alert, DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone($alert['city_timezone']))->modify('-6 hours')->format('Y-m-d');
    }

    private static function hydrate(array $row): array
    {
        $row['prefs'] = Prefs::decode($row['prefs']);
        $row['enabled'] = (bool) $row['enabled'];
        return $row;
    }
}
