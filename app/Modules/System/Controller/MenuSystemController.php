<?php
namespace App\Modules\System\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\System\Service\MenuSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class MenuSystemController extends Controller
{
    public function arbol(): JsonResponse
    {
        return response()->json(MenuSystemService::arbol());
    }

    public function crear_menu(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:64',
            'path' => 'nullable|string|max:128',
            'numero_orden' => 'required|integer|min:0',
            'es_desplegable' => 'required|boolean',
        ], [
            'path.string' => 'El path debe ser texto.',
            'path.max' => 'El path no puede tener más de 128 caracteres.',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        $esDesplegable = (bool) $request->input('es_desplegable');
        $path = $esDesplegable ? null : ($request->input('path') ?: null);

        return response()->json(MenuSystemService::crear_menu(
            $request->input('nombre'),
            $path,
            (int) $request->input('numero_orden'),
            $esDesplegable,
        ));
    }

    public function editar_menu(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:64',
            'path' => 'nullable|string|max:128',
            'numero_orden' => 'required|integer|min:0',
            'es_desplegable' => 'required|boolean',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        $esDesplegable = (bool) $request->input('es_desplegable');
        $path = $esDesplegable ? null : ($request->input('path') ?: null);

        return response()->json(MenuSystemService::editar_menu(
            $id,
            $request->input('nombre'),
            $path,
            (int) $request->input('numero_orden'),
            $esDesplegable,
        ));
    }

    public function eliminar_menu(int $id): JsonResponse
    {
        return response()->json(MenuSystemService::eliminar_menu($id));
    }

    public function crear_submenu(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'id_menu' => 'required|integer|min:1',
            'nombre' => 'required|string|min:2|max:64',
            'path' => 'nullable|string|max:128',
            'numero_orden' => 'required|integer|min:0',
            'es_desplegable' => 'required|boolean',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        $esDesplegable = (bool) $request->input('es_desplegable');
        $path = $esDesplegable ? null : ($request->input('path') ?: null);

        return response()->json(MenuSystemService::crear_submenu(
            (int) $request->input('id_menu'),
            $request->input('nombre'),
            $path,
            (int) $request->input('numero_orden'),
            $esDesplegable,
        ));
    }

    public function editar_submenu(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:64',
            'path' => 'nullable|string|max:128',
            'numero_orden' => 'required|integer|min:0',
            'es_desplegable' => 'required|boolean',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        $esDesplegable = (bool) $request->input('es_desplegable');
        $path = $esDesplegable ? null : ($request->input('path') ?: null);

        return response()->json(MenuSystemService::editar_submenu(
            $id,
            $request->input('nombre'),
            $path,
            (int) $request->input('numero_orden'),
            $esDesplegable,
        ));
    }

    public function eliminar_submenu(int $id): JsonResponse
    {
        return response()->json(MenuSystemService::eliminar_submenu($id));
    }

    public function crear_modulo(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'id_submenu' => 'required|integer|min:1',
            'nombre' => 'required|string|min:2|max:64',
            'path' => 'required|string|max:128',
            'numero_orden' => 'required|integer|min:0',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        return response()->json(MenuSystemService::crear_modulo(
            (int) $request->input('id_submenu'),
            $request->input('nombre'),
            $request->input('path'),
            (int) $request->input('numero_orden'),
        ));
    }

    public function editar_modulo(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:64',
            'path' => 'required|string|max:128',
            'numero_orden' => 'required|integer|min:0',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        return response()->json(MenuSystemService::editar_modulo(
            $id,
            $request->input('nombre'),
            $request->input('path'),
            (int) $request->input('numero_orden'),
        ));
    }

    public function eliminar_modulo(int $id): JsonResponse
    {
        return response()->json(MenuSystemService::eliminar_modulo($id));
    }
}