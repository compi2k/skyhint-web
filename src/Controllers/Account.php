<?php
declare(strict_types=1);

namespace PlanetAlerts\Controllers;

use PlanetAlerts\App;
use PlanetAlerts\Auth;
use PlanetAlerts\CalcException;
use PlanetAlerts\Cities;
use PlanetAlerts\Emails;
use PlanetAlerts\Http;
use PlanetAlerts\I18n;
use PlanetAlerts\RateLimit;
use PlanetAlerts\Session;
use PlanetAlerts\View;

/** Signup, verification, login, password reset, unsubscribe. */
final class Account
{
    private const VERIFY_TTL = 48 * 3600;
    private const RESET_TTL = 3600;

    public static function signupForm(): void
    {
        if (Auth::current()) {
            Http::redirect('/alerts');
        }
        $city = null;
        $id = (int) Http::get('city');
        if ($id > 0) {
            try {
                $city = Cities::ensure($id);
            } catch (CalcException) {
            }
        }
        View::render('signup', ['title' => I18n::t('signup.title'), 'city' => $city, 'old' => Session::takeOld()]);
    }

    public static function signup(): void
    {
        Session::checkCsrf();
        $email = Auth::normalizeEmail(Http::post('email'));
        $password = Http::post('password');
        $lang = I18n::valid(Http::post('lang')) ? Http::post('lang') : I18n::lang();
        $old = ['email' => $email, 'city_id' => Http::post('city_id'), 'city' => Http::post('city'), 'lang' => $lang];

        $fail = static function (string $msg) use ($old): never {
            Session::flash('error', $msg);
            Session::keepOld($old);
            Http::redirect('/signup');
        };

        if (!RateLimit::hit('signup:' . Http::ip(), 5, 3600)) {
            $fail(I18n::t('error.too_many'));
        }
        if (!Auth::validEmail($email)) {
            $fail(I18n::t('auth.email_invalid'));
        }
        if ($problem = Auth::passwordProblem($password)) {
            $fail($problem);
        }
        if (Http::post('privacy') !== '1') {
            $fail(I18n::t('signup.privacy_required'));
        }
        try {
            $city = self::resolveCity((int) Http::post('city_id'), Http::post('city'));
        } catch (CalcException) {
            Pages::calcDown();
        }
        if (!$city) {
            $fail(I18n::t('signup.city_required'));
        }

        $existing = Auth::findByEmail($email);
        if ($existing) {
            // Same answer as a new signup, so nobody can test which addresses are registered.
            if (RateLimit::hit('exists:' . $email, 2, 86400)) {
                Emails::alreadyRegistered($existing);
            }
        } else {
            $userId = Auth::register($email, $password, (int) $city['id'], $lang);
            $user = Auth::find($userId);
            Emails::verify($user, Auth::createToken($userId, 'verify', self::VERIFY_TTL));
        }
        View::render('message', [
            'title' => I18n::t('signup.check_title'),
            'text'  => I18n::t('signup.check_text', ['email' => $email]),
        ]);
    }

    /** City from the autocomplete id, or the best match for the typed name (no-JS fallback). */
    public static function resolveCity(int $id, string $typed): ?array
    {
        if ($id > 0) {
            return Cities::ensure($id);
        }
        if (mb_strlen($typed) >= 2) {
            $r = App::calc()->searchCities(mb_substr($typed, 0, 80), null, 1);
            return $r ? Cities::ensure((int) $r[0]['id']) : null;
        }
        return null;
    }

    public static function verify(): void
    {
        $userId = Auth::consumeToken(Http::get('token'), 'verify');
        if (!$userId) {
            View::render('message', [
                'title' => I18n::t('verify.failed_title'),
                'text'  => I18n::t('verify.failed_text'),
            ], 400);
        }
        Auth::markVerified($userId);
        \PlanetAlerts\Stats::bump('verifications');
        Session::login($userId);
        Session::flash('success', I18n::t('verify.done'));
        Http::redirect('/alerts');
    }

    public static function resendVerification(): void
    {
        Session::checkCsrf();
        $user = self::requireUser();
        if ($user['email_verified_at'] === null && RateLimit::hit('resend:' . $user['id'], 3, 3600)) {
            Emails::verify($user, Auth::createToken((int) $user['id'], 'verify', self::VERIFY_TTL));
            Session::flash('success', I18n::t('verify.resent'));
        } else {
            Session::flash('error', I18n::t('error.too_many'));
        }
        Http::redirect('/alerts');
    }

    public static function loginForm(): void
    {
        if (Auth::current()) {
            Http::redirect('/alerts');
        }
        View::render('login', ['title' => I18n::t('login.title'), 'old' => Session::takeOld()]);
    }

    public static function login(): void
    {
        Session::checkCsrf();
        $email = Auth::normalizeEmail(Http::post('email'));
        if (!RateLimit::hit('login-ip:' . Http::ip(), 20, 900) || !RateLimit::hit('login:' . $email, 5, 900)) {
            Session::flash('error', I18n::t('login.locked'));
            Session::keepOld(['email' => $email]);
            Http::redirect('/login');
        }
        $user = Auth::attempt($email, Http::post('password'));
        if (!$user) {
            Session::flash('error', I18n::t('login.failed'));
            Session::keepOld(['email' => $email]);
            Http::redirect('/login');
        }
        Session::login((int) $user['id']);
        if (!in_array(I18n::lang(), [$user['lang']], true)) {
            setcookie('lang', $user['lang'], ['expires' => time() + 31536000, 'path' => '/',
                'secure' => Http::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        }
        Http::redirect('/alerts');
    }

    public static function logout(): void
    {
        Session::checkCsrf();
        Session::logout();
        Http::redirect('/');
    }

    public static function forgotForm(): void
    {
        View::render('forgot', ['title' => I18n::t('forgot.title')]);
    }

    public static function forgot(): void
    {
        Session::checkCsrf();
        $email = Auth::normalizeEmail(Http::post('email'));
        if (RateLimit::hit('forgot-ip:' . Http::ip(), 5, 3600) && RateLimit::hit('forgot:' . $email, 3, 3600)) {
            $user = Auth::findByEmail($email);
            if ($user) {
                Emails::reset($user, Auth::createToken((int) $user['id'], 'reset', self::RESET_TTL));
            }
        }
        View::render('message', [
            'title' => I18n::t('forgot.sent_title'),
            'text'  => I18n::t('forgot.sent_text'),
        ]);
    }

    public static function resetForm(): void
    {
        View::render('reset', ['title' => I18n::t('reset.title'), 'token' => Http::get('token')]);
    }

    public static function reset(): void
    {
        Session::checkCsrf();
        $token = Http::post('token');
        $password = Http::post('password');
        if ($problem = Auth::passwordProblem($password)) {
            Session::flash('error', $problem);
            Http::redirect('/reset?token=' . urlencode($token));
        }
        $userId = Auth::consumeToken($token, 'reset');
        if (!$userId) {
            View::render('message', [
                'title' => I18n::t('verify.failed_title'),
                'text'  => I18n::t('reset.failed_text'),
            ], 400);
        }
        Auth::setPassword($userId, $password);
        Auth::markVerified($userId);  // the link proves the address works
        Session::login($userId);
        Session::flash('success', I18n::t('reset.done'));
        Http::redirect('/alerts');
    }

    private static function unsubscribeUser(): ?array
    {
        $id = (int) Http::get('u', Http::post('u'));
        $token = Http::get('t', Http::post('t'));
        if ($id <= 0 || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $user = Auth::find($id);
        return ($user && hash_equals($user['unsubscribe_token'], $token)) ? $user : null;
    }

    /** Shows a confirm button; link scanners in mail programs only ever GET this page. */
    public static function unsubscribeForm(): void
    {
        $user = self::unsubscribeUser();
        if (!$user) {
            View::notFound();
        }
        View::render('unsubscribe', ['title' => I18n::t('unsub.title'), 'u' => $user['id'],
            't' => $user['unsubscribe_token'], 'done' => false]);
    }

    /** Handles both the form and RFC 8058 one-click POSTs from mail programs (no CSRF token there). */
    public static function unsubscribe(): void
    {
        $user = self::unsubscribeUser();
        if (!$user) {
            View::notFound();
        }
        if ($user['mail_enabled']) {
            \PlanetAlerts\Stats::bump('unsubscribes');
        }
        App::db()->run('UPDATE users SET mail_enabled = 0 WHERE id = ?', [$user['id']]);
        View::render('unsubscribe', ['title' => I18n::t('unsub.title'), 'done' => true]);
    }

    public static function requireUser(): array
    {
        $user = Auth::current();
        if (!$user) {
            Http::redirect('/login');
        }
        return $user;
    }
}
