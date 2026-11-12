<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/establecer-password', function () {
    $nonce = base64_encode(random_bytes(18));

    return response()->view('auth.establecer-password', ['nonce' => $nonce])
        ->header('Cache-Control', 'no-store, private')
        ->header('Referrer-Policy', 'no-referrer')
        ->header('X-Content-Type-Options', 'nosniff')
        ->header('Content-Security-Policy', "default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'nonce-{$nonce}'; connect-src 'self'; form-action 'none'; base-uri 'none'; frame-ancestors 'none'");
})->name('password.establecer');
