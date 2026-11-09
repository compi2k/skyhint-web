<?php
declare(strict_types=1);

use PlanetAlerts\I18n;
use PlanetAlerts\Session;

/** Escape for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Translated string. */
function t(string $key, array $vars = []): string
{
    return I18n::t($key, $vars);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(Session::csrfToken()) . '">';
}

/** Asset URL with a version stamp so browsers pick up changes. */
function asset(string $file): string
{
    $path = APP_ROOT . '/public/assets/' . $file;
    $v = is_file($path) ? filemtime($path) : 0;
    return '/assets/' . $file . '?v=' . $v;
}
