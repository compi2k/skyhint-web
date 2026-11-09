<?php
declare(strict_types=1);

namespace PlanetAlerts;

/** iCalendar feed of events (RFC 5545). */
final class Ics
{
    /** @param array $events events, optionally with 'place' set to prefix the title */
    public static function build(array $events): string
    {
        $host = parse_url(App::url('/'), PHP_URL_HOST) ?: 'skyhint.app';
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//' . App::name() . '//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::esc(App::name()),
            'REFRESH-INTERVAL;VALUE=DURATION:P1D',
        ];
        $stamp = gmdate('Ymd\THis\Z');
        foreach ($events as $e) {
            [$start, $end] = self::span($e);
            if ($start === null) {
                continue;
            }
            $desc = implode("\n", array_merge(Format::eventLines($e), [Format::moon($e['moon'])]));
            $title = (!empty($e['place']) ? $e['place'] . ': ' : '') . Format::eventTitle($e);
            array_push($lines,
                'BEGIN:VEVENT',
                'UID:' . self::esc($e['key'] . '@' . $host),
                'DTSTAMP:' . $stamp,
                'DTSTART:' . $start,
                'DTEND:' . $end,
                'SUMMARY:' . self::esc($title),
                'DESCRIPTION:' . self::esc($desc),
                'TRANSP:TRANSPARENT',
                'BEGIN:VALARM',
                'ACTION:DISPLAY',
                'DESCRIPTION:' . self::esc($title),
                'TRIGGER:-PT15M',
                'END:VALARM',
                'END:VEVENT');
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /** Event span: around the best viewing time of the first planet, one hour long. */
    private static function span(array $e): array
    {
        $vis = $e['visibility'][$e['planets'][0]] ?? [];
        $best = $vis['best']['time'] ?? null;
        if ($best === null) {
            return [null, null];
        }
        $t = (new \DateTimeImmutable($best))->setTimezone(new \DateTimeZone('UTC'));
        return [$t->modify('-30 minutes')->format('Ymd\THis\Z'), $t->modify('+30 minutes')->format('Ymd\THis\Z')];
    }

    private static function esc(string $s): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], $s);
    }

    /** Lines longer than 75 octets are folded. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $cur = '';
        foreach (mb_str_split($line) as $ch) {
            if (strlen($cur . $ch) > 74) {
                $out .= ($out === '' ? '' : "\r\n ") . $cur;
                $cur = '';
            }
            $cur .= $ch;
        }
        return $out . "\r\n " . $cur;
    }
}
