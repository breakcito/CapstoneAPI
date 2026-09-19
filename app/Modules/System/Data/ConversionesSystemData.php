<?php
namespace App\Modules\System\Data;

use App\Models\ConversionUnidadMedida;
use Illuminate\Support\Facades\DB;

class ConversionesSystemData
{
    public static function listar(): array
    {
        $sql = '
        SELECT
            c.id,
            c.id_unidad_medida_a,
            ua.nombre AS nombre_a,
            ua.abreviatura AS abreviatura_a,
            c.id_unidad_medida_b,
            ub.nombre AS nombre_b,
            ub.abreviatura AS abreviatura_b,
            c.factor_conversion
        FROM conversion_unidad_medida c
        INNER JOIN unidad_medida ua ON ua.id = c.id_unidad_medida_a
        INNER JOIN unidad_medida ub ON ub.id = c.id_unidad_medida_b
        ORDER BY ua.nombre ASC, ub.nombre ASC
        ';
        return DB::select($sql);
    }

    public static function obtener(int $id): ?ConversionUnidadMedida
    {
        return ConversionUnidadMedida::find($id);
    }

    public static function crear(int $id_a, int $id_b, float $factor): int
    {
        return ConversionUnidadMedida::insertGetId([
            'id_unidad_medida_a' => $id_a,
            'id_unidad_medida_b' => $id_b,
            'factor_conversion'  => $factor,
        ]);
    }

    public static function editar(int $id, int $id_a, int $id_b, float $factor): bool
    {
        return ConversionUnidadMedida::where('id', $id)->update([
            'id_unidad_medida_a' => $id_a,
            'id_unidad_medida_b' => $id_b,
            'factor_conversion'  => $factor,
        ]) > 0;
    }

    public static function eliminar(int $id): bool
    {
        return ConversionUnidadMedida::where('id', $id)->delete() > 0;
    }

    public static function ya_existe(int $id_a, int $id_b, ?int $excluir_id = null): bool
    {
        $q = ConversionUnidadMedida::query();
        $q->where(function ($w) use ($id_a, $id_b) {
            $w->orWhere(function ($w2) use ($id_a, $id_b) {
                $w2->where('id_unidad_medida_a', $id_a)->where('id_unidad_medida_b', $id_b);
            })->orWhere(function ($w2) use ($id_a, $id_b) {
                $w2->where('id_unidad_medida_a', $id_b)->where('id_unidad_medida_b', $id_a);
            });
        });
        if ($excluir_id !== null) $q->where('id', '!=', $excluir_id);
        return $q->exists();
    }
}
