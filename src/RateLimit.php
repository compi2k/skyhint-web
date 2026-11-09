<?php
declare(strict_types=1);

namespace PlanetAlerts;

/** Fixed-window counters in the database. */
final class RateLimit
{
    /** Counts a hit; returns false once the limit for the window is exceeded. */
    public static function hit(string $bucket, int $limit, int $windowSeconds): bool
    {
        $window = intdiv(time(), $windowSeconds) * $windowSeconds;
        // store a keyed hash instead of raw IP addresses or emails
        [$kind, $subject] = array_pad(explode(':', $bucket, 2), 2, '');
        $bucket = $kind . ':' . hash_hmac('sha256', $subject, (string) App::config('app.secret_key'));
        $db = App::db();
        $db->run('INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1)
                  ON DUPLICATE KEY UPDATE hits = hits + 1', [$bucket, $window]);
        $hits = (int) $db->value('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?',
            [$bucket, $window]);
        return $hits <= $limit;
    }

    /** Seconds until the current window ends (for Retry-After). */
    public static function retryAfter(int $windowSeconds): int
    {
        return $windowSeconds - (time() % $windowSeconds);
    }

    public static function cleanup(): int
    {
        // keep two days, enough for the daily API window
        return App::db()->run('DELETE FROM rate_limits WHERE window_start < ?', [time() - 2 * 86400])
            ->rowCount();
    }
}
