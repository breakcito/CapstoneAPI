<?php

namespace App\Data;

use App\Models\LoteProducto;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class LotesProductosData
{
    /**
     * Obtener los lotes disponibles de un almacen.
     * Solo se traen lotes activos, con stock y no vencidos.
     * @param array<int> $ids_productos
     * @return array<mixed>
     */
    public static function get_lotes_disponibles(int $id_almacen, array $ids_productos)
    {
        if (empty($ids_productos)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids_productos), '?'));

        $sql = "
        SELECT
            lp.id AS id_lote,
            lp.correlativo,
            lp.id_almacen,
            lp.id_producto,
            
            -- unidad base del producto
            unib.id AS id_unidad_medida_base,
            unib.nombre AS unidad_medida_base,
            unib.abreviatura AS unidad_medida_base_abv,
            
            -- unidad de medida del lote
            uni.id AS id_unidad_medida_lote,
            uni.nombre AS unidad_medida_lote,
            uni.abreviatura AS unidad_medida_lote_abv,
            
            -- stocks
            lp.stock_actual_base,
            lp.contenido_por_presentacion,
            lp.stock_actual,
            
            -- fechas
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            
            -- info de compra
            lp.comprobante_compra,
            lp.costo_por_unidad,
            lp.costo_por_unidad_base,
            
            DATEDIFF(lp.fecha_vencimiento, NOW()) AS dias_para_vencer,
            CASE 
                WHEN pr.es_perecible != 1 THEN 'N/A' 
                WHEN lp.fecha_vencimiento IS NULL THEN 'Sin fecha' 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) < 0 THEN 'Vencido' 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= IFNULL(pr.dias_espera_vencimiento, 0) THEN 'Por vencer' 
                ELSE 'Vigente'
            END AS estado_vencimiento
        FROM
            lote_producto lp
        INNER JOIN unidad_medida uni ON uni.id = lp.id_unidad_medida
        INNER JOIN producto pr ON pr.id = lp.id_producto
        INNER JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
        WHERE
            lp.id_producto IN ($placeholders) AND 
            lp.id_almacen = ? AND 
            lp.stock_actual_base > 0 AND 
            lp.estado = 'Activo' AND
            (lp.fecha_vencimiento IS NULL OR DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) >= 0)
        ORDER BY
            CASE 
                WHEN lp.fecha_vencimiento IS NULL THEN 3 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= IFNULL(pr.dias_espera_vencimiento, 0) THEN 1 
                ELSE 2 
            END ASC,
            lp.fecha_vencimiento ASC,
            lp.fecha_hora_ingreso ASC,
            lp.created_at ASC
        ";

        $params = array_merge($ids_productos, [$id_almacen]);

        return DB::select($sql, $params);
    }

    /**
     * Obtiene información dinámica de uno o varios lotes.
     * @param int|array<int> $id_lote
     * @param array<string> $columnas
     * @return array<mixed>|null
     */
    public static function get_lote_dinamico_by_id(int|array $id_lote, array $columnas): ?array
    {
        $esArray = is_array($id_lote);
        $ids = $esArray ? $id_lote : [$id_lote];

        if (!in_array('id as id_lote', $columnas, true) && !in_array('id', $columnas, true)) {
            $columnas[] = 'id as id_lote';
        }

        $query = LoteProducto::whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }

        return $query->first()?->toArray();
    }

    /**
     * Actualiza el stock de un lote
     */
    public static function update_stock(int $id_lote, float $nuevo_stock, float $nuevo_stock_base): int
    {
        return LoteProducto::where('id', $id_lote)->update([
            'stock_actual' => $nuevo_stock,
            'stock_actual_base' => $nuevo_stock_base,
        ]);
    }

    /**
     * Generar correlativo para nuevo lote
     */
    public static function get_nuevo_correlativo(): array
    {
        return CorrelativoHelper::generar(
            tabla: 'lote_producto',
            prefijo: 'LT',
            filtros: [],
            columnaFecha: 'created_at'
        );
    }

    /**
     * Crear lote en base de datos
     */
    public static function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        string $correlativo,
        int $numero_correlativo,
        float $contenido_por_presentacion,
        float $stock_inicial,
        ?string $fecha_hora_ingreso = null,
        ?string $fecha_vencimiento = null,
        ?string $comprobante_compra = null,
        ?float $costo_por_unidad = 0.0
    ): int {
        $stock_actual_base = $stock_inicial * $contenido_por_presentacion;
        $costo_por_unidad_base = ($contenido_por_presentacion > 0 && $costo_por_unidad !== null)
            ? round($costo_por_unidad / $contenido_por_presentacion, 4)
            : ($costo_por_unidad ?? 0.0);

        return LoteProducto::insertGetId([
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen' => $id_almacen,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'comprobante_compra' => $comprobante_compra,
            'stock_actual' => $stock_inicial,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'stock_actual_base' => $stock_actual_base,
            'costo_por_unidad' => $costo_por_unidad ?? 0.0,
            'costo_por_unidad_base' => $costo_por_unidad_base,
            'fecha_hora_ingreso' => $fecha_hora_ingreso ?? now()->toDateTimeString(),
            'fecha_vencimiento' => $fecha_vencimiento,
            'created_at' => now(),
            'estado' => 'Activo',
        ]);
    }

    /**
     * Obtiene el costo promedio del producto del lote
     */
    public static function get_costo_promedio_producto(int $id_lote): float
    {
        $sql = '
        SELECT
            lp.costo_por_unidad_base
        FROM
            lote_producto lp
        WHERE lp.id = :id_lote
        ';

        $resultado = DB::selectOne($sql, ['id_lote' => $id_lote]);

        return (float) ($resultado?->costo_por_unidad_base ?? 0.0);
    }
}
