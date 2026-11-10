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

    // Optional banner across the top of every page. Empty text: no banner.
    // style: info (blue), warning (amber), danger (red), construction (amber with stripes).
    // text and link text can be one string or one per language.
    'banner' => [
        'text'  => '',      // e.g. ['de' => 'Prototyp im Aufbau: Es kann noch hakeln.', 'en' => 'Prototype under construction: expect rough edges.']
        'style' => 'construction',
        'link'  => ['url' => '', 'text' => ''],   // optional, e.g. ['url' => '/status', 'text' => 'Status']
    ],

    // Optional visitor statistics with your own Matomo server. Leave url empty to switch off.
    // In Matomo, also set: Privacy > Anonymize data > mask 2 bytes of the IP, and delete
    // raw data after retention_days (Privacy > Anonymize data > Delete old raw data).
    'matomo' => [
        'url'            => '',     // e.g. 'https://stats.skyhint.app/'
        'site_id'        => 0,
        'retention_days' => 180,    // only shown on the privacy page; set the same in Matomo
    ],

    // Privacy page (/privacy). Fill in to match how YOU run the servers.
    'privacy' => [
        // Companies running servers or services your data lives on (processors, Art. 28 GDPR).
        // Rented servers count: name the hoster and sign its data processing agreement.
        // Empty list: the page says you run everything yourself.
        'processors' => [
            // ['name' => 'Hetzner Online GmbH', 'address' => 'Industriestr. 25, 91710 Gunzenhausen, Deutschland',
            //  'service' => ['de' => 'Server für Website, Datenbank, Mail und Berechnungen',
            //                'en' => 'Servers for website, database, mail and calculations']],
        ],
        'eu_only'       => true,   // true: says no data leaves the EU/EEA
        // Web server access logs. ip: full | shortened_daily (full IP for up to a day, then shortened,
        // e.g. KeyHelp's "anonymize IP addresses" option) | shortened | none
        'web_log'       => ['ip' => 'full', 'days' => 7],
        'mail_log_days' => 7,      // how long the mail server keeps its delivery log
        // Supervisory authority for the operator (optional), e.g. for Baden-Württemberg:
        // ['name' => 'Der Landesbeauftragte für den Datenschutz und die Informationsfreiheit Baden-Württemberg',
        //  'address' => "Lautenschlagerstraße 20\n70173 Stuttgart", 'url' => 'https://www.baden-wuerttemberg.datenschutz.de']
        'authority'     => [],
    ],

    // Legal notice (Impressum). Shown on /impressum and /legal-notice. Leave as is until you have the details.
    'imprint' => [
        'name'    => '[Name]',
        'address' => "[Street]\n[Postcode City]\n[Country]",
        'email'   => '[contact@example.com]',
        'telephone' => '',   // optional
        'extra'     => '',   // optional free text, e.g. VAT ID or register entry
    ],
];
