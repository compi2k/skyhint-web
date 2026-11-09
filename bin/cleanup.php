<?php
declare(strict_types=1);

/*
 * Housekeeping. Run once a day from cron:
 *   17 4 * * * php /path/to/skyhint-web/bin/cleanup.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use PlanetAlerts\App;
use PlanetAlerts\RateLimit;

$db = App::db();
$steps = [
    'expired tokens'             => 'DELETE FROM auth_tokens WHERE expires_at < UTC_TIMESTAMP()',
    'expired cache entries'      => 'DELETE FROM calc_cache WHERE expires_at < UTC_TIMESTAMP()',
    'unverified accounts > 7 d'  => 'DELETE FROM users WHERE email_verified_at IS NULL
                                     AND created_at < UTC_TIMESTAMP() - INTERVAL 7 DAY',
    'mail runs > 60 d'           => 'DELETE FROM mail_runs WHERE local_date < UTC_DATE() - INTERVAL 60 DAY',
    'status checks > 100 d'      => 'DELETE FROM status_checks WHERE checked_at < UTC_TIMESTAMP() - INTERVAL 100 DAY',
    'daily counters > 400 d'     => 'DELETE FROM stat_counters WHERE day < UTC_DATE() - INTERVAL 400 DAY',
    'sent log > 400 d'           => 'DELETE FROM sent_log WHERE sent_at < UTC_TIMESTAMP() - INTERVAL 400 DAY',
    'cities nobody uses'         => 'DELETE c FROM cities c LEFT JOIN alerts a ON a.city_id = c.id
                                     WHERE a.id IS NULL AND c.updated_at < UTC_TIMESTAMP() - INTERVAL 30 DAY
                                       AND c.id NOT IN (SELECT CAST(value AS UNSIGNED) FROM app_state WHERE name LIKE \'week\\_city:%\')',
];
foreach ($steps as $label => $sql) {
    $n = $db->run($sql)->rowCount();
    echo "$label: $n removed\n";
}
echo 'rate limit windows: ' . RateLimit::cleanup() . " removed\n";
App::log('cleanup done');
