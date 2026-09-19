<?php

namespace App\Controllers;

use App\Services\HealthCheckService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    /**
     * Endpoint público de health check. Retorna HTTP 200 cuando todos los
     * servicios críticos están operativos, o 503 cuando alguno falla.
     */
    public function check(): JsonResponse
    {
        $result = $this->check_all();
        $httpCode = ($result['success'] ?? false) ? 200 : 503;

        return response()->json($result, $httpCode);
    }

    private function check_all(): array
    {
        $checks = [
            'database' => $this->ping_database(),
        ];

        $allHealthy = !in_array(false, $checks, true);

        $payload = [
            'status' => $allHealthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];

        return $allHealthy
            ? ApiResponse::success($payload, 'Service is healthy')
            : ApiResponse::error('Service is degraded', $payload);
    }

    private function ping_database(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}