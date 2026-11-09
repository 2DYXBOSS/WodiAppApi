<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Resources\AdminClientResource;
use App\Http\Resources\AdminPaymentResource;
use App\Http\Resources\AdminReservationResource;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Support\SalonClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** `GET /admin/dashboard?period=today|week|month|year` → `DashboardData` (même calcul que la démo Angular). */
class DashboardService
{
    private const MONTHS = ['Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];

    private const WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

    public function __construct(private readonly SettingsService $settings) {}

    public function build(string $period, Request $request): array
    {
        $today = SalonClock::today();
        [$from, $to] = $this->range($period, $today);
        [$startUtc, $endUtc] = SalonClock::dayBounds($from, $to);

        $inRange = Booking::query()->whereBetween('starts_at', [$startUtc, $endUtc])
            ->get(['id', 'starts_at', 'status', 'service_name_snapshot', 'price_snapshot', 'amount_paid']);
        $active = $inRange->filter(fn ($b) => $b->status !== BookingStatus::Cancelled);
        $paid = Payment::query()->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::Partial->value])
            ->whereBetween('paid_at', [$startUtc, $endUtc])->get(['amount', 'paid_at']);
        $buckets = $this->buckets($period, $from, $to);

        $series = fn ($items, callable $at, callable $value) => array_map(fn ($bucket) => [
            'label' => $bucket['label'],
            'value' => $items->filter(fn ($i) => $bucket['match'](SalonClock::local($at($i))))->sum($value),
        ], $buckets);

        $recentReservations = Booking::query()->with(['customer', 'professional', 'histories'])->latest()->limit(6)->get();
        $recentClients = Customer::query()->withCount(['bookings as reservations_count'])->latest()->limit(5)->get();
        $recentPayments = Payment::query()->with(['booking', 'customer'])->latest('paid_at')->limit(5)->get();

        return [
            'period' => $period,
            'kpis' => [
                'totalReservations' => $inRange->count(),
                'todayReservations' => Booking::query()->whereBetween('starts_at', SalonClock::dayBounds($today))
                    ->where('status', '!=', BookingStatus::Cancelled->value)->count(),
                'pending' => $inRange->where('status', BookingStatus::Pending)->count(),
                'confirmed' => $inRange->where('status', BookingStatus::Confirmed)->count(),
                'cancelled' => $inRange->where('status', BookingStatus::Cancelled)->count(),
                'clients' => Customer::query()->count(),
                'revenue' => (int) $paid->sum('amount'),
                'pendingPayments' => (int) $inRange
                    ->filter(fn ($b) => ! in_array($b->status, [BookingStatus::Cancelled, BookingStatus::NoShow], true))
                    ->sum(fn ($b) => max(0, (int) $b->price_snapshot - $b->amount_paid)),
            ],
            'reservationsSeries' => $series($active, fn ($b) => $b->starts_at, fn () => 1),
            'revenueSeries' => $series($paid, fn ($p) => $p->paid_at, fn ($p) => $p->amount),
            'byService' => $active->groupBy('service_name_snapshot')->map(fn ($g, $label) => ['label' => $label, 'value' => $g->count()])
                ->sortByDesc('value')->values()->all(),
            'byStatus' => array_map(fn (BookingStatus $s) => ['status' => $s->value, 'value' => $inRange->where('status', $s)->count()], BookingStatus::cases()),
            'recentReservations' => AdminReservationResource::collection($recentReservations)->resolve($request),
            'recentClients' => AdminClientResource::collection($recentClients)->resolve($request),
            'recentPayments' => AdminPaymentResource::collection($recentPayments)->resolve($request),
        ];
    }

    private function range(string $period, string $today): array
    {
        $d = CarbonImmutable::createFromFormat('Y-m-d', $today, SalonClock::tz());

        return match ($period) {
            'today' => [$today, $today],
            'week' => [$d->startOfWeek()->toDateString(), $d->startOfWeek()->addDays(6)->toDateString()],
            'month' => [$d->startOfMonth()->toDateString(), $d->endOfMonth()->toDateString()],
            'year' => [$d->startOfYear()->toDateString(), $d->endOfYear()->toDateString()],
        };
    }

    private function buckets(string $period, string $from, string $to): array
    {
        if ($period === 'today') {
            $hours = collect($this->settings->salonHours())->reject(fn ($h) => $h['closed']);
            $open = $hours->isEmpty() ? 9 : intdiv((int) $hours->min(fn ($h) => SalonClock::minutes($h['open'])), 60);
            $close = $hours->isEmpty() ? 19 : intdiv((int) $hours->max(fn ($h) => SalonClock::minutes($h['close'])) - 1, 60);

            return array_map(fn ($h) => ['label' => "{$h}h", 'match' => fn (CarbonImmutable $t) => $t->hour === $h], range($open, max($open, $close)));
        }
        if ($period === 'year') {
            return array_map(fn ($m) => ['label' => self::MONTHS[$m - 1], 'match' => fn (CarbonImmutable $t) => $t->month === $m], range(1, 12));
        }
        $out = [];
        for ($d = $from, $i = 0; $d <= $to; $d = SalonClock::addDays($d, 1), $i++) {
            $day = $d;
            $out[] = [
                'label' => $period === 'week' ? self::WEEKDAYS[$i] : (string) (int) substr($d, 8, 2),
                'match' => fn (CarbonImmutable $t) => $t->toDateString() === $day,
            ];
        }

        return $out;
    }
}
