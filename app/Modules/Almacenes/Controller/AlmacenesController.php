<?php

namespace App\Modules\Almacenes\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Service\AlmacenesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AlmacenesController extends Controller
{
    /**
     * Listar almacenes.
     *
     * Acepta `?para_carbon=true|false`. Si no se envia, NO se filtra y se
     * devuelven tanto logistica como carbon (la vista pagina por tabs y
     * siempre pasa el flag explicito).
     */
    public function get_almacenes(Request $request): JsonResponse
    {
        $para_carbon = $request->has('para_carbon')
            ? $request->boolean('para_carbon')
            : null;

        $result = AlmacenesService::get_almacenes(para_carbon: $para_carbon);

        return response()->json($result);
    }

    public function crear_almacen(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'descripcion' => 'nullable|string',
            // es_principal solo aplica para almacenes de logistica. La vista
            // de carbon no envia este campo; lo forzamos a false en ese caso.
            'es_principal' => 'sometimes|boolean',
            'para_carbon' => 'sometimes|boolean',
            // Ubicacion geografica (opcional, tanto para carbon como logistica)
            'id_departamento' => 'nullable|integer',
            'id_provincia' => 'nullable|integer',
            'id_distrito' => 'nullable|integer',
            'direccion' => 'nullable|string|max:255',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $para_carbon = (bool) ($v['para_carbon'] ?? false);
        // es_principal no tiene sentido para un almacen de carbon: lo
        // forzamos a false en ese caso para mantener la regla de negocio.
        $es_principal = $para_carbon ? false : (bool) ($v['es_principal'] ?? false);

        $result = AlmacenesService::crear_almacen(
            nombre: (string) $v['nombre'],
            es_principal: $es_principal,
            para_carbon: $para_carbon,
            descripcion: isset($v['descripcion']) ? (string) $v['descripcion'] : null,
            id_departamento: isset($v['id_departamento']) ? (int) $v['id_departamento'] : null,
            id_provincia: isset($v['id_provincia']) ? (int) $v['id_provincia'] : null,
            id_distrito: isset($v['id_distrito']) ? (int) $v['id_distrito'] : null,
            direccion: isset($v['direccion']) ? (string) $v['direccion'] : null,
        );

        return response()->json($result);
    }
}
