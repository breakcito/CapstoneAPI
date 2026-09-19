<?php

namespace App\Modules\Almacenes\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Service\ResponsablesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ResponsablesController extends Controller
{
    public function get_historial_responsables(Request $request, $id_almacen): JsonResponse
    {
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El almacen es requerido'));
        }

        $result = ResponsablesService::get_historial_responsables((int) $id_almacen);

        return response()->json($result);
    }

    public function nuevo_responsable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen' => 'required|integer',
            'id_empleado' => 'required|integer',
            'fecha_inicio' => 'required|date',
        ], [
            'id_almacen.required' => 'El almacén es requerido',
            'id_empleado.required' => 'El empleado es requerido',
            'fecha_inicio.required' => 'La fecha de inicio es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = ResponsablesService::nuevo_responsable(
            id_almacen: (int) $v['id_almacen'],
            id_empleado: (int) $v['id_empleado'],
            fecha_inicio: (string) $v['fecha_inicio'],
        );

        return response()->json($result);
    }

    public function inactivar_responsable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_responsable_almacen' => 'required|integer',
            'fecha_fin' => 'required|date',
        ], [
            'id_responsable_almacen.required' => 'El responsable es requerido',
            'fecha_fin.required' => 'La fecha de fin es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = ResponsablesService::inactivar_responsable(
            id_responsable: (int) $v['id_responsable_almacen'],
            fecha_fin: (string) $v['fecha_fin'],
        );

        return response()->json($result);
    }
}
