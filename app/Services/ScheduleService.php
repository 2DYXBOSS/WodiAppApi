<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking;
use App\Models\Professional;
use App\Models\ScheduleBreak;
use App\Models\WorkingHour;
use App\Support\SalonClock;
use Illuminate\Support\Facades\DB;

/** Horaires hebdomadaires (et pauses) d'un professionnel. */
class ScheduleService
{
    public const DAY_LABELS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    public function __construct(private readonly SettingsService $settings, private readonly ActivityLogger $logger) {}

    /** `WorkDay[7]` du mobile (avec pauses). */
    public function days(Professional $professional): array
    {
        $hours = $professional->workingHours()->get()->keyBy('iso_day');
        $breaks = $professional->breaks()->get()->groupBy('iso_day');

        return array_map(function (int $d) use ($hours, $breaks) {
            $h = $hours[$d] ?? null;
            $working = (bool) ($h?->is_working && $h->start_time && $h->end_time);

            return [
                'isoDay' => $d,
                'working' => $working,
                'start' => $working ? SalonClock::hm($h->start_time) : null,
                'end' => $working ? SalonClock::hm($h->end_time) : null,
                'breaks' => $working ? ($breaks[$d] ?? collect())->map(fn (ScheduleBreak $b) => [
                    'start' => SalonClock::hm($b->start_time), 'end' => SalonClock::hm($b->end_time), 'reason' => $b->reason,
                ])->values()->all() : [],
            ];
        }, range(1, 7));
    }

    public function updatedAt(Professional $professional): ?string
    {
        $latest = $professional->workingHours()->reorder()->latest('updated_at')->first()?->updated_at;

        return SalonClock::iso($latest);
    }

    /**
     * Enregistre les 7 jours. Contrôles : horaires dans ceux du salon, pauses dans les horaires
     * et sans chevauchement, aucune réservation à venir laissée hors des nouveaux horaires (409).
     *
     * @param  list<array{isoDay:int,working?:bool,start:?string,end:?string,breaks?:array}>  $days
     * @param  bool  $keepBreaks  true pour le back-office (le format admin ne transporte pas les pauses)
     */
    public function save(Professional $professional, array $days, bool $keepBreaks = false): void
    {
        $byDay = collect($days)->keyBy(fn ($d) => (int) $d['isoDay']);
        if ($byDay->keys()->sort()->values()->all() !== range(1, 7)) {
            throw ApiException::unprocessable('Les 7 jours de la semaine sont requis.', ['days' => '7 jours requis.']);
        }
        $existingBreaks = $professional->breaks()->get()->groupBy('iso_day');
        $salon = $this->settings->salonHours();

        $normalized = $byDay->map(function ($day, $isoDay) use ($keepBreaks, $existingBreaks) {
            $working = array_key_exists('working', $day) ? (bool) $day['working'] : ($day['start'] !== null && $day['end'] !== null);
            $breaks = $keepBreaks
                ? ($existingBreaks[$isoDay] ?? collect())->map(fn ($b) => ['start' => SalonClock::hm($b->start_time), 'end' => SalonClock::hm($b->end_time), 'reason' => $b->reason])->all()
                : ($day['breaks'] ?? []);

            return ['isoDay' => $isoDay, 'working' => $working, 'start' => $working ? $day['start'] : null,
                'end' => $working ? $day['end'] : null, 'breaks' => $working ? array_values($breaks) : []];
        });

        foreach ($normalized as $day) {
            if ($error = $this->validateDay($day, $salon[$day['isoDay']] ?? null)) {
                throw ApiException::unprocessable($error, ['days' => $error]);
            }
        }

        $conflicts = $this->bookingsOutside($professional, $normalized->all());
        if ($conflicts->isNotEmpty()) {
            $first = $conflicts->first();
            throw ApiException::conflict(sprintf(
                '%d réservation(s) à venir ne seraient plus couvertes (ex. %s le %s à %s). Déplacez-les avant de modifier ces horaires.',
                $conflicts->count(), $first->reference, SalonClock::localDate($first->starts_at), SalonClock::localTime($first->starts_at)
            ));
        }

        DB::transaction(function () use ($professional, $normalized) {
            foreach ($normalized as $day) {
                WorkingHour::query()->updateOrCreate(
                    ['professional_id' => $professional->id, 'iso_day' => $day['isoDay']],
                    ['is_working' => $day['working'], 'start_time' => $day['start'], 'end_time' => $day['end']],
                );
            }
            $professional->breaks()->delete();
            foreach ($normalized as $day) {
                foreach ($day['breaks'] as $b) {
                    $professional->breaks()->create([
                        'iso_day' => $day['isoDay'], 'start_time' => $b['start'], 'end_time' => $b['end'], 'reason' => $b['reason'] ?? null,
                    ]);
                }
            }
        });
        $this->logger->log('schedule.updated', $professional, ['days' => $normalized->values()->all()]);
    }

    public function validateDay(array $day, ?array $salon): ?string
    {
        $label = self::DAY_LABELS[$day['isoDay']];
        if (! $day['working']) {
            return null;
        }
        if (! $day['start'] || ! $day['end']) {
            return "{$label} : indiquez l’heure de début et de fin.";
        }
        $start = SalonClock::minutes($day['start']);
        $end = SalonClock::minutes($day['end']);
        if ($start >= $end) {
            return "{$label} : l’heure de fin doit suivre l’heure de début.";
        }
        if (! $salon || $salon['closed']) {
            return "{$label} : le salon est fermé ce jour-là.";
        }
        if ($start < SalonClock::minutes($salon['open']) || $end > SalonClock::minutes($salon['close'])) {
            return "{$label} : les horaires doivent rester dans ceux du salon ({$salon['open']} – {$salon['close']}).";
        }
        $breaks = collect($day['breaks'])->sortBy(fn ($b) => SalonClock::minutes($b['start']))->values();
        foreach ($breaks as $i => $b) {
            $bs = SalonClock::minutes($b['start']);
            $be = SalonClock::minutes($b['end']);
            if ($bs >= $be) {
                return "{$label} : une pause se termine avant de commencer.";
            }
            if ($bs < $start || $be > $end) {
                return "{$label} : chaque pause doit être comprise dans les horaires.";
            }
            $next = $breaks[$i + 1] ?? null;
            if ($next && SalonClock::minutes($next['start']) < $be) {
                return "{$label} : deux pauses se chevauchent.";
            }
        }

        return null;
    }

    /** Réservations actives à venir qui ne seraient plus couvertes par les nouveaux horaires. */
    private function bookingsOutside(Professional $professional, array $days)
    {
        return Booking::query()->where('professional_id', $professional->id)
            ->whereIn('status', BookingStatus::values(BookingStatus::active()))
            ->where('starts_at', '>=', now())->orderBy('starts_at')->get()
            ->filter(function (Booking $b) use ($days) {
                $day = $days[SalonClock::isoDay(SalonClock::localDate($b->starts_at))] ?? null;
                if (! $day || ! $day['working']) {
                    return true;
                }
                $start = SalonClock::minutes(SalonClock::localTime($b->starts_at));
                $end = $start + $b->duration_minutes;
                if ($start < SalonClock::minutes($day['start']) || $end > SalonClock::minutes($day['end'])) {
                    return true;
                }

                return collect($day['breaks'])->contains(fn ($p) => $start < SalonClock::minutes($p['end']) && SalonClock::minutes($p['start']) < $end);
            })->values();
    }
}
