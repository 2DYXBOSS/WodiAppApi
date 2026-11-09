<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\SalonHour;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/**
 * Base de test : PostgreSQL `wodi_test` (jamais la base de développement/production).
 * Horloge figée au lundi 9 novembre 2026, 08:00 (Abidjan = UTC).
 * Salon : fermé le lundi, ouvert 09:00–20:00 du mardi au dimanche.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Mercredi (jour ouvré) utilisé dans les scénarios. */
    protected const DAY = '2026-11-11';

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.connections.pgsql.database') !== 'wodi_test') {
            $this->fail('Les tests doivent utiliser la base wodi_test.');
        }
        $now = CarbonImmutable::parse('2026-11-09 08:00:00', 'UTC');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        $this->seed(RolesAndPermissionsSeeder::class);
        foreach (range(1, 7) as $day) {
            SalonHour::query()->create(['iso_day' => $day, 'is_closed' => $day === 1,
                'open_time' => $day === 1 ? null : '09:00', 'close_time' => $day === 1 ? null : '20:00']);
        }
    }

    protected function service(array $attributes = []): Service
    {
        return Service::factory()->create($attributes);
    }

    /** @return array{0:User,1:Professional} */
    protected function barber(?Service $service = null, array $permissions = []): array
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Barber->value);
        if ($permissions) {
            $user->givePermissionTo($permissions);
        }
        $pro = Professional::factory()->withSchedule()->create(['user_id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name]);
        if ($service) {
            $pro->services()->attach($service->id);
        }

        return [$user->fresh(), $pro];
    }

    /** @return array{0:User,1:Customer} */
    protected function client(): array
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Client->value);
        $customer = Customer::factory()->create(['user_id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name]);

        return [$user->fresh(), $customer];
    }

    protected function admin(Role $role = Role::SuperAdmin): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user->fresh();
    }

    protected function actingAsToken(User $user): static
    {
        Sanctum::actingAs($user, ['access']);

        return $this;
    }

    protected function book(User $client, Service $service, ?string $staffId, string $time, string $date = self::DAY)
    {
        return $this->actingAsToken($client)->postJson('/api/v1/me/bookings', [
            'serviceId' => $service->id, 'staffId' => $staffId, 'date' => $date, 'time' => $time, 'location' => 'salon',
        ]);
    }
}
