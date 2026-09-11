<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DependenciaController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\EquipoController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\ServicioController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\EncuestaController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\NotificacionController;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class,'login']);
    Route::post('/register', [AuthController::class,'register']);
    Route::post('/public/register-compania', [PublicController::class,'registerCompania']);

    Route::middleware(['auth:sanctum','dependencia','licencia'])->group(function(){
    Route::get('/me', [AuthController::class,'me']);
    Route::post('/logout', [AuthController::class,'logout']);

    Route::apiResource('dependencias', DependenciaController::class)->only(['index','show','update']);
    Route::apiResource('areas', AreaController::class);
    Route::apiResource('equipos', EquipoController::class);
    Route::apiResource('usuarios', UsuarioController::class);
    Route::apiResource('tickets', TicketController::class);
    Route::patch('tickets/{ticket}/asignar', [TicketController::class,'asignar']);
    Route::apiResource('servicios', ServicioController::class);
    Route::apiResource('reportes', ReporteController::class);
    Route::patch('reportes/{reporte}/conformidad', [ReporteController::class,'conformidad']);
    Route::get('reportes/{reporte}/pdf', [ReporteController::class,'pdf']);
    Route::apiResource('materiales', MaterialController::class)->parameters(['materiales'=>'material']);
    Route::post('materiales/{material}/entrada', [MaterialController::class,'entrada']);
    Route::get('materiales/{material}/movimientos', [MaterialController::class,'movimientos']);
    Route::apiResource('encuestas', EncuestaController::class)->only(['index','store','show']);

    Route::get('history', [HistoryController::class,'index']);
    Route::apiResource('notificaciones', NotificacionController::class)->only(['index','store']);
    Route::patch('notificaciones/{notificacion}/leida', [NotificacionController::class,'markRead']);
    Route::post('notificaciones/leidas-todas', [NotificacionController::class,'markAllRead']);
    Route::post('notificaciones/{notificacion}/reenviar', [NotificacionController::class,'resend']);

    Route::get('dashboard/tickets-por-asignar', [DashboardController::class,'ticketsPorAsignar']);
    Route::get('dashboard/servicios-proximos', [DashboardController::class,'serviciosProximos']);
    Route::get('dashboard/actividad-reciente', [DashboardController::class,'actividadReciente']);
    Route::get('dashboard', [DashboardController::class,'index']);

    Route::prefix('analytics')->group(function(){
        Route::get('sla', [AnalyticsController::class,'sla']);
        Route::get('mttr', [AnalyticsController::class,'mttr']);
        Route::get('horas-hombre', [AnalyticsController::class,'horasHombre']);
        Route::get('horas-por-tecnico', [AnalyticsController::class,'horasPorTecnico']);
        Route::get('retrabajos', [AnalyticsController::class,'retrabajos']);
        Route::get('categoria', [AnalyticsController::class,'categoria']);
        Route::get('tiempos', [AnalyticsController::class,'tiempos']);
        Route::get('volumen', [AnalyticsController::class,'volumen']);
        Route::get('eficacia', [AnalyticsController::class,'eficacia']);
        Route::get('proactivo-reactivo', [AnalyticsController::class,'proactivoReactivo']);
        Route::get('conteo', [AnalyticsController::class,'conteo']);
        Route::get('all', [AnalyticsController::class,'all']);
    });
    });
}); // v1

// Backward compatibility: keep old routes without prefix for 1 release
Route::post('/login', [AuthController::class,'login']);
Route::post('/register', [AuthController::class,'register']);
Route::post('/public/register-compania', [PublicController::class,'registerCompania']);
Route::middleware(['auth:sanctum','dependencia','licencia'])->group(function(){
    Route::get('/me', [AuthController::class,'me']);
    Route::post('/logout', [AuthController::class,'logout']);
    Route::get('history', [HistoryController::class,'index']);
    Route::get('dashboard', [DashboardController::class,'index']);
});
