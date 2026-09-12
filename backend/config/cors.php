<?php

return [
    // Пути, куда SPA стучится с другого origin: сам API и эндпоинт CSRF-куки.
    'paths' => ['api/*', 'login', 'logout', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Перечисляем origin'ы явно (не '*'), потому что шлём куки.
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
