<?php

namespace App\Modules\Almacenes\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Service\VecinosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class VecinosController extends Controller
{
    public function get_vecinos(Request $request, $id_almacen): JsonResponse
    {
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El almacén es requerido'));
        }

        $result = VecinosService::get_vecinos((int) $id_almacen);
        return response()->json($result);
    }

    public function get_almacenes_disponibles_vecinos(Request $request, $id_almacen): JsonResponse
    {
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El almacén es requerido'));
        }

        $result = VecinosService::get_almacenes_disponibles_vecinos((int) $id_almacen);
        return response()->json($result);
    }

    public function agregar_vecino(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen_a' => 'required|integer',
            'id_almacen_b' => 'required|integer',
        ], [
            'id_almacen_a.required' => 'El almacén A es requerido',
            'id_almacen_b.required' => 'El almacén B es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = VecinosService::agregar_vecino(
            id_almacen_a: (int) $v['id_almacen_a'],
            id_almacen_b: (int) $v['id_almacen_b']
        );

        return response()->json($result);
    }

    public function eliminar_vecino(Request $request, $id_almacen_vecino): JsonResponse
    {
        if (!$id_almacen_vecino) {
            return response()->json(ApiResponse::error('El id del vecino es requerido'));
        }

        $result = VecinosService::eliminar_vecino((int) $id_almacen_vecino);
        return response()->json($result);
    }
}
