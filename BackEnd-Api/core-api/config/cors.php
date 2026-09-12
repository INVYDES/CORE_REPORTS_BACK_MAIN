<?php

$rawOrigins = env('CORS_ALLOWED_ORIGINS', '*');
$parsedOrigins = preg_split('/[\s,]+/', $rawOrigins, -1, PREG_SPLIT_NO_EMPTY) ?: ['*'];
if (! in_array('*', $parsedOrigins)) {
    $parsedOrigins[] = '*';
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $parsedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['*'],
    'max_age' => 86400,
    'supports_credentials' => false,
];
