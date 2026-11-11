<?php
declare(strict_types=1);

namespace PlanetAlerts\Controllers;

use PlanetAlerts\App;
use PlanetAlerts\CalcException;
use PlanetAlerts\Cities;
use PlanetAlerts\Http;
use PlanetAlerts\I18n;
use PlanetAlerts\Prefs;
use PlanetAlerts\RateLimit;
use PlanetAlerts\SkyTrack;
use PlanetAlerts\Stats;
use PlanetAlerts\View;
use PlanetAlerts\WeekCity;

/** Public pages: no account needed. */
final class Pages
{
    public const CITY_DAYS = 60;

    public static function home(): void
    {
        View::render('home', self::homeData());
    }

    /** The start page needs no calc server: the city of the week is prepared by the mail job. */
    private static function homeData(array $extra = []): array
    {
        $week = WeekCity::forHome();
        return $extra + [
            'title' => App::name(),
            'week'  => $week ? $week['city'] : null,
            'sky'   => $week ? SkyTrack::prepare($week['track'], $week['city']) : null,
            'glance' => $week ? SkyTrack::glance($week['track']) : null,
        ];
    }

    /** Autocomplete for the city field (JSON). */
    public static function search(): void
    {
        if (!RateLimit::hit('search:' . Http::ip(), 60, 60)) {
            Http::json(['results' => []], 429, ['Retry-After: ' . RateLimit::retryAfter(60)]);
        }
        $q = mb_substr(Http::get('q'), 0, 80);
        if (mb_strlen($q) < 2) {
            Http::json(['results' => []]);
        }
        try {
            $results = App::calc()->searchCities($q, null, 8);
        } catch (CalcException) {
            Http::json(['results' => [], 'error' => 'unavailable'], 503);
        }
        Http::json(['results' => array_map(static fn ($c) => [
            'id'    => $c['id'],
            'label' => Cities::label($c),
        ], $results)]);
    }

    /** Fallback for the home form without JavaScript: go to the best match. */
    public static function find(): void
    {
        $id = (int) Http::get('city_id');
        if ($id > 0) {
            Http::redirect('/city/' . $id);
        }
        $q = mb_substr(Http::get('city'), 0, 80);
        if (mb_strlen($q) >= 2) {
            try {
                $r = App::calc()->searchCities($q, null, 1);
                if ($r) {
                    Http::redirect('/city/' . $r[0]['id']);
                }
            } catch (CalcException) {
                self::calcDown();
            }
        }
        View::render('home', self::homeData(['notFound' => $q]));
    }

    public static function city(string $id): void
    {
        if (!ctype_digit($id) || !RateLimit::hit('city:' . Http::ip(), 120, 60)) {
            View::notFound();
        }
        try {
            $city = Cities::ensure((int) $id);
            if (!$city) {
                View::notFound();
            }
            $tonight = Cities::currentNight($city);
            $end = (new \DateTimeImmutable($tonight))->modify('+' . (self::CITY_DAYS - 1) . ' days')->format('Y-m-d');
            $calc = App::calc();
            $night = $calc->nights((int) $city['id'], $tonight, $tonight)[0];
            $events = $calc->events((int) $city['id'], $tonight, $end);
            $track = $calc->track((int) $city['id'], $tonight);
        } catch (CalcException) {
            self::calcDown();
        }
        Stats::bump('city_pages');
        // moon_nearby is opt-in, so the public page leaves it out
        $events = array_values(array_filter($events, static fn ($e) => $e['type'] !== 'moon_nearby'));
        View::render('city', [
            'title'  => I18n::t('city.title', ['city' => $city['name']]),
            'city'   => $city,
            'night'  => $night,
            'sky'    => SkyTrack::prepare($track, $city),
            'events' => $events,
            'days'   => self::CITY_DAYS,
            'planets' => Prefs::PLANETS,
        ]);
    }

    public static function privacy(): void
    {
        View::render('privacy', ['title' => I18n::t('privacy.title')]);
    }

    public static function imprint(): void
    {
        View::render('imprint', ['title' => I18n::t('imprint.title'), 'imprint' => App::config('imprint', [])]);
    }

    public static function apiDocs(): void
    {
        View::render('api-docs', ['title' => I18n::t('apidocs.title')]);
    }

    public static function calcDown(): never
    {
        View::render('message', [
            'title' => I18n::t('error.calc_title'),
            'text'  => I18n::t('error.calc_text'),
        ], 503);
    }
}
