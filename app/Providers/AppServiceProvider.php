<?php

namespace App\Providers;

use App\Services\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Une seule instance par requête (cache mémoire des paramètres).
        $this->app->scoped(SettingsService::class);
    }

    public function boot(): void
    {
        // Contrat des frontends : pas d'enveloppe { data }.
        JsonResource::withoutWrapping();

        // Identifiants UUID : un identifiant mal formé donne 404 (et jamais une erreur SQL).
        foreach (['booking', 'reservation', 'client', 'category', 'service', 'staff', 'unavailability', 'absence',
            'payment', 'promotion', 'image', 'notification', 'user', 'message', 'professional'] as $param) {
            Route::pattern($param, '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
        }

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('auth-sensitive', fn (Request $request) => Limit::perMinute(5)->by('auth:'.$request->ip()));
        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(3)->by('contact:'.$request->ip()),
            Limit::perDay(30)->by('contact-day:'.$request->ip()),
        ]);
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
    }
}
