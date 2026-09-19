<?php
namespace App\Modules\System\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\System\Service\UnidadesMedidaSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class UnidadesMedidaSystemController extends Controller
{
    public function listar(): JsonResponse
    {
        return response()->json(UnidadesMedidaSystemService::listar());
    }

    public function crear(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:64',
            'abreviatura' => 'required|string|min:1|max:8',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        return response()->json(UnidadesMedidaSystemService::crear(
            $request->input('nombre'),
            $request->input('abreviatura'),
        ));
    }

    public function editar(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:64',
            'abreviatura' => 'required|string|min:1|max:8',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        return response()->json(UnidadesMedidaSystemService::editar(
            $id,
            $request->input('nombre'),
            $request->input('abreviatura'),
        ));
    }

    public function eliminar(int $id): JsonResponse
    {
        return response()->json(UnidadesMedidaSystemService::eliminar($id));
    }
}
