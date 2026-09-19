<?php

namespace App\Modules\LotesProductos\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class LotesData
{

    /**
     * Listar lotes de un almacén.
     */
    public static function get_resumen_lotes(?int $id_almacen = null, ?int $id_lote = null)
    {
        $sql = '
        SELECT
            lp.id AS id_lote,
            lp.id_producto,
            lp.id_unidad_medida,
            lp.id_almacen,
            p.nombre as producto,
            um_base.abreviatura as unidad_medida_base_abv,
            um_lote.abreviatura AS unidad_medida_abv,
            lp.correlativo,
            lp.numero_correlativo,
            lp.stock_actual,
            lp.contenido_por_presentacion,
            lp.stock_actual_base,
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            lp.estado,
            lp.comprobante_compra,
            lp.costo_por_unidad,
            lp.costo_por_unidad_base,
            p.es_perecible,
            p.stock_minimo_base,
            p.dias_espera_vencimiento,
            /* Cálculo de días restantes */
            CASE
                WHEN lp.fecha_vencimiento IS NOT NULL THEN
                    DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE)
                ELSE NULL
            END AS dias_para_vencer,
            /* Determinación del estado de vencimiento */
            CASE
                WHEN p.es_perecible != 1 THEN "N/A"
                WHEN lp.fecha_vencimiento IS NULL THEN "Sin fecha"
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) < 0 THEN "Vencido"
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= p.dias_espera_vencimiento THEN "Por vencer"
                ELSE "Vigente"
            END AS estado_vencimiento
        FROM
            lote_producto lp
        INNER JOIN producto p ON
            p.id = lp.id_producto
        LEFT JOIN unidad_medida um_base ON
            um_base.id = p.id_unidad_medida_base
        LEFT JOIN unidad_medida um_lote ON
            um_lote.id = lp.id_unidad_medida
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_lote !== null) {
            $sql .= ' AND lp.id = :id_lote';
            $params['id_lote'] = $id_lote;

            return DB::selectOne($sql, $params);
        }

        if ($id_almacen !== null) {
            $sql .= ' AND lp.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        $sql .= ' ORDER BY lp.fecha_hora_ingreso DESC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener lote por ID (para retorno post-creación).
     */
    public static function get_lote_by_id(int $id_lote)
    {
        return self::get_resumen_lotes(id_lote: $id_lote);
    }

    /**
     * Actualizar un lote.
     */
    public static function actualizar_lote(
        int $id_lote,
        ?string $comprobante_compra,
        ?string $fecha_hora_ingreso,
        ?float $costo_por_unidad = null
    ): int {
        $updatePayload = [];
        if ($comprobante_compra !== null) {
            $updatePayload['comprobante_compra'] = $comprobante_compra;
        }
        if ($fecha_hora_ingreso !== null) {
            $updatePayload['fecha_hora_ingreso'] = $fecha_hora_ingreso;
        }
        if ($costo_por_unidad !== null) {
            $updatePayload['costo_por_unidad'] = $costo_por_unidad;
        }

        if (empty($updatePayload)) {
            return 0;
        }

        $affected = DB::table('lote_producto')
            ->where('id', $id_lote)
            ->update($updatePayload);

        return (int) $affected;
    }

    /**
     * Desactivar (soft delete) un lote cambiando su estado a Inactivo.
     */
    public static function eliminar_lote(int $id_lote): int
    {
        $affected = DB::table('lote_producto')
            ->where('id', $id_lote)
            ->update(['estado' => EstadoBase::Inactivo->value]);

        return (int) $affected;
    }
}
