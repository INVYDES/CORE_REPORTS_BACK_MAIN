<?php

return [
    'verificacion_obligatoria' => (bool) env('CORREO_VERIFICACION_OBLIGATORIA', false),

    'codigo_expiracion_minutos' => (int) env('CORREO_CODIGO_EXPIRACION_MINUTOS', 20),

    'reenvio_segundos' => (int) env('CORREO_REENVIO_SEGUNDOS', 60),

    'max_intentos_codigo' => (int) env('CORREO_MAX_INTENTOS_CODIGO', 5),

    'recordatorios_dias' => [7, 3, 1],

    'frontend_url' => rtrim((string) (env('FRONTEND_URL') ?: env('APP_URL', 'http://localhost')), '/'),

    'logo_url' => env('CORREO_LOGO_URL'),
];
