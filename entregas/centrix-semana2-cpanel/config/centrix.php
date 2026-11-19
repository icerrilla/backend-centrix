<?php

return [
    'inactividad_segundos' => 600,
    'max_intentos' => 3,
    'password_url' => env('CENTRIX_PASSWORD_URL'),
    // En producción se requiere HTTPS; el proxy debe estar configurado explícitamente.
    'https_obligatorio' => env('CENTRIX_REQUIRE_HTTPS', env('APP_ENV', 'production') === 'production'),
];
