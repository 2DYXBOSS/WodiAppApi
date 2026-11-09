<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordLinkMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Réinitialisation par lien à usage unique (`{ token, password }`, sans e-mail dans la requête,
 * conformément au contrat mobile). Seul le hash SHA-256 du jeton est stocké.
 */
class PasswordResetService
{
    public function __construct(private readonly AuthTokenService $tokens) {}

    /** Toujours silencieux : aucune information sur l'existence du compte. */
    public function sendLink(string $email): void
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->where('is_active', true)->first();
        if (! $user) {
            return;
        }
        $this->send($user, false);
    }

    public function sendInvitation(User $user): void
    {
        $this->send($user, true);
    }

    private function send(User $user, bool $invitation): void
    {
        $plain = Str::random(64);
        PasswordResetCode::query()->where('user_id', $user->id)->whereNull('used_at')->delete();
        PasswordResetCode::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $invitation ? now()->addDays(7) : now()->addMinutes(config('wodi.reset_password.ttl')),
        ]);
        $template = $user->isAdmin() ? config('wodi.reset_password.admin_url') : config('wodi.reset_password.mobile_url');
        try {
            $user->notify(new PasswordLinkMail(str_replace('{token}', urlencode($plain), $template), $invitation));
        } catch (\Throwable $e) {
            Log::error('Lien de mot de passe non envoyé', ['user' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    public function reset(string $token, string $password): void
    {
        DB::transaction(function () use ($token, $password) {
            $code = PasswordResetCode::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $code || $code->used_at || $code->expires_at->isPast() || ! $code->user?->is_active) {
                throw ApiException::unprocessable('Lien de réinitialisation invalide ou expiré.', ['token' => 'Lien invalide ou expiré.']);
            }
            $code->user->forceFill(['password' => $password, 'email_verified_at' => $code->user->email_verified_at ?? now()])->save();
            $code->update(['used_at' => now()]);
            // Toutes les sessions mobiles sont révoquées après un changement de mot de passe.
            $this->tokens->revokeAll($code->user);
        });
    }
}
