<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Notifications\PasswordLinkMail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Contrat du back-office Angular (ADMIN-API.md / admin.models.ts). */
class AdminTest extends TestCase
{
    private const PAGE = ['items', 'total', 'page', 'pageSize'];

    public function test_reservation_crud_contract_and_rules(): void
    {
        $service = $this->service(['duration_minutes' => 30, 'price' => 8000]);
        [, $pro] = $this->barber($service);
        $customer = Customer::factory()->create();
        $this->actingAsToken($this->admin());

        $payload = ['clientId' => $customer->id, 'serviceId' => $service->id, 'staffId' => $pro->id, 'date' => self::DAY, 'time' => '09:00', 'location' => 'salon', 'notes' => null];
        $id = $this->postJson('/api/v1/admin/reservations', $payload)->assertCreated()
            ->assertJsonStructure(['id', 'reference', 'clientId', 'clientName', 'clientPhone', 'serviceId', 'serviceName', 'staffId', 'staffName',
                'date', 'time', 'durationMinutes', 'amount', 'amountPaid', 'status', 'paymentStatus', 'location', 'notes', 'createdAt', 'history'])
            ->json('id');
        $this->postJson('/api/v1/admin/reservations', $payload)->assertStatus(409)->assertJsonPath('errors.time', 'Créneau indisponible.');
        $this->postJson('/api/v1/admin/reservations', ['date' => '2026-11-01', 'time' => '10:00'] + $payload)->assertStatus(422);

        $this->getJson('/api/v1/admin/reservations?page=1&pageSize=10&status=pending&date='.self::DAY)->assertOk()
            ->assertJsonStructure(self::PAGE)->assertJsonPath('total', 1)->assertJsonPath('pageSize', 10);
        $this->patchJson("/api/v1/admin/reservations/{$id}", ['time' => '11:00'])->assertOk()->assertJsonPath('time', '11:00');
        $this->deleteJson("/api/v1/admin/reservations/{$id}")->assertStatus(409);
        $this->postJson("/api/v1/admin/reservations/{$id}/confirm")->assertOk()->assertJsonPath('status', 'confirmed');
        $this->postJson("/api/v1/admin/reservations/{$id}/cancel", [])->assertStatus(422);
        $this->postJson("/api/v1/admin/reservations/{$id}/cancel", ['reason' => 'Demande du client'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->patchJson("/api/v1/admin/reservations/{$id}", ['time' => '12:00'])->assertStatus(409);
    }

    public function test_catalog_management_and_delete_protection(): void
    {
        $this->actingAsToken($this->admin());
        [, $pro] = $this->barber();

        $category = $this->postJson('/api/v1/admin/categories', ['name' => 'Soins', 'description' => 'Visage', 'order' => 3])->assertCreated()
            ->assertJsonStructure(['id', 'name', 'description', 'order', 'createdAt', 'servicesCount'])->json('id');
        $service = $this->postJson('/api/v1/admin/services', [
            'name' => 'Soin visage', 'categoryId' => $category, 'description' => '', 'price' => 15000, 'durationMinutes' => 40, 'active' => true, 'image' => null,
        ])->assertCreated()->assertJsonStructure(['id', 'name', 'categoryId', 'categoryName', 'description', 'price', 'durationMinutes', 'active', 'image', 'createdAt'])
            ->assertJsonPath('staffIds', [$pro->id])->json('id');

        $this->deleteJson("/api/v1/admin/categories/{$category}")->assertStatus(409);
        $this->postJson("/api/v1/admin/services/{$service}/toggle")->assertOk()->assertJsonPath('active', false);
        $this->getJson('/api/v1/catalog/services')->assertJsonCount(0);
        $this->getJson("/api/v1/admin/services?categoryId={$category}&active=false")->assertJsonPath('total', 1);
        $this->deleteJson("/api/v1/admin/services/{$service}")->assertNoContent();
        $this->deleteJson("/api/v1/admin/categories/{$category}")->assertStatus(409); // prestation archivée encore rattachée
    }

    public function test_staff_schedule_account_and_vitrine(): void
    {
        Notification::fake();
        $this->actingAsToken($this->admin());
        $id = $this->postJson('/api/v1/admin/staff', [
            'firstName' => 'Moussa', 'lastName' => 'Traoré', 'photo' => null, 'phone' => '+2250700000099', 'email' => 'moussa@example.com',
            'jobTitle' => 'Barbier', 'specialties' => ['Barbe'], 'bio' => null, 'active' => true,
        ])->assertCreated()->assertJsonStructure(['id', 'firstName', 'lastName', 'photo', 'phone', 'email', 'jobTitle', 'specialties', 'bio', 'active', 'createdAt'])->json('id');

        $this->getJson("/api/v1/admin/staff/{$id}/schedule")->assertOk()->assertJsonPath('staffId', $id)->assertJsonPath('days.1.start', '09:00');
        $days = array_map(fn ($d) => ['isoDay' => $d, 'start' => $d >= 6 ? null : '10:00', 'end' => $d >= 6 ? null : '18:00'], range(1, 7));
        $days[0] = ['isoDay' => 1, 'start' => '10:00', 'end' => '18:00'];
        $this->putJson("/api/v1/admin/staff/{$id}/schedule", ['id' => $id, 'staffId' => $id, 'days' => $days])->assertStatus(422); // lundi : salon fermé
        $days[0] = ['isoDay' => 1, 'start' => null, 'end' => null];
        $this->putJson("/api/v1/admin/staff/{$id}/schedule", ['id' => $id, 'staffId' => $id, 'days' => $days])->assertOk()->assertJsonPath('days.5.start', null);

        $this->getJson('/api/v1/team')->assertJsonPath('0.workingDays', [2, 3, 4, 5]);
        $this->postJson("/api/v1/admin/staff/{$id}/account")->assertOk()->assertJsonPath('hasAccount', true);
        $user = User::query()->where('email', 'moussa@example.com')->first();
        $this->assertTrue($user->isBarber());
        Notification::assertSentTo($user, PasswordLinkMail::class);
        $this->postJson("/api/v1/admin/staff/{$id}/toggle")->assertOk()->assertJsonPath('active', false);
        $this->getJson('/api/v1/team')->assertJsonCount(0);
        $this->assertFalse($user->fresh()->is_active, 'le compte mobile est désactivé avec la fiche');
        $this->postJson("/api/v1/admin/staff/{$id}/toggle")->assertOk()->assertJsonPath('active', true);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_absence_requests_are_approved_by_the_salon(): void
    {
        $service = $this->service();
        [$barber] = $this->barber($service);
        $admin = $this->admin();
        $absence = $this->actingAsToken($barber)->postJson('/api/v1/pro/absences', ['type' => 'conge', 'startDate' => '2026-11-20', 'endDate' => '2026-11-22', 'reason' => null])
            ->assertCreated()->assertJsonPath('status', 'pending')->json('id');

        $this->assertContains('absence_request', $admin->notifications()->get()->pluck('data.type'));
        $this->actingAsToken($admin)->getJson('/api/v1/admin/unavailabilities?status=pending')->assertJsonPath('total', 1)
            ->assertJsonStructure(['items' => [['id', 'staffId', 'staffName', 'type', 'startDate', 'endDate', 'reason', 'status']]]);
        $this->postJson("/api/v1/admin/unavailabilities/{$absence}/approve")->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson("/api/v1/admin/unavailabilities/{$absence}/reject")->assertStatus(409);
        $this->assertContains('availability_approved', $barber->notifications()->get()->pluck('data.type'));
    }

    public function test_client_crm_and_loyalty(): void
    {
        $this->actingAsToken($this->admin());
        $id = $this->postJson('/api/v1/admin/clients', ['firstName' => 'Fatou', 'lastName' => 'Bamba', 'phone' => '+2250700000123', 'email' => null, 'preferences' => null, 'notes' => 'VIP potentiel'])
            ->assertCreated()->assertJsonStructure(['id', 'firstName', 'lastName', 'phone', 'email', 'createdAt', 'loyaltyTier', 'vipSince', 'preferences', 'notes', 'reservationsCount', 'lastVisit'])
            ->json('id');
        $this->postJson('/api/v1/admin/clients', ['firstName' => 'X', 'lastName' => 'Y', 'phone' => '+2250700000123'])->assertStatus(422)
            ->assertJsonPath('errors.phone', 'Un client existe déjà avec ce numéro.');
        $this->getJson('/api/v1/admin/clients?hasEmail=no&sort=name&dir=asc')->assertJsonPath('total', 1);
        $this->postJson("/api/v1/admin/clients/{$id}/loyalty", ['tier' => 'vip'])->assertOk()->assertJsonPath('loyaltyTier', 'vip')->assertJsonPath('vipSince', '2026-11-09');
        $this->getJson("/api/v1/admin/loyalty/events?clientId={$id}")->assertJsonPath('items.0.label', 'Passage au statut VIP');
        $this->deleteJson("/api/v1/admin/clients/{$id}")->assertNoContent();
    }

    public function test_settings_and_site_content_feed_public_endpoints(): void
    {
        $this->actingAsToken($this->admin());
        $settings = $this->getJson('/api/v1/admin/settings')->assertOk()
            ->assertJsonStructure(['id', 'general' => ['name', 'description', 'address', 'phone', 'email', 'whatsapp', 'logoUrl'], 'hours' => [['isoDay', 'closed', 'open', 'close']],
                'exceptionalDays', 'booking' => ['minNoticeHours', 'cancellationNoticeHours', 'cancellationPolicy', 'autoConfirm', 'slotStepMinutes', 'depositPercent'],
                'notifications' => ['channels' => ['email', 'whatsapp'], 'events' => ['newReservation', 'cancellation', 'payment', 'newClient']], 'loyalty' => ['vipBenefits']])
            ->json();

        $settings['general']['phone'] = '+225 27 22 00 00 00';
        $settings['booking']['depositPercent'] = 20;
        $settings['hours'][6] = ['isoDay' => 7, 'closed' => true, 'open' => '09:00', 'close' => '19:00'];
        $settings['exceptionalDays'] = [['date' => '2026-12-25', 'label' => 'Noël', 'closed' => true]];
        $this->putJson('/api/v1/admin/settings', $settings)->assertOk()->assertJsonPath('booking.depositPercent', 20);

        $this->putJson('/api/v1/admin/site-content', [
            'id' => 'site', 'presentation' => 'Texte', 'heroTitle' => 'Titre', 'heroSubtitle' => '', 'bannerImage' => null,
            'phone' => '+225 27 22 00 00 00', 'whatsapp' => '+225 07 00 00 00 01', 'email' => 'contact@maisonwodi.com', 'address' => 'Cocody, Abidjan',
            'socials' => ['instagram' => 'https://instagram.com/maisonwodi', 'facebook' => null, 'tiktok' => null], 'updatedAt' => null,
        ])->assertOk()->assertJsonPath('heroSubtitle', '');

        $this->getJson('/api/v1/site-content')->assertJsonPath('contact.phone', '+225 27 22 00 00 00')
            ->assertJsonPath('contact.whatsapp', '2250700000001')->assertJsonPath('hours.6.hours', 'closed')
            ->assertJsonPath('socials.instagram', 'https://instagram.com/maisonwodi')->assertJsonPath('address.street', 'Cocody, Abidjan');
        $this->getJson('/api/v1/booking-rules')->assertJsonPath('depositPercent', 20);
        $this->getJson('/api/v1/salon')->assertJsonPath('address', 'Cocody, Abidjan');
    }

    public function test_admin_account_rules(): void
    {
        Notification::fake();
        $super = $this->admin();
        $this->actingAsToken($super);
        $id = $this->postJson('/api/v1/admin/users', ['firstName' => 'Ana', 'lastName' => 'Kouassi', 'email' => 'ana@example.com', 'role' => 'manager'])
            ->assertCreated()->assertJsonStructure(['id', 'firstName', 'lastName', 'email', 'role', 'active', 'lastLoginAt', 'createdAt'])->json('id');
        Notification::assertSentTo(User::query()->find($id), PasswordLinkMail::class);

        $this->patchJson("/api/v1/admin/users/{$super->id}", ['role' => 'admin'])->assertStatus(409);
        $this->postJson("/api/v1/admin/users/{$super->id}/toggle")->assertStatus(409);
        $this->deleteJson("/api/v1/admin/users/{$super->id}")->assertStatus(409);
        $this->getJson('/api/v1/admin/users?role=manager')->assertJsonPath('total', 1);
        $this->postJson("/api/v1/admin/users/{$id}/toggle")->assertOk()->assertJsonPath('active', false);
    }

    public function test_dashboard_shape(): void
    {
        $this->actingAsToken($this->admin())->getJson('/api/v1/admin/dashboard?period=month')->assertOk()
            ->assertJsonStructure(['period', 'kpis' => ['totalReservations', 'todayReservations', 'pending', 'confirmed', 'cancelled', 'clients', 'revenue', 'pendingPayments'],
                'reservationsSeries' => [['label', 'value']], 'revenueSeries', 'byService', 'byStatus' => [['status', 'value']], 'recentReservations', 'recentClients', 'recentPayments'])
            ->assertJsonCount(30, 'reservationsSeries');
        $this->getJson('/api/v1/admin/dashboard?period=year')->assertJsonCount(12, 'revenueSeries');
        $this->getJson('/api/v1/admin/dashboard?period=decade')->assertStatus(422);
    }

    public function test_media_upload_validates_type_and_returns_url(): void
    {
        Storage::fake('public');
        $this->actingAsToken($this->admin());
        // PNG 1×1 valide (sans dépendre de l'extension GD).
        $png = UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        $this->postJson('/api/v1/admin/media', ['file' => $png])
            ->assertCreated()->assertJson(fn ($json) => $json->whereType('url', 'string'));
        $this->postJson('/api/v1/admin/media', ['file' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php')])->assertStatus(422);
        $this->postJson('/api/v1/admin/media', ['file' => UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg')])->assertStatus(422);
    }

    public function test_notifications_center(): void
    {
        $admin = $this->admin();
        $this->postJson('/api/v1/contact-messages', ['name' => 'Jean', 'email' => 'j@example.com', 'subject' => 'Info', 'message' => 'Un message assez long.']);
        $this->actingAsToken($admin)->getJson('/api/v1/admin/notifications/unread-count')->assertExactJson(['count' => 1]);
        $page = $this->getJson('/api/v1/admin/notifications?read=false')->assertJsonStructure(['items' => [['id', 'type', 'title', 'message', 'createdAt', 'read', 'link']]])->json();
        $this->postJson('/api/v1/admin/notifications/'.$page['items'][0]['id'].'/read')->assertOk()->assertJsonPath('read', true);
        $this->getJson('/api/v1/admin/notifications/unread-count')->assertExactJson(['count' => 0]);
        $this->getJson('/api/v1/admin/contact-messages')->assertJsonPath('total', 1);
    }
}
