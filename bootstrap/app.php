<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\JsonUtf8;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Réponse d'erreur au format commun des frontends : { message, errors: { champ: "message" } }. */
$error = fn (int $status, string $message, array $errors = []) => response()->json(
    $errors === [] ? ['message' => $message] : ['message' => $message, 'errors' => $errors],
    $status,
);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sessions Sanctum (cookie HttpOnly + CSRF) pour le back-office ; jetons Bearer pour le mobile.
        $middleware->statefulApi();
        $middleware->trustProxies(at: '*');
        $middleware->prependToGroup('api', JsonUtf8::class);
        $middleware->alias([
            'role.is' => EnsureRole::class,
            'active' => EnsureActiveUser::class,
            'permission' => PermissionMiddleware::class,
        ]);
        // API pure : pas de redirection vers une page de connexion.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($error): void {
        // Erreurs métier attendues (409, 422, 404…) : pas de bruit dans les journaux.
        $exceptions->dontReport([ApiException::class]);
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(fn (ApiException $e) => $error($e->status, $e->getMessage(), $e->errors));

        $exceptions->render(function (ValidationException $e) use ($error) {
            $errors = collect($e->errors())->map(fn (array $messages) => $messages[0])->all();

            return $error(422, count($errors) === 1 ? reset($errors) : 'Certaines informations sont invalides.', $errors);
        });

        $exceptions->render(fn (AuthenticationException $e) => $error(401, 'Votre session a expiré. Veuillez vous reconnecter.'));

        $exceptions->render(fn (TokenMismatchException $e) => $error(419, 'Session expirée (jeton CSRF invalide). Rechargez la page.'));

        $exceptions->render(fn (AuthorizationException|UnauthorizedException|AccessDeniedHttpException $e) => $error(403, 'Vous n’avez pas l’autorisation d’effectuer cette action.'));

        $exceptions->render(fn (ThrottleRequestsException $e) => $error(429, 'Trop de tentatives. Patientez quelques minutes.')
            ->withHeaders($e->getHeaders()));

        $exceptions->render(function (NotFoundHttpException $e) use ($error) {
            return $error(404, $e->getPrevious() instanceof ModelNotFoundException ? 'Élément introuvable.' : 'Ressource introuvable.');
        });

        $exceptions->render(function (QueryException $e) use ($error) {
            return match ($e->errorInfo[0] ?? null) {
                '23P01' => $error(409, 'Ce créneau vient d’être réservé ou n’est plus disponible. Choisissez-en un autre.'),
                '23505' => $error(409, 'Cet élément existe déjà.'),
                '23503' => $error(409, 'Cet élément est utilisé ailleurs et ne peut pas être supprimé.'),
                default => null,
            };
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($error) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match ($e->getStatusCode()) {
                405 => $error(405, 'Méthode non autorisée.'),
                419 => $error(419, 'Session expirée (jeton CSRF invalide). Rechargez la page.'),
                401 => $error(401, 'Votre session a expiré. Veuillez vous reconnecter.'),
                403 => $error(403, 'Vous n’avez pas l’autorisation d’effectuer cette action.'),
                413 => $error(413, 'Fichier trop volumineux.'),
                default => $error($e->getStatusCode(), $e->getMessage() ?: 'Requête invalide.'),
            };
        });

        // Erreurs inattendues : message générique (aucun détail interne en production).
        $exceptions->render(function (Throwable $e, Request $request) use ($error) {
            if (config('app.debug') || ! $request->is('api/*') || $e instanceof HttpExceptionInterface) {
                return null;
            }

            return $error(500, 'Une erreur est survenue sur le serveur. Réessayez plus tard.');
        });
    })->create();
