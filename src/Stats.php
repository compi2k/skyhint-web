<?php
declare(strict_types=1);

namespace PlanetAlerts;

/**
 * Daily counters for the public status page. Only totals per day are stored,
 * never who did what. Counting never breaks the action being counted.
 */
final class Stats
{
    public static function bump(string $name, int $by = 1): void
    {
        try {
            App::db()->run('INSERT INTO stat_counters (day, name, value) VALUES (UTC_DATE(), ?, ?)
                            ON DUPLICATE KEY UPDATE value = value + VALUES(value)', [$name, $by]);
        } catch (\Throwable $e) {
            App::log('stats: ' . $e->getMessage());
        }
    }

    /** Keeps the highest value seen today (e.g. slowest response). */
    public static function max(string $name, int $value): void
    {
        try {
            App::db()->run('INSERT INTO stat_counters (day, name, value) VALUES (UTC_DATE(), ?, ?)
                            ON DUPLICATE KEY UPDATE value = GREATEST(value, VALUES(value))', [$name, $value]);
        } catch (\Throwable $e) {
            App::log('stats: ' . $e->getMessage());
        }
    }

    /** Sum over the last $days days, today included. */
    public static function sum(string $name, int $days): int
    {
        return (int) App::db()->value('SELECT COALESCE(SUM(value), 0) FROM stat_counters
            WHERE name = ? AND day > UTC_DATE() - INTERVAL ? DAY', [$name, $days]);
    }

    /** [Y-m-d => value] for each of the last $days days, oldest first, zeros filled in. */
    public static function daily(string $name, int $days): array
    {
        $rows = App::db()->all('SELECT day, value FROM stat_counters
            WHERE name = ? AND day > UTC_DATE() - INTERVAL ? DAY', [$name, $days]);
        $by = array_column($rows, 'value', 'day');
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', time() - $i * 86400);
            $out[$d] = (int) ($by[$d] ?? 0);
        }
        return $out;
    }
}
