<?php

use Illuminate\Support\Facades\Route;

// This backend is API-only; the Flutter app is the client. This route just
// confirms the server is up when visited directly in a browser.
Route::get('/', fn () => response()->json([
    'app' => config('app.name'),
    'api' => url('/api/v1/ping'),
]));
