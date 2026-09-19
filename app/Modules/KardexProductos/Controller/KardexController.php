<?php

namespace App\Modules\KardexProductos\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\KardexProductos\Service\KardexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class KardexController extends Controller
{
    public function get_resumen_kardex(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        $mes = $request->query('mes') ? (int) $request->query('mes') : null;
        $yearcito = $request->query('yearcito') ? (int) $request->query('yearcito') : null;

        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El almacén es requerido'));
        }

        $result = KardexService::get_resumen_kardex((int) $id_almacen, $mes, $yearcito);

        return response()->json($result);
    }
}
