<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Uploads Disk
    |--------------------------------------------------------------------------
    |
    | Disco donde se persisten los archivos subidos por los usuarios: logotipos
    | de empresa y evidencias fotograficas de reportes. En produccion apunta al
    | bucket de Cloudflare R2 (disco "r2"); en local cae al disco "public".
    |
    */

    'uploads' => env('FILESYSTEM_UPLOADS_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Cloudflare R2: API compatible con S3, sin coste de egress.
        // Se usa para logotipos y evidencias cuando FILESYSTEM_UPLOADS_DISK=r2.
        'r2' => [
            'driver' => 's3',
            'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
            'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
            'region' => env('CLOUDFLARE_R2_REGION', 'auto'),
            'bucket' => env('R2_BUCKET_NAME'),
            'endpoint' => env(
                'R2_ENDPOINT_URL',
                env('CLOUDFLARE_ACCOUNT_ID') ? 'https://'.env('CLOUDFLARE_ACCOUNT_ID').'.r2.cloudflarestorage.com' : null
            ),
            // Dominio publico del bucket (r2.dev o dominio propio). Si esta vacio
            // las URLs se resuelven contra el endpoint path-style del bucket.
            'url' => env('R2_PUBLIC_URL'),
            'use_path_style_endpoint' => true,
            // R2 no soporta ACLs por objeto: el acceso publico se configura en el
            // bucket (r2.dev o dominio propio), por eso los objetos van privados.
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
