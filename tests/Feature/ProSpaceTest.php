<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProSpaceTest extends TestCase
{
    private function week(string $start = '09:00', string $end = '19:00', array $breaks = []): array
    {
        return array_map(fn ($d) => ['isoDay' => $d, 'working' => $d !== 1, 'start' => $d === 1 ? null : $start, 'end' => $d === 1 ? null : $end,
            'breaks' => $d === 1 ? [] : $breaks], range(1, 7));
    }

    public function test_schedule_must_fit_salon_hours_and_existing_bookings(): void
    {
        $service = $this->service();
        [$barber, $pro] = $this->barber($service);
        [$client] = $this->client();

        $this->actingAsToken($barber)->getJson('/api/v1/pro/schedule')->assertOk()->assertJsonStructure(['staffId', 'days' => [['isoDay', 'working', 'start', 'end', 'breaks']], 'updatedAt']);
        $this->putJson('/api/v1/pro/schedule', ['days' => $this->week('08:00')])->assertStatus(422);
        $this->putJson('/api/v1/pro/schedule', ['days' => $this->week(breaks: [['start' => '12:00', 'end' => '13:00', 'reason' => null], ['start' => '12:30', 'end' => '13:30', 'reason' => null]])])->assertStatus(422);
        $this->putJson('/api/v1/pro/schedule', ['days' => array_slice($this->week(), 0, 6)])->assertStatus(422);

        $this->book($client, $service, $pro->id, '17:00')->assertCreated();
        $this->actingAsToken($barber)->putJson('/api/v1/pro/schedule', ['days' => $this->week('09:00', '16:00')])->assertStatus(409);
        $this->putJson('/api/v1/pro/schedule', ['days' => $this->week(breaks: [['start' => '13:00', 'end' => '14:00', 'reason' => 'Déjeuner']])])
            ->assertOk()->assertJsonPath('days.2.breaks.0.reason', 'Déjeuner');
    }

    public function test_unavailability_and_absence_conflicts(): void
    {
        $service = $this->service();
        [$barber, $pro] = $this->barber($service);
        [$client] = $this->client();
        $this->book($client, $service, $pro->id, '10:00')->assertCreated();

        $this->actingAsToken($barber)->postJson('/api/v1/pro/unavailabilities', ['date' => self::DAY, 'start' => '10:30', 'end' => '11:30'])->assertStatus(409);
        $this->postJson('/api/v1/pro/unavailabilities', ['date' => '2026-11-01', 'start' => '10:00', 'end' => '11:00'])->assertStatus(422);
        $this->postJson('/api/v1/pro/absences', ['type' => 'conge', 'startDate' => self::DAY, 'endDate' => self::DAY])->assertStatus(409);
        $this->postJson('/api/v1/pro/absences', ['type' => 'vacances', 'startDate' => '2026-11-20', 'endDate' => '2026-11-21'])->assertStatus(422);
        $id = $this->postJson('/api/v1/pro/absences', ['type' => 'formation', 'startDate' => '2026-11-20', 'endDate' => '2026-11-21', 'reason' => 'Stage'])->assertCreated()->json('id');
        $this->postJson('/api/v1/pro/absences', ['type' => 'conge', 'startDate' => '2026-11-21', 'endDate' => '2026-11-22'])->assertStatus(409);
        $this->getJson('/api/v1/pro/absences')->assertJsonCount(1);
        $this->deleteJson("/api/v1/pro/absences/{$id}")->assertNoContent();
    }

    public function test_services_toggle_profile_and_activity(): void
    {
        $service = $this->service();
        [$barber] = $this->barber($service);

        $this->actingAsToken($barber)->getJson('/api/v1/pro/services')->assertOk()->assertJsonPath('canManage', true)->assertJsonPath('services.0.active', true);
        $this->postJson("/api/v1/pro/services/{$service->id}/toggle")->assertOk()->assertJsonPath('services.0.active', false);
        $this->getJson('/api/v1/catalog/services')->assertJsonPath('0.staffIds', [])->assertJsonPath('0.bookable', false);

        $this->getJson('/api/v1/pro/profile')->assertOk()->assertJsonPath('editableFields', ['phone', 'bio', 'specialties', 'photo']);
        $this->patchJson('/api/v1/pro/profile', ['bio' => 'Expert', 'specialties' => ['Barbe'], 'firstName' => 'Hack', 'jobTitle' => 'Directeur'])
            ->assertOk()->assertJsonPath('bio', 'Expert')->assertJsonPath('jobTitle', 'Barbier')->assertJsonPath('firstName', $barber->first_name);
        $this->getJson('/api/v1/pro/activity?period=week')->assertOk()->assertJsonPath('revenue', null);
        $this->getJson('/api/v1/pro/dashboard')->assertOk()->assertJsonStructure(['today' => ['total', 'upcoming', 'completed', 'pending'], 'todayBookings', 'nextBooking']);
    }

    public function test_notifications_preferences_and_devices(): void
    {
        [$client] = $this->client();
        $this->actingAsToken($client)->getJson('/api/v1/me/notification-preferences')->assertExactJson(['bookingUpdates' => true, 'reminders' => true, 'promotions' => false]);
        $this->putJson('/api/v1/me/notification-preferences', ['bookingUpdates' => false, 'reminders' => true, 'promotions' => true])->assertJsonPath('promotions', true);
        $this->postJson('/api/v1/me/devices', ['token' => 'ExponentPushToken[abc]', 'platform' => 'ios'])->assertNoContent();
        $this->assertDatabaseHas('device_tokens', ['token' => 'ExponentPushToken[abc]', 'user_id' => $client->id]);
        $this->deleteJson('/api/v1/me/devices/'.rawurlencode('ExponentPushToken[abc]'))->assertNoContent();
        $this->assertDatabaseCount('device_tokens', 0);
        $this->getJson('/api/v1/me/loyalty')->assertOk()->assertJsonStructure(['tier', 'vipSince', 'benefits', 'events', 'promotions']);
    }
}
