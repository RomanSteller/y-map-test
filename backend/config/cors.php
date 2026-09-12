<?php

return [
    // Paths the SPA hits cross-origin: the API and the CSRF-cookie endpoint.
    'paths' => ['api/*', 'login', 'logout', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Explicit origins (not '*') because credentials (cookies) are sent.
    'allowed_origins' => array_filter(explode(
        ',',
        (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost')
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
