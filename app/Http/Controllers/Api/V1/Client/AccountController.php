<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PromotionResource;
use App\Models\Payment;
use App\Models\Promotion;
use App\Services\SettingsService;
use App\Support\SalonClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /** Paiements enregistrés du client (lecture seule). */
    public function payments(Request $request): JsonResponse
    {
        $payments = Payment::query()->where('customer_id', $request->user()->customer->id)
            ->with('booking')->latest('paid_at')->limit(200)->get();

        return response()->json(PaymentResource::collection($payments));
    }

    /** Fidélité calculée par le serveur. */
    public function loyalty(Request $request, SettingsService $settings): JsonResponse
    {
        $customer = $request->user()->customer;
        $events = $customer->loyaltyTransactions()->orderByDesc('occurred_on')->limit(100)->get();

        return response()->json([
            'tier' => $customer->loyalty_tier,
            'vipSince' => $customer->vip_since?->toDateString(),
            // Avantages VIP publiés par le salon ; liste vide = formules non encore définies.
            'benefits' => array_values($settings->get('loyalty')['vipBenefits']),
            'events' => $events->map(fn ($e) => ['id' => $e->id, 'date' => $e->occurred_on->toDateString(), 'label' => $e->label])->all(),
            'promotions' => PromotionResource::collection(Promotion::query()->current(SalonClock::today())->get())->resolve(),
        ]);
    }
}
