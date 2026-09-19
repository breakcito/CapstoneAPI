<?php

namespace App\Modules\Contratistas\Controller;

use App\Modules\Contratistas\Service\ContratistasService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContratistasController
{
    /**
     * Extrae id_empleado y nombre completo del usuario autenticado (JWT).
     */
    private function getAuthUserInfo(Request $request): array
    {
        $authUser = $request->attributes->get('auth_user');
        if (! is_object($authUser)) {
            return [null, null];
        }
        $idEmpleado = isset($authUser->id_empleado) ? (int) $authUser->id_empleado : null;
        $nombreCompleto = trim(($authUser->nombre ?? '') . ' ' . ($authUser->apellido ?? '')) ?: null;

        return [$idEmpleado, $nombreCompleto];
    }

    public function get_contratistas(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina') ? (int) $request->query('id_mina') : null;
        $result = ContratistasService::get_contratistas($id_mina);

        return response()->json($result);
    }

    public function crear_contratista(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'genero' => 'nullable|string|max:16',
            'dni' => 'nullable|string|max:20',
            'ruc' => 'nullable|string|max:20',
            'carnet_extranjeria' => 'nullable|string|max:20',
            'pasaporte' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:128',
            'foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
            'ids_labor' => 'nullable|array',
            'ids_labor.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ContratistasService::crear_contratista(
            nombre: (string) $request->input('nombre'),
            apellido: (string) $request->input('apellido'),
            id_mina: $request->input('id_mina') ? (int) $request->input('id_mina') : null,
            genero: $request->input('genero'),
            dni: $request->input('dni'),
            ruc: $request->input('ruc'),
            carnet_extranjeria: $request->input('carnet_extranjeria'),
            pasaporte: $request->input('pasaporte'),
            fecha_nacimiento: $request->input('fecha_nacimiento'),
            direccion: $request->input('direccion'),
            telefono: $request->input('telefono'),
            email: $request->input('email'),
            foto: $request->file('foto'),
            ids_labor: (array) $request->input('ids_labor', [])
        );

        return response()->json($result);
    }

    public function actualizar_foto(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpg,png,jpeg',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ContratistasService::actualizar_foto($id, $request->file('foto'));

        return response()->json($result);
    }

    public function asignar_labores(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'ids_labor' => 'nullable|array',
            'ids_labor.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ContratistasService::asignar_labores(
            id_contratista: $id,
            id_mina: $request->input('id_mina') ? (int) $request->input('id_mina') : null,
            ids_labor: (array) $request->input('ids_labor', [])
        );

        return response()->json($result);
    }

    /**
     * Actualizar un contratista (datos personales + contacto).
     * NO se editan mina ni labores aquí (van por sus endpoints propios).
     * La foto va por `POST /contratistas/{id}/foto`.
     */
    public function actualizar_contratista(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'genero' => 'nullable|string|max:16',
            'dni' => 'nullable|string|max:20',
            'ruc' => 'nullable|string|max:20',
            'carnet_extranjeria' => 'nullable|string|max:20',
            'pasaporte' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:128',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        [$idEmpleadoLog, $nombreEmpleadoLog] = $this->getAuthUserInfo($request);

        return response()->json(ContratistasService::actualizar_contratista(
            id_contratista: $id,
            nombre: (string) $request->input('nombre'),
            apellido: (string) $request->input('apellido'),
            dni: $request->input('dni'),
            ruc: $request->input('ruc'),
            carnet_extranjeria: $request->input('carnet_extranjeria'),
            pasaporte: $request->input('pasaporte'),
            fecha_nacimiento: $request->input('fecha_nacimiento'),
            genero: $request->input('genero'),
            direccion: $request->input('direccion'),
            telefono: $request->input('telefono'),
            email: $request->input('email'),
            idEmpleadoLog: $idEmpleadoLog,
            nombreEmpleadoLog: $nombreEmpleadoLog,
        ));
    }

    /**
     * Borrado logico de un contratista (cambia estado a Inactivo).
     */
    public function eliminar_contratista(Request $request, int $id): JsonResponse
    {
        $result = ContratistasService::eliminar_contratista(id_contratista: $id);

        return response()->json($result);
    }
}
