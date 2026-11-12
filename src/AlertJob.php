<?php
declare(strict_types=1);

namespace PlanetAlerts;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The mail job. Runs every 15 minutes from cron.
 *
 * Per user: once their mail time has come (a slot such as 15–17 in a time
 * zone they choose, see MailTime), every enabled place is checked for the
 * next night that has not started there yet, and everything found goes out
 * in ONE mail with a section per place. On Sundays the weekly overview
 * follows the same pattern. If the calc service or the mail server fails,
 * later runs retry for a few hours, then the day is skipped and reported.
 * Each event is logged per place, so nothing is ever sent twice.
 */
final class AlertJob
{
    public const DIGEST_WEEKDAY = 0;   // Sunday
    public const STATUS_HOUR = 18;     // owner status mail, owner's local time

    private array $log = [];
    /** alert id => newest night announced in this run */
    private array $announced = [];

    public function __construct(private DateTimeImmutable $now, private bool $dryRun = false) {}

    public function run(): array
    {
        $this->heartbeat();
        $users = App::db()->all(
            'SELECT u.* FROM users u
             WHERE u.email_verified_at IS NOT NULL AND u.mail_enabled = 1
               AND EXISTS (SELECT 1 FROM alerts a WHERE a.user_id = u.id AND a.enabled = 1)');

        foreach ($users as $user) {
            $alerts = Alerts::forUser((int) $user['id'], true);
            $local = $this->now->setTimezone(new DateTimeZone(MailTime::timezone($user, $alerts)));
            $date = $local->format('Y-m-d');
            $late = MailTime::minuteOfDay($local) - MailTime::dueMinute($user);   // minutes past the due time
            if ($late < 0) {
                continue;
            }
            if ($user['paused_until'] !== null && $user['paused_until'] >= $date) {
                continue;
            }
            if (!$this->dryRun) {
                // failed attempts from earlier days will not be retried any more
                App::db()->run("UPDATE mail_runs SET status = 'skipped' WHERE user_id = ? AND status = 'failed' AND local_date < ?",
                    [$user['id'], $date]);
            }
            $style = Prefs::style($user['mail_style']);
            if (Prefs::wantsAlerts($style)) {
                $this->step($user, $alerts, $date, 'alerts', $late);
            }
            if (Prefs::wantsDigest($style) && (int) $local->format('w') === self::DIGEST_WEEKDAY) {
                $this->step($user, $alerts, $date, 'digest', $late);
            }
        }
        $this->maybeStatusMail();
        if (!$this->dryRun) {
            // the start page reads the city of the week from the cache only
            array_push($this->log, ...WeekCity::prepare($this->now));
        }
        return $this->log;
    }

    private function step(array $user, array $alerts, string $date, string $kind, int $late): void
    {
        $db = App::db();
        $run = $db->one('SELECT * FROM mail_runs WHERE user_id = ? AND local_date = ? AND kind = ?',
            [$user['id'], $date, $kind]);
        if ($run && in_array($run['status'], ['done', 'skipped'], true)) {
            return;
        }
        $label = "$kind user {$user['id']} $date";
        if ($late >= MailTime::RETRY_HOURS * 60 && !$run) {
            // never tried today: the account is new or its mail time was moved to an earlier slot after
            // that slot had passed. Nothing went wrong, so this is not reported as skipped. The next
            // mail still covers tonight if it is needed (see Alerts::nightsToAnnounce).
            $this->record((int) $user['id'], $date, $kind, 'done', 0, 'mail time had already passed today');
            return;
        }
        if ($late >= MailTime::RETRY_HOURS * 60) {
            $this->record((int) $user['id'], $date, $kind, 'skipped', 0,
                $run['message'] ?? 'not processed within ' . MailTime::RETRY_HOURS . ' hours');
            $this->log[] = "SKIPPED $label";
            return;
        }
        try {
            $this->announced = [];
            [$sent, $ok] = $kind === 'alerts' ? $this->alerts($user, $alerts) : $this->digest($user, $alerts, $date);
            if (!$ok) {
                $this->record((int) $user['id'], $date, $kind, 'failed', 0, 'mail could not be sent, see storage/logs/app.log');
                $this->log[] = "FAILED $label: mail could not be sent";
                return;
            }
            $this->record((int) $user['id'], $date, $kind, 'done', $sent, '');
            if (!$this->dryRun) {
                foreach ($this->announced as $alertId => $night) {
                    Alerts::markAnnounced($alertId, $night);
                }
            }
            if ($sent) {
                $this->log[] = "done $label: mail sent";
            }
        } catch (CalcException $e) {
            $this->record((int) $user['id'], $date, $kind, 'failed', 0, $e->getMessage());
            $this->log[] = "FAILED $label: " . $e->getMessage();
        }
    }

    /** @return array{int, bool} [mails sent, ok] */
    private function alerts(array $user, array $alerts): array
    {
        $db = App::db();
        $groups = [];
        foreach ($alerts as $a) {
            $nights = Alerts::nightsToAnnounce($a, $this->now);
            $this->announced[(int) $a['id']] = end($nights);
            $events = Prefs::filter($a['prefs'], App::calc()->events((int) $a['city_id'], $nights[0], end($nights)));
            $events = array_values(array_filter($events, static fn ($e) => in_array($e['date'], $nights, true)));
            $found = count($events);
            $events = array_values(array_filter($events, static fn ($e) => !$db->value(
                'SELECT 1 FROM sent_log WHERE user_id = ? AND event_key = ?', [$user['id'], "a{$a['id']}:{$e['key']}"])));
            if ($events) {
                foreach ($events as &$e) {
                    $e['when'] = Alerts::when($a, $e['date'], $this->now);
                    $e['shown_date'] = $e['date'];
                    // a morning-sky event of the night starting on day D happens on the morning of D+1:
                    // say "tomorrow morning, Thu 12 Nov" rather than "tonight, Wed 11 Nov"
                    if (Format::skyPart($e['visibility'][$e['planets'][0]] ?? []) === 'morning') {
                        $e['when'] = $e['when'] === 'tonight' ? 'morning_tomorrow' : 'morning_after';
                        $e['shown_date'] = (new DateTimeImmutable($e['date']))->modify('+1 day')->format('Y-m-d');
                    }
                }
                unset($e);
                $groups[] = ['alert' => $a, 'events' => $events];
            }
            if (!$this->dryRun && $found) {
                Stats::bump('events_found', $found);
            }
        }
        if (!$groups) {
            return [0, true];
        }
        if ($this->dryRun) {
            foreach ($groups as $g) {
                $this->log[] = "  would send {$user['email']} [" . Alerts::label($g['alert']) . ']: '
                    . implode(', ', array_map(static fn ($e) => "{$e['key']} ({$e['when']})", $g['events']));
            }
            return [1, true];
        }
        if (!Emails::alerts($user, $groups)) {
            return [0, false];
        }
        Stats::bump('mails_alerts');
        Stats::bump('events_sent', array_sum(array_map(static fn ($g) => count($g['events']), $groups)));
        foreach ($groups as $g) {
            foreach ($g['events'] as $e) {
                $db->run('INSERT IGNORE INTO sent_log (user_id, event_key, sent_at) VALUES (?, ?, ?)',
                    [$user['id'], "a{$g['alert']['id']}:{$e['key']}", Db::now()]);
            }
        }
        return [1, true];
    }

    /** @return array{int, bool} */
    private function digest(array $user, array $alerts, string $date): array
    {
        $calc = App::calc();
        $sections = [];
        foreach ($alerts as $a) {
            $nights = Alerts::nightsToAnnounce($a, $this->now);
            $start = end($nights);
            $end = (new DateTimeImmutable($start))->modify('+6 days')->format('Y-m-d');
            $sections[] = [
                'alert'  => $a,
                'nights' => $calc->nights((int) $a['city_id'], $start, $end),
                'events' => Prefs::filter($a['prefs'], $calc->events((int) $a['city_id'], $start, $end)),
            ];
        }
        if ($this->dryRun) {
            $this->log[] = "  would send digest to {$user['email']} (" . count($sections) . ' place(s))';
            return [1, true];
        }
        if (Emails::digest($user, $sections)) {
            Stats::bump('mails_digest');
            return [1, true];
        }
        return [0, false];
    }

    /** Every run notes that it ran and whether the calc server answered. */
    private function heartbeat(): void
    {
        if ($this->dryRun) {
            return;
        }
        $db = App::db();
        $t0 = microtime(true);
        try {
            App::calc()->health();
            $ok = 1;
            $ms = (int) round((microtime(true) - $t0) * 1000);
        } catch (CalcException) {
            $ok = 0;
            $ms = null;
        }
        $db->run('INSERT INTO status_checks (checked_at, calc_ok, calc_ms) VALUES (?, ?, ?)', [Db::now(), $ok, $ms]);
        $db->run("REPLACE INTO app_state (name, value) VALUES ('job_last_run', ?)", [Db::now()]);
    }

    private function record(int $userId, string $date, string $kind, string $status, int $mails, string $msg): void
    {
        if ($this->dryRun) {
            return;
        }
        App::db()->run(
            'INSERT INTO mail_runs (user_id, local_date, kind, status, attempts, mails_sent, message, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), attempts = attempts + 1,
                mails_sent = VALUES(mails_sent), message = VALUES(message), updated_at = VALUES(updated_at)',
            [$userId, $date, $kind, $status, $mails, mb_substr($msg, 0, 500), Db::now()]);
    }

    /** Daily report to the owner. A missing report means the job isn't running. */
    private function maybeStatusMail(): void
    {
        $owner = (string) App::config('app.owner_email', '');
        if ($owner === '' || $this->dryRun) {
            return;
        }
        $local = $this->now->setTimezone(new DateTimeZone((string) App::config('app.owner_timezone', 'UTC')));
        if ((int) $local->format('G') < self::STATUS_HOUR) {
            return;
        }
        $db = App::db();
        $today = $local->format('Y-m-d');
        if ($db->value("SELECT value FROM app_state WHERE name = 'status_mail'") === $today) {
            return;
        }
        $since = gmdate('Y-m-d H:i:s', time() - 86400);
        $runs = $db->all('SELECT status, COUNT(*) n, SUM(mails_sent) mails FROM mail_runs
                          WHERE updated_at >= ? GROUP BY status', [$since]);
        $by = array_column($runs, null, 'status');
        $problems = $db->all("SELECT * FROM mail_runs WHERE updated_at >= ? AND status IN ('failed','skipped')
                              ORDER BY updated_at DESC LIMIT 20", [$since]);
        try {
            $health = App::calc()->health();
            $calcLine = 'Calc service: ok, ' . $health['cities_loaded'] . ' cities loaded';
        } catch (CalcException $e) {
            $calcLine = 'Calc service: NOT REACHABLE (' . $e->getMessage() . ')';
        }
        $lines = [
            'Last 24 hours:',
            sprintf('Users checked: %d done, %d failed (retrying), %d skipped',
                $by['done']['n'] ?? 0, $by['failed']['n'] ?? 0, $by['skipped']['n'] ?? 0),
            'Mails sent: ' . (int) ($by['done']['mails'] ?? 0),
            sprintf('Users: %d total, %d verified, %d receiving mail. Alerts: %d (%d enabled)',
                $db->value('SELECT COUNT(*) FROM users'),
                $db->value('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL'),
                $db->value('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL AND mail_enabled = 1'),
                $db->value('SELECT COUNT(*) FROM alerts'),
                $db->value('SELECT COUNT(*) FROM alerts WHERE enabled = 1')),
            $calcLine,
        ];
        foreach ($problems as $p) {
            $lines[] = "Problem: {$p['kind']} user {$p['user_id']} {$p['local_date']} {$p['status']}: {$p['message']}";
        }
        $bad = ($by['skipped']['n'] ?? 0) > 0 || str_contains($calcLine, 'NOT');
        $subject = App::name() . ' status ' . $today . ($bad ? ': needs attention' : ': ok');
        if (Emails::status($owner, $subject, $lines)) {
            $db->run("REPLACE INTO app_state (name, value) VALUES ('status_mail', ?)", [$today]);
            $this->log[] = 'status mail sent';
        }
    }
}
