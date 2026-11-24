<?php

return [
    'enabled' => env('TELESCOPE_ENABLED', true),
    'domain' => env('TELESCOPE_DOMAIN'),
    'path' => env('TELESCOPE_PATH', 'telescope'),
    'driver' => env('TELESCOPE_DRIVER', 'database'),
    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],
    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION', null),
        'queue' => env('TELESCOPE_QUEUE', null),
    ],
    'middleware' => ['web'],
    'watchers' => [
        \Laravel\Telescope\Watchers\RequestWatcher::class => true,
        \Laravel\Telescope\Watchers\QueryWatcher::class => true,
        \Laravel\Telescope\Watchers\JobWatcher::class => true,
        \Laravel\Telescope\Watchers\LogWatcher::class => true,
    ],
];
