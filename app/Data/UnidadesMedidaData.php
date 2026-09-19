<?php

namespace App\Data;

use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;

class UnidadesMedidaData
{
    /**
     * Metodo generico para obtener unidades de medida.
     * Si $incluir_conversiones es true, cada unidad trae el array `conversiones`
     * con [{ id_unidad_destino, factor_conversion }].
     */
    public static function get_unidades(
        ?int $id_unidad_medida = null,
        bool $incluir_conversiones = false,
    ) {
        $sql = '
        SELECT
            id AS id_unidad_medida,
            nombre,
            abreviatura,
            es_universal
        FROM unidad_medida
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_unidad_medida) {
            $sql .= " AND id = :id_unidad_medida";
            $params['id_unidad_medida'] = $id_unidad_medida;
            $row = DB::selectOne($sql, $params);

            if ($row && $incluir_conversiones) {
                $conversiones = self::get_conversiones_por_unidades([(int) $row->id_unidad_medida]);
                $row->conversiones = $conversiones[$row->id_unidad_medida] ?? [];
            }

            return $row;
        }

        $sql .= " ORDER BY nombre ASC";
        $rows = DB::select($sql, $params);

        if ($incluir_conversiones && !empty($rows)) {
            // 1. Recopilar todos los IDs de unidades
            $ids = array_map(fn($r) => (int) $r->id_unidad_medida, $rows);

            // 2. Traer TODAS las conversiones relacionadas en 1 sola consulta
            $conversionesAgrupadas = self::get_conversiones_por_unidades($ids);

            // 3. Asignar las conversiones a cada unidad desde memoria
            foreach ($rows as $r) {
                $r->conversiones = $conversionesAgrupadas[$r->id_unidad_medida] ?? [];
            }
        }

        return $rows;
    }

    /**
     * Obtiene en UNA SOLA CONSULTA las conversiones de múltiples unidades
     * y las devuelve agrupadas por id_unidad_origen.
     * 
     * @param array<int> $ids
     * @return array<int, array>
     */
    private static function get_conversiones_por_unidades(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Se pasan exactamente los IDs que corresponden a los placeholders del WHERE IN
        $bindings = $ids;

        $sql = "
        SELECT
            id_unidad_medida_a AS id_unidad_origen,
            id_unidad_medida_b AS id_unidad_destino,
            factor_conversion
        FROM conversion_unidad_medida
        WHERE id_unidad_medida_a IN ($placeholders)
        ";

        $rows = DB::select($sql, $bindings);

        // Agrupar los resultados por el ID de origen
        $conversionesAgrupadas = [];
        foreach ($rows as $r) {
            $id_unidad_origen = (int) $r->id_unidad_origen;
            $conversionesAgrupadas[$id_unidad_origen][] = [
                'id_unidad_destino' => (int) $r->id_unidad_destino,
                'factor_conversion' => $r->factor_conversion,
            ];
        }

        return $conversionesAgrupadas;
    }

    /**
     * Insertar una nueva unidad de medida en el catálogo
     */
    public static function crear_unidad_medida(
        string $nombre,
        string $abreviatura
    ): int {
        return UnidadMedida::insertGetId([
            'nombre' => $nombre,
            'abreviatura' => $abreviatura,
        ]);
    }

    /**
     * Verificar si ya existe una unidad de medida con ese nombre o abreviatura.
     */
    public static function ya_existe(array $criterios): bool
    {
        $query = UnidadMedida::query();

        $nombre = $criterios['nombre'] ?? null;
        $abreviatura = $criterios['abreviatura'] ?? null;
        $excluir_id = $criterios['excluir_id'] ?? null;

        $query->where(function ($q) use ($nombre, $abreviatura) {
            if ($nombre !== null && $nombre !== '') {
                $q->orWhereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)]);
            }
            if ($abreviatura !== null && $abreviatura !== '') {
                $q->orWhereRaw('LOWER(abreviatura) = ?', [mb_strtolower($abreviatura)]);
            }
        });

        if ($excluir_id !== null) {
            $query->where('id', '!=', $excluir_id);
        }

        return $query->exists();
    }
}