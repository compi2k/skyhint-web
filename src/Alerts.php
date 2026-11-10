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

    /** Time zone of the first enabled place (fallback for accounts without a mail time zone). */
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
     * Which nights a mail sent now should announce for this place, as Y-m-d.
     *
     * Normally one: the next night whose darkness has not begun yet, so the
     * mail always arrives ahead of time, whatever the slot and however far
     * away the place is ("tonight" before dusk, "tomorrow night" after).
     * If the mail time drifted past dusk since the last mail (daylight saving,
     * dusk getting earlier), the night that was skipped is added as long as it
     * is still running, so no night falls through the gap.
     * @return list<string> oldest first; the last one is the "target"
     */
    public static function nightsToAnnounce(array $alert, DateTimeImmutable $now): array
    {
        $tz = new DateTimeZone($alert['city_timezone']);
        $local = $now->setTimezone($tz);
        $days = [
            $local->modify('-1 day')->format('Y-m-d'),
            $local->format('Y-m-d'),
            $local->modify('+1 day')->format('Y-m-d'),
        ];
        $nights = [];
        foreach (App::calc()->nights((int) $alert['city_id'], $days[0], $days[2]) as $n) {
            $nights[$n['date']] = $n;
        }
        $target = $days[2];
        foreach ([$days[1], $days[2]] as $d) {
            if (self::darkStart($nights[$d] ?? null, $d, $tz) > $now) {
                $target = $d;
                break;
            }
        }
        $out = [];
        $last = $alert['last_night'] ?? null;
        foreach ($days as $d) {
            if ($d >= $target) {
                break;
            }
            if ($last !== null && $d > $last && self::darkEnd($nights[$d] ?? null, $d, $tz) > $now) {
                $out[] = $d;
            }
        }
        $out[] = $target;
        return $out;
    }

    private static function darkStart(?array $night, string $date, DateTimeZone $tz): DateTimeImmutable
    {
        $start = $night['dark'][0]['start'] ?? null;
        return $start ? new DateTimeImmutable($start) : new DateTimeImmutable("$date 20:00", $tz);   // no darkness: white night
    }

    private static function darkEnd(?array $night, string $date, DateTimeZone $tz): DateTimeImmutable
    {
        $dark = $night['dark'] ?? [];
        return $dark ? new DateTimeImmutable($dark[count($dark) - 1]['end'])
                     : (new DateTimeImmutable("$date 04:00", $tz))->modify('+1 day');
    }

    /** "tonight" or "tomorrow" seen from the place itself. */
    public static function when(array $alert, string $night, DateTimeImmutable $now): string
    {
        $today = $now->setTimezone(new DateTimeZone($alert['city_timezone']))->format('Y-m-d');
        return $night > $today ? 'tomorrow' : 'tonight';
    }

    /** Remembers the newest night announced, see nightsToAnnounce(). */
    public static function markAnnounced(int $alertId, string $night): void
    {
        App::db()->run('UPDATE alerts SET last_night = ? WHERE id = ? AND (last_night IS NULL OR last_night < ?)',
            [$night, $alertId, $night]);
    }

    private static function hydrate(array $row): array
    {
        $row['prefs'] = Prefs::decode($row['prefs']);
        $row['enabled'] = (bool) $row['enabled'];
        return $row;
    }
}
