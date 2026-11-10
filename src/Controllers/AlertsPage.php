<?php
declare(strict_types=1);

namespace PlanetAlerts\Controllers;

use PlanetAlerts\Alerts;
use PlanetAlerts\App;
use PlanetAlerts\CalcException;
use PlanetAlerts\Cities;
use PlanetAlerts\Http;
use PlanetAlerts\I18n;
use PlanetAlerts\Prefs;
use PlanetAlerts\Session;
use PlanetAlerts\View;

/** "My alerts": list, create, edit, switch on/off, delete. */
final class AlertsPage
{
    private const PREVIEW_DAYS = 30;

    public static function index(): void
    {
        $user = Account::requireUser();
        $alerts = Alerts::forUser((int) $user['id']);

        // combined preview of the next 30 days across all enabled alerts
        $upcoming = [];
        $calcDown = false;
        foreach ($alerts as $a) {
            if (!$a['enabled']) {
                continue;
            }
            try {
                $from = Cities::currentNight(Alerts::city($a));
                $to = (new \DateTimeImmutable($from))->modify('+' . (self::PREVIEW_DAYS - 1) . ' days')->format('Y-m-d');
                foreach (Prefs::filter($a['prefs'], App::calc()->events((int) $a['city_id'], $from, $to)) as $e) {
                    $upcoming[] = ['event' => $e, 'alert' => $a];
                }
            } catch (CalcException) {
                $calcDown = true;
            }
        }
        usort($upcoming, static fn ($x, $y) => [$x['event']['date'], $x['alert']['id']] <=> [$y['event']['date'], $y['alert']['id']]);

        View::render('alerts', [
            'title'    => I18n::t('alerts.title'),
            'u'        => $user,
            'alerts'   => $alerts,
            'upcoming' => $upcoming,
            'calcDown' => $calcDown,
            'days'     => self::PREVIEW_DAYS,
            'max'      => Alerts::MAX_PER_USER,
            'mailTz'   => \PlanetAlerts\MailTime::timezone($user, $alerts),
            'mailSlot' => \PlanetAlerts\MailTime::slot($user['mail_slot'] ?? null),
            'paused'   => $user['paused_until'] !== null && $user['paused_until'] >= gmdate('Y-m-d'),
        ]);
    }

    public static function newForm(): void
    {
        $user = Account::requireUser();
        if (Alerts::count((int) $user['id']) >= Alerts::MAX_PER_USER) {
            Session::flash('error', I18n::t('alerts.max', ['n' => Alerts::MAX_PER_USER]));
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
        self::form(null, $city, Prefs::defaults());
    }

    public static function create(): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        if (Alerts::count((int) $user['id']) >= Alerts::MAX_PER_USER) {
            Session::flash('error', I18n::t('alerts.max', ['n' => Alerts::MAX_PER_USER]));
            Http::redirect('/alerts');
        }
        [$city, $prefs, $name] = self::input('/alerts/new');
        Alerts::create((int) $user['id'], (int) $city['id'], $prefs, $name);
        Session::flash('success', I18n::t('alerts.created', ['place' => $name !== '' ? $name : $city['name']]));
        Http::redirect('/alerts');
    }

    public static function editForm(string $id): void
    {
        $user = Account::requireUser();
        $a = Alerts::find((int) $user['id'], (int) $id);
        if (!$a) {
            View::notFound();
        }
        self::form($a, Alerts::city($a), $a['prefs']);
    }

    public static function update(string $id): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        $a = Alerts::find((int) $user['id'], (int) $id);
        if (!$a) {
            View::notFound();
        }
        [$city, $prefs, $name] = self::input("/alerts/{$a['id']}/edit", (int) $a['city_id']);
        Alerts::update((int) $user['id'], (int) $a['id'], (int) $city['id'], $prefs, $name);
        Session::flash('success', I18n::t('alerts.saved'));
        Http::redirect('/alerts');
    }

    public static function toggle(string $id): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        $a = Alerts::find((int) $user['id'], (int) $id);
        if ($a) {
            Alerts::setEnabled((int) $user['id'], (int) $a['id'], !$a['enabled']);
            Session::flash('success', I18n::t($a['enabled'] ? 'alerts.turned_off' : 'alerts.turned_on',
                ['place' => Alerts::label($a)]));
        }
        Http::redirect('/alerts#alert-' . (int) $id);
    }

    public static function delete(string $id): void
    {
        Session::checkCsrf();
        $user = Account::requireUser();
        $a = Alerts::find((int) $user['id'], (int) $id);
        if ($a) {
            Alerts::delete((int) $user['id'], (int) $a['id']);
            Session::flash('success', I18n::t('alerts.deleted', ['place' => Alerts::label($a)]));
        }
        Http::redirect('/alerts');
    }

    private static function form(?array $alert, ?array $city, array $prefs): never
    {
        $old = Session::takeOld();
        View::render('alert-form', [
            'title' => I18n::t($alert ? 'alerts.edit_title' : 'alerts.new_title'),
            'alert' => $alert,
            'city'  => $city,
            'prefs' => $old['prefs'] ?? $prefs,
            'name'  => $old['name'] ?? ($alert['name'] ?? ''),
        ]);
    }

    /** Reads and checks the form; on a problem, goes back to it. */
    private static function input(string $back, int $currentCity = 0): array
    {
        $prefs = Prefs::normalize([
            'planets' => Http::postArray('planets'),
            'events'  => Http::postArray('events'),
        ]);
        $name = mb_substr(Http::post('name'), 0, 80);
        $fail = static function (string $msg) use ($back, $prefs, $name): never {
            Session::flash('error', $msg);
            Session::keepOld(['prefs' => $prefs, 'name' => $name]);
            Http::redirect($back);
        };
        if (!$prefs['planets']) {
            $fail(I18n::t('alerts.need_planet'));
        }
        if (!$prefs['events']) {
            $fail(I18n::t('alerts.need_event'));
        }
        $cityId = (int) Http::post('city_id');
        try {
            $city = Account::resolveCity($cityId, Http::post('city'))
                ?? ($currentCity > 0 ? Cities::ensure($currentCity) : null);
        } catch (CalcException) {
            Pages::calcDown();
        }
        if (!$city) {
            $fail(I18n::t('signup.city_required'));
        }
        return [$city, $prefs, $name];
    }
}
