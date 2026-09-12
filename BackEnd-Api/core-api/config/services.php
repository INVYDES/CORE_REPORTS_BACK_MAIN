<?php

return [

    // ── Mercado Pago ──────────────────────────────────────────────────────
    'mercadopago' => [
        'token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
    ],

    // ── Frontend (back_urls de la pasarela) ──────────────────────────────
    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:5173'),
    ],

];
