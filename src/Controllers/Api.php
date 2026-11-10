<?php
declare(strict_types=1);

namespace PlanetAlerts\Controllers;

use PlanetAlerts\Alerts;
use PlanetAlerts\ApiKeys;
use PlanetAlerts\App;
use PlanetAlerts\CalcException;
use PlanetAlerts\Cities;
use PlanetAlerts\Http;
use PlanetAlerts\I18n;
use PlanetAlerts\Ics;
use PlanetAlerts\Prefs;
use PlanetAlerts\RateLimit;

/** Public API v1. Every call needs a key from the dashboard. */
final class Api
{
    private const PER_MINUTE = 60;
    private const PER_DAY = 5000;
    private const MAX_EVENT_DAYS = 366;

    private static array $auth = [];

    /** Runs before every API route: CORS, key check, rate limits. */
    public static function guard(bool $keyInQuery = false): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 86400');
        if (Http::method() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $key = '';
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) {
            $key = $m[1];
        } elseif ($keyInQuery) {
            $key = Http::get('key');
        }
        if ($key === '') {
            self::error(401, 'missing_key', 'Send your API key as "Authorization: Bearer <key>".');
        }
        $found = ApiKeys::authenticate($key);
        if (!$found) {
            if (!RateLimit::hit('apifail:' . Http::ip(), 30, 60)) {
                self::error(429, 'rate_limited', 'Too many failed attempts.', RateLimit::retryAfter(60));
            }
            self::error(401, 'invalid_key', 'Unknown or revoked key, or the account is not verified.');
        }
        $id = $found['key_id'];
        if (!RateLimit::hit("api-min:$id", self::PER_MINUTE, 60)) {
            self::error(429, 'rate_limited', 'Limit: ' . self::PER_MINUTE . ' requests per minute.',
                RateLimit::retryAfter(60));
        }
        if (!RateLimit::hit("api-day:$id", self::PER_DAY, 86400)) {
            self::error(429, 'rate_limited', 'Limit: ' . self::PER_DAY . ' requests per day.',
                RateLimit::retryAfter(86400));
        }
        self::$auth = $found;
        \PlanetAlerts\Stats::bump('api_requests');
    }

    public static function cities(): void
    {
        self::guard();
        $q = mb_substr(Http::get('q'), 0, 80);
        if (mb_strlen($q) < 2) {
            self::error(400, 'bad_request', 'Parameter q needs at least 2 characters.');
        }
        $country = Http::get('country');
        $country = preg_match('/^[A-Za-z]{2}$/', $country) ? strtoupper($country) : null;
        self::respond(['results' => self::calc(fn () => App::calc()->searchCities($q, $country, 10))]);
    }

    public static function skyTonight(): void
    {
        self::guard();
        $city = self::city();
        $night = Cities::currentNight($city);
        $n = self::calc(fn () => App::calc()->nights((int) $city['id'], $night, $night))[0];
        $visible = [];
        foreach ($n['planets'] as $name => $p) {
            if ($p['visible']) {
                $visible[] = ['planet' => $name] + $p;
            }
        }
        self::respond(['city' => self::cityOut($city), 'date' => $n['date'], 'dark' => $n['dark'],
            'moon' => $n['moon'], 'visible' => $visible, 'all' => $n['planets']]);
    }

    public static function events(): void
    {
        self::guard();
        $city = self::city();
        [$from, $to] = self::range($city);
        $events = self::calc(fn () => App::calc()->events((int) $city['id'], $from, $to));
        $types = array_filter(explode(',', Http::get('types')));
        if ($types) {
            $events = array_values(array_filter($events, static fn ($e) => in_array($e['type'], $types, true)));
        }
        self::respond(['city' => self::cityOut($city), 'from' => $from, 'to' => $to, 'events' => $events]);
    }

    /**
     * Calendar feed of the key owner's alerts (all enabled ones, or ?alert=ID).
     * Calendar apps can't send headers, so the key may go in ?key=.
     */
    public static function ics(): void
    {
        self::guard(true);
        $user = self::$auth['user'];
        $alerts = Alerts::forUser((int) $user['id'], true);
        $only = (int) Http::get('alert');
        if ($only > 0) {
            $alerts = array_values(array_filter($alerts, static fn ($a) => (int) $a['id'] === $only));
        }
        $lang = Http::get('lang');
        I18n::set(I18n::valid($lang) ? $lang : $user['lang']);
        $events = [];
        foreach ($alerts as $a) {
            $from = Cities::currentNight(Alerts::city($a));
            $to = (new \DateTimeImmutable($from))->modify('+180 days')->format('Y-m-d');
            $found = Prefs::filter($a['prefs'], self::calc(fn () => App::calc()->events((int) $a['city_id'], $from, $to)));
            foreach ($found as $e) {
                $e['key'] = "a{$a['id']}-{$e['key']}";
                $e['place'] = count($alerts) > 1 ? Alerts::label($a) : null;
                $events[] = $e;
            }
        }
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="planets.ics"');
        echo Ics::build($events);
        exit;
    }

    public static function meGet(): void
    {
        self::guard();
        self::respond(self::meOut(self::$auth['user']));
    }

    /** Account-wide settings: style, lang, mail_enabled. Places and planets live in /alerts. */
    public static function mePatch(): void
    {
        self::guard();
        $user = self::$auth['user'];
        $in = self::body();
        $style = array_key_exists('style', $in) ? Prefs::style((string) $in['style']) : $user['mail_style'];
        $mail = array_key_exists('mail_enabled', $in) ? (int) (bool) $in['mail_enabled'] : (int) $user['mail_enabled'];
        $lang = I18n::valid($in['lang'] ?? null) ? $in['lang'] : $user['lang'];
        if (array_key_exists('mail_slot', $in) && (!is_string($in['mail_slot']) || !isset(\PlanetAlerts\MailTime::SLOTS[$in['mail_slot']]))) {
            self::error(400, 'invalid_mail_slot', 'mail_slot must be one of: ' . implode(', ', array_keys(\PlanetAlerts\MailTime::SLOTS)) . '.');
        }
        if (array_key_exists('mail_tz', $in) && !\PlanetAlerts\MailTime::validTimezone($in['mail_tz'])) {
            self::error(400, 'invalid_mail_tz', 'mail_tz must be an IANA time zone such as Europe/Berlin.');
        }
        $slot = \PlanetAlerts\MailTime::slot($in['mail_slot'] ?? $user['mail_slot']);
        $tz = $in['mail_tz'] ?? $user['mail_tz'];
        App::db()->run('UPDATE users SET mail_style = ?, mail_enabled = ?, lang = ?, mail_slot = ?, mail_tz = ? WHERE id = ?',
            [$style, $mail, $lang, $slot, $tz, $user['id']]);
        self::respond(self::meOut(\PlanetAlerts\Auth::find((int) $user['id'])));
    }

    public static function alertsList(): void
    {
        self::guard();
        self::respond(['max' => Alerts::MAX_PER_USER, 'alerts' => array_map([self::class, 'alertOut'],
            Alerts::forUser((int) self::$auth['user']['id']))]);
    }

    public static function alertGet(string $id): void
    {
        self::guard();
        self::respond(self::alertOut(self::ownAlert($id)));
    }

    public static function alertCreate(): void
    {
        self::guard();
        $uid = (int) self::$auth['user']['id'];
        if (Alerts::count($uid) >= Alerts::MAX_PER_USER) {
            self::error(409, 'limit_reached', 'At most ' . Alerts::MAX_PER_USER . ' alerts per account.');
        }
        $in = self::body();
        $city = self::cityFromBody($in, true);
        $prefs = self::prefsFromBody($in, Prefs::defaults());
        $id = Alerts::create($uid, (int) $city['id'], $prefs, (string) ($in['name'] ?? ''));
        if (array_key_exists('enabled', $in) && !$in['enabled']) {
            Alerts::setEnabled($uid, $id, false);
        }
        Http::json(self::alertOut(Alerts::find($uid, $id)), 201);
    }

    public static function alertPatch(string $id): void
    {
        self::guard();
        $uid = (int) self::$auth['user']['id'];
        $a = self::ownAlert($id);
        $in = self::body();
        $city = isset($in['city']) ? self::cityFromBody($in, true) : ['id' => $a['city_id']];
        $prefs = self::prefsFromBody($in, $a['prefs']);
        Alerts::update($uid, (int) $a['id'], (int) $city['id'], $prefs,
            array_key_exists('name', $in) ? (string) $in['name'] : $a['name']);
        if (array_key_exists('enabled', $in)) {
            Alerts::setEnabled($uid, (int) $a['id'], (bool) $in['enabled']);
        }
        self::respond(self::alertOut(Alerts::find($uid, (int) $a['id'])));
    }

    public static function alertDelete(string $id): void
    {
        self::guard();
        $a = self::ownAlert($id);
        Alerts::delete((int) self::$auth['user']['id'], (int) $a['id']);
        http_response_code(204);
        exit;
    }

    public static function notFound(): void
    {
        self::error(404, 'not_found', 'No such endpoint. See ' . App::url('/api'));
    }

    // ------------------------------------------------------------------ helpers

    private static function city(): array
    {
        $id = (int) Http::get('city');
        if ($id <= 0) {
            self::error(400, 'bad_request', 'Parameter city (a city id from /api/v1/cities) is required.');
        }
        $city = self::calc(fn () => Cities::ensure($id));
        if (!$city) {
            self::error(404, 'not_found', 'Unknown city.');
        }
        return $city;
    }

    private static function range(array $city): array
    {
        $from = Http::get('from') ?: Cities::currentNight($city);
        $fromD = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $to = Http::get('to') ?: ($fromD ? $fromD->modify('+89 days')->format('Y-m-d') : '');
        $toD = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if (!$fromD || !$toD || $fromD->format('Y-m-d') !== $from || $toD->format('Y-m-d') !== $to) {
            self::error(400, 'bad_request', 'Dates must be YYYY-MM-DD.');
        }
        $days = (int) $fromD->diff($toD)->format('%r%a') + 1;
        if ($days < 1 || $days > self::MAX_EVENT_DAYS) {
            self::error(400, 'bad_request', 'Range must be 1 to ' . self::MAX_EVENT_DAYS . ' days.');
        }
        return [$from, $to];
    }

    private static function calc(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (CalcException $e) {
            if ($e->getCode() === 404) {
                self::error(404, 'not_found', 'Unknown city.');
            }
            self::error(503, 'unavailable', 'Calculation service temporarily unavailable.', 60);
        }
    }

    private static function cityOut(array $c): array
    {
        return ['id' => (int) $c['id'], 'name' => $c['name'], 'region' => $c['region'],
                'country' => $c['country'], 'timezone' => $c['timezone']];
    }

    private static function meOut(array $u): array
    {
        return [
            'email'        => $u['email'],
            'lang'         => $u['lang'],
            'style'        => Prefs::style($u['mail_style']),
            'mail_enabled' => (bool) $u['mail_enabled'],
            'mail_slot'    => \PlanetAlerts\MailTime::slot($u['mail_slot'] ?? null),
            'mail_tz'      => \PlanetAlerts\MailTime::timezone($u, Alerts::forUser((int) $u['id'])),
            'paused_until' => $u['paused_until'],
            'alerts'       => array_map([self::class, 'alertOut'], Alerts::forUser((int) $u['id'])),
        ];
    }

    private static function alertOut(array $a): array
    {
        return [
            'id'      => (int) $a['id'],
            'name'    => $a['name'],
            'enabled' => $a['enabled'],
            'city'    => self::cityOut(Alerts::city($a) + ['region' => $a['city_region']]),
            'planets' => $a['prefs']['planets'],
            'events'  => $a['prefs']['events'],
        ];
    }

    private static function ownAlert(string $id): array
    {
        $a = ctype_digit($id) ? Alerts::find((int) self::$auth['user']['id'], (int) $id) : null;
        if (!$a) {
            self::error(404, 'not_found', 'No such alert.');
        }
        return $a;
    }

    private static function body(): array
    {
        $in = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($in)) {
            self::error(400, 'bad_request', 'Body must be a JSON object.');
        }
        return $in;
    }

    private static function cityFromBody(array $in, bool $required): array
    {
        $id = (int) ($in['city'] ?? 0);
        if ($id <= 0) {
            self::error(400, 'bad_request', 'Field city (a city id from /api/v1/cities) is required.');
        }
        $c = self::calc(fn () => Cities::ensure($id));
        if (!$c) {
            self::error(404, 'not_found', 'Unknown city.');
        }
        return $c;
    }

    private static function prefsFromBody(array $in, array $current): array
    {
        $p = Prefs::normalize([
            'planets' => $in['planets'] ?? $current['planets'],
            'events'  => $in['events'] ?? $current['events'],
        ]);
        if (!$p['planets'] || !$p['events']) {
            self::error(400, 'bad_request', 'An alert needs at least one known planet and one known event type.');
        }
        return $p;
    }

    private static function respond(array $data): never
    {
        Http::json($data);
    }

    private static function error(int $status, string $code, string $message, ?int $retryAfter = null): never
    {
        Http::json(['error' => ['code' => $code, 'message' => $message]], $status,
            $retryAfter ? ['Retry-After: ' . $retryAfter] : []);
    }
}
