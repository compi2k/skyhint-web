<?php
declare(strict_types=1);

use PlanetAlerts\Controllers\Account;
use PlanetAlerts\Controllers\AlertsPage;
use PlanetAlerts\Controllers\Api;
use PlanetAlerts\Controllers\Dashboard;
use PlanetAlerts\Controllers\Pages;
use PlanetAlerts\Controllers\Status;
use PlanetAlerts\Http;
use PlanetAlerts\I18n;
use PlanetAlerts\Router;
use PlanetAlerts\Session;
use PlanetAlerts\View;

require dirname(__DIR__) . '/src/bootstrap.php';

$path = Http::path();
$isApi = str_starts_with($path, '/api/v1');

if (!$isApi) {
    Http::securityHeaders();
    Session::start();
}
I18n::set($isApi ? 'en' : I18n::detect());

$r = new Router();

// public pages
$r->get('/', [Pages::class, 'home']);
$r->get('/find', [Pages::class, 'find']);
$r->get('/search', [Pages::class, 'search']);
$r->get('/city/{id}', [Pages::class, 'city']);
$r->get('/privacy', [Pages::class, 'privacy']);
$r->get('/imprint', [Pages::class, 'imprint']);
$r->get('/api', [Pages::class, 'apiDocs']);
$r->get('/status', [Status::class, 'page']);
$r->get('/status.json', [Status::class, 'json']);

// accounts
$r->get('/signup', [Account::class, 'signupForm']);
$r->post('/signup', [Account::class, 'signup']);
$r->get('/verify', [Account::class, 'verify']);
$r->post('/verify/resend', [Account::class, 'resendVerification']);
$r->get('/login', [Account::class, 'loginForm']);
$r->post('/login', [Account::class, 'login']);
$r->post('/logout', [Account::class, 'logout']);
$r->get('/forgot', [Account::class, 'forgotForm']);
$r->post('/forgot', [Account::class, 'forgot']);
$r->get('/reset', [Account::class, 'resetForm']);
$r->post('/reset', [Account::class, 'reset']);
$r->get('/unsubscribe', [Account::class, 'unsubscribeForm']);
$r->post('/unsubscribe', [Account::class, 'unsubscribe']);

// my alerts
$r->get('/alerts', [AlertsPage::class, 'index']);
$r->get('/alerts/new', [AlertsPage::class, 'newForm']);
$r->post('/alerts', [AlertsPage::class, 'create']);
$r->get('/alerts/{id}/edit', [AlertsPage::class, 'editForm']);
$r->post('/alerts/{id}', [AlertsPage::class, 'update']);
$r->post('/alerts/{id}/toggle', [AlertsPage::class, 'toggle']);
$r->post('/alerts/{id}/delete', [AlertsPage::class, 'delete']);

// account
$r->get('/dashboard', [Dashboard::class, 'legacy']);
$r->get('/account', [Dashboard::class, 'show']);
$r->post('/account/settings', [Dashboard::class, 'saveSettings']);
$r->post('/account/pause', [Dashboard::class, 'pause']);
$r->post('/account/test-mail', [Dashboard::class, 'testMail']);
$r->post('/account/password', [Dashboard::class, 'changePassword']);
$r->post('/account/keys', [Dashboard::class, 'createKey']);
$r->post('/account/keys/{id}/revoke', [Dashboard::class, 'revokeKey']);
$r->post('/account/delete', [Dashboard::class, 'delete']);

// API v1
$r->get('/api/v1/cities', [Api::class, 'cities']);
$r->get('/api/v1/sky/tonight', [Api::class, 'skyTonight']);
$r->get('/api/v1/events', [Api::class, 'events']);
$r->get('/api/v1/events.ics', [Api::class, 'ics']);
$r->get('/api/v1/me', [Api::class, 'meGet']);
$r->add('PATCH', '/api/v1/me', [Api::class, 'mePatch']);
$r->get('/api/v1/alerts', [Api::class, 'alertsList']);
$r->post('/api/v1/alerts', [Api::class, 'alertCreate']);
$r->get('/api/v1/alerts/{id}', [Api::class, 'alertGet']);
$r->add('PATCH', '/api/v1/alerts/{id}', [Api::class, 'alertPatch']);
$r->add('DELETE', '/api/v1/alerts/{id}', [Api::class, 'alertDelete']);

try {
    if ($isApi && Http::method() === 'OPTIONS') {
        Api::guard();  // CORS preflight, answers 204
    }
    if ($isApi && !preg_match('#^/api/v1/(cities|sky/tonight|events|events\.ics|me|alerts|alerts/\d+)$#', $path)) {
        Api::notFound();
    }
    $r->dispatch(Http::method(), $path);
} catch (Throwable $e) {
    error_log((string) $e);
    if ($isApi) {
        Http::json(['error' => ['code' => 'server_error', 'message' => 'Internal error.']], 500);
    }
    View::render('message', [
        'title' => I18n::t('error.server_title'),
        'text'  => \PlanetAlerts\App::config('app.debug') ? $e->getMessage() : I18n::t('error.server_text'),
    ], 500);
}
