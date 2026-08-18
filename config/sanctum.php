<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from these domains will receive stateful API authentication
    | cookies. Typically used by web/SPA clients; the Flutter app instead
    | authenticates via bearer tokens (see FR-1.5 in SRD.md), so this list
    | mainly matters for the admin web dashboard, if one is added later.
    |
    */

    'stateful' => explode(',', (string) env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'
    )),

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Access tokens issued to the Flutter client expire after this many
    | minutes, forcing the refresh-token rotation flow described in
    | ARCHITECTURE.md 3.3. Null = tokens never expire (not recommended).
    |
    */

    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 60 * 24), // 24h access token

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
