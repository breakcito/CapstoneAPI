<?php

namespace App\Modules\Indicadores\Controller;

use App\Modules\Indicadores\Service\IndicadoresService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IndicadoresController extends Controller
{
    /**
     * Helper: extrae id_almacen del query string si viene.
     */
    private function idAlmacenFromRequest(Request $request): ?int
    {
        $raw = $request->query('id_almacen');
        if ($raw === null || $raw === '') {
            return null;
        }
        $n = (int) $raw;
        return $n > 0 ? $n : null;
    }

    public function get_generales(Request $request): JsonResponse
    {
        return response()->json(
            IndicadoresService::get_generales($this->idAlmacenFromRequest($request)),
        );
    }

    public function get_stock_valorizado(Request $request): JsonResponse
    {
        return response()->json(
            IndicadoresService::get_stock_valorizado(
                $this->idAlmacenFromRequest($request),
            ),
        );
    }

    public function get_top_productos(Request $request): JsonResponse
    {
        $dias = $request->query('dias') ? (int) $request->query('dias') : 30;
        return response()->json(
            IndicadoresService::get_top_productos(
                $dias,
                $this->idAlmacenFromRequest($request),
            ),
        );
    }

    public function get_rotacion_mensual(Request $request): JsonResponse
    {
        $meses = $request->query('meses') ? (int) $request->query('meses') : 12;
        return response()->json(
            IndicadoresService::get_rotacion_mensual(
                $meses,
                $this->idAlmacenFromRequest($request),
            ),
        );
    }

    public function get_alertas_stock(Request $request): JsonResponse
    {
        return response()->json(
            IndicadoresService::get_alertas_stock(
                $this->idAlmacenFromRequest($request),
            ),
        );
    }

    public function get_tap_por_almacen(Request $request): JsonResponse
    {
        return response()->json(
            IndicadoresService::get_tap_por_almacen(
                $this->idAlmacenFromRequest($request),
            ),
        );
    }

    public function get_tendencias_mensuales(Request $request): JsonResponse
    {
        return response()->json(
            IndicadoresService::get_tendencias_mensuales(
                $this->idAlmacenFromRequest($request),
            ),
        );
    }
}