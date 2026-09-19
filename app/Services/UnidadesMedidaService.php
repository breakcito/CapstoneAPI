<?php
namespace App\Services;

use App\Data\UnidadesMedidaData;
use App\Shared\Responses\ApiResponse;
class UnidadesMedidaService
{
    /**
     * Listar unidades de medida (incluye conversiones por defecto).
     */
    public static function get_unidades(
        ?int $id_unidad_medida = null,
        ?bool $incluir_conversiones = null,
    ) {
        $incluir = $incluir_conversiones ?? true;

        $unidades = UnidadesMedidaData::get_unidades(
            id_unidad_medida: $id_unidad_medida,
            incluir_conversiones: $incluir,
        );

        return ApiResponse::success($unidades);
    }

    /**
     * Registrar una nueva unidad de medida en el catálogo.
     * Normaliza el nombre (trim) y la abreviatura (trim + mayúsculas) antes de
     * validar unicidad e insertar, para evitar duplicados visuales.
     */
    public static function crear_unidad_medida(
        ?string $nombre = null,
        ?string $abreviatura = null
    ) {
        $nombre = $nombre !== null ? trim($nombre) : '';
        $abreviatura = $abreviatura !== null ? mb_strtoupper(trim($abreviatura)) : '';

        if ($nombre === '' || mb_strlen($nombre) < 2 || mb_strlen($nombre) > 64) {
            return ApiResponse::error('El nombre de la unidad de medida debe tener entre 2 y 64 caracteres.');
        }

        if ($abreviatura === '' || mb_strlen($abreviatura) < 1 || mb_strlen($abreviatura) > 8) {
            return ApiResponse::error('La abreviatura debe tener entre 1 y 8 caracteres.');
        }

        if (
            UnidadesMedidaData::ya_existe([
                'nombre' => $nombre,
                'abreviatura' => $abreviatura,
            ])
        ) {
            return ApiResponse::error('Ya existe una unidad de medida con ese nombre o abreviatura.');
        }

        $id_unidad_medida = UnidadesMedidaData::crear_unidad_medida(
            nombre: $nombre,
            abreviatura: $abreviatura,
        );

        $unidad = UnidadesMedidaData::get_unidades(id_unidad_medida: $id_unidad_medida);

        return ApiResponse::success($unidad, 'Unidad de medida registrada correctamente');
    }
}