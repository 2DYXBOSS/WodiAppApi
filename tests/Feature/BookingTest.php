<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Absence;
use App\Models\Booking;
use App\Models\SalonClosure;
use App\Services\SettingsService;
use App\Support\SalonClock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BookingTest extends TestCase
{
    public function test_valid_booking_uses_server_price_and_records_snapshots_history_notifications(): void
    {
        $service = $this->service(['price' => 12000, 'duration_minutes' => 45]);
        [$barberUser, $pro] = $this->barber($service);
        [$client] = $this->client();

        $response = $this->actingAsToken($client)->postJson('/api/v1/me/bookings', [
            'serviceId' => $service->id, 'staffId' => $pro->id, 'date' => self::DAY, 'time' => '10:00',
            'location' => 'salon', 'notes' => 'Dégradé bas', 'amount' => 1, 'price' => 1,
        ])->assertCreated();

        $response->assertJsonStructure(['id', 'reference', 'status', 'date', 'time', 'endTime', 'durationMinutes', 'location', 'notes',
            'service' => ['id', 'name'], 'staff' => ['id', 'name', 'photo'], 'client', 'amount', 'amountPaid', 'paymentStatus',
            'depositAmount', 'allowedActions', 'cancelBlockedReason', 'cancellationReason', 'createdAt', 'history' => [['at', 'by', 'action', 'detail']]])
            ->assertJsonPath('status', 'pending')->assertJsonPath('amount', 12000)->assertJsonPath('endTime', '10:45')
            ->assertJsonPath('client', null)->assertJsonPath('allowedActions', ['cancel', 'reschedule']);

        $booking = Booking::query()->first();
        $this->assertSame($service->name, $booking->service_name_snapshot);
        $this->assertSame(12000, $booking->price_snapshot);
        $this->assertSame('2026-11-11 10:00', SalonClock::local($booking->starts_at)->format('Y-m-d H:i'));
        $this->assertSame(1, $booking->histories()->count());
        $this->assertSame('booking_request', $barberUser->notifications()->first()->data['type']);
        $this->assertSame('booking_received', $client->notifications()->first()->data['type']);

        // Le prix de la prestation change ensuite : la réservation garde son instantané.
        $service->update(['price' => 99999, 'name' => 'Renommée']);
        $this->getJson('/api/v1/me/bookings/'.$booking->id)->assertJsonPath('amount', 12000)->assertJsonPath('service.name', $booking->service_name_snapshot);
    }

    public function test_auto_confirm_setting_confirms_immediately(): void
    {
        app(SettingsService::class)->put('booking', ['autoConfirm' => true]);
        $service = $this->service();
        [, $pro] = $this->barber($service);
        [$client] = $this->client();
        $this->book($client, $service, $pro->id, '10:00')->assertCreated()->assertJsonPath('status', 'confirmed');
    }

    public function test_inactive_or_unbookable_service_is_rejected(): void
    {
        $inactive = $this->service(['is_active' => false]);
        $noDuration = $this->service(['duration_minutes' => null]);
        [, $pro] = $this->barber($inactive);
        $pro->services()->attach($noDuration->id);
        [$client] = $this->client();

        $this->book($client, $inactive, $pro->id, '10:00')->assertStatus(409);
        $this->book($client, $noDuration, $pro->id, '10:00')->assertStatus(409);
    }

    public function test_slot_outside_hours_break_absence_closure_or_notice_is_rejected(): void
    {
        $service = $this->service(['duration_minutes' => 60]);
        [, $pro] = $this->barber($service);
        $pro->breaks()->create(['iso_day' => 3, 'start_time' => '13:00', 'end_time' => '14:00']);
        [$client] = $this->client();

        $this->book($client, $service, $pro->id, '18:30')->assertStatus(409); // dépasse 19:00
        $this->book($client, $service, $pro->id, '12:30')->assertStatus(409); // chevauche la pause
        $this->book($client, $service, $pro->id, '10:00', '2026-11-16')->assertStatus(409); // lundi : salon fermé
        $this->book($client, $service, $pro->id, '09:00', '2026-11-09')->assertStatus(409); // passé / délai de prévenance

        SalonClosure::query()->create(['date' => '2026-11-12', 'label' => 'Fermeture', 'is_closed' => true]);
        $this->book($client, $service, $pro->id, '10:00', '2026-11-12')->assertStatus(409);

        Absence::query()->create(['professional_id' => $pro->id, 'type' => 'conge', 'start_date' => '2026-11-13', 'end_date' => '2026-11-13', 'status' => 'pending']);
        $this->book($client, $service, $pro->id, '10:00', '2026-11-13')->assertStatus(409);

        $this->book($client, $service, $pro->id, '14:00')->assertCreated();
    }

    public function test_slots_respect_bookings_breaks_and_unavailabilities(): void
    {
        $service = $this->service(['duration_minutes' => 60]);
        [$barber, $pro] = $this->barber($service);
        [$client] = $this->client();
        $this->book($client, $service, $pro->id, '10:00')->assertCreated();
        $pro->unavailabilities()->create(['date' => self::DAY, 'start_time' => '15:00', 'end_time' => '16:00']);

        $times = collect($this->actingAsToken($client)
            ->getJson("/api/v1/availability/slots?serviceId={$service->id}&staffId={$pro->id}&date=".self::DAY)
            ->assertOk()->json())->pluck('time');

        $this->assertContains('09:00', $times);
        $this->assertNotContains('09:15', $times); // chevaucherait 10:00
        $this->assertNotContains('10:00', $times);
        $this->assertContains('11:00', $times);
        $this->assertNotContains('15:00', $times);
        $this->assertContains('18:00', $times);
        $this->assertNotContains('18:15', $times);

        $days = $this->getJson("/api/v1/availability/days?serviceId={$service->id}&staffId=any&from=2026-11-15&to=2026-11-16")->assertOk()->json();
        $this->assertSame([['date' => '2026-11-15', 'available' => true], ['date' => '2026-11-16', 'available' => false]], $days);
    }

    public function test_same_slot_twice_returns_409_and_overlap_is_refused(): void
    {
        $service = $this->service(['duration_minutes' => 60]);
        [, $pro] = $this->barber($service);
        [$alice] = $this->client();
        [$bob] = $this->client();

        $this->book($alice, $service, $pro->id, '10:00')->assertCreated();
        $this->book($bob, $service, $pro->id, '10:00')->assertStatus(409)
            ->assertJsonPath('message', 'Ce créneau vient d’être réservé ou n’est plus disponible. Choisissez-en un autre.');
        $this->book($bob, $service, $pro->id, '10:30')->assertStatus(409);
        $this->book($bob, $service, $pro->id, '11:00')->assertCreated();
    }

    public function test_any_staff_assigns_another_free_professional(): void
    {
        $service = $this->service();
        [, $first] = $this->barber($service);
        [, $second] = $this->barber($service);
        [$alice] = $this->client();
        [$bob] = $this->client();
        [$carol] = $this->client();

        $a = $this->book($alice, $service, null, '10:00')->assertCreated()->json('staff.id');
        $b = $this->book($bob, $service, null, '10:00')->assertCreated()->json('staff.id');
        $this->assertEqualsCanonicalizing([$first->id, $second->id], [$a, $b]);
        $this->book($carol, $service, null, '10:00')->assertStatus(409);
    }

    public function test_database_exclusion_constraint_blocks_concurrent_overlap(): void
    {
        $service = $this->service();
        [, $pro] = $this->barber($service);
        [, $customer] = $this->client();
        $row = fn (string $start) => [
            'customer_id' => $customer->id, 'professional_id' => $pro->id, 'service_id' => $service->id,
            'starts_at' => SalonClock::toUtc(self::DAY, $start), 'ends_at' => SalonClock::toUtc(self::DAY, $start)->addHour(),
            'duration_minutes' => 60, 'service_name_snapshot' => 'X', 'status' => BookingStatus::Pending, 'payment_status' => 'pending',
        ];
        Booking::query()->create($row('10:00'));

        // Simule deux transactions concurrentes ayant passé les contrôles applicatifs : la base refuse la seconde.
        try {
            DB::transaction(fn () => Booking::query()->create($row('10:30')));
            $this->fail('Le chevauchement aurait dû être refusé par PostgreSQL.');
        } catch (QueryException $e) {
            $this->assertSame('23P01', $e->errorInfo[0]);
        }
        // Une réservation annulée libère le créneau.
        Booking::query()->first()->update(['status' => BookingStatus::Cancelled]);
        Booking::query()->create($row('10:30'));
        $this->assertSame(2, Booking::query()->count());
    }

    public function test_client_cancel_and_reschedule_rules(): void
    {
        $service = $this->service();
        [$barber, $pro] = $this->barber($service);
        [$client] = $this->client();
        $id = $this->book($client, $service, $pro->id, '10:00')->assertCreated()->json('id');

        $this->postJson("/api/v1/me/bookings/{$id}/reschedule", ['date' => self::DAY, 'time' => '15:00', 'staffId' => $pro->id])
            ->assertOk()->assertJsonPath('time', '15:00')->assertJsonPath('status', 'pending');
        $this->assertContains('booking_updated', $barber->notifications()->get()->pluck('data.type'));

        $this->postJson("/api/v1/me/bookings/{$id}/cancel", ['reason' => 'Empêchement'])
            ->assertOk()->assertJsonPath('status', 'cancelled')->assertJsonPath('allowedActions', [])
            ->assertJsonPath('cancellationReason', 'Empêchement');
        $this->postJson("/api/v1/me/bookings/{$id}/cancel")->assertStatus(409);

        // Moins de 24 h avant : annulation refusée avec la raison affichable.
        $this->travelTo(SalonClock::toUtc('2026-11-10', '06:00'));
        $soon = $this->book($client, $service, $pro->id, '15:00', '2026-11-10')->assertCreated();
        $soon->assertJsonPath('allowedActions', [])->assertJsonPath('cancelBlockedReason', 'Modification et annulation possibles jusqu’à 24 h avant le rendez-vous. Contactez le salon.');
        $this->postJson('/api/v1/me/bookings/'.$soon->json('id').'/cancel')->assertStatus(409);
    }

    public function test_status_transitions_by_professional_are_controlled(): void
    {
        $service = $this->service();
        [$barber, $pro] = $this->barber($service);
        [$client] = $this->client();
        $id = $this->book($client, $service, $pro->id, '10:00')->json('id');

        $this->actingAsToken($barber)->getJson("/api/v1/pro/bookings/{$id}")
            ->assertOk()->assertJsonPath('allowedActions', ['confirm', 'decline'])
            ->assertJsonPath('client.phone', $client->customer->phone);
        $this->postJson("/api/v1/pro/bookings/{$id}/start")->assertStatus(409); // pending → in_progress interdit
        $this->postJson("/api/v1/pro/bookings/{$id}/confirm")->assertOk()->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('allowedActions', []); // pas encore le jour J
        $this->assertContains('booking_confirmed', $client->notifications()->get()->pluck('data.type'));

        $this->travelTo(SalonClock::toUtc(self::DAY, '10:05'));
        $this->getJson("/api/v1/pro/bookings/{$id}")->assertJsonPath('allowedActions', ['start', 'complete', 'no_show']);
        $this->postJson("/api/v1/pro/bookings/{$id}/start")->assertOk()->assertJsonPath('status', 'in_progress');
        $this->postJson("/api/v1/pro/bookings/{$id}/complete")->assertOk()->assertJsonPath('status', 'completed');
        $this->postJson("/api/v1/pro/bookings/{$id}/no-show")->assertStatus(409);
        $this->assertDatabaseHas('loyalty_transactions', ['booking_id' => $id, 'type' => 'visit']);
    }

    public function test_professional_without_permission_gets_403(): void
    {
        $service = $this->service();
        [$barber, $pro] = $this->barber($service);
        $barber->roles->first()->revokePermissionTo('bookings.respond');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        [$client] = $this->client();
        $id = $this->book($client, $service, $pro->id, '10:00')->json('id');

        $this->actingAsToken($barber->fresh())->postJson("/api/v1/pro/bookings/{$id}/confirm")->assertStatus(403);
    }

    public function test_professional_unavailability_changes_client_slots_immediately(): void
    {
        $service = $this->service(['duration_minutes' => 60]);
        [$barber, $pro] = $this->barber($service);
        [$client] = $this->client();
        $this->actingAsToken($barber)->postJson('/api/v1/pro/unavailabilities', ['date' => self::DAY, 'start' => '09:00', 'end' => '12:00', 'reason' => 'RDV'])
            ->assertCreated()->assertJsonStructure(['id', 'date', 'start', 'end', 'reason']);

        $times = collect($this->actingAsToken($client)->getJson("/api/v1/availability/slots?serviceId={$service->id}&date=".self::DAY)->json())->pluck('time');
        $this->assertNotContains('09:00', $times);
        $this->assertSame('12:00', $times->first());
    }
}
