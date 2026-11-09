<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `role.is:client` | `role.is:barber` | `role.is:admin` — contrôle serveur du rôle (403 sinon).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        $allowed = match ($role) {
            'client' => $user?->isClient() && $user->customer !== null,
            'barber' => $user?->isBarber() && $user->professional !== null,
            'admin' => $user?->isAdmin(),
            default => false,
        };
        if (! $allowed) {
            throw ApiException::forbidden();
        }

        return $next($request);
    }
}
