<?php
namespace App\Modules\System\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\System\Service\ConversionesSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ConversionesSystemController extends Controller
{
    public function listar(): JsonResponse
    {
        return response()->json(ConversionesSystemService::listar());
    }

    public function crear(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'id_unidad_medida_a' => 'required|integer|min:1',
            'id_unidad_medida_b' => 'required|integer|min:1|different:id_unidad_medida_a',
            'factor_conversion'  => 'required|numeric|min:0.00000001',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        return response()->json(ConversionesSystemService::crear(
            (int) $request->input('id_unidad_medida_a'),
            (int) $request->input('id_unidad_medida_b'),
            (float) $request->input('factor_conversion'),
        ));
    }

    public function editar(Request $request, int $id): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'id_unidad_medida_a' => 'required|integer|min:1',
            'id_unidad_medida_b' => 'required|integer|min:1|different:id_unidad_medida_a',
            'factor_conversion'  => 'required|numeric|min:0.00000001',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        return response()->json(ConversionesSystemService::editar(
            $id,
            (int) $request->input('id_unidad_medida_a'),
            (int) $request->input('id_unidad_medida_b'),
            (float) $request->input('factor_conversion'),
        ));
    }

    public function eliminar(int $id): JsonResponse
    {
        return response()->json(ConversionesSystemService::eliminar($id));
    }
}
