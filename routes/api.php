<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController; 
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubdependenciaController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\EncuestaController;
use App\Http\Controllers\EmpresaController;

/*
|--------------------------------------------------------------------------
| API Routes - Sistema Core Reports
|--------------------------------------------------------------------------
|
| Prefijo automático: "/api"
| Ejemplo: http://localhost:8000/api/login
|
*/

// =========================================================================
// 1. RUTAS PÚBLICAS
// =========================================================================
Route::post('/login', [AuthController::class, 'login']);

// =========================================================================
// 2. RUTAS PROTEGIDAS (Sanctum)
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // 👇 1. MÓDULO DE TICKETS
    Route::get('/tickets', [TicketController::class, 'index']);       // Para listar
    Route::post('/tickets', [TicketController::class, 'store']);     // Para crear
    Route::get('/tickets/{id}', [TicketController::class, 'show']);   // Detalle
    Route::put('/tickets/{id}', [TicketController::class, 'update']); // Actualizar / Asignar
    Route::get('/tecnicos', [TicketController::class, 'tecnicos']);   // Lista de técnicos

    // 👇 2. MÓDULO DE USUARIOS / EQUIPO
    Route::get('/usuarios', [UserController::class, 'index']);
    Route::post('/usuarios', [UserController::class, 'store']);
    Route::put('/usuarios/{id}', [UserController::class, 'update']);

    // 👇 3. MÓDULO DE SUBDEPENDENCIAS / ÁREAS
    Route::get('/subdependencias', [SubdependenciaController::class, 'index']);
    Route::post('/subdependencias', [SubdependenciaController::class, 'store']);
    Route::put('/subdependencias/{id}', [SubdependenciaController::class, 'update']);
    Route::delete('/subdependencias/{id}', [SubdependenciaController::class, 'destroy']);

    // 👇 4. MÓDULO DE SERVICIOS PROGRAMADOS
    Route::get('/servicios', [ServicioController::class, 'index']);
    Route::post('/servicios', [ServicioController::class, 'store']);
    Route::get('/servicios/{id}', [ServicioController::class, 'show']);
    Route::put('/servicios/{id}', [ServicioController::class, 'update']);

    // 👇 5. MÓDULO DE REPORTES DE SERVICIO
    Route::get('/reportes', [ReporteController::class, 'index']);
    Route::post('/reportes', [ReporteController::class, 'store']);
    Route::get('/reportes/{id}', [ReporteController::class, 'show']);
    Route::put('/reportes/{id}', [ReporteController::class, 'update']);

    // 👇 6. MÓDULO DE ENCUESTAS DE SATISFACCIÓN
    Route::post('/encuestas', [EncuestaController::class, 'store']);
    Route::get('/encuestas/{idReporte}', [EncuestaController::class, 'show']);

    // 👇 7. MÓDULO DE EMPRESA / CONFIGURACIÓN COMERCIAL Y LOGOTIPO
    Route::get('/empresa', [EmpresaController::class, 'show']);
    Route::put('/empresa', [EmpresaController::class, 'update']);
    Route::post('/empresa/logo', [EmpresaController::class, 'uploadLogo']);
});

