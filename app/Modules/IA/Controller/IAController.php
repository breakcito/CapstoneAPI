<?php

namespace App\Modules\IA\Controller;

use App\Modules\IA\Service\IAService;
use App\Modules\IA\Service\ReportGenerator;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class IAController extends Controller
{
    /**
     * POST /api/ia/chat/stream
     *
     * Unica excepcion documentada a la regla de ApiResponse (README:50): un
     * stream SSE no puede ser JSON. Todas las validaciones ocurren ANTES de
     * abrir el stream, para que un 401 o un 422 lleguen como JSON y el cliente
     * pueda distinguirlos de un corte de conexion.
     */
    public function chatStream(Request $request): StreamedResponse|JsonResponse
    {
        if (! $this->esAdmin($request)) {
            return response()->json(ApiResponse::error('No autorizado para el modulo de IA'), 403);
        }

        $mensajes = $request->input('messages');
        $adjuntos = $request->input('adjuntos', []);

        if (! is_array($mensajes) || $mensajes === []) {
            return response()->json(ApiResponse::error('Debes enviar al menos un mensaje'), 422);
        }

        if (count($mensajes) > (int) config('ia.max_mensajes')) {
            return response()->json(ApiResponse::error(sprintf(
                'La conversacion excede los %d mensajes. Empieza una nueva.',
                (int) config('ia.max_mensajes'),
            )), 422);
        }

        if (! is_array($adjuntos)) {
            return response()->json(ApiResponse::error('El campo adjuntos debe ser una lista'), 422);
        }

        if (count($adjuntos) > (int) config('ia.max_adjuntos')) {
            return response()->json(ApiResponse::error(sprintf(
                'Maximo %d archivos por mensaje.',
                (int) config('ia.max_adjuntos'),
            )), 422);
        }

        // Se validan los adjuntos aqui, con JSON de error, no ya en el stream.
        try {
            IAService::validarAdjuntos($adjuntos);
        } catch (Throwable $e) {
            return response()->json(ApiResponse::error($e->getMessage()), 422);
        }

        return response()->stream(
            function () use ($mensajes, $adjuntos) {
                // Una respuesta del modelo puede tardar minutos. Sin esto, el
                // max_execution_time de PHP (30s por defecto en el web SAPI)
                // corta el stream a mitad y el chat se queda colgado.
                set_time_limit(0);
                ini_set('max_execution_time', '0');

                $emitir = function (string $evento, array $payload): void {
                    echo 'event: '.$evento."\n";
                    echo 'data: '.json_encode($payload ?: (object) [], JSON_UNESCAPED_UNICODE)."\n\n";

                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    flush();
                };

                try {
                    IAService::streamChat($mensajes, $adjuntos, $emitir);
                } catch (Throwable $e) {
                    $emitir('error', ['mensaje' => $e->getMessage()]);
                    $emitir('fin', []);
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store, no-transform',
                'Connection' => 'keep-alive',
                // Critico: sin esto el proxy (Traefik/Nginx) acumula el
                // response en su buffer y el cliente no ve nada hasta el final.
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /**
     * GET /api/ia/reporte/{uuid}
     */
    public function descargarReporte(Request $request, string $uuid): mixed
    {
        if (! $this->esAdmin($request)) {
            return response()->json(ApiResponse::error('No autorizado para el modulo de IA'), 403);
        }

        if (! ReportGenerator::existe($uuid)) {
            return response()->json(ApiResponse::error('Reporte no encontrado o expirado'), 404);
        }

        $nombre = ReportGenerator::nombreDe($uuid);

        return ReportGenerator::descargar($uuid, $nombre);
    }

    /**
     * JwtAuthMiddleware deja el usuario en los attributes del request.
     */
    private function esAdmin(Request $request): bool
    {
        $usuario = $request->attributes->get('auth_user');

        return $usuario !== null
            && (int) ($usuario->id_rol ?? 0) === (int) config('ia.rol_admin');
    }
}
