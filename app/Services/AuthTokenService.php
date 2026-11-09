<?php

namespace App\Services;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Jetons de l'application mobile : un jeton d'accès court (`access`) et un jeton de
 * renouvellement long (`refresh`), tous deux des jetons Sanctum hachés en base.
 * Le renouvellement applique une rotation : l'ancienne paire est révoquée.
 */
class AuthTokenService
{
    public function issue(User $user, string $device = 'mobile'): array
    {
        $access = $user->createToken($device, ['access'], now()->addMinutes(config('wodi.tokens.access_ttl')));
        $refresh = $user->createToken('refresh:'.$access->accessToken->id, ['refresh'], now()->addMinutes(config('wodi.tokens.refresh_ttl')));

        return ['accessToken' => $access->plainTextToken, 'refreshToken' => $refresh->plainTextToken];
    }

    /** @return array{0:User,1:array}|null */
    public function refresh(string $refreshToken): ?array
    {
        $token = PersonalAccessToken::findToken($refreshToken);
        if (! $token || ! $token->can('refresh') || ($token->expires_at && $token->expires_at->isPast())) {
            return null;
        }
        $user = $token->tokenable;
        if (! $user instanceof User || ! $user->is_active) {
            $token->delete();

            return null;
        }
        $this->revokePair($token);

        return [$user, $this->issue($user)];
    }

    /** Révoque un jeton d'accès et son jeton de renouvellement associé (ou l'inverse). */
    public function revokePair(PersonalAccessToken $token): void
    {
        if (str_starts_with($token->name, 'refresh:')) {
            PersonalAccessToken::query()->whereKey((int) substr($token->name, 8))->delete();
        } else {
            PersonalAccessToken::query()->where('name', 'refresh:'.$token->id)->delete();
        }
        $token->delete();
    }

    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();
    }
}
