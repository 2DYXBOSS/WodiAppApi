<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\SalonHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * ⚠️ DONNÉES DE DÉMONSTRATION — fictives, marquées `isPlaceholder`, jamais en production.
 * Tarifs, durées et horaires ne sont PAS les valeurs officielles de Maison WODI.
 * Comptes de test (mot de passe : « Demo1234 ») : admin@demo.wodi, manager@demo.wodi,
 * barbier@demo.wodi, client@demo.wodi.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo1234';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder est interdit en production.');
        }
        $this->call(RolesAndPermissionsSeeder::class);

        // Horaires de démonstration (à remplacer par les horaires officiels dans /admin/settings).
        foreach (range(1, 7) as $day) {
            SalonHour::query()->updateOrCreate(['iso_day' => $day], [
                'is_closed' => $day === 1, 'open_time' => $day === 1 ? null : '09:00', 'close_time' => $day === 1 ? null : '20:00',
            ]);
        }
        app(SettingsService::class)->put('general', ['description' => 'Contenu de démonstration — à remplacer.']);

        $categories = collect([
            ['Coupe', 'coupe', 'Précision et style', 'scissors', 1, 'images/demo/side-fade'],
            ['Barbe', 'barbe', 'Taille et soin', 'razor', 2, 'images/demo/beard-razor'],
            ['Formules', 'formules', 'Coupe + barbe', 'crown', 3, 'images/demo/scissor-cut'],
        ])->map(fn ($c) => ServiceCategory::query()->updateOrCreate(['slug' => $c[1]], [
            'name' => $c[0], 'tagline' => $c[2], 'icon' => $c[3], 'sort_order' => $c[4],
            'image' => $c[5], 'image_alt' => 'Photo d’illustration — '.$c[0],
            'description' => 'Catégorie de démonstration.', 'is_active' => true,
        ]))->keyBy('slug');

        $services = collect([
            ['coupe', 'Coupe classique (démo)', 'coupe-classique-demo', 45, 10000, true],
            ['coupe', 'Dégradé (démo)', 'degrade-demo', 60, 15000, true],
            ['barbe', 'Taille de barbe (démo)', 'taille-barbe-demo', 30, 7000, false],
            ['formules', 'Coupe + barbe (démo)', 'coupe-barbe-demo', 75, 18000, true],
            ['formules', 'Soin sur demande (démo)', 'soin-sur-demande-demo', null, null, false],
        ])->map(fn ($s, $i) => Service::query()->updateOrCreate(['slug' => $s[2]], [
            'category_id' => $categories[$s[0]]->id, 'name' => $s[1], 'duration_minutes' => $s[3], 'price' => $s[4],
            'available_at_home' => $s[5], 'available_in_salon' => true, 'is_active' => true, 'is_placeholder' => true,
            'description' => 'Prestation de démonstration (tarif et durée fictifs).', 'sort_order' => $i,
        ]));

        $admin = $this->user('admin@demo.wodi', 'Awa', 'Admin', Role::SuperAdmin);
        $this->user('manager@demo.wodi', 'Marc', 'Manager', Role::Manager);
        $barberUser = $this->user('barbier@demo.wodi', 'Koffi', 'Barbier', Role::Barber);
        $clientUser = $this->user('client@demo.wodi', 'Yao', 'Client', Role::Client, '+2250700000001');

        $barbers = collect([
            [$barberUser, 'Koffi', 'Barbier', ['Dégradés', 'Barbe']],
            [null, 'Ismaël', 'Démo', ['Coupes classiques']],
        ])->map(function ($b, $i) {
            $pro = Professional::query()->firstOrCreate(['first_name' => $b[1], 'last_name' => $b[2]], [
                'user_id' => $b[0]?->id, 'job_title' => 'Barbier', 'specialties' => $b[3], 'is_placeholder' => true,
                'bio' => 'Profil de démonstration.', 'sort_order' => $i,
            ]);
            foreach (range(1, 7) as $day) {
                $pro->workingHours()->updateOrCreate(['iso_day' => $day], [
                    'is_working' => $day !== 1, 'start_time' => $day === 1 ? null : '09:00', 'end_time' => $day === 1 ? null : '19:00',
                ]);
            }
            if (! $pro->breaks()->exists()) {
                foreach (range(2, 7) as $day) {
                    $pro->breaks()->create(['iso_day' => $day, 'start_time' => '13:00', 'end_time' => '14:00', 'reason' => 'Pause déjeuner']);
                }
            }

            return $pro;
        });
        foreach ($services as $service) {
            $service->professionals()->syncWithoutDetaching($barbers->pluck('id')->all());
        }

        Customer::query()->firstOrCreate(['user_id' => $clientUser->id], [
            'first_name' => 'Yao', 'last_name' => 'Client', 'phone' => '+2250700000001', 'email' => 'client@demo.wodi',
        ]);
        Customer::query()->firstOrCreate(['phone' => '+2250700000002'], ['first_name' => 'Client', 'last_name' => 'Comptoir (démo)']);

        $this->command?->info('Démo prête. Comptes *@demo.wodi / mot de passe « '.self::PASSWORD." » (super admin : {$admin->email}).");
    }

    private function user(string $email, string $first, string $last, Role $role, ?string $phone = null): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'first_name' => $first, 'last_name' => $last, 'password' => self::PASSWORD, 'phone' => $phone, 'email_verified_at' => now(),
        ]);
        $user->syncRoles([$role->value]);

        return $user;
    }
}
