<?php
// Router for PHP's built-in web server, for local development only:
//   php -S localhost:8080 -t public bin/dev-router.php
// Serves files from public/ as they are and sends everything else to index.php,
// like the .htaccess rules do on Apache.
$file = $_SERVER['DOCUMENT_ROOT'] . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
