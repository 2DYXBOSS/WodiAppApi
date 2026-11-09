<?php

namespace App\Services;

use App\Models\SalonClosure;
use App\Models\SalonHour;
use App\Models\SiteSetting;
use App\Support\Media;
use App\Support\SalonClock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètres du salon : une seule source de vérité pour
 * - `/site-content` (vitrine), `/salon` et `/booking-rules` (mobile) — vues publiques FILTRÉES ;
 * - `/admin/settings` et `/admin/site-content` (back-office).
 *
 * Les horaires et jours exceptionnels sont dans des tables dédiées (utilisées par le moteur de réservation).
 */
class SettingsService
{
    public const PUBLIC_CACHE_KEY = 'wodi.public-settings';

    /** Valeurs par défaut : `null` = non communiqué (jamais de valeur inventée). */
    public const DEFAULTS = [
        'general' => [
            'name' => 'Maison Wodi',
            'shortName' => 'Wodi',
            'tagline' => 'Barberie haut de gamme',
            'description' => '',
            'logoUrl' => null,
        ],
        'contact' => ['phone' => null, 'whatsapp' => null, 'email' => null],
        'address' => [
            'full' => null, 'street' => null, 'district' => null, 'city' => null, 'country' => null,
            'access' => null, 'geo' => null,
        ],
        'socials' => ['instagram' => null, 'facebook' => null, 'tiktok' => null],
        'apps' => ['appStore' => null, 'playStore' => null],
        'legal' => [
            'companyName' => null, 'legalForm' => null, 'shareCapital' => null, 'rccm' => null, 'taxId' => null,
            'headOffice' => null, 'publicationDirector' => null, 'host' => null, 'hostAddress' => null,
        ],
        'content' => ['presentation' => '', 'heroTitle' => '', 'heroSubtitle' => '', 'bannerImage' => null],
        'booking' => [
            'minNoticeHours' => 2,
            'cancellationNoticeHours' => 24,
            'cancellationPolicy' => null,
            'autoConfirm' => false,
            'slotStepMinutes' => 15,
            'depositPercent' => 0,
            'allowAnyStaff' => true,
            'bookingWindowDays' => 60,
            'paymentMethods' => ['cash'],
            'platformUrl' => null,
        ],
        'notifications' => [
            'channels' => ['email' => true, 'whatsapp' => false],
            'events' => ['newReservation' => true, 'cancellation' => true, 'payment' => true, 'newClient' => true],
        ],
        'loyalty' => ['vipBenefits' => []],
    ];

    /** @var array<string,array>|null */
    private ?array $loaded = null;

    public function get(string $key): array
    {
        $this->loaded ??= SiteSetting::query()->pluck('value', 'key')->all();
        $stored = $this->loaded[$key] ?? [];

        return array_replace_recursive(self::DEFAULTS[$key] ?? [], is_array($stored) ? $stored : []);
    }

    public function put(string $key, array $value): void
    {
        $merged = array_replace(self::DEFAULTS[$key] ?? [], $this->get($key), $value);
        SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $merged]);
        $this->loaded = null;
        $this->forgetPublicCache();
    }

    public function forgetPublicCache(): void
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }

    public function booking(): array
    {
        return $this->get('booking');
    }

    // ------------------------------------------------------------------ Horaires

    /** @return array<int,array{isoDay:int,closed:bool,open:?string,close:?string}> indexé par jour ISO */
    public function salonHours(): array
    {
        return SalonHour::query()->orderBy('iso_day')->get()->mapWithKeys(fn (SalonHour $h) => [$h->iso_day => [
            'isoDay' => $h->iso_day,
            'closed' => $h->is_closed || ! $h->open_time || ! $h->close_time,
            'open' => SalonClock::hm($h->open_time),
            'close' => SalonClock::hm($h->close_time),
        ]])->all();
    }

    /** Horaires mobiles (`OpeningHours[]`) ou null si non renseignés. */
    public function openingHours(): ?array
    {
        $hours = $this->salonHours();

        return $hours === [] ? null : array_values($hours);
    }

    /** @param list<array{isoDay:int,closed:bool,open:?string,close:?string}> $hours */
    public function saveSalonHours(array $hours): void
    {
        foreach ($hours as $day) {
            SalonHour::query()->updateOrCreate(['iso_day' => (int) $day['isoDay']], [
                'is_closed' => (bool) $day['closed'],
                'open_time' => $day['closed'] ? null : ($day['open'] ?: null),
                'close_time' => $day['closed'] ? null : ($day['close'] ?: null),
            ]);
        }
        $this->forgetPublicCache();
    }

    /** @param list<array{date:string,label:string,closed:bool}> $days */
    public function saveExceptionalDays(array $days): void
    {
        $dates = collect($days)->pluck('date')->all();
        SalonClosure::query()->whereNotIn('date', $dates)->delete();
        foreach ($days as $day) {
            SalonClosure::query()->updateOrCreate(['date' => $day['date']], [
                'label' => $day['label'], 'is_closed' => (bool) $day['closed'],
            ]);
        }
    }

    // --------------------------------------------------------- Vues publiques

    /** `GET /site-content` (vitrine) : objet construit, jamais une copie brute des paramètres. */
    public function publicSiteContent(): array
    {
        $general = $this->get('general');
        $contact = $this->get('contact');
        $address = $this->get('address');
        $booking = $this->booking();
        $hours = $this->salonHours();

        $content = [
            'name' => $general['name'],
            'tagline' => $general['tagline'],
            'description' => $general['description'] !== '' ? $general['description'] : null,
            'logoUrl' => Media::url($general['logoUrl']),
            'contact' => [
                'phone' => $contact['phone'],
                // Format wa.me : chiffres uniquement, sans « + » ni espaces.
                'whatsapp' => $contact['whatsapp'] ? preg_replace('/\D+/', '', $contact['whatsapp']) : null,
                'email' => $contact['email'],
            ],
            'address' => [
                'street' => $address['street'] ?? $address['full'],
                'district' => $address['district'],
                'city' => $address['city'],
                'country' => $address['country'],
                'access' => $address['access'],
                'geo' => is_array($address['geo']) && isset($address['geo']['lat'], $address['geo']['lng'])
                    ? ['lat' => (float) $address['geo']['lat'], 'lng' => (float) $address['geo']['lng']] : null,
            ],
            'booking' => ['platformUrl' => $booking['platformUrl']],
            'socials' => $this->get('socials'),
            'apps' => $this->get('apps'),
            'legal' => $this->get('legal'),
        ];

        // Champ absent = la vitrine conserve ses valeurs locales (site.config.ts).
        if ($hours !== []) {
            $content['hours'] = array_values(array_map(fn (array $h) => [
                'isoDay' => $h['isoDay'],
                'hours' => $h['closed'] ? 'closed' : $h['open'].' – '.$h['close'],
            ], $hours));
        }

        return $content;
    }

    /** `GET /salon` (mobile). */
    public function publicSalon(): array
    {
        $general = $this->get('general');
        $contact = $this->get('contact');
        $address = $this->get('address');

        return [
            'name' => $general['name'],
            'tagline' => $general['tagline'],
            'description' => $general['description'],
            'phone' => $contact['phone'],
            'whatsapp' => $contact['whatsapp'],
            'email' => $contact['email'],
            'address' => $this->addressLine($address),
            'hours' => $this->openingHours(),
        ];
    }

    /** `GET /booking-rules` (mobile) : règles publiques uniquement. */
    public function publicBookingRules(): array
    {
        $b = $this->booking();

        return [
            'minNoticeHours' => (int) $b['minNoticeHours'],
            'cancellationNoticeHours' => (int) $b['cancellationNoticeHours'],
            'cancellationPolicy' => $b['cancellationPolicy'] ?: null,
            'autoConfirm' => (bool) $b['autoConfirm'],
            'allowAnyStaff' => (bool) $b['allowAnyStaff'],
            'bookingWindowDays' => (int) $b['bookingWindowDays'],
            'depositPercent' => $b['depositPercent'] ? (int) $b['depositPercent'] : null,
            'paymentMethods' => array_values($b['paymentMethods']),
            // Aucun prestataire de paiement en ligne n'est intégré à ce jour.
            'onlinePaymentAvailable' => false,
        ];
    }

    public function addressLine(array $address): ?string
    {
        if ($address['full']) {
            return $address['full'];
        }
        $parts = array_filter([$address['street'], $address['district'], $address['city']]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    // ---------------------------------------------------------- Back-office

    /** `GET /admin/settings` → `SalonSettings` (+ sections complémentaires sans écran). */
    public function adminSettings(): array
    {
        $general = $this->get('general');
        $contact = $this->get('contact');
        $address = $this->get('address');
        $booking = $this->booking();
        $hours = $this->salonHours();

        return [
            'id' => 'settings',
            'general' => [
                'name' => $general['name'],
                'description' => $general['description'],
                'address' => $this->addressLine($address),
                'phone' => $contact['phone'],
                'email' => $contact['email'],
                'whatsapp' => $contact['whatsapp'],
                'logoUrl' => Media::url($general['logoUrl']),
                'tagline' => $general['tagline'],
            ],
            'hours' => array_map(fn (int $d) => [
                'isoDay' => $d,
                'closed' => $hours[$d]['closed'] ?? true,
                'open' => $hours[$d]['open'] ?? '09:00',
                'close' => $hours[$d]['close'] ?? '19:00',
            ], range(1, 7)),
            'exceptionalDays' => SalonClosure::query()->orderBy('date')->get()->map(fn (SalonClosure $c) => [
                'date' => $c->date->toDateString(), 'label' => $c->label, 'closed' => $c->is_closed,
            ])->all(),
            'booking' => [
                'minNoticeHours' => (int) $booking['minNoticeHours'],
                'cancellationNoticeHours' => (int) $booking['cancellationNoticeHours'],
                'cancellationPolicy' => (string) ($booking['cancellationPolicy'] ?? ''),
                'autoConfirm' => (bool) $booking['autoConfirm'],
                'slotStepMinutes' => (int) $booking['slotStepMinutes'],
                'depositPercent' => (int) $booking['depositPercent'],
                'allowAnyStaff' => (bool) $booking['allowAnyStaff'],
                'bookingWindowDays' => (int) $booking['bookingWindowDays'],
                'paymentMethods' => $booking['paymentMethods'],
                'platformUrl' => $booking['platformUrl'],
            ],
            'notifications' => $this->get('notifications'),
            'loyalty' => $this->get('loyalty'),
            // Sections publiées sur la vitrine, sans écran dédié dans le back-office actuel.
            'addressDetails' => Arr::except($address, ['full']),
            'socials' => $this->get('socials'),
            'apps' => $this->get('apps'),
            'legal' => $this->get('legal'),
        ];
    }

    /** `PUT /admin/settings` : fusion (une section absente reste inchangée). */
    public function saveAdminSettings(array $input): array
    {
        if (isset($input['general'])) {
            $g = $input['general'];
            $general = Arr::only($g, array_intersect(['name', 'description', 'logoUrl', 'tagline'], array_keys($g)));
            if (array_key_exists('description', $general)) {
                $general['description'] = (string) $general['description'];
            }
            $this->put('general', $general);
            $this->put('contact', Arr::only($g, array_intersect(['phone', 'email', 'whatsapp'], array_keys($g))));
            if (array_key_exists('address', $g)) {
                $this->put('address', ['full' => $g['address'] ?: null]);
            }
        }
        if (isset($input['hours'])) {
            $this->saveSalonHours($input['hours']);
        }
        if (isset($input['exceptionalDays'])) {
            $this->saveExceptionalDays($input['exceptionalDays']);
        }
        if (isset($input['booking'])) {
            $b = $input['booking'];
            if (array_key_exists('cancellationPolicy', $b)) {
                $b['cancellationPolicy'] = $b['cancellationPolicy'] ?: null;
            }
            $this->put('booking', $b);
        }
        foreach (['notifications', 'loyalty', 'socials', 'apps', 'legal'] as $section) {
            if (isset($input[$section])) {
                $this->put($section, $input[$section]);
            }
        }
        if (isset($input['addressDetails'])) {
            $this->put('address', Arr::except($input['addressDetails'], ['full']));
        }

        return $this->adminSettings();
    }

    /** `GET /admin/site-content` → `SiteContent`. */
    public function adminSiteContent(): array
    {
        $content = $this->get('content');
        $contact = $this->get('contact');

        return [
            'id' => 'site',
            'presentation' => $content['presentation'],
            'heroTitle' => $content['heroTitle'],
            'heroSubtitle' => $content['heroSubtitle'],
            'bannerImage' => Media::url($content['bannerImage']),
            'phone' => $contact['phone'],
            'whatsapp' => $contact['whatsapp'],
            'email' => $contact['email'],
            'address' => $this->addressLine($this->get('address')),
            'socials' => $this->get('socials'),
            'updatedAt' => SalonClock::iso(SiteSetting::query()->latest('updated_at')->first()?->updated_at),
        ];
    }

    public function saveAdminSiteContent(array $input): array
    {
        $this->put('content', Arr::only($input, ['presentation', 'heroTitle', 'heroSubtitle', 'bannerImage']));
        $this->put('contact', Arr::only($input, array_intersect(['phone', 'whatsapp', 'email'], array_keys($input))));
        if (array_key_exists('address', $input)) {
            $this->put('address', ['full' => $input['address'] ?: null]);
        }
        if (isset($input['socials'])) {
            $this->put('socials', $input['socials']);
        }

        return $this->adminSiteContent();
    }
}
