<?php

namespace App\Modules\Contratistas\Controller;

use App\Modules\Contratistas\Service\ContratistasService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContratistasController
{
    public function get_contratistas(Request $request): JsonResponse
    {
        $result = ContratistasService::get_contratistas();

        return response()->json($result);
    }

    public function crear_contratista(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'apellido' => 'required|string|max:128',
            'dni' => 'nullable|string|max:8',
            'foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ], [
            'nombre.required' => 'El nombre es requerido',
            'apellido.required' => 'El apellido es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ContratistasService::crear_contratista(
            nombre: (string) $request->input('nombre'),
            apellido: (string) $request->input('apellido'),
            dni: $request->input('dni'),
            foto: $request->file('foto')
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

    public function actualizar_contratista(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'apellido' => 'required|string|max:128',
            'dni' => 'nullable|string|max:8',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ContratistasService::actualizar_contratista(
            id_contratista: $id,
            nombre: (string) $request->input('nombre'),
            apellido: (string) $request->input('apellido'),
            dni: $request->input('dni')
        );

        return response()->json($result);
    }

    public function eliminar_contratista(Request $request, int $id): JsonResponse
    {
        $result = ContratistasService::eliminar_contratista(id_contratista: $id);

        return response()->json($result);
    }
}
