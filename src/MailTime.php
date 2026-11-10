<?php
declare(strict_types=1);

namespace PlanetAlerts;

use DateTimeImmutable;
use DateTimeZone;

/**
 * When an account gets its mails: a rough time slot in a time zone the user
 * picks. Within the slot every account has its own fixed minute (from its ID),
 * so the mail server never sends everything at once. No promise beyond the slot.
 */
final class MailTime
{
    /** slot => [first hour, end hour) local time */
    public const SLOTS = [
        'morning'   => [7, 9],
        'midday'    => [12, 14],
        'afternoon' => [15, 17],
        'evening'   => [19, 21],
    ];
    public const DEFAULT_SLOT = 'afternoon';
    /** How long failed attempts are retried after the planned minute. */
    public const RETRY_HOURS = 5;

    public static function slot(mixed $slot): string
    {
        return is_string($slot) && isset(self::SLOTS[$slot]) ? $slot : self::DEFAULT_SLOT;
    }

    public static function validTimezone(mixed $tz): bool
    {
        return is_string($tz) && in_array($tz, DateTimeZone::listIdentifiers(), true);
    }

    /** The account's mail time zone; older accounts without one use their first place's. */
    public static function timezone(array $user, array $alerts): string
    {
        if (self::validTimezone($user['mail_tz'] ?? null)) {
            return $user['mail_tz'];
        }
        return Alerts::mailTimezone($alerts) ?? 'UTC';
    }

    /** Minute of the day (local) at which this account's mail is due. */
    public static function dueMinute(array $user): int
    {
        [$from, $to] = self::SLOTS[self::slot($user['mail_slot'] ?? null)];
        $span = ($to - $from) * 60 - 15;   // the job runs every 15 minutes: stay inside the slot
        return $from * 60 + (int) (crc32('mail-slot:' . $user['id']) % ($span + 1));
    }

    public static function minuteOfDay(DateTimeImmutable $local): int
    {
        return (int) $local->format('G') * 60 + (int) $local->format('i');
    }

    /** "afternoon, 15–17" in the current language. */
    public static function label(string $slot): string
    {
        [$from, $to] = self::SLOTS[self::slot($slot)];
        return I18n::t('slot.' . self::slot($slot), ['from' => $from, 'to' => $to]);
    }

    /** "Berlin" for Europe/Berlin, "Argentina/Buenos Aires" stays readable. */
    public static function zoneLabel(string $tz): string
    {
        $parts = explode('/', $tz, 2);
        return str_replace('_', ' ', $parts[1] ?? $parts[0]);
    }

    /** All time zones for a dropdown, grouped by region. @return array<string, array<string,string>> */
    public static function zonesGrouped(): array
    {
        $out = [];
        foreach (DateTimeZone::listIdentifiers() as $tz) {
            $region = str_contains($tz, '/') ? explode('/', $tz, 2)[0] : 'UTC';
            $out[$region][$tz] = self::zoneLabel($tz);
        }
        return $out;
    }
}
