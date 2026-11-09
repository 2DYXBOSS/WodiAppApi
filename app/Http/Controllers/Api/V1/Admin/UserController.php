<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Permission as PermissionList;
use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PasswordResetService;
use App\Support\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Comptes du back-office (users.manage). Règles : pas d'auto-modification du rôle, pas de
 * désactivation/suppression de son propre compte ni du dernier super administrateur, pas
 * d'attribution d'un rôle supérieur au sien. Création = invitation par e-mail.
 */
class UserController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): JsonResponse
    {
        $q = User::query()->role(Role::adminRoles())->with('roles');
        if ($role = $request->query('role')) {
            $q->role($role);
        }
        if (($active = Page::bool($request, 'active')) !== null) {
            $q->where('is_active', $active);
        }
        Page::search($q, $request->query('search'), ['first_name', 'last_name', 'email']);

        return response()->json(Page::make($q, $request, fn ($u) => (new AdminUserResource($u))->resolve($request), [
            'name' => fn ($q, $dir) => $q->orderBy('last_name', $dir), 'email' => 'email', 'lastLoginAt' => 'last_login_at', 'createdAt' => 'created_at',
        ], fn ($q) => $q->orderBy('last_name')));
    }

    public function show(string $user): JsonResponse
    {
        return response()->json(new AdminUserResource($this->find($user)));
    }

    public function store(AdminUserRequest $request, PasswordResetService $reset): JsonResponse
    {
        $d = $request->validated();
        $this->assertCanGrant($request->user(), $d['role']);
        $user = DB::transaction(function () use ($d) {
            $user = User::query()->create(['first_name' => $d['firstName'], 'last_name' => $d['lastName'], 'email' => $d['email']]);
            $user->assignRole($d['role']);

            return $user;
        });
        $reset->sendInvitation($user);
        $this->logger->log('user.invited', $user, ['role' => $d['role']]);

        return response()->json(new AdminUserResource($user), 201);
    }

    public function update(AdminUserRequest $request, string $user): JsonResponse
    {
        $target = $this->find($user);
        $actor = $request->user();
        $d = $request->validated();
        $this->assertCanManage($actor, $target);

        if (isset($d['role']) && $d['role'] !== $target->primaryRole()) {
            if ($target->is($actor)) {
                throw ApiException::conflict('Vous ne pouvez pas modifier votre propre rôle.');
            }
            $this->assertCanGrant($actor, $d['role']);
            if ($target->hasRole(Role::SuperAdmin->value)) {
                $this->assertNotLastSuperAdmin($target);
            }
        }
        DB::transaction(function () use ($target, $d) {
            $target->update(array_filter(['first_name' => $d['firstName'] ?? null, 'last_name' => $d['lastName'] ?? null, 'email' => $d['email'] ?? null]));
            if (isset($d['role'])) {
                $target->syncRoles([$d['role']]);
            }
        });
        $this->logger->log('user.updated', $target, array_keys($d));

        return response()->json(new AdminUserResource($target->fresh('roles')));
    }

    public function destroy(Request $request, string $user): Response
    {
        $target = $this->find($user);
        $this->assertCanManage($request->user(), $target);
        if ($target->is($request->user())) {
            throw ApiException::conflict('Vous ne pouvez pas supprimer votre propre compte.');
        }
        $this->assertNotLastSuperAdmin($target);
        $target->tokens()->delete();
        $target->update(['is_active' => false]);
        $target->delete();
        $this->logger->log('user.deleted', $target);

        return response()->noContent();
    }

    public function toggle(Request $request, string $user): JsonResponse
    {
        $target = $this->find($user);
        $this->assertCanManage($request->user(), $target);
        if ($target->is($request->user())) {
            throw ApiException::conflict('Vous ne pouvez pas désactiver votre propre compte.');
        }
        if ($target->is_active) {
            $this->assertNotLastSuperAdmin($target);
            $target->tokens()->delete();
        }
        $target->update(['is_active' => ! $target->is_active]);
        $this->logger->log('user.toggled', $target, ['active' => $target->is_active]);

        return response()->json(new AdminUserResource($target));
    }

    /** `GET /admin/roles` : rôles d'administration et leurs permissions. */
    public function roles(): JsonResponse
    {
        return response()->json(collect(Role::adminRoles())->map(fn ($r) => ['name' => $r, 'permissions' => PermissionList::MATRIX[$r]])->values());
    }

    public function permissions(): JsonResponse
    {
        return response()->json(['admin' => PermissionList::ADMIN, 'barber' => PermissionList::BARBER]);
    }

    private function find(string $id): User
    {
        return User::query()->role(Role::adminRoles())->with('roles')->findOrFail($id);
    }

    private function assertCanGrant(User $actor, string $role): void
    {
        if (Role::from($role)->rank() > $actor->roleRank()) {
            throw ApiException::forbidden('Vous ne pouvez pas attribuer un rôle supérieur au vôtre.');
        }
    }

    private function assertCanManage(User $actor, User $target): void
    {
        if ($target->roleRank() > $actor->roleRank()) {
            throw ApiException::forbidden('Vous ne pouvez pas modifier un compte de niveau supérieur au vôtre.');
        }
    }

    private function assertNotLastSuperAdmin(User $target): void
    {
        if (! $target->hasRole(Role::SuperAdmin->value)) {
            return;
        }
        $others = User::query()->role(Role::SuperAdmin->value)->where('is_active', true)->whereKeyNot($target->id)->count();
        if ($others === 0) {
            throw ApiException::conflict('Impossible : c’est le dernier super administrateur actif.');
        }
    }
}
