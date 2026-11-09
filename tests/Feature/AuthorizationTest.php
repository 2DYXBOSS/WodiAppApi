<?php

namespace Tests\Feature;

use App\Enums\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_client_sees_only_own_bookings(): void
    {
        $service = $this->service();
        [, $pro] = $this->barber($service);
        [$alice] = $this->client();
        [$bob] = $this->client();
        $aliceBooking = $this->book($alice, $service, $pro->id, '10:00')->json('id');

        $this->actingAsToken($alice)->getJson('/api/v1/me/bookings?scope=upcoming')->assertOk()->assertJsonCount(1);
        $this->actingAsToken($bob)->getJson('/api/v1/me/bookings?scope=upcoming')->assertOk()->assertJsonCount(0);
        $this->getJson("/api/v1/me/bookings/{$aliceBooking}")->assertNotFound();
        $this->postJson("/api/v1/me/bookings/{$aliceBooking}/cancel")->assertNotFound();
    }

    public function test_barber_sees_only_own_schedule_and_bookings(): void
    {
        $service = $this->service();
        [$barberA, $proA] = $this->barber($service);
        [$barberB] = $this->barber($service);
        [$client] = $this->client();
        $id = $this->book($client, $service, $proA->id, '10:00')->json('id');

        $this->actingAsToken($barberA)->getJson('/api/v1/pro/agenda?from='.self::DAY.'&to='.self::DAY)->assertOk()->assertJsonCount(1, 'bookings');
        $this->actingAsToken($barberB)->getJson('/api/v1/pro/agenda?from='.self::DAY.'&to='.self::DAY)->assertOk()->assertJsonCount(0, 'bookings');
        $this->getJson("/api/v1/pro/bookings/{$id}")->assertNotFound();
        $this->postJson("/api/v1/pro/bookings/{$id}/confirm")->assertNotFound();
    }

    public function test_roles_are_enforced_per_space(): void
    {
        [$client] = $this->client();
        [$barber] = $this->barber();
        $admin = $this->admin();

        $this->actingAsToken($client)->getJson('/api/v1/pro/dashboard')->assertForbidden();
        $this->getJson('/api/v1/admin/reservations')->assertForbidden();
        $this->actingAsToken($barber)->getJson('/api/v1/me/bookings')->assertForbidden();
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->actingAsToken($admin)->getJson('/api/v1/me/bookings')->assertForbidden();
        $this->getJson('/api/v1/pro/dashboard')->assertForbidden();
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_admin_permissions_follow_the_matrix(): void
    {
        $manager = $this->admin(Role::Manager);
        $this->actingAsToken($manager)->getJson('/api/v1/admin/reservations')->assertOk();
        $this->getJson('/api/v1/admin/payments')->assertOk();
        $this->postJson('/api/v1/admin/payments', [])->assertForbidden();
        $this->getJson('/api/v1/admin/services')->assertForbidden();
        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->getJson('/api/v1/admin/users')->assertForbidden();

        $admin = $this->admin(Role::Admin);
        $this->actingAsToken($admin)->getJson('/api/v1/admin/services')->assertOk();
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->getJson('/api/v1/admin/settings')->assertForbidden();

        $this->actingAsToken($this->admin())->getJson('/api/v1/admin/users')->assertOk();
        $this->getJson('/api/v1/admin/settings')->assertOk();
    }

    public function test_client_cannot_escalate_through_profile_update(): void
    {
        [$client] = $this->client();
        $this->actingAsToken($client)->patchJson('/api/v1/me', [
            'firstName' => 'Neo', 'lastName' => 'Client', 'phone' => '+2250799999999', 'birthDate' => '1990-05-01',
            'role' => 'super_admin', 'permissions' => ['users.manage'], 'email' => 'hack@example.com',
        ])->assertOk()->assertJsonPath('role', 'client')->assertJsonPath('firstName', 'Neo')
            ->assertJsonPath('email', $client->email)->assertJsonPath('birthDate', '1990-05-01');
        $this->assertFalse($client->fresh()->isAdmin());
        $this->assertSame('+2250799999999', $client->customer->fresh()->phone);
    }
}
