<?php
declare(strict_types=1);

namespace PlanetAlerts\Controllers;

use PlanetAlerts\ApiKeys;
use PlanetAlerts\App;
use PlanetAlerts\Auth;
use PlanetAlerts\Emails;
use PlanetAlerts\Http;
use PlanetAlerts\I18n;
use PlanetAlerts\Prefs;
use PlanetAlerts\RateLimit;
use PlanetAlerts\Session;
use PlanetAlerts\View;

/** Account page: mail style, language, pause, test mail, API keys, password, deletion. */
final class Dashboard
{
    /** /dashboard from older links: the alerts are the main page now. */
    public static function legacy(): void
    {
        Http::redirect('/alerts', 301);
    }

    public static function show(): void
    {
        $user = Account::requireUser();
        $newKey = $_SESSION['new_api_key'] ?? null;
        unset($_SESSION['new_api_key']);

        View::render('account', [
            'title'  => I18n::t('account.title'),
            'u'      => $user,
            'style'  => Prefs::style($user['mail_style']),
            'slot'   => \PlanetAlerts\MailTime::slot($user['mail_slot'] ?? null),
            'tz'     => \PlanetAlerts\MailTime::timezone($user, \PlanetAlerts\Alerts::forUser((int) $user['id'])),
            'placeZones' => array_values(array_unique(array_column(\PlanetAlerts\Alerts::forUser((int) $user['id']), 'city_timezone'))),
            'keys'   => ApiKeys::forUser((int) $user['id']),
            'newKey' => $newKey,
            'paused' => $user['paused_until'] !== null && $user['paused_until'] >= gmdate('Y-m-d'),
        ]);
    }

    public static function saveSettings(): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        $style = Prefs::style(Http::post('style'));
        $lang = I18n::valid(Http::post('lang')) ? Http::post('lang') : $user['lang'];
        $mailEnabled = Http::post('mail_enabled') === '1' ? 1 : 0;
        $slot = \PlanetAlerts\MailTime::slot(Http::post('mail_slot'));
        $tz = \PlanetAlerts\MailTime::validTimezone(Http::post('mail_tz')) ? Http::post('mail_tz') : $user['mail_tz'];

        App::db()->run('UPDATE users SET mail_style = ?, lang = ?, mail_enabled = ?, mail_slot = ?, mail_tz = ? WHERE id = ?',
            [$style, $lang, $mailEnabled, $slot, $tz, $user['id']]);
        setcookie('lang', $lang, ['expires' => time() + 31536000, 'path' => '/',
            'secure' => Http::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        I18n::set($lang);
        Session::flash('success', I18n::t('dash.saved'));
        Http::redirect('/account');
    }

    public static function pause(): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        if (Http::post('resume') === '1') {
            App::db()->run('UPDATE users SET paused_until = NULL WHERE id = ?', [$user['id']]);
            Session::flash('success', I18n::t('dash.resumed'));
            Http::redirect('/account');
        }
        $until = Http::post('until');
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $until);
        $today = new \DateTimeImmutable('today');
        if (!$d || $d < $today || $d > $today->modify('+1 year')) {
            Session::flash('error', I18n::t('dash.pause_invalid'));
            Http::redirect('/account');
        }
        App::db()->run('UPDATE users SET paused_until = ? WHERE id = ?', [$d->format('Y-m-d'), $user['id']]);
        Session::flash('success', I18n::t('dash.paused', ['date' => \PlanetAlerts\Format::date($d->format('Y-m-d'))]));
        Http::redirect('/account');
    }

    public static function testMail(): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        if ($user['email_verified_at'] === null) {
            Session::flash('error', I18n::t('dash.verify_first'));
        } elseif (!RateLimit::hit('testmail:' . $user['id'], 3, 3600)) {
            Session::flash('error', I18n::t('error.too_many'));
        } elseif (Emails::test($user)) {
            Session::flash('success', I18n::t('dash.test_sent', ['email' => $user['email']]));
        } else {
            Session::flash('error', I18n::t('dash.test_failed'));
        }
        Http::redirect('/account');
    }

    public static function changePassword(): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        if (!RateLimit::hit('pwchange:' . $user['id'], 5, 900)
            || !Auth::attempt($user['email'], Http::post('current'))) {
            Session::flash('error', I18n::t('dash.password_wrong'));
            Http::redirect('/account#account');
        }
        if ($problem = Auth::passwordProblem(Http::post('password'))) {
            Session::flash('error', $problem);
            Http::redirect('/account#account');
        }
        Auth::setPassword((int) $user['id'], Http::post('password'));
        Session::login((int) $user['id']);
        Session::flash('success', I18n::t('dash.password_changed'));
        Http::redirect('/account#account');
    }

    public static function createKey(): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        if ($user['email_verified_at'] === null) {
            Session::flash('error', I18n::t('dash.verify_first'));
        } elseif (ApiKeys::countActive((int) $user['id']) >= ApiKeys::MAX_PER_USER) {
            Session::flash('error', I18n::t('dash.keys_max', ['n' => ApiKeys::MAX_PER_USER]));
        } else {
            $name = Http::post('name') !== '' ? Http::post('name') : I18n::t('dash.key_default_name');
            $_SESSION['new_api_key'] = ApiKeys::create((int) $user['id'], $name);
        }
        Http::redirect('/account#api');
    }

    public static function revokeKey(string $id): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        ApiKeys::revoke((int) $user['id'], (int) $id);
        Session::flash('success', I18n::t('dash.key_revoked'));
        Http::redirect('/account#api');
    }

    public static function delete(): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        if (!RateLimit::hit('delete:' . $user['id'], 5, 900)
            || !Auth::attempt($user['email'], Http::post('password'))) {
            Session::flash('error', I18n::t('dash.password_wrong'));
            Http::redirect('/account#account');
        }
        Auth::delete((int) $user['id']);
        Session::logout();
        View::render('message', ['title' => I18n::t('dash.deleted_title'), 'text' => I18n::t('dash.deleted_text')]);
    }
}
