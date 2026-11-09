<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\SessionUserResource;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\Notifier;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authentification commune :
 * - back-office Angular (origine déclarée dans SANCTUM_STATEFUL_DOMAINS) → cookie de session, 204 ;
 * - application mobile → `{ tokens: { accessToken, refreshToken }, user }`.
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuthTokenService $tokens) {}

    public function login(LoginRequest $request): JsonResponse|Response
    {
        $user = User::query()->where('email', mb_strtolower($request->input('email')))->first();
        $valid = $user && $user->password && Hash::check($request->input('password'), $user->password);
        if (! $user) {
            Hash::make($request->input('password')); // temps de réponse comparable (anti-énumération)
        }
        if (! $valid) {
            throw new ApiException(401, 'Adresse e-mail ou mot de passe incorrect.');
        }
        if (! $user->is_active) {
            throw new ApiException(403, 'Ce compte est désactivé. Contactez le salon.');
        }

        if ($this->isBackOffice($request)) {
            if (! $user->isAdmin()) {
                throw new ApiException(403, 'Ce compte n’a pas accès au back-office.');
            }
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
            $user->forceFill(['last_login_at' => now()])->save();

            return response()->noContent();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['tokens' => $this->tokens->issue($user), 'user' => new SessionUserResource($user)]);
    }

    /** Inscription client : rôle TOUJOURS `client` (tout champ role/permissions est ignoré). */
    public function register(RegisterRequest $request, Notifier $notifier): JsonResponse
    {
        $data = $request->validated();
        $email = mb_strtolower($data['email']);
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw ApiException::conflict('Un compte existe déjà avec cette adresse.', ['email' => 'Adresse déjà utilisée.']);
        }
        if (Customer::query()->where('phone', $data['phone'])->exists()) {
            throw ApiException::conflict('Ce numéro est déjà associé à un client du salon. Contactez Maison WODI pour activer votre accès.',
                ['phone' => 'Numéro déjà utilisé.']);
        }

        $user = DB::transaction(function () use ($data, $email) {
            $user = User::query()->create([
                'first_name' => $data['firstName'], 'last_name' => $data['lastName'], 'email' => $email,
                'phone' => $data['phone'], 'password' => $data['password'],
            ]);
            $user->assignRole(Role::Client->value);
            Customer::query()->create([
                'user_id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name,
                'phone' => $user->phone, 'email' => $email,
            ]);

            return $user;
        });

        $notifier->toAdmins('client_created', 'Nouveau client', "{$user->fullName()} a créé un compte.", '/admin/clients', 'newClient');

        return response()->json(['tokens' => $this->tokens->issue($user), 'user' => new SessionUserResource($user)], 201);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refreshToken' => ['required', 'string', 'max:255']]);
        $result = $this->tokens->refresh($request->input('refreshToken'));
        if (! $result) {
            throw new ApiException(401, 'Votre session a expiré. Veuillez vous reconnecter.');
        }

        return response()->json($result[1]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new SessionUserResource($request->user()->load('professional')));
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $this->tokens->revokePair($token);
        } else {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    /** Toujours 204 : aucune information sur l'existence du compte. */
    public function forgotPassword(Request $request, PasswordResetService $reset): Response
    {
        $request->validate(['email' => ['required', 'email', 'max:191']]);
        $reset->sendLink($request->input('email'));

        return response()->noContent();
    }

    public function resetPassword(ResetPasswordRequest $request, PasswordResetService $reset): Response
    {
        $reset->reset($request->input('token'), $request->input('password'));

        return response()->noContent();
    }

    public function changePassword(ChangePasswordRequest $request): Response
    {
        $user = $request->user();
        if (! $user->password || ! Hash::check($request->input('currentPassword'), $user->password)) {
            throw ApiException::unprocessable('Mot de passe actuel incorrect.', ['currentPassword' => 'Mot de passe incorrect.']);
        }
        $user->forceFill(['password' => $request->input('newPassword')])->save();

        // Les autres sessions mobiles sont révoquées ; la session courante reste ouverte.
        $current = $user->currentAccessToken();
        $keep = $current instanceof PersonalAccessToken ? [$current->id] : [];
        if ($keep) {
            $keepRefresh = PersonalAccessToken::query()->where('name', 'refresh:'.$current->id)->value('id');
            $keep[] = $keepRefresh;
        }
        $user->tokens()->whereNotIn('id', array_filter($keep))->delete();

        return response()->noContent();
    }

    private function isBackOffice(Request $request): bool
    {
        return EnsureFrontendRequestsAreStateful::fromFrontend($request) && $request->hasSession();
    }
}
