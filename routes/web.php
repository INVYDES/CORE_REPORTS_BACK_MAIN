<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Healthcheck para balanceadores / orquestadores (Cloud Run, Railway...)
Route::get('/health', HealthController::class);
