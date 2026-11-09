<?php
declare(strict_types=1);

/*
 * Loaded by every entry point (public/index.php and bin/*.php).
 * No Composer needed: a tiny autoloader covers our code and PHPMailer.
 */

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $map = [
        'PlanetAlerts\\'          => APP_ROOT . '/src/',
        'PHPMailer\\PHPMailer\\'  => APP_ROOT . '/vendor/phpmailer/src/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

require APP_ROOT . '/src/helpers.php';

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit("Missing config/config.php. Copy config/config.sample.php to config/config.php and fill it in.\n");
}

\PlanetAlerts\App::boot(require $configFile);
