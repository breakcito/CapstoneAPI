<?php

namespace App\Modules\KardexProductos\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\KardexProductos\Data\KardexData;

class KardexService
{
    /**
     * Listar movimientos de kardex por almacén.
     */
    public static function get_resumen_kardex(int $id_almacen, int $mes, int $yearcito)
    {
        $movimientos = KardexData::get_resumen_kardex($id_almacen, $mes, $yearcito);
        return ApiResponse::success($movimientos);
    }
}
