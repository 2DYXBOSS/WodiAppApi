<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use App\Services\SettingsService;
use App\Support\SalonClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/** Informations publiques du salon, construites et filtrées (jamais une copie des paramètres internes). */
class SalonController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /** Vitrine : `PublicSiteContent`. */
    public function siteContent(): JsonResponse
    {
        $content = Cache::remember(SettingsService::PUBLIC_CACHE_KEY.':site', 600, fn () => $this->settings->publicSiteContent());

        return response()->json($content);
    }

    /** Mobile : `SalonInfo`. */
    public function salon(): JsonResponse
    {
        return response()->json($this->settings->publicSalon());
    }

    /** Mobile : `BookingRules`. */
    public function bookingRules(): JsonResponse
    {
        return response()->json($this->settings->publicBookingRules());
    }

    public function promotions(): JsonResponse
    {
        $promotions = Promotion::query()->current(SalonClock::today())->orderBy('end_date')->get();

        return response()->json(PromotionResource::collection($promotions));
    }
}
