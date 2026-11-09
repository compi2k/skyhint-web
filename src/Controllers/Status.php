<?php
declare(strict_types=1);

namespace PlanetAlerts\Controllers;

use PlanetAlerts\App;
use PlanetAlerts\CalcException;
use PlanetAlerts\Http;
use PlanetAlerts\I18n;
use PlanetAlerts\Prefs;
use PlanetAlerts\Stats;
use PlanetAlerts\View;

/**
 * Public status page. Everything here is a total or a share, never about one person.
 * Places are only listed once at least MIN_GROUP accounts watch them.
 */
final class Status
{
    public const MIN_GROUP = 3;
    private const CACHE_SECONDS = 60;
    private const CHECKS_PER_DAY = 96;   // the mail job runs every 15 minutes

    public static function page(): void
    {
        View::render('status', ['title' => I18n::t('status.title'), 's' => self::data()]);
    }

    /** Machine-readable summary for monitoring tools. */
    public static function json(): void
    {
        $s = self::data();
        header('Access-Control-Allow-Origin: *');
        Http::json([
            'status'        => $s['overall'],
            'generated_at'  => $s['generated_at'],
            'calc'          => $s['calc'],
            'mail_job'      => $s['job'],
            'uptime'        => $s['uptime'],
            'users'         => $s['users'],
            'alerts'        => ['total' => $s['alerts']['total'], 'enabled' => $s['alerts']['enabled']],
        ]);
    }

    public static function data(): array
    {
        $db = App::db();
        $row = $db->one("SELECT payload FROM calc_cache WHERE cache_key = 'status:page' AND expires_at > UTC_TIMESTAMP()");
        if ($row) {
            return json_decode($row['payload'], true);
        }
        $s = self::collect();
        $db->run('REPLACE INTO calc_cache (cache_key, payload, expires_at) VALUES (?, ?, ?)',
            ['status:page', json_encode($s), gmdate('Y-m-d H:i:s', time() + self::CACHE_SECONDS)]);
        return $s;
    }

    private static function collect(): array
    {
        $db = App::db();
        $v = static fn (string $sql, array $p = []) => (int) $db->value($sql, $p);

        // ---- health
        $t0 = microtime(true);
        $db->value('SELECT 1');
        $dbMs = (int) round((microtime(true) - $t0) * 1000);
        $calc = ['ok' => false, 'ms' => null];
        $t0 = microtime(true);
        try {
            $h = App::calc()->health();
            $calc = [
                'ok'              => ($h['status'] ?? '') === 'ok',
                'ms'              => (int) round((microtime(true) - $t0) * 1000),
                'version'         => $h['version'] ?? null,
                'cities'          => $h['cities_loaded'] ?? null,
                'cities_date'     => $h['cities_file_date'] ?? null,
                'engine'          => $h['astronomy_engine'] ?? null,
                'started_at'      => $h['started_at'] ?? null,
            ];
        } catch (CalcException) {
        }
        $lastRun = $db->value("SELECT value FROM app_state WHERE name = 'job_last_run'");
        $lastAgo = $lastRun ? (int) floor((time() - strtotime($lastRun . ' UTC')) / 60) : null;
        $runs24 = $db->all('SELECT status, COUNT(*) n FROM mail_runs WHERE updated_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY GROUP BY status');
        $runs24 = array_column($runs24, 'n', 'status');
        $job = [
            'last_run_minutes_ago' => $lastAgo,
            'ok'      => $lastAgo !== null && $lastAgo <= 30,
            'done'    => (int) ($runs24['done'] ?? 0),
            'failed'  => (int) ($runs24['failed'] ?? 0),
            'skipped' => (int) ($runs24['skipped'] ?? 0),
        ];

        // ---- uptime of the calc server, measured by every mail-job run
        $uptime = [];
        foreach ([1 => '24h', 7 => '7d', 30 => '30d'] as $days => $key) {
            $r = $db->one('SELECT COUNT(*) n, COALESCE(SUM(calc_ok), 0) ok FROM status_checks
                           WHERE checked_at >= UTC_TIMESTAMP() - INTERVAL ? DAY', [$days]);
            $uptime[$key] = (int) $r['n'] ? round($r['ok'] / $r['n'] * 100, 2) : null;
        }
        $checks = $db->all('SELECT DATE(checked_at) d, COUNT(*) n, SUM(calc_ok) ok FROM status_checks
                            WHERE checked_at >= UTC_DATE() - INTERVAL 89 DAY GROUP BY d');
        $checks = array_column($checks, null, 'd');
        $skipped = array_column($db->all("SELECT local_date d, COUNT(*) n FROM mail_runs WHERE status = 'skipped'
                            AND local_date >= UTC_DATE() - INTERVAL 89 DAY GROUP BY d"), 'n', 'd');
        $first = $db->value('SELECT DATE(MIN(checked_at)) FROM status_checks');
        $today = gmdate('Y-m-d');
        $days90 = [];
        for ($i = 89; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', time() - $i * 86400);
            $c = $checks[$d] ?? null;
            if (!$c) {
                $state = 'none';
            } else {
                $ratio = $c['ok'] / max(1, $c['n']);
                $complete = $d === $today || $d === $first || $c['n'] >= 0.9 * self::CHECKS_PER_DAY;
                $state = $ratio >= 0.99 && $complete && empty($skipped[$d]) ? 'ok' : ($ratio >= 0.9 ? 'partial' : 'down');
            }
            $days90[] = ['date' => $d, 'state' => $state, 'checks' => (int) ($c['n'] ?? 0),
                         'ok' => (int) ($c['ok'] ?? 0)];
        }
        $calcMsAvg = $db->one("SELECT COALESCE(AVG(calc_ms), 0) a FROM status_checks
                              WHERE calc_ok = 1 AND checked_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY");

        // ---- users
        $users = [
            'total'       => $v('SELECT COUNT(*) FROM users'),
            'verified'    => $v('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL'),
            'mail'        => $v('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL AND mail_enabled = 1'),
            'paused'      => $v('SELECT COUNT(*) FROM users WHERE paused_until >= UTC_DATE()'),
            'new_today'   => $v('SELECT COUNT(*) FROM users WHERE created_at >= UTC_DATE()'),
            'new_7d'      => $v('SELECT COUNT(*) FROM users WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY'),
            'new_30d'     => $v('SELECT COUNT(*) FROM users WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY'),
            'active_7d'   => $v('SELECT COUNT(*) FROM users WHERE last_login_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY'),
            'active_30d'  => $v('SELECT COUNT(*) FROM users WHERE last_login_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY'),
            'logins_30d'  => Stats::sum('logins', 30),
            'deleted_30d' => Stats::sum('deletions', 30),
            'unsub_30d'   => Stats::sum('unsubscribes', 30),
            'langs'       => array_column($db->all('SELECT lang, COUNT(*) n FROM users GROUP BY lang'), 'n', 'lang'),
        ];
        // signups per week, last 12 weeks
        $signupsDaily = Stats::daily('signups', 84);
        $weeks = [];
        foreach (array_chunk($signupsDaily, 7, true) as $chunk) {
            $weeks[array_key_first($chunk)] = array_sum($chunk);
        }
        $users['signups_weekly'] = $weeks;
        // total accounts over the last 30 days (from signup dates of current accounts)
        $growth = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', time() - $i * 86400);
            $growth[$d] = $v('SELECT COUNT(*) FROM users WHERE created_at < ? + INTERVAL 1 DAY', [$d]);
        }
        $users['growth'] = $growth;

        // ---- alerts
        $alertRows = $db->all('SELECT user_id, prefs, enabled FROM alerts');
        $planets = array_fill_keys(Prefs::PLANETS, 0);
        $types = array_fill_keys(array_keys(Prefs::EVENTS), 0);
        $perUser = [];
        $enabled = 0;
        foreach ($alertRows as $a) {
            $perUser[$a['user_id']] = ($perUser[$a['user_id']] ?? 0) + 1;
            if (!$a['enabled']) {
                continue;
            }
            $enabled++;
            $p = Prefs::decode($a['prefs']);
            foreach ($p['planets'] as $x) {
                $planets[$x]++;
            }
            foreach ($p['events'] as $x) {
                $types[$x]++;
            }
        }
        $dist = ['1' => 0, '2' => 0, '3-5' => 0, '6-10' => 0];
        foreach ($perUser as $n) {
            $dist[$n === 1 ? '1' : ($n === 2 ? '2' : ($n <= 5 ? '3-5' : '6-10'))]++;
        }
        arsort($planets);
        arsort($types);
        $alerts = [
            'total'    => count($alertRows),
            'enabled'  => $enabled,
            'per_user' => $perUser ? round(count($alertRows) / count($perUser), 1) : 0,
            'multi'    => count(array_filter($perUser, static fn ($n) => $n > 1)),
            'dist'     => $dist,
            'planets'  => $planets,
            'types'    => $types,
            'styles'   => array_column($db->all('SELECT mail_style s, COUNT(*) n FROM users GROUP BY mail_style'), 'n', 's'),
        ];

        // ---- mails
        $alertMails = Stats::daily('mails_alerts', 56);
        $digestMails = Stats::daily('mails_digest', 56);
        $weekday = array_fill(0, 7, 0);
        foreach ($alertMails as $d => $n) {
            $weekday[(int) gmdate('N', strtotime($d)) - 1] += $n + $digestMails[$d];
        }
        $mails = [
            'today'       => Stats::sum('mails_total', 1),
            '7d'          => Stats::sum('mails_total', 7),
            '30d'         => Stats::sum('mails_total', 30),
            'alerts_30d'  => Stats::sum('mails_alerts', 30),
            'digest_30d'  => Stats::sum('mails_digest', 30),
            'failed_30d'  => Stats::sum('mails_failed', 30),
            'found_30d'   => Stats::sum('events_found', 30),
            'sent_30d'    => Stats::sum('events_sent', 30),
            'daily'       => Stats::daily('mails_total', 30),
            'weekday'     => $weekday,
        ];

        // ---- API and calculations
        $hits = Stats::sum('cache_hits', 1);
        $miss = Stats::sum('cache_misses', 1);
        $hits30 = Stats::sum('cache_hits', 30);
        $miss30 = Stats::sum('cache_misses', 30);
        $req = Stats::sum('calc_requests', 1);
        $api = [
            'keys'          => $v('SELECT COUNT(*) FROM api_keys WHERE revoked_at IS NULL'),
            'key_users'     => $v('SELECT COUNT(DISTINCT user_id) FROM api_keys WHERE revoked_at IS NULL'),
            'used_7d'       => $v('SELECT COUNT(*) FROM api_keys WHERE revoked_at IS NULL AND last_used_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY'),
            'today'         => Stats::sum('api_requests', 1),
            '30d'           => Stats::sum('api_requests', 30),
            'daily'         => Stats::daily('api_requests', 30),
            'calc_today'    => $req,
            'calc_30d'      => Stats::sum('calc_requests', 30),
            'calc_avg_ms'   => $req ? (int) round(Stats::sum('calc_ms_total', 1) / $req) : null,
            'calc_max_ms'   => Stats::sum('calc_ms_max', 1) ?: null,
            'check_avg_ms'  => (int) round((float) $calcMsAvg['a']),
            'cache_rate'    => ($hits + $miss) ? round($hits / ($hits + $miss) * 100) : null,
            'cache_rate_30' => ($hits30 + $miss30) ? round($hits30 / ($hits30 + $miss30) * 100) : null,
            'cache_entries' => $v('SELECT COUNT(*) FROM calc_cache WHERE expires_at > UTC_TIMESTAMP()'),
            'city_pages'    => Stats::sum('city_pages', 1),
            'city_pages_30' => Stats::sum('city_pages', 30),
        ];

        // ---- places: counts always, names only for groups of MIN_GROUP or more
        $places = [
            'cities'     => $v('SELECT COUNT(DISTINCT city_id) FROM alerts'),
            'countries'  => $v('SELECT COUNT(DISTINCT c.country) FROM alerts a JOIN cities c ON c.id = a.city_id'),
            'timezones'  => $v('SELECT COUNT(DISTINCT c.timezone) FROM alerts a JOIN cities c ON c.id = a.city_id'),
            'top_cities' => $db->all('SELECT c.name, c.country, COUNT(DISTINCT a.user_id) n FROM alerts a
                JOIN cities c ON c.id = a.city_id GROUP BY c.id HAVING n >= ? ORDER BY n DESC, c.name LIMIT 10', [self::MIN_GROUP]),
            'top_countries' => $db->all('SELECT c.country, COUNT(DISTINCT a.user_id) n FROM alerts a
                JOIN cities c ON c.id = a.city_id GROUP BY c.country HAVING n >= ? ORDER BY n DESC LIMIT 10', [self::MIN_GROUP]),
        ];

        // ---- the sky: next big events worldwide
        $sky = [];
        try {
            $from = gmdate('Y-m-d');
            $sky = array_slice(App::calc()->globalEvents($from, gmdate('Y-m-d', time() + 365 * 86400)), 0, 8);
        } catch (CalcException) {
        }

        $overall = !$calc['ok'] ? 'down' : (!$job['ok'] || $job['skipped'] > 0 ? 'degraded' : 'ok');
        return [
            'generated_at' => gmdate('c'),
            'overall' => $overall,
            'db_ms'   => $dbMs,
            'calc'    => $calc,
            'job'     => $job,
            'uptime'  => $uptime,
            'days90'  => $days90,
            'users'   => $users,
            'alerts'  => $alerts,
            'mails'   => $mails,
            'api'     => $api,
            'places'  => $places,
            'sky'     => $sky,
        ];
    }
}
