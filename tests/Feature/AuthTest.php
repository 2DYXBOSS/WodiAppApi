<?php

namespace Tests\Feature;

use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordLinkMail;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_register_always_creates_a_client_and_ignores_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'firstName' => 'Awa', 'lastName' => 'Koné', 'email' => 'AWA@example.com', 'phone' => '+225 07 11 22 33 44',
            'password' => 'Secret123', 'role' => 'admin', 'permissions' => ['users.manage'],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['tokens' => ['accessToken', 'refreshToken'], 'user' => ['id', 'role', 'firstName', 'lastName', 'email', 'phone', 'birthDate', 'avatarUrl', 'permissions', 'staffId']])
            ->assertJsonPath('user.role', 'client')
            ->assertJsonPath('user.email', 'awa@example.com')
            ->assertJsonPath('user.permissions', []);
        $this->assertTrue(User::query()->where('email', 'awa@example.com')->first()->isClient());
        $this->assertDatabaseHas('customers', ['email' => 'awa@example.com']);
    }

    public function test_register_with_existing_email_returns_409(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $this->postJson('/api/v1/auth/register', [
            'firstName' => 'A', 'lastName' => 'B', 'email' => 'taken@example.com', 'phone' => '+2250711223344', 'password' => 'Secret123',
        ])->assertStatus(409)->assertJsonPath('errors.email', 'Adresse déjà utilisée.');
    }

    public function test_validation_errors_use_one_message_per_field(): void
    {
        $this->postJson('/api/v1/auth/register', ['email' => 'nope'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['firstName', 'lastName', 'email', 'phone', 'password']])
            ->assertJson(fn ($json) => $json->whereType('errors.email', 'string')->etc());
    }

    public function test_mobile_login_returns_tokens_and_wrong_password_is_401(): void
    {
        [$user] = $this->client();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123'])
            ->assertOk()->assertJsonPath('user.role', 'client')->assertJsonStructure(['tokens' => ['accessToken', 'refreshToken']]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(401)->assertJsonPath('message', 'Adresse e-mail ou mot de passe incorrect.');
        $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertStatus(401);
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'bad']);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_invalid_token_is_401_and_logout_revokes_token(): void
    {
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer wodi_invalid'])->assertStatus(401);

        [$user] = $this->client();
        $tokens = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123'])->json('tokens');
        $headers = ['Authorization' => 'Bearer '.$tokens['accessToken']];
        $this->getJson('/api/v1/auth/me', $headers)->assertOk();
        $this->postJson('/api/v1/auth/logout', [], $headers)->assertNoContent();
        $this->assertSame(0, PersonalAccessToken::query()->count(), 'le jeton d’accès et son jeton de renouvellement sont révoqués');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me', $headers)->assertStatus(401);
    }

    public function test_refresh_rotates_tokens_and_refresh_token_cannot_access_api(): void
    {
        [$user] = $this->client();
        $tokens = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123'])->json('tokens');

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$tokens['refreshToken']])->assertStatus(401);
        $this->app['auth']->forgetGuards();

        $new = $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']])
            ->assertOk()->assertJsonStructure(['accessToken', 'refreshToken'])->json();
        $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $tokens['refreshToken']])->assertStatus(401);
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$new['accessToken']])->assertOk();
    }

    public function test_expired_access_token_is_rejected(): void
    {
        [$user] = $this->client();
        $tokens = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123'])->json('tokens');
        $this->travel(config('wodi.tokens.access_ttl') + 1)->minutes();
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$tokens['accessToken']])->assertStatus(401);
    }

    public function test_inactive_account_cannot_login_or_use_token(): void
    {
        [$user] = $this->client();
        $user->update(['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123'])->assertStatus(403);
        $this->actingAsToken($user)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_forgot_password_never_reveals_accounts_and_reset_works(): void
    {
        Notification::fake();
        [$user] = $this->client();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])->assertNoContent();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertNoContent();

        $token = null;
        Notification::assertSentTo($user, PasswordLinkMail::class, function ($n) use (&$token, $user) {
            preg_match('/token=([^&]+)/', $n->toMail($user)->actionUrl, $m);
            $token = urldecode($m[1]);

            return true;
        });
        $this->assertStringStartsWith('maisonwodi://reset-password?token=', 'maisonwodi://reset-password?token='.$token);

        $this->postJson('/api/v1/auth/reset-password', ['token' => 'bad', 'password' => 'NewSecret1'])->assertStatus(422);
        $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'password' => 'NewSecret1'])->assertNoContent();
        $this->postJson('/api/v1/auth/reset-password', ['token' => $token, 'password' => 'NewSecret2'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'NewSecret1'])->assertOk();
        $this->assertNotNull(PasswordResetCode::query()->first()->used_at);
    }

    public function test_change_password_checks_current_password(): void
    {
        [$user] = $this->client();
        $this->actingAsToken($user)->postJson('/api/v1/auth/change-password', ['currentPassword' => 'bad', 'newPassword' => 'Another123'])
            ->assertStatus(422)->assertJsonPath('errors.currentPassword', 'Mot de passe incorrect.');
        $this->postJson('/api/v1/auth/change-password', ['currentPassword' => 'Password123', 'newPassword' => 'Another123'])->assertNoContent();
    }

    public function test_back_office_login_uses_session_cookie(): void
    {
        $admin = $this->admin();
        $headers = ['Origin' => 'http://localhost:4200', 'Referer' => 'http://localhost:4200/admin/login'];
        $this->withHeaders($headers)->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'Password123'])->assertNoContent();
        $this->withHeaders($headers)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('roles', ['super_admin'])->assertJsonPath('role', 'admin')
            ->assertJsonCount(15, 'permissions');
        $this->assertSame(0, PersonalAccessToken::query()->count(), 'aucun jeton pour le back-office');
    }

    // Note : Laravel désactive la vérification CSRF pendant les tests unitaires ; le refus (419) sans
    // en-tête X-XSRF-TOKEN est vérifié en conditions réelles (voir docs/INTEGRATION.md).

    public function test_client_cannot_open_a_back_office_session(): void
    {
        [$user] = $this->client();
        $this->withHeaders(['Origin' => 'http://localhost:4200', 'Referer' => 'http://localhost:4200/'])
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123'])->assertStatus(403);
    }
}
