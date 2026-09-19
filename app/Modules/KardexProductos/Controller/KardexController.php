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
        $mes = $request->query('mes');
        $yearcito = $request->query('yearcito');

        if (!$id_almacen || !$mes || !$yearcito) {
            return response()->json(ApiResponse::error('Los parametros de almacen, mes y año son requeridos'));
        }

        $result = KardexService::get_resumen_kardex((int) $id_almacen, (int) $mes, (int) $yearcito);

        return response()->json($result);
    }
}
