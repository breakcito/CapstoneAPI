<?php
namespace App\Modules\System\Service;

use App\Shared\Responses\ApiResponse;
use App\Shared\Helpers\ConversionHelper;
use App\Modules\System\Data\ConversionesSystemData;

class ConversionesSystemService
{
    public static function listar()
    {
        return ApiResponse::success(ConversionesSystemData::listar());
    }

    public static function crear(int $id_a, int $id_b, float $factor)
    {
        if ($id_a === $id_b) {
            return ApiResponse::error('Las unidades deben ser distintas.');
        }
        if ($factor <= 0) {
            return ApiResponse::error('El factor debe ser mayor a 0.');
        }
        if (ConversionesSystemData::ya_existe($id_a, $id_b)) {
            return ApiResponse::error('Ya existe una conversión entre estas unidades (o su inversa).');
        }

        // Crea la conversion principal A -> B
        $id = ConversionesSystemData::crear($id_a, $id_b, $factor);
        // Auto-crea la conversion inversa B -> A con factor 1/factor (solo si no existe)
        ConversionesSystemData::crear($id_b, $id_a, 1.0 / $factor);

        // if (!ConversionesSystemData::ya_existe($id_b, $id_a)) {
        // }
        return ApiResponse::success(ConversionesSystemData::obtener($id), 'Conversión creada (y su inversa).');
    }

    public static function editar(int $id, int $id_a, int $id_b, float $factor)
    {
        if (!ConversionesSystemData::obtener($id)) {
            return ApiResponse::error('Conversión no encontrada.');
        }
        if ($id_a === $id_b) {
            return ApiResponse::error('Las unidades deben ser distintas.');
        }
        if ($factor <= 0) {
            return ApiResponse::error('El factor debe ser mayor a 0.');
        }
        if (ConversionesSystemData::ya_existe($id_a, $id_b, $id)) {
            return ApiResponse::error('Ya existe otra conversión entre estas unidades.');
        }
        ConversionesSystemData::editar($id, $id_a, $id_b, $factor);

        return ApiResponse::success(ConversionesSystemData::obtener($id), 'Conversión actualizada.');
    }

    public static function eliminar(int $id)
    {
        if (!ConversionesSystemData::obtener($id)) {
            return ApiResponse::error('Conversión no encontrada.');
        }
        ConversionesSystemData::eliminar($id);
        return ApiResponse::success(null, 'Conversión eliminada.');
    }
}
