<?php
/*
 * Skyhint.app configuration.
 * Copy this file to config.php (same folder) and fill in the values.
 * Moving to a new domain later = change app.url and mail.from here, nothing else.
 */
return [
    'app' => [
        'name'         => 'Skyhint.app',
        // Public address without trailing slash. Used in links in emails.
        'url'          => 'https://skyhint.app',
        'default_lang' => 'de',                     // de or en
        // Receives the daily status mail of the mail job.
        'owner_email'  => 'you@example.com',
        // Time zone of the owner, for the status mail.
        'owner_timezone' => 'Europe/Berlin',
        // Random string, at least 32 characters. Generate one with:
        //   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
        'secret_key'   => 'CHANGE-ME',
        // Fallback for the start page if no city of the week can be found (GeoNames ID; 2950159 = Berlin).
        'home_city'    => 2950159,
        // Where the source code of this installation is published (AGPL-3.0: users of a
        // hosted copy must be able to get its source). Change it if you run a modified copy.
        'source_url'   => 'https://github.com/compi2k/skyhint-web',
        // Show detailed errors in the browser. Never true in production.
        'debug'        => false,
    ],

    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'skyhint',
        'user'     => 'skyhint',
        'password' => 'CHANGE-ME',
    ],

    'mail' => [
        'from'      => 'noreply@skyhint.app',
        'from_name' => 'Skyhint.app',
        // 'smtp' (recommended) or 'mail' (PHP mail() function)
        'transport' => 'smtp',
        'host'      => 'localhost',
        'port'      => 587,
        'secure'    => 'tls',                       // 'tls', 'ssl' or '' (none)
        'user'      => 'noreply@skyhint.app',
        'password'  => 'CHANGE-ME',
    ],

    'calc' => [
        'url'     => 'https://calc.skyhint.app',
        'secret'  => 'CHANGE-ME',                   // CALC_SECRET from the calc server
        'timeout' => 30,
    ],

    // Imprint (Impressum). Shown on /imprint. Leave as is until you have the details.
    'imprint' => [
        'name'    => '[Name]',
        'address' => "[Street]\n[Postcode City]\n[Country]",
        'email'   => '[contact@example.com]',
    ],
];
