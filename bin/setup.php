<?php
declare(strict_types=1);

/*
 * One-time setup and health check. Run after uploading:
 *   php bin/setup.php
 * Creates missing tables, then checks database, calc service and mail settings.
 * Add --mail-test=you@example.com to send a test mail.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

use PlanetAlerts\App;
use PlanetAlerts\CalcException;
use PlanetAlerts\Mailer;

$opts = getopt('', ['mail-test:']);
$ok = true;
$say = static function (bool $good, string $msg) use (&$ok): void {
    echo ($good ? '[ok]   ' : '[FAIL] ') . $msg . "\n";
    $ok = $ok && $good;
};

echo 'PHP ' . PHP_VERSION . "\n";
foreach (['pdo_mysql', 'curl', 'mbstring', 'openssl'] as $ext) {
    $say(extension_loaded($ext), "PHP extension $ext");
}
$say(defined('PASSWORD_ARGON2ID'), 'Argon2id password hashing (falls back to bcrypt if missing)');
$say(strlen((string) App::config('app.secret_key')) >= 32 && App::config('app.secret_key') !== 'CHANGE-ME',
    'app.secret_key set');
$say(str_starts_with((string) App::config('app.url'), 'https://'), 'app.url uses https: ' . App::config('app.url'));
$say(is_writable(APP_ROOT . '/storage/logs'), 'storage/logs writable');
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(APP_ROOT . '/bin/check-lang.php') . ' 2>&1', $langOut, $langCode);
$say($langCode === 0, 'text strings: ' . ($langCode === 0 ? $langOut[0] : implode('; ', array_slice($langOut, 0, 3))));

try {
    $db = App::db();
    $sql = file_get_contents(APP_ROOT . '/sql/schema.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $db->pdo()->exec($statement);
    }
    // v1 -> v2: each account's single city and settings become its first alert
    $hasCity = (int) $db->value("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'city_id'");
    if ($hasCity) {
        $hasStyle = (int) $db->value("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'mail_style'");
        if (!$hasStyle) {
            $db->pdo()->exec("ALTER TABLE users ADD COLUMN mail_style ENUM('alerts','digest','both')
                              NOT NULL DEFAULT 'alerts' AFTER lang");
        }
        $moved = $db->run("INSERT INTO alerts (user_id, name, city_id, prefs, enabled, created_at, updated_at)
            SELECT u.id, '', u.city_id,
                   JSON_OBJECT('planets', JSON_EXTRACT(u.prefs, '$.planets'), 'events', JSON_EXTRACT(u.prefs, '$.events')),
                   1, u.created_at, UTC_TIMESTAMP()
            FROM users u WHERE NOT EXISTS (SELECT 1 FROM alerts a WHERE a.user_id = u.id)")->rowCount();
        $db->run("UPDATE users SET mail_style = IF(JSON_UNQUOTE(JSON_EXTRACT(prefs, '$.style'))
                  IN ('alerts','digest','both'), JSON_UNQUOTE(JSON_EXTRACT(prefs, '$.style')), 'alerts')");
        // sent events are now remembered per alert
        $db->run("UPDATE sent_log s JOIN alerts a ON a.user_id = s.user_id
                  SET s.event_key = CONCAT('a', a.id, ':', s.event_key)
                  WHERE s.event_key NOT REGEXP '^(a[0-9]+|digest):'");
        $db->pdo()->exec('ALTER TABLE users DROP FOREIGN KEY fk_users_city');
        $db->pdo()->exec('ALTER TABLE users DROP COLUMN city_id, DROP COLUMN prefs');
        $db->pdo()->exec('DROP TABLE IF EXISTS job_runs');
        $say(true, "upgraded to multiple alerts: $moved account(s) moved to their first alert");
    }

    $tables = $db->all('SHOW TABLES');
    $say(count($tables) >= 10, 'database connected, ' . count($tables) . ' tables present');
} catch (Throwable $e) {
    $say(false, 'database: ' . $e->getMessage());
    exit(1);
}

try {
    $h = App::calc()->health();
    $say(($h['status'] ?? '') === 'ok', 'calc service reachable, ' . ($h['cities_loaded'] ?? 0) . ' cities');
    $r = App::calc()->searchCities('Berlin', null, 1);
    $say(!empty($r), 'calc secret accepted (search for Berlin works)');
    $problems = PlanetAlerts\WeekCity::prepare(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $week = PlanetAlerts\WeekCity::forHome();
    $say($week !== null, 'city of the week prepared: ' . ($week['city']['name'] ?? implode('; ', $problems)));
} catch (CalcException $e) {
    $say(false, 'calc service: ' . $e->getMessage());
}

if (!empty($opts['mail-test'])) {
    $sent = Mailer::send($opts['mail-test'], App::name() . ' setup test',
        '<p>Mail from ' . htmlspecialchars(App::url('/')) . ' works.</p>', 'Mail from ' . App::url('/') . ' works.');
    $say($sent, 'test mail to ' . $opts['mail-test'] . ' (details in storage/logs/app.log if it failed)');
}

echo $ok ? "\nAll good.\n" : "\nFix the items marked FAIL, then run this again.\n";
exit($ok ? 0 : 1);
