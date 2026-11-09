<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminScheduleRequest;
use App\Http\Requests\Admin\StaffRequest;
use App\Http\Resources\StaffMemberResource;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PasswordResetService;
use App\Services\ScheduleService;
use App\Services\SettingsService;
use App\Services\VitrineRebuild;
use App\Support\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly VitrineRebuild $vitrine,
        private readonly ScheduleService $schedules,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = Professional::query()->with('services');
        if (($active = Page::bool($request, 'active')) !== null) {
            $q->where('is_active', $active);
        }
        Page::search($q, $request->query('search'), ['first_name', 'last_name', 'job_title']);

        return response()->json(Page::make($q, $request, fn ($p) => (new StaffMemberResource($p))->resolve($request), [
            'name' => fn ($q, $dir) => $q->orderBy('last_name', $dir)->orderBy('first_name', $dir), 'createdAt' => 'created_at',
        ], fn ($q) => $q->orderBy('sort_order')->orderBy('first_name')));
    }

    public function show(string $staff): JsonResponse
    {
        return response()->json(new StaffMemberResource(Professional::query()->with('services')->findOrFail($staff)));
    }

    public function store(StaffRequest $request, SettingsService $settings): JsonResponse
    {
        $d = $request->validated();
        $pro = DB::transaction(function () use ($d, $settings) {
            $pro = Professional::query()->create($this->attributes($d));
            $pro->services()->sync($d['serviceIds'] ?? Service::query()->pluck('id')->all());
            // Horaires initiaux = horaires du salon (modifiables ensuite par le professionnel ou le back-office).
            foreach ($settings->salonHours() as $day) {
                $pro->workingHours()->create([
                    'iso_day' => $day['isoDay'], 'is_working' => ! $day['closed'],
                    'start_time' => $day['closed'] ? null : $day['open'], 'end_time' => $day['closed'] ? null : $day['close'],
                ]);
            }

            return $pro;
        });
        $this->changed('staff.created', $pro);

        return response()->json(new StaffMemberResource($pro->load('services')), 201);
    }

    public function update(StaffRequest $request, string $staff): JsonResponse
    {
        $pro = Professional::query()->findOrFail($staff);
        $d = $request->validated();
        DB::transaction(function () use ($pro, $d) {
            $pro->update($this->attributes($d));
            if (array_key_exists('serviceIds', $d)) {
                $pro->services()->syncWithoutDetaching($d['serviceIds']);
                $pro->services()->detach($pro->services()->pluck('services.id')->diff($d['serviceIds'])->all());
            }
            // Le compte du barbier garde le même nom que sa fiche.
            $pro->user?->update(array_filter(['first_name' => $d['firstName'] ?? null, 'last_name' => $d['lastName'] ?? null]));
        });
        $this->changed('staff.updated', $pro, array_keys($d));

        return response()->json(new StaffMemberResource($pro->load('services')));
    }

    public function destroy(string $staff): Response
    {
        $pro = Professional::query()->findOrFail($staff);
        if ($pro->bookings()->exists()) {
            throw ApiException::conflict('Ce professionnel a des réservations : désactivez-le plutôt que de le supprimer.');
        }
        DB::transaction(function () use ($pro) {
            $pro->user?->update(['is_active' => false]);
            $pro->user?->tokens()->delete();
            $pro->delete();
        });
        $this->changed('staff.deleted', $pro);

        return response()->noContent();
    }

    public function toggle(string $staff): JsonResponse
    {
        $pro = Professional::query()->findOrFail($staff);
        DB::transaction(function () use ($pro) {
            $pro->update(['is_active' => ! $pro->is_active]);
            // Le compte mobile suit l'état de la fiche : désactivé = jetons révoqués, connexion refusée.
            if ($pro->user) {
                $pro->user->update(['is_active' => $pro->is_active]);
                if (! $pro->is_active) {
                    $pro->user->tokens()->delete();
                }
            }
        });
        $this->changed('staff.toggled', $pro, ['active' => $pro->is_active]);

        return response()->json(new StaffMemberResource($pro->load('services')));
    }

    /** `GET /admin/staff/:id/schedule` → `WeeklySchedule`. */
    public function schedule(string $staff): JsonResponse
    {
        return response()->json($this->weekly(Professional::query()->findOrFail($staff)));
    }

    public function saveSchedule(AdminScheduleRequest $request, string $staff): JsonResponse
    {
        $pro = Professional::query()->findOrFail($staff);
        $days = $request->validated('days');
        $withBreaks = collect($days)->contains(fn ($d) => array_key_exists('breaks', $d));
        $this->schedules->save($pro, $days, keepBreaks: ! $withBreaks);

        return response()->json($this->weekly($pro));
    }

    /**
     * Crée (ou réinvite) le compte de connexion mobile d'un professionnel : un lien d'activation
     * est envoyé par e-mail. `POST /admin/staff/:id/account { email }`.
     */
    public function account(Request $request, string $staff, PasswordResetService $reset): JsonResponse
    {
        $pro = Professional::query()->findOrFail($staff);
        $data = $request->validate(['email' => ['nullable', 'email', 'max:191']]);
        $email = mb_strtolower($data['email'] ?? $pro->email ?? '');
        if ($email === '') {
            throw ApiException::unprocessable('Indiquez l’adresse e-mail du professionnel.', ['email' => 'Adresse requise.']);
        }

        $user = DB::transaction(function () use ($pro, $email) {
            if ($pro->user) {
                return $pro->user;
            }
            if (User::withTrashed()->where('email', $email)->exists()) {
                throw ApiException::conflict('Un compte existe déjà avec cette adresse.', ['email' => 'Adresse déjà utilisée.']);
            }
            $user = User::query()->create(['first_name' => $pro->first_name, 'last_name' => $pro->last_name, 'email' => $email, 'phone' => $pro->phone]);
            $user->assignRole(Role::Barber->value);
            $pro->update(['user_id' => $user->id, 'email' => $pro->email ?? $email]);

            return $user;
        });
        $reset->sendInvitation($user);
        $this->logger->log('staff.account', $pro, ['user' => $user->id]);

        return response()->json(new StaffMemberResource($pro->fresh()->load('services')));
    }

    private function weekly(Professional $pro): array
    {
        $days = $this->schedules->days($pro);

        return [
            'id' => $pro->id,
            'staffId' => $pro->id,
            'days' => array_map(fn ($d) => $d + ['start' => null, 'end' => null], $days),
            'updatedAt' => $this->schedules->updatedAt($pro),
        ];
    }

    private function attributes(array $d): array
    {
        $map = ['firstName' => 'first_name', 'lastName' => 'last_name', 'photo' => 'photo', 'phone' => 'phone', 'email' => 'email',
            'jobTitle' => 'job_title', 'specialties' => 'specialties', 'bio' => 'bio', 'active' => 'is_active',
            'bookable' => 'is_bookable', 'public' => 'is_public'];

        return collect($map)->filter(fn ($col, $in) => array_key_exists($in, $d))->mapWithKeys(fn ($col, $in) => [$col => $d[$in]])->all();
    }

    private function changed(string $action, Professional $pro, array $properties = []): void
    {
        $this->logger->log($action, $pro, $properties);
        $this->vitrine->request();
    }
}
