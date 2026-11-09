<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Heure métier du salon. Les dates sont stockées en UTC ; les dates `YYYY-MM-DD`
 * et heures `HH:mm` échangées avec les frontends sont exprimées dans le fuseau du salon.
 */
final class SalonClock
{
    public static function tz(): string
    {
        return config('wodi.timezone', 'Africa/Abidjan');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::tz());
    }

    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /** Date + heure locales → instant UTC. */
    public static function toUtc(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.substr($time, 0, 5), self::tz())->utc();
    }

    public static function local(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone(self::tz());
    }

    public static function localDate(CarbonInterface $instant): string
    {
        return self::local($instant)->toDateString();
    }

    public static function localTime(CarbonInterface $instant): string
    {
        return self::local($instant)->format('H:i');
    }

    /** "09:30" ou "09:30:00" → 570. */
    public static function minutes(?string $time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    public static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** "09:30:00" → "09:30" ; null conservé. */
    public static function hm(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    public static function isoDay(string $date): int
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, self::tz())->dayOfWeekIso;
    }

    public static function addDays(string $date, int $days): string
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, self::tz())->addDays($days)->toDateString();
    }

    public static function daysBetween(string $from, string $to): int
    {
        return (int) CarbonImmutable::createFromFormat('Y-m-d', $from, self::tz())->startOfDay()
            ->diffInDays(CarbonImmutable::createFromFormat('Y-m-d', $to, self::tz())->startOfDay(), false);
    }

    /** Bornes UTC d'une journée locale. */
    public static function dayBounds(string $from, ?string $to = null): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $from, self::tz())->startOfDay();
        $end = CarbonImmutable::createFromFormat('Y-m-d', $to ?? $from, self::tz())->addDay()->startOfDay();

        return [$start->utc(), $end->utc()];
    }

    public static function iso(?CarbonInterface $instant): ?string
    {
        return $instant?->copy()->utc()->toIso8601ZuluString();
    }
}
