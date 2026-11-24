<?php

return [
    'title' => 'CoreReports API',
    'description' => 'Documentación OpenAPI para CoreReports - Laravel + Vue',
    'base_url' => env('APP_URL', 'http://localhost:8000'),
    'routes' => [
        [
            'include' => ['api/v1/*'],
            'exclude' => [],
            'auth' => [
                'enabled' => true,
                'default' => false,
                'in' => 'bearer',
                'name' => 'Authorization',
                'value' => 'Bearer {token}',
            ],
        ],
    ],
    'type' => 'static',
    'theme' => 'default',
    'static' => ['output_path' => 'public/docs'],
];
