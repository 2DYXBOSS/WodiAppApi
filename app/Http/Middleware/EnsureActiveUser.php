<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse les jetons de renouvellement sur les routes protégées (401) et les comptes désactivés (401,
 * sessions et jetons révoqués).
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can('access')) {
            throw new ApiException(401, 'Votre session a expiré. Veuillez vous reconnecter.');
        }
        if ($user && (! $user->is_active || $user->trashed())) {
            $token instanceof PersonalAccessToken ? $user->tokens()->delete() : auth('web')->logout();
            throw new ApiException(401, 'Ce compte est désactivé.');
        }

        return $next($request);
    }
}
