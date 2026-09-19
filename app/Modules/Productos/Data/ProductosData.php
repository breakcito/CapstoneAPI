<?php

namespace App\Modules\Productos\Data;

use App\Models\Producto;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ProductosData
{
    /**
     * Listar todos los productos del catálogo con su unidad de medida
     */
    public static function get_productos(?int $id_producto = null)
    {
        $sql = '
            SELECT
                p.id AS id_producto,
                p.nombre,
                p.tipo_producto,
                --
                p.id_unidad_medida_base,
                um.nombre as unidad_medida_base,
                um.abreviatura as unidad_medida_base_abreviatura,
                -- 
                p.es_perecible,
                p.stock_minimo_base,
                -- 
                p.tiempo_espera_vencimiento,
                p.periodo_espera_vencimiento,
                p.dias_espera_vencimiento,
                -- 
                p.estado
            FROM
                producto p
            INNER JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
            WHERE
                1 = 1
        ';

        $params = [];
        if ($id_producto !== null) {
            $sql .= ' AND p.id = :id_producto';
            $params['id_producto'] = $id_producto;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' AND p.estado != :estado_inactivo ORDER BY p.nombre ASC';
        $params['estado_inactivo'] = EstadoBase::Inactivo->value;

        return DB::select($sql, $params);
    }

    /**
     * Actualizar un producto existente
     */
    public static function actualizar_producto(
        int $id_producto,
        int $id_unidad_medida_base,
        string $nombre,
        ?string $tipo_producto = null,
        bool $es_perecible = false,
        float $stock_minimo_base = 0.0,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null,
        ?int $dias_espera_vencimiento = null
    ): int {
        $updatePayload = [
            'id_unidad_medida_base' => $id_unidad_medida_base,
            'nombre' => $nombre,
            'tipo_producto' => $tipo_producto,
            'es_perecible' => $es_perecible ? 1 : 0,
            'stock_minimo_base' => $stock_minimo_base,
            'tiempo_espera_vencimiento' => $tiempo_espera_vencimiento,
            'periodo_espera_vencimiento' => $periodo_espera_vencimiento,
            'dias_espera_vencimiento' => $dias_espera_vencimiento,
        ];

        return DB::table('producto')
            ->where('id', $id_producto)
            ->update($updatePayload);
    }

    /**
     * Desactivar (soft delete) un producto cambiando su estado a Inactivo.
     */
    public static function eliminar_producto(int $id_producto): int
    {
        return DB::table('producto')
            ->where('id', $id_producto)
            ->update([
                'estado' => EstadoBase::Inactivo->value,
            ]);
    }

    /**
     * Verificar si ya existe un producto activo con el mismo nombre
     */
    public static function existe_nombre(string $nombre, ?int $excluir_id = null): bool
    {
        return DB::table('producto')
            ->where('nombre', $nombre)
            ->where('estado', '!=', EstadoBase::Inactivo->value)
            ->when($excluir_id !== null, fn($q) => $q->where('id', '!=', $excluir_id))
            ->exists();
    }
}
