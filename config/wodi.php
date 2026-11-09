<?php

/*
 * Configuration métier Maison WODI (valeurs techniques ; les règles du salon
 * modifiables dans le back-office sont stockées en base : table site_settings).
 */
return [
    'timezone' => env('APP_BUSINESS_TIMEZONE', 'Africa/Abidjan'),
    'currency' => env('APP_CURRENCY', 'XOF'),

    'tokens' => [
        'access_ttl' => (int) env('MOBILE_ACCESS_TOKEN_TTL', 120),
        'refresh_ttl' => (int) env('MOBILE_REFRESH_TOKEN_TTL', 43200),
    ],

    'reset_password' => [
        'mobile_url' => env('MOBILE_RESET_PASSWORD_URL', 'maisonwodi://reset-password?token={token}'),
        'admin_url' => env('ADMIN_RESET_PASSWORD_URL', 'http://localhost:4200/admin/reset-password?token={token}'),
        'ttl' => 60,
    ],

    'vitrine_webhook' => [
        'url' => env('VITRINE_BUILD_WEBHOOK_URL'),
        'secret' => env('VITRINE_BUILD_WEBHOOK_SECRET'),
    ],

    'expo' => [
        'enabled' => (bool) env('EXPO_PUSH_ENABLED', false),
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        'url' => 'https://exp.host/--/api/v2/push/send',
    ],

    'media' => [
        'disk' => env('MEDIA_DISK', 'public'),
        'max_kb' => 5120,
        'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'docs_enabled' => (bool) env('API_DOCS_ENABLED', env('APP_ENV') !== 'production'),

    'booking' => [
        'max_notes_length' => 500,
        'max_days_range' => 62,
    ],
];
