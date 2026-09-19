<?php

namespace App\Data;

use App\Models\LoteProducto;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class LotesProductosData
{
    /**
     * Obtener los lotes disponibles de un almacen, util para cualquier 
     * tipo de entregas o ingresos. Solo se traen lotes activos, con stock y
     * no vencidos.
     */
    public static function get_lotes_disponibles(
        int $id_almacen,
        array $ids_productos
    ) {
        // 1. Creamos los placeholders (?,?,?)
        $placeholders = implode(',', array_fill(0, count($ids_productos), '?'));

        $sql = "
        SELECT
            lp.id AS id_lote,
            lp.correlativo,
            
            lp.id_almacen,
            
            lp.id_producto,
            
            -- unidad base del producto
            unib.id as id_unidad_medida_base,
            unib.nombre AS unidad_medida_base,
            unib.abreviatura AS unidad_medida_base_abv,
            
            -- unidad de medida del lote
            uni.id as id_unidad_medida_lote,
            uni.nombre AS unidad_medida_lote,
            uni.abreviatura AS unidad_medida_lote_abv,
            
            -- stocks
            lp.stock_actual_base, -- en base a la unidad base del producto
            lp.contenido_por_presentacion, -- cuantas unidades base hay en una unidad del lote
            lp.stock_actual,  -- en base a la unidad de medida del lote
            
            -- fechas
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            
            -- info de la compra del lote
            lp.comprobante_compra,
            lp.costo_por_unidad, -- costo por unidad de medida del lote
            lp.costo_por_unidad_base, -- costo por unidad de medida base del producto
            
            DATEDIFF(lp.fecha_vencimiento, NOW()) AS dias_para_vencer,
            CASE 
                WHEN pr.es_perecible != 1 THEN 'N/A' 
                WHEN lp.fecha_vencimiento IS NULL THEN 'Sin fecha' 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) < 0 THEN 'Vencido' 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= pr.dias_espera_vencimiento THEN 'Por vencer' 
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
            -- no traer vencidos
            (lp.fecha_vencimiento IS NULL OR DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) >= 0)
        ORDER BY
            CASE 
                WHEN lp.fecha_vencimiento IS NULL THEN 3 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= pr.dias_espera_vencimiento THEN 1 
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
     */
    public static function get_lote_dinamico_by_id(int|array $id_lote, array $columnas): ?array
    {
        $esArray = is_array($id_lote);
        $ids = $esArray ? $id_lote : [$id_lote];
        // Forzamos la inclusión del ID con su alias
        if (!in_array('id as id_lote', $columnas)) {
            $columnas[] = 'id as id_lote';
        }
        $query = LoteProducto::whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }
        return $query->first()?->toArray();
    }

    /**
     * Actualiza el stock de un lote en caso de decidir ajustar stock luego de una 
     * reposicion por parte de logistica al almacen prestamista
     */
    public static function update_stock(int $id_lote, float $nuevo_stock, float $nuevo_stock_base)
    {
        return LoteProducto::where('id', $id_lote)->update([
            'stock_actual' => $nuevo_stock,
            'stock_actual_base' => $nuevo_stock_base,
        ]);
    }

    public static function get_nuevo_correlativo()
    {
        return CorrelativoHelper::generar(
            tabla: 'lote_producto',
            prefijo: 'LT',
            filtros: [],
            columnaFecha: 'fecha_hora_ingreso'
        );
    }

    public static function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        int|null $id_origen,
        //
        string|null $tabla_origen,
        //
        string $correlativo,
        int $numero_correlativo,
        //
        float $contenido_por_presentacion,
        float $stock_inicial,
        //
        float $costo_promedio_base,
        //
        string $fecha_hora_ingreso,
        ?string $descripcion = null,
        ?string $fecha_vencimiento = null,
        // Nuevos
        ?string $serie_factura_compra = null,
        ?string $numero_factura_compra = null,
        ?float $costo_por_unidad = null,
        ?int $id_orden_compra_recepcion_detalle = null,
        ?int $id_orden_compra_detalle = null,
        ?string $correlativo_auditoria = null,
        ?int $numero_correlativo_auditoria = null
    ) {
        $stock_actual_base = $stock_inicial * $contenido_por_presentacion;
        return LoteProducto::insertGetId([
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen' => $id_almacen,
            'id_origen' => $id_origen,
            'tabla_origen' => $tabla_origen,
            'descripcion' => $descripcion,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'correlativo_auditoria' => $correlativo_auditoria,
            'numero_correlativo_auditoria' => $numero_correlativo_auditoria,
            'stock_actual' => $stock_inicial,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'stock_actual_base' => $stock_actual_base,
            'costo_promedio_base' => $costo_promedio_base,
            'fecha_hora_ingreso' => $fecha_hora_ingreso,
            'fecha_vencimiento' => $fecha_vencimiento,
            'created_at' => now(),
            'estado' => 'Activo',
            // Nuevos
            'serie_factura_compra' => $serie_factura_compra,
            'numero_factura_compra' => $numero_factura_compra,
            'costo_por_unidad' => $costo_por_unidad,
            'id_orden_compra_recepcion_detalle' => $id_orden_compra_recepcion_detalle,
            'id_orden_compra_detalle' => $id_orden_compra_detalle,
        ]);
    }

    /**
     * Obtiene informacion de lotes para la impresion de tickets
     */
    public static function get_info_to_ticket(
        ?int $id_lote = null,
        array $ids_lotes = []
    ) {
        if ($id_lote) {
            $ids_lotes = [$id_lote];
        }

        if (empty($ids_lotes)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids_lotes), '?'));
        $sql = "
        SELECT
            lot.id,
            pr.nombre AS producto,
            lot.correlativo AS lote,
            alm.nombre AS almacen,
            DATE(lot.fecha_hora_ingreso) AS fecha_ingreso
        FROM
            lote_producto lot
        INNER JOIN producto pr ON
            pr.id = lot.id_producto
        INNER JOIN almacen alm ON
            alm.id = lot.id_almacen
        WHERE lot.id IN ($placeholders)
        ";

        return DB::select($sql, $ids_lotes);
    }

    /**
     * Obtiene el costo promedio del producto del lote
     */
    public static function get_costo_promedio_producto(int $id_lote): float
    {
        $sql = '
        SELECT
            pr.costo_promedio_base
        FROM
            lote_producto lot
        INNER JOIN producto pr ON
            pr.id = lot.id_producto
        WHERE lot.id = :id_lote
        ';

        $resultado = DB::selectOne($sql, [
            'id_lote' => $id_lote
        ]);

        return (float) ($resultado?->costo_promedio_base ?? 0.0);
    }
}
