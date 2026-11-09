<?php

/*
|--------------------------------------------------------------------------
| Maison WODI Core API — /api/v1
|--------------------------------------------------------------------------
| Contrats respectés :
|  - site vitrine   : Wodi Core/site/docs/VITRINE-API.md
|  - back-office    : Wodi Core/site/docs/ADMIN-API.md      (session Sanctum, cookie HttpOnly)
|  - app mobile     : MaisonWodi/docs/API-MOBILE.md          (jetons Bearer access + refresh)
| JSON camelCase sans enveloppe ; erreurs { message, errors: { champ: "message" } }.
*/

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Client;
use App\Http\Controllers\Api\V1\Me;
use App\Http\Controllers\Api\V1\Pro;
use App\Http\Controllers\Api\V1\Public;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {

    // ------------------------------------------------------------- Public
    Route::get('catalog/categories', [Public\CatalogController::class, 'categories']);
    Route::get('catalog/services', [Public\CatalogController::class, 'services']);
    Route::get('catalog/services/{service}', [Public\CatalogController::class, 'service']);
    Route::get('team', [Public\TeamController::class, 'index']);
    Route::get('team/{professional}', [Public\TeamController::class, 'show']);
    Route::get('gallery', [Public\GalleryController::class, 'index']);
    Route::get('site-content', [Public\SalonController::class, 'siteContent']);
    Route::get('salon', [Public\SalonController::class, 'salon']);
    Route::get('booking-rules', [Public\SalonController::class, 'bookingRules']);
    Route::get('promotions', [Public\SalonController::class, 'promotions']);
    Route::post('contact-messages', [Public\ContactMessageController::class, 'store'])->middleware('throttle:contact');

    // ------------------------------------------------------ Authentification
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-sensitive');
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-sensitive');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-sensitive');

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('throttle:auth-sensitive');
        });
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {

        // ------------------------------------------------- Disponibilités
        Route::get('availability/days', [Me\AvailabilityController::class, 'days'])->name('availability.days');
        Route::get('availability/slots', [Me\AvailabilityController::class, 'slots'])->name('availability.slots');

        // ------------------------------------------------- Compte (tous rôles)
        Route::prefix('me')->group(function () {
            Route::patch('/', [Me\ProfileController::class, 'update']);
            Route::post('avatar', [Me\ProfileController::class, 'avatar'])->middleware('throttle:uploads');
            Route::get('notifications', [Me\NotificationController::class, 'index']);
            Route::post('notifications/read-all', [Me\NotificationController::class, 'readAll']);
            Route::post('notifications/{notification}/read', [Me\NotificationController::class, 'read']);
            Route::get('notification-preferences', [Me\NotificationController::class, 'preferences']);
            Route::put('notification-preferences', [Me\NotificationController::class, 'updatePreferences']);
            Route::post('devices', [Me\NotificationController::class, 'registerDevice']);
            Route::delete('devices/{token}', [Me\NotificationController::class, 'unregisterDevice'])->where('token', '.+');

            // Espace client (rôle client uniquement)
            Route::middleware('role.is:client')->group(function () {
                Route::get('bookings', [Client\BookingController::class, 'index']);
                Route::post('bookings', [Client\BookingController::class, 'store']);
                Route::get('bookings/{booking}', [Client\BookingController::class, 'show']);
                Route::post('bookings/{booking}/cancel', [Client\BookingController::class, 'cancel']);
                Route::post('bookings/{booking}/reschedule', [Client\BookingController::class, 'reschedule']);
                Route::get('payments', [Client\AccountController::class, 'payments']);
                Route::get('loyalty', [Client\AccountController::class, 'loyalty']);
            });
        });

        // ------------------------------------------- Espace professionnel
        Route::prefix('pro')->middleware('role.is:barber')->group(function () {
            Route::get('dashboard', [Pro\DashboardController::class, 'dashboard']);
            Route::get('activity', [Pro\DashboardController::class, 'activity']);
            Route::get('bookings', [Pro\BookingController::class, 'index']);
            Route::get('bookings/{booking}', [Pro\BookingController::class, 'show']);
            Route::post('bookings/{booking}/{action}', [Pro\BookingController::class, 'action'])
                ->whereIn('action', ['confirm', 'decline', 'start', 'complete', 'no-show']);
            Route::get('agenda', [Pro\ScheduleController::class, 'agenda']);
            Route::get('schedule', [Pro\ScheduleController::class, 'show']);
            Route::put('schedule', [Pro\ScheduleController::class, 'update']);
            Route::get('unavailabilities', [Pro\UnavailabilityController::class, 'index']);
            Route::post('unavailabilities', [Pro\UnavailabilityController::class, 'store']);
            Route::delete('unavailabilities/{unavailability}', [Pro\UnavailabilityController::class, 'destroy']);
            Route::get('absences', [Pro\AbsenceController::class, 'index']);
            Route::post('absences', [Pro\AbsenceController::class, 'store']);
            Route::delete('absences/{absence}', [Pro\AbsenceController::class, 'destroy']);
            Route::get('profile', [Pro\ProfileController::class, 'show']);
            Route::patch('profile', [Pro\ProfileController::class, 'update']);
            Route::get('services', [Pro\ServiceController::class, 'index']);
            Route::post('services/{service}/toggle', [Pro\ServiceController::class, 'toggle']);
        });

        // ------------------------------------------------- Back-office
        Route::prefix('admin')->middleware('role.is:admin')->group(function () {
            Route::get('dashboard', Admin\DashboardController::class)->middleware('permission:dashboard.view');

            Route::middleware('permission:reservations.manage')->group(function () {
                Route::apiResource('reservations', Admin\ReservationController::class)->except('update');
                Route::patch('reservations/{reservation}', [Admin\ReservationController::class, 'update']);
                Route::post('reservations/{reservation}/{action}', [Admin\ReservationController::class, 'action'])
                    ->whereIn('action', ['confirm', 'start', 'complete', 'no-show', 'cancel']);
            });

            Route::middleware('permission:clients.manage')->group(function () {
                Route::apiResource('clients', Admin\ClientController::class)->except('update');
                Route::patch('clients/{client}', [Admin\ClientController::class, 'update']);
                Route::get('clients/{client}/bookings', [Admin\ClientController::class, 'bookings']);
                Route::get('clients/{client}/payments', [Admin\ClientController::class, 'payments'])->middleware('permission:payments.view');
                Route::get('clients/{client}/loyalty', [Admin\ClientController::class, 'loyaltyEvents']);
                Route::post('clients/{client}/loyalty', [Admin\ClientController::class, 'loyalty'])->middleware('permission:loyalty.manage');
            });

            Route::middleware('permission:services.manage')->group(function () {
                Route::apiResource('categories', Admin\CategoryController::class)->except('update');
                Route::patch('categories/{category}', [Admin\CategoryController::class, 'update']);
                Route::apiResource('services', Admin\ServiceController::class)->except('update');
                Route::patch('services/{service}', [Admin\ServiceController::class, 'update']);
                Route::post('services/{service}/toggle', [Admin\ServiceController::class, 'toggle']);
            });

            Route::middleware('permission:team.manage')->group(function () {
                Route::apiResource('staff', Admin\StaffController::class)->except('update')->parameters(['staff' => 'staff']);
                Route::patch('staff/{staff}', [Admin\StaffController::class, 'update']);
                Route::post('staff/{staff}/toggle', [Admin\StaffController::class, 'toggle']);
                Route::post('staff/{staff}/account', [Admin\StaffController::class, 'account']);
            });

            Route::middleware('permission:schedules.manage')->group(function () {
                Route::get('staff/{staff}/schedule', [Admin\StaffController::class, 'schedule']);
                Route::put('staff/{staff}/schedule', [Admin\StaffController::class, 'saveSchedule']);
                Route::apiResource('unavailabilities', Admin\UnavailabilityController::class)->except('update');
                Route::patch('unavailabilities/{unavailability}', [Admin\UnavailabilityController::class, 'update']);
                Route::post('unavailabilities/{unavailability}/approve', [Admin\UnavailabilityController::class, 'approve']);
                Route::post('unavailabilities/{unavailability}/reject', [Admin\UnavailabilityController::class, 'reject']);
            });

            Route::get('payments', [Admin\PaymentController::class, 'index'])->middleware('permission:payments.view');
            Route::get('payments/{payment}', [Admin\PaymentController::class, 'show'])->middleware('permission:payments.view');
            Route::post('payments', [Admin\PaymentController::class, 'store'])->middleware('permission:payments.manage');

            Route::middleware('permission:promotions.manage')->group(function () {
                Route::apiResource('promotions', Admin\PromotionController::class)->except('update');
                Route::patch('promotions/{promotion}', [Admin\PromotionController::class, 'update']);
                Route::post('promotions/{promotion}/toggle', [Admin\PromotionController::class, 'toggle']);
            });
            Route::get('loyalty/events', [Admin\PromotionController::class, 'loyaltyEvents'])->middleware('permission:loyalty.manage');

            Route::middleware('permission:gallery.manage')->group(function () {
                Route::apiResource('gallery', Admin\GalleryController::class)->except('update')->parameters(['gallery' => 'image']);
                Route::patch('gallery/{image}', [Admin\GalleryController::class, 'update']);
                Route::post('gallery/{image}/toggle', [Admin\GalleryController::class, 'toggle']);
            });
            Route::post('media', [Admin\MediaController::class, 'store'])->middleware('throttle:uploads');

            Route::get('site-content', [Admin\SiteController::class, 'content'])->middleware('permission:content.manage');
            Route::put('site-content', [Admin\SiteController::class, 'updateContent'])->middleware('permission:content.manage');
            Route::get('settings', [Admin\SiteController::class, 'settings'])->middleware('permission:settings.manage');
            Route::put('settings', [Admin\SiteController::class, 'updateSettings'])->middleware('permission:settings.manage');

            Route::middleware('permission:notifications.view')->group(function () {
                Route::get('notifications', [Admin\NotificationController::class, 'index']);
                Route::get('notifications/unread-count', [Admin\NotificationController::class, 'unreadCount']);
                Route::post('notifications/read-all', [Admin\NotificationController::class, 'readAll']);
                Route::get('notifications/{notification}', [Admin\NotificationController::class, 'show']);
                Route::post('notifications/{notification}/read', [Admin\NotificationController::class, 'read']);
                Route::get('contact-messages', [Admin\ContactMessageController::class, 'index']);
                Route::get('contact-messages/{message}', [Admin\ContactMessageController::class, 'show']);
                Route::post('contact-messages/{message}/read', [Admin\ContactMessageController::class, 'read']);
                Route::patch('contact-messages/{message}/status', [Admin\ContactMessageController::class, 'status']);
            });

            Route::middleware('permission:users.manage')->group(function () {
                Route::get('roles', [Admin\UserController::class, 'roles']);
                Route::get('permissions', [Admin\UserController::class, 'permissions']);
                Route::apiResource('users', Admin\UserController::class)->except('update');
                Route::patch('users/{user}', [Admin\UserController::class, 'update']);
                Route::post('users/{user}/toggle', [Admin\UserController::class, 'toggle']);
            });
        });
    });
});
