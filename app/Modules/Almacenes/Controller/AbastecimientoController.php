<?php

namespace App\Modules\Almacenes\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Service\AbastecimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AbastecimientoController extends Controller
{

    public function get_minas_abastecidas(Request $request, $id_almacen): JsonResponse
    {
        if (! $id_almacen) {
            return response()->json(ApiResponse::error('El almacen es requerido'));
        }

        $result = AbastecimientoService::get_minas_abastecidas((int) $id_almacen);

        return response()->json($result);
    }

    public function nueva_mina_por_abastecer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen' => 'required|integer',
            'id_mina' => 'required|integer',
        ], [
            'id_almacen.required' => 'El almacén es requerido',
            'id_mina.required' => 'La mina es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = AbastecimientoService::nueva_mina_por_abastecer(
            id_almacen: (int) $v['id_almacen'],
            id_mina: (int) $v['id_mina']
        );

        return response()->json($result);
    }

    public function eliminar_abastecimiento_mina(Request $request, $id_almacen_mina): JsonResponse
    {
        if (! $id_almacen_mina) {
            return response()->json(ApiResponse::error('El id_asignacion es requerido'));
        }

        $result = AbastecimientoService::eliminar_abastecimiento_mina((int) $id_almacen_mina);

        return response()->json($result);
    }

    public function get_minas(Request $request, $id_almacen): JsonResponse
    {
        if (! $id_almacen) {
            return response()->json(ApiResponse::error('El almacen es requerido'));
        }

        $result = AbastecimientoService::get_minas((int) $id_almacen);

        return response()->json($result);
    }
}
