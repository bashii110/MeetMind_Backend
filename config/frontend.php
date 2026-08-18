<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend URLs
    |--------------------------------------------------------------------------
    |
    | The Flutter app is the client, not a Blade/Vite frontend, so
    | password-reset and post-verification links in emails point at either
    | a deep link the app can handle, or a minimal static web fallback page
    | you host separately. Configure both via .env.
    |
    */

    // e.g. meetmindai://reset-password (custom scheme) or https://app.meetmind.ai/reset-password
    'reset_password_url' => env('FRONTEND_RESET_PASSWORD_URL', 'meetmindai://reset-password'),

    // Where to send the browser after tapping the verification link, since
    // the verification itself happens against the API (see AuthController).
    'email_verified_redirect_url' => env('FRONTEND_EMAIL_VERIFIED_URL', 'meetmindai://email-verified'),

];
