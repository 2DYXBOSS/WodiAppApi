<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualPaymentRequest;
use App\Http\Resources\AdminPaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Page;
use App\Support\SalonClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Payment::query()->with(['booking', 'customer']);
        foreach (['status' => 'status', 'method' => 'method', 'clientId' => 'customer_id', 'reservationId' => 'booking_id', 'reference' => 'reference'] as $p => $col) {
            if ($v = $request->query($p)) {
                $q->where($col, $v);
            }
        }
        if ($from = $request->query('dateFrom')) {
            $q->where('paid_at', '>=', SalonClock::dayBounds($from)[0]);
        }
        if ($to = $request->query('dateTo')) {
            $q->where('paid_at', '<', SalonClock::dayBounds($to)[1]);
        }
        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('reference', 'ilike', "%{$search}%")
                ->orWhereHas('booking', fn ($b) => $b->where('reference', 'ilike', "%{$search}%"))
                ->orWhereHas('customer', fn ($c) => Page::search($c, $search, ['first_name', 'last_name', 'phone'])));
        }

        return response()->json(Page::make($q, $request, fn ($p) => (new AdminPaymentResource($p))->resolve($request),
            ['date' => 'paid_at', 'amount' => 'amount', 'reference' => 'reference'], fn ($q) => $q->orderByDesc('paid_at')));
    }

    public function show(string $payment): JsonResponse
    {
        return response()->json(new AdminPaymentResource(Payment::query()->with(['booking', 'customer'])->findOrFail($payment)));
    }

    /** Paiement MANUEL : `{ reservationId, amount, method, note }`, montant ≤ reste à payer. */
    public function store(ManualPaymentRequest $request, PaymentService $payments): JsonResponse
    {
        $d = $request->validated();
        $payment = $payments->recordManual($request->user(), $d['reservationId'], (int) $d['amount'], PaymentMethod::from($d['method']), $d['note'] ?? null);

        return response()->json(new AdminPaymentResource($payment), 201);
    }
}
