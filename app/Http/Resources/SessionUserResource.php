<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\User;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `GET /auth/me` — union de `SessionUser` (mobile) et `AdminProfile` (back-office).
 * Rôles et permissions sont décidés par le serveur.
 *
 * @mixin User
 */
class SessionUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $permissions = $this->getAllPermissions()->pluck('name');
        $scope = $this->isAdmin() ? Permission::ADMIN : ($this->isBarber() ? Permission::BARBER : []);

        return [
            'id' => $this->id,
            'role' => $this->mobileRole(),
            'roles' => $this->getRoleNames()->values()->all(),
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birthDate' => $this->birth_date?->toDateString(),
            'avatarUrl' => Media::url($this->avatar_path) ?? Media::url($this->professional?->photo),
            'permissions' => $permissions->intersect($scope)->values()->all(),
            'staffId' => $this->isBarber() ? $this->professional?->id : null,
        ];
    }
}
