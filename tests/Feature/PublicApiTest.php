<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\GalleryImage;
use App\Models\Professional;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\SettingsService;
use Tests\TestCase;

/** Contrat de la vitrine (VITRINE-API.md) et des données publiques du mobile. */
class PublicApiTest extends TestCase
{
    public function test_categories_are_active_only_sorted_plain_array(): void
    {
        ServiceCategory::factory()->create(['name' => 'B', 'sort_order' => 2]);
        ServiceCategory::factory()->create(['name' => 'A', 'sort_order' => 1]);
        ServiceCategory::factory()->create(['name' => 'Hidden', 'is_active' => false]);

        $this->getJson('/api/v1/catalog/categories')->assertOk()
            ->assertJsonCount(2)->assertJsonPath('0.name', 'A')
            ->assertJsonPath('0.image', 'images/demo/tools-flatlay') // jamais null (NgOptimizedImage)
            ->assertJsonStructure([['id', 'name', 'tagline', 'description', 'icon', 'image', 'imageAlt', 'order']]);
    }

    public function test_services_contract_and_inactive_services_hidden(): void
    {
        $service = $this->service(['price' => null, 'duration_minutes' => null]);
        $this->service(['is_active' => false]);
        [, $pro] = $this->barber($service);

        $this->getJson('/api/v1/catalog/services')->assertOk()->assertJsonCount(1)
            ->assertJsonStructure([['id', 'categoryId', 'categoryName', 'name', 'description', 'durationMinutes', 'price', 'priceFrom',
                'image', 'locations', 'conditions', 'bookable', 'staffIds', 'isPlaceholder']])
            ->assertJsonPath('0.price', null)
            ->assertJsonPath('0.durationMinutes', null)
            ->assertJsonPath('0.bookable', false)
            ->assertJsonPath('0.locations', ['salon'])
            ->assertJsonPath('0.staffIds', [$pro->id]);
        $this->getJson('/api/v1/catalog/services/'.$service->id)->assertOk()->assertJsonPath('id', $service->id);
    }

    public function test_team_only_exposes_active_public_members_without_private_data(): void
    {
        $service = $this->service();
        [, $pro] = $this->barber($service);
        Professional::factory()->create(['is_active' => false]);
        Professional::factory()->create(['is_public' => false]);

        $response = $this->getJson('/api/v1/team')->assertOk()->assertJsonCount(1)
            ->assertJsonStructure([['id', 'name', 'role', 'specialties', 'bio', 'photo', 'photoAlt', 'isPlaceholder',
                'firstName', 'lastName', 'jobTitle', 'serviceIds', 'rating', 'workingDays']])
            ->assertJsonPath('0.workingDays', [2, 3, 4, 5, 6, 7]);
        $this->assertArrayNotHasKey('phone', $response->json(0));
        $this->assertArrayNotHasKey('email', $response->json(0));
        $this->getJson('/api/v1/team/'.$pro->id)->assertOk();
    }

    public function test_gallery_returns_only_published_images(): void
    {
        GalleryImage::query()->create(['title' => 'Ok', 'image_path' => 'https://cdn.test/a.webp', 'category' => 'coupes', 'is_published' => true]);
        GalleryImage::query()->create(['title' => 'Draft', 'image_path' => 'https://cdn.test/b.webp', 'category' => 'barbe', 'is_published' => false]);

        $this->getJson('/api/v1/gallery')->assertOk()->assertJsonCount(1)
            ->assertJsonStructure([['id', 'src', 'alt', 'caption', 'category', 'orientation', 'isIllustration', 'title', 'url']])
            ->assertJsonPath('0.src', 'https://cdn.test/a.webp');
    }

    public function test_site_content_is_a_filtered_public_object(): void
    {
        $settings = app(SettingsService::class);
        $settings->put('contact', ['whatsapp' => '+225 07 00 00 00 00', 'phone' => '+225 07 00 00 00 00']);
        $settings->put('booking', ['depositPercent' => 30]);
        $settings->put('loyalty', ['vipBenefits' => ['Secret interne']]);

        $json = $this->getJson('/api/v1/site-content')->assertOk()
            ->assertJsonPath('contact.whatsapp', '2250700000000')
            ->assertJsonPath('hours.0', ['isoDay' => 1, 'hours' => 'closed'])
            ->assertJsonPath('hours.1', ['isoDay' => 2, 'hours' => '09:00 – 20:00'])
            ->assertJsonPath('booking', ['platformUrl' => null])
            ->json();
        $encoded = json_encode($json);
        $this->assertStringNotContainsString('depositPercent', $encoded);
        $this->assertStringNotContainsString('Secret interne', $encoded);
        $this->assertSame(['name', 'tagline', 'description', 'logoUrl', 'contact', 'address', 'booking', 'socials', 'apps', 'legal', 'hours'], array_keys($json));
    }

    public function test_salon_and_booking_rules_for_mobile(): void
    {
        $this->getJson('/api/v1/salon')->assertOk()->assertJsonStructure(['name', 'tagline', 'description', 'phone', 'whatsapp', 'email', 'address', 'hours' => [['isoDay', 'closed', 'open', 'close']]]);
        $this->getJson('/api/v1/booking-rules')->assertOk()->assertExactJson([
            'minNoticeHours' => 2, 'cancellationNoticeHours' => 24, 'cancellationPolicy' => null, 'autoConfirm' => false,
            'allowAnyStaff' => true, 'bookingWindowDays' => 60, 'depositPercent' => null, 'paymentMethods' => ['cash'], 'onlinePaymentAvailable' => false,
        ]);
    }

    public function test_contact_message_is_saved_and_notifies_admins(): void
    {
        $admin = $this->admin();
        $this->postJson('/api/v1/contact-messages', [
            'name' => 'Jean', 'email' => 'jean@example.com', 'phone' => null, 'subject' => 'Info', 'message' => 'Bonjour, je souhaite un renseignement.',
        ])->assertCreated();
        $this->assertSame(1, ContactMessage::query()->where('status', 'new')->count());
        $this->assertSame('contact_message', $admin->notifications()->first()->data['type']);
    }

    public function test_contact_message_validation_and_honeypot(): void
    {
        $this->postJson('/api/v1/contact-messages', ['name' => 'J', 'email' => 'bad'])->assertStatus(422);
        $this->postJson('/api/v1/contact-messages', [
            'name' => 'Bot', 'email' => 'bot@example.com', 'subject' => 'Spam', 'message' => 'Achetez maintenant ceci.', 'website' => 'http://spam',
        ])->assertCreated();
        $this->assertSame('spam', ContactMessage::query()->first()->status);
        $this->assertSame(0, User::query()->first()?->notifications()->count() ?? 0);
    }

    public function test_contact_message_is_rate_limited(): void
    {
        $payload = ['name' => 'Jean', 'email' => 'j@example.com', 'subject' => 'Info', 'message' => 'Un message assez long.'];
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/contact-messages', $payload)->assertCreated();
        }
        $this->postJson('/api/v1/contact-messages', $payload)->assertStatus(429);
    }
}
