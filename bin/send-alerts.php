<?php
declare(strict_types=1);

// Mail job. Run every 15 minutes from cron:
//   */15 * * * * php /path/to/skyhint-web/bin/send-alerts.php
//
// Options for testing:
//   --now="2026-11-15 15:05"   pretend it is this time (UTC)
//   --dry-run                  show what would be sent, send nothing

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

$opts = getopt('', ['now:', 'dry-run']);
$now = new DateTimeImmutable($opts['now'] ?? 'now', new DateTimeZone('UTC'));

// one run at a time
$lock = fopen(APP_ROOT . '/storage/send-alerts.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "another run is still active\n");
    exit(0);
}

$job = new PlanetAlerts\AlertJob($now, isset($opts['dry-run']));
foreach ($job->run() as $line) {
    echo $line, "\n";
    PlanetAlerts\App::log('job: ' . $line);
}
