<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDb(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $saludable = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $saludable ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'app' => [
                'name' => config('app.name'),
                'env' => app()->environment(),
                'version' => config('app.version', '1.0.0'),
            ],
            'checks' => $checks,
        ], $saludable ? 200 : 503);
    }

    private function checkDb(): bool|array
    {
        try {
            DB::select('select 1');

            return ['ok' => true, 'driver' => config('database.default')];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => 'unavailable'];
        }
    }

    private function checkCache(): bool|array
    {
        try {
            $key = 'health:'.uniqid();
            Cache::put($key, true, 5);
            $ok = Cache::get($key) === true;
            Cache::forget($key);

            return ['ok' => $ok, 'driver' => config('cache.default')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'unavailable'];
        }
    }

    private function checkQueue(): bool|array
    {
        try {
            $driver = config('queue.default');

            // Solo verificamos conectividad con drivers basados en DB
            if ($driver === 'database') {
                DB::table('jobs')->limit(1)->get();
            }

            return ['ok' => true, 'driver' => $driver];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'unavailable'];
        }
    }
}
