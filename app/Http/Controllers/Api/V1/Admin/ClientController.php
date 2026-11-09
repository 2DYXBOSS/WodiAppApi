<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientRequest;
use App\Http\Resources\AdminClientResource;
use App\Http\Resources\AdminPaymentResource;
use App\Http\Resources\AdminReservationResource;
use App\Http\Resources\LoyaltyEventResource;
use App\Models\Customer;
use App\Services\ActivityLogger;
use App\Support\Page;
use App\Support\SalonClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): JsonResponse
    {
        $q = $this->query();
        if ($tier = $request->query('tier')) {
            $q->where('loyalty_tier', $tier);
        }
        if ($hasEmail = $request->query('hasEmail')) {
            $hasEmail === 'yes' ? $q->whereNotNull('email') : $q->whereNull('email');
        }
        Page::search($q, $request->query('search'), ['first_name', 'last_name', 'phone', 'email', 'reference']);

        return response()->json(Page::make($q, $request, fn ($c) => (new AdminClientResource($c))->resolve($request), [
            'name' => fn (Builder $q, $dir) => $q->orderBy('last_name', $dir)->orderBy('first_name', $dir),
            'createdAt' => 'created_at',
            'reservationsCount' => 'reservations_count',
            'lastVisit' => 'last_visit_at',
        ], fn ($q) => $q->orderByDesc('created_at')));
    }

    public function show(string $client): JsonResponse
    {
        return response()->json(new AdminClientResource($this->query()->findOrFail($client)));
    }

    public function store(ClientRequest $request): JsonResponse
    {
        $d = $request->validated();
        $customer = Customer::query()->create([
            'first_name' => $d['firstName'], 'last_name' => $d['lastName'], 'phone' => $d['phone'],
            'email' => isset($d['email']) ? mb_strtolower($d['email']) : null, 'preferences' => $d['preferences'] ?? null,
            'notes' => $d['notes'] ?? null, 'birth_date' => $d['birthDate'] ?? null,
        ]);
        $this->logger->log('client.created', $customer);

        return response()->json(new AdminClientResource($this->query()->findOrFail($customer->id)), 201);
    }

    public function update(ClientRequest $request, string $client): JsonResponse
    {
        $customer = Customer::query()->findOrFail($client);
        $d = $request->validated();
        $map = ['firstName' => 'first_name', 'lastName' => 'last_name', 'phone' => 'phone', 'email' => 'email',
            'preferences' => 'preferences', 'notes' => 'notes', 'birthDate' => 'birth_date'];
        $changes = [];
        foreach ($map as $in => $col) {
            if (array_key_exists($in, $d)) {
                $changes[$col] = $in === 'email' && $d[$in] ? mb_strtolower($d[$in]) : $d[$in];
            }
        }
        DB::transaction(function () use ($customer, $changes) {
            $customer->update($changes);
            // L'e-mail de connexion reste géré par le client (non synchronisé) ; nom et téléphone suivent.
            $customer->user?->update(array_intersect_key($changes, array_flip(['first_name', 'last_name', 'phone'])));
        });
        $this->logger->log('client.updated', $customer, array_keys($changes));

        return response()->json(new AdminClientResource($this->query()->findOrFail($customer->id)));
    }

    public function destroy(string $client): Response
    {
        $customer = Customer::query()->findOrFail($client);
        if ($customer->bookings()->exists()) {
            throw ApiException::conflict('Ce client a des réservations : il ne peut pas être supprimé.');
        }
        $customer->delete();
        $this->logger->log('client.deleted', $customer);

        return response()->noContent();
    }

    /** `POST /admin/clients/:id/loyalty { tier }` (permission loyalty.manage). */
    public function loyalty(Request $request, string $client): JsonResponse
    {
        $data = $request->validate(['tier' => ['required', 'in:standard,vip']]);
        $customer = Customer::query()->findOrFail($client);
        if ($customer->loyalty_tier !== $data['tier']) {
            DB::transaction(function () use ($customer, $data, $request) {
                $customer->update(['loyalty_tier' => $data['tier'], 'vip_since' => $data['tier'] === 'vip' ? SalonClock::today() : null]);
                $customer->loyaltyTransactions()->create([
                    'type' => 'tier_change', 'label' => $data['tier'] === 'vip' ? 'Passage au statut VIP' : 'Retour au statut standard',
                    'occurred_on' => SalonClock::today(), 'created_by' => $request->user()->id,
                ]);
            });
            $this->logger->log('client.loyalty', $customer, ['tier' => $data['tier']]);
        }

        return response()->json(new AdminClientResource($this->query()->findOrFail($customer->id)));
    }

    public function bookings(Request $request, string $client): JsonResponse
    {
        $customer = Customer::query()->findOrFail($client);

        return response()->json(Page::make($customer->bookings()->with(['customer', 'professional', 'histories'])->getQuery(), $request,
            fn ($b) => (new AdminReservationResource($b))->resolve($request), [], fn ($q) => $q->orderByDesc('starts_at')));
    }

    public function payments(Request $request, string $client): JsonResponse
    {
        $customer = Customer::query()->findOrFail($client);

        return response()->json(Page::make($customer->payments()->with(['booking', 'customer'])->getQuery(), $request,
            fn ($p) => (new AdminPaymentResource($p))->resolve($request), [], fn ($q) => $q->orderByDesc('paid_at')));
    }

    public function loyaltyEvents(Request $request, string $client): JsonResponse
    {
        $customer = Customer::query()->findOrFail($client);

        return response()->json(LoyaltyEventResource::collection($customer->loyaltyTransactions()->with('customer')->orderByDesc('occurred_on')->get()));
    }

    private function query(): Builder
    {
        return Customer::query()
            ->withCount(['bookings as reservations_count' => fn ($q) => $q->where('status', '!=', BookingStatus::Cancelled->value)])
            ->withMax(['bookings as last_visit_at' => fn ($q) => $q->where('status', BookingStatus::Completed->value)], 'starts_at');
    }
}
