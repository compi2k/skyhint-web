<?php
declare(strict_types=1);

namespace PlanetAlerts;

/** Builds and sends every mail the app sends, in the recipient's language. */
final class Emails
{
    public static function verify(array $user, string $token): bool
    {
        return self::inLang($user['lang'], fn () => self::deliver($user['email'], [
            'subject'    => I18n::t('mail.verify.subject', ['app' => App::name()]),
            'heading'    => I18n::t('mail.verify.heading'),
            'paragraphs' => [I18n::t('mail.verify.text')],
            'button'     => ['label' => I18n::t('mail.verify.button'),
                             'url' => App::url('/verify', ['token' => $token])],
            'after'      => [I18n::t('mail.verify.ignore')],
        ]));
    }

    public static function alreadyRegistered(array $user): bool
    {
        return self::inLang($user['lang'], fn () => self::deliver($user['email'], [
            'subject'    => I18n::t('mail.exists.subject', ['app' => App::name()]),
            'heading'    => I18n::t('mail.exists.heading'),
            'paragraphs' => [I18n::t('mail.exists.text')],
            'button'     => ['label' => I18n::t('mail.exists.button'), 'url' => App::url('/forgot')],
            'after'      => [I18n::t('mail.verify.ignore')],
        ]));
    }

    public static function reset(array $user, string $token): bool
    {
        return self::inLang($user['lang'], fn () => self::deliver($user['email'], [
            'subject'    => I18n::t('mail.reset.subject', ['app' => App::name()]),
            'heading'    => I18n::t('mail.reset.heading'),
            'paragraphs' => [I18n::t('mail.reset.text')],
            'button'     => ['label' => I18n::t('mail.reset.button'),
                             'url' => App::url('/reset', ['token' => $token])],
            'after'      => [I18n::t('mail.reset.ignore')],
        ]));
    }

    /**
     * One mail bundling tonight's events from all of a user's alerts.
     * @param array $groups list of ['alert' => alert row, 'events' => events for that place]
     */
    public static function alerts(array $user, array $groups): bool
    {
        return self::inLang($user['lang'], function () use ($user, $groups) {
            // usually every event is for the same night ("tonight" or "tomorrow night");
            // if not, each event says which night it is about
            $nights = [];
            foreach ($groups as $g) {
                foreach ($g['events'] as $e) {
                    $nights[$e['when'] . '|' . $e['date']] = true;
                }
            }
            $mixed = count($nights) > 1;
            $titles = [];
            $sections = [];
            foreach ($groups as $g) {
                $blocks = [];
                foreach ($g['events'] as $e) {
                    $t = Format::eventTitle($e);
                    $titles[] = $t;
                    $blocks[] = ['title' => $mixed
                        ? I18n::t('when.block', ['when' => I18n::t('when.' . $e['when']), 'date' => Format::date($e['date']), 'title' => $t])
                        : $t, 'lines' => Format::eventLines($e)];
                }
                $sections[] = [
                    'title'  => self::placeTitle($g['alert']),
                    'blocks' => $blocks,
                    'after'  => [Format::moon($g['events'][0]['moon'])],
                ];
            }
            $first = $groups[0]['events'][0];
            $when = I18n::t('when.' . $first['when']);
            $subject = count($titles) === 1
                ? I18n::t('mail.alert.subject_one', ['when' => $when, 'title' => $titles[0]])
                : I18n::t('mail.alert.subject_many', ['when' => $when, 'title' => $titles[0], 'n' => count($titles) - 1]);
            $date = Format::date($first['date']);
            $single = count($groups) === 1;
            $heading = match (true) {
                $mixed  => I18n::t('mail.alert.heading_mixed'),
                $single => I18n::t('mail.alert.heading', ['when' => $when, 'city' => $groups[0]['alert']['city_name'], 'date' => $date]),
                default => I18n::t('mail.alert.heading_many', ['when' => $when, 'date' => $date, 'n' => count($groups)]),
            };
            return self::deliver($user['email'], [
                'subject'  => $subject,
                'heading'  => $heading,
                'sections' => $single ? [['title' => '', 'blocks' => $sections[0]['blocks'], 'after' => $sections[0]['after']]] : $sections,
                'after'    => [I18n::t('mail.alert.weather')],
            ], $user);
        });
    }

    /**
     * Weekly overview across all of a user's alerts.
     * @param array $places list of ['alert' => .., 'nights' => next 7 nights, 'events' => matching events]
     */
    public static function digest(array $user, array $places): bool
    {
        return self::inLang($user['lang'], function () use ($user, $places) {
            $sections = [];
            foreach ($places as $pl) {
                $blocks = [];
                foreach ($pl['alert']['prefs']['planets'] as $p) {
                    $visibleDays = [];
                    $sample = null;
                    foreach ($pl['nights'] as $n) {
                        $vis = $n['planets'][$p] ?? [];
                        if (!empty($vis['visible'])) {
                            $visibleDays[] = $n;
                            $sample ??= $vis;
                        }
                    }
                    if (!$visibleDays) {
                        $blocks[] = ['title' => Format::planet($p), 'lines' => [I18n::t('mail.digest.not_visible')]];
                        continue;
                    }
                    $blocks[] = ['title' => Format::planet($p), 'lines' => [
                        I18n::t('mail.digest.nights', ['n' => count($visibleDays), 'total' => count($pl['nights']),
                            'sky' => I18n::t('sky.' . Format::skyPart($sample))]),
                        I18n::t('mail.digest.example', ['date' => Format::date($visibleDays[0]['date'])])
                            . ' ' . Format::howTo($p, $sample),
                    ]];
                }
                if ($pl['events']) {
                    $lines = [];
                    foreach ($pl['events'] as $e) {
                        $lines[] = Format::date($e['date']) . ': ' . Format::eventTitle($e);
                    }
                    $blocks[] = ['title' => I18n::t('mail.digest.events'), 'lines' => $lines];
                }
                $sections[] = ['title' => self::placeTitle($pl['alert']), 'blocks' => $blocks,
                               'after' => [Format::moon($pl['nights'][0]['moon'])]];
            }
            $single = count($places) === 1;
            $city = $places[0]['alert']['city_name'];
            return self::deliver($user['email'], [
                'subject'  => $single ? I18n::t('mail.digest.subject', ['city' => $city])
                                      : I18n::t('mail.digest.subject_many', ['n' => count($places)]),
                'heading'  => $single ? I18n::t('mail.digest.heading', ['city' => $city])
                                      : I18n::t('mail.digest.heading_many'),
                'sections' => $single ? [['title' => '', 'blocks' => $sections[0]['blocks'], 'after' => $sections[0]['after']]] : $sections,
            ], $user);
        });
    }

    /** "Ferienhaus: Palma, Balearic Islands, ES" or just the city. */
    private static function placeTitle(array $alert): string
    {
        $city = Cities::label(Alerts::city($alert));
        return $alert['name'] !== '' ? $alert['name'] . ': ' . $city : $city;
    }

    public static function test(array $user): bool
    {
        return self::inLang($user['lang'], fn () => self::deliver($user['email'], [
            'subject'    => I18n::t('mail.test.subject', ['app' => App::name()]),
            'heading'    => I18n::t('mail.test.heading'),
            'paragraphs' => [I18n::t('mail.test.text', ['places' => implode(', ',
                array_map([Alerts::class, 'label'], Alerts::forUser((int) $user['id'], true))) ?: '-'])],
        ], $user));
    }

    /** Plain status report for the owner (English only). */
    public static function status(string $to, string $subject, array $lines): bool
    {
        return self::inLang('en', fn () => self::deliver($to, [
            'subject'    => $subject,
            'heading'    => $subject,
            'paragraphs' => $lines,
        ]));
    }

    public static function unsubscribeUrl(array $user): string
    {
        return App::url('/unsubscribe', ['u' => $user['id'], 't' => $user['unsubscribe_token']]);
    }

    /** Renders templates/emails/mail.php (HTML) and a plain-text twin, then sends. */
    private static function deliver(string $to, array $m, ?array $user = null): bool
    {
        if ($user !== null) {
            $m['unsubscribe_url'] = self::unsubscribeUrl($user);
            $m['settings_url'] = App::url('/alerts');
        }
        $html = View::partial('emails/mail', ['m' => $m]);
        $text = self::text($m);
        return Mailer::send($to, $m['subject'], $html, $text,
            $user !== null ? ['url' => $m['unsubscribe_url']] : []);
    }

    private static function text(array $m): string
    {
        $out = [$m['heading'], str_repeat('=', min(60, mb_strlen($m['heading']))), ''];
        foreach ($m['paragraphs'] ?? [] as $p) {
            $out[] = $p;
            $out[] = '';
        }
        if (!empty($m['button'])) {
            $out[] = $m['button']['label'] . ': ' . $m['button']['url'];
            $out[] = '';
        }
        foreach ($m['sections'] ?? [] as $sec) {
            if ($sec['title'] !== '') {
                $out[] = '## ' . $sec['title'];
                $out[] = '';
            }
            foreach ($sec['blocks'] as $b) {
                $out[] = '* ' . $b['title'];
                foreach ($b['lines'] as $l) {
                    $out[] = '  ' . $l;
                }
                $out[] = '';
            }
            foreach ($sec['after'] ?? [] as $p) {
                $out[] = $p;
                $out[] = '';
            }
        }
        foreach ($m['after'] ?? [] as $p) {
            $out[] = $p;
            $out[] = '';
        }
        $out[] = '-- ';
        $out[] = App::name() . ': ' . App::url('/');
        if (!empty($m['unsubscribe_url'])) {
            $out[] = I18n::t('mail.footer.settings') . ': ' . $m['settings_url'];
            $out[] = I18n::t('mail.footer.unsubscribe') . ': ' . $m['unsubscribe_url'];
        }
        return implode("\n", $out);
    }

    private static function inLang(string $lang, callable $fn): mixed
    {
        $prev = I18n::lang();
        I18n::set($lang);
        try {
            return $fn();
        } finally {
            I18n::set($prev);
        }
    }
}
