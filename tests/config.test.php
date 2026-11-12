<?php
// Configuration for local development and tests only.
//   cp tests/config.test.php config/config.php
// Matches the commands in CLAUDE.md, "Local test setup". No real data here.
return [
    'app' => [
        'name' => 'Skyhint.app', 'url' => 'http://localhost:8080', 'default_lang' => 'de',
        'owner_email' => 'owner@example.test', 'owner_timezone' => 'Europe/Berlin',
        'secret_key' => str_repeat('k', 40), 'debug' => true, 'home_city' => 100002,
    ],
    'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'planet_alerts', 'user' => 'pa', 'password' => 'pa-test'],
    'mail' => [
        'from' => 'noreply@example.test', 'from_name' => 'Skyhint.app', 'transport' => 'smtp',
        'host' => '127.0.0.1', 'port' => 1025, 'secure' => '', 'user' => '', 'password' => '',
    ],
    'calc' => ['url' => 'http://127.0.0.1:8100', 'secret' => 's3cret', 'timeout' => 30],
    'imprint' => ['name' => '[Name]', 'address' => "[Street]\n[City]", 'email' => 'owner@example.test'],
];
