<?php
namespace App\Modules\System\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\System\Service\ArchivosSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArchivosSystemController extends Controller
{
    public function listar(Request $request): JsonResponse
    {
        $carpeta = (string) $request->query('carpeta', '');
        return response()->json(ArchivosSystemService::listar($carpeta));
    }

    public function descargar(Request $request)
    {
        $carpeta = (string) $request->query('carpeta', '');
        $archivo = (string) $request->query('nombre', '');

        $result = ArchivosSystemService::descargar($carpeta, $archivo);
        if (isset($result['error'])) {
            return response()->json(ApiResponse::error($result['error']), 404);
        }

        return new BinaryFileResponse($result['path']);
    }

    public function renombrar(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'carpeta' => 'required|string',
            'old' => 'required|string',
            'new' => 'required|string',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        return response()->json(ArchivosSystemService::renombrar(
            $request->input('carpeta'),
            $request->input('old'),
            $request->input('new'),
        ));
    }

    public function eliminar(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'carpeta' => 'required|string',
            'nombre'  => 'required|string',
        ]);
        if ($v->fails()) return response()->json(ApiResponse::error($v->errors()->first()));

        return response()->json(ArchivosSystemService::eliminar(
            $request->input('carpeta'),
            $request->input('nombre'),
        ));
    }
}
