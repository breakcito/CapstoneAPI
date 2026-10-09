<?php

namespace App\Modules\Indicadores\Data;

use Illuminate\Support\Facades\DB;

/**
 * Capa de acceso a datos para el modulo BI.
 *
 * Cada metodo acepta opcionalmente un idAlmacen para filtrar por almacen.
 * Si llega null, devuelve data global de todos los almacenes.
 *
 * Las queries se construyen con SQL dinamico porque las VISTAS en MySQL
 * no aceptan parametros. La capa Data agrega el WHERE cuando hay filtro.
 */
class IndicadoresData
{
    /**
     * Helper: agrega el WHERE de filtro por almacen al final de una query.
     * Mantiene las queries base limpias en el archivo.
     */
    private static function aplicarFiltroAlmacen(string $sql, ?int $idAlmacen): array
    {
        if ($idAlmacen === null) {
            return [$sql, []];
        }
        return [$sql . ' WHERE id_almacen = ? ', [$idAlmacen]];
    }

    /**
     * KPIs principales (1 fila resumen).
     *
     * Reescrito como queries inline para soportar el filtro por almacen.
     * La vista v_bi_kpis_principales es global por diseño, pero las
     * consultas siguientes filtran correctamente por id_almacen.
     */
    public static function get_kpis_principales(?int $idAlmacen = null): array
    {
        // Construir WHERE extra para aplicar filtro por almacen en cada
        // subconsulta. Cada métrica filtra por el campo que le corresponde.
        $whereReq = $idAlmacen !== null
            ? ' AND id_almacen_destino = ? '
            : '';
        $whereKardex = $idAlmacen !== null
            ? ' AND id_almacen = ? '
            : '';
        $whereLote = $idAlmacen !== null
            ? ' AND lp.id_almacen = ? '
            : '';

        // Parametros en el mismo orden que aparecen en las subconsultas
        $paramsReq = $idAlmacen !== null ? [$idAlmacen] : [];
        $paramsKardex = $idAlmacen !== null ? [$idAlmacen] : [];
        $paramsLote = $idAlmacen !== null ? [$idAlmacen] : [];
        $paramsEdi = $idAlmacen !== null ? [$idAlmacen] : [];
        $paramsCtl = $idAlmacen !== null
            ? [$idAlmacen, $idAlmacen, $idAlmacen]
            : [];

        $sql = "
            SELECT
                -- Pedidos en gestion (ultimos 30 dias)
                (SELECT COUNT(*)
                   FROM requerimiento_almacen
                   WHERE estado NOT IN ('Anulado','Cerrado')
                     AND COALESCE(fecha_solicitud, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     $whereReq
                ) AS req_ultimos_30_dias,

                -- Pedidos en los 30 dias ANTERIORES (entre -60 y -30 dias)
                -- Para calcular el delta correctamente: comparar 30d vs 30d anteriores
                -- (NO contra mes anterior, que es inconsistente con la ventana de 30d).
                (SELECT COUNT(*)
                   FROM requerimiento_almacen
                   WHERE estado NOT IN ('Anulado','Cerrado')
                     AND COALESCE(fecha_solicitud, created_at) >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                     AND COALESCE(fecha_solicitud, created_at) < DATE_SUB(NOW(), INTERVAL 30 DAY)
                     $whereReq
                ) AS req_30_dias_anteriores,

                -- Pedidos cerrados (Completado + Cerrado) totales
                (SELECT COUNT(*)
                   FROM requerimiento_almacen
                   WHERE estado IN ('Completado','Cerrado')
                     $whereReq
                ) AS req_cerrados_total,

                -- TDA: Tiempo promedio de despacho en minutos
                (SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, COALESCE(ra.fecha_solicitud, ra.created_at), primera.fecha_hora_entrega)), 1)
                   FROM requerimiento_almacen ra
                   INNER JOIN (
                       SELECT rae.id_requerimiento_almacen, MIN(rae.fecha_hora_entrega) AS fecha_hora_entrega
                       FROM requerimiento_almacen_entrega rae
                       WHERE rae.estado = 'Entregado'
                       GROUP BY rae.id_requerimiento_almacen
                   ) primera ON primera.id_requerimiento_almacen = ra.id
                   WHERE ra.estado NOT IN ('Anulado','Cerrado')
                     $whereReq
                ) AS tda_minutos_promedio,

                -- EDI: % de exactitud del registro
                (SELECT ROUND(100.0 * AVG(
                    CASE
                        WHEN rad.cantidad_solicitada_base IS NULL
                             OR rad.cantidad_solicitada_base = 0 THEN NULL
                        ELSE GREATEST(0, 1 - ABS(
                            COALESCE(despachado.total_despachado_base, 0) - rad.cantidad_solicitada_base
                        ) / rad.cantidad_solicitada_base)
                    END
                 ), 1)
                 FROM requerimiento_almacen_detalle rad
                 INNER JOIN requerimiento_almacen ra ON ra.id = rad.id_requerimiento_almacen
                 LEFT JOIN (
                     SELECT raed.id_requerimiento_almacen_detalle, SUM(raed.cantidad_base) AS total_despachado_base
                     FROM requerimiento_almacen_entrega_detalle raed
                     INNER JOIN requerimiento_almacen_entrega rae ON rae.id = raed.id_requerimiento_almacen_entrega
                     WHERE rae.estado = 'Entregado'
                     GROUP BY raed.id_requerimiento_almacen_detalle
                 ) despachado ON despachado.id_requerimiento_almacen_detalle = rad.id
                 WHERE ra.estado IN ('Completado','Cerrado')
                   $whereReq
                ) AS edi_porcentaje,

                -- CTL: % de trazabilidad por lotes
                (SELECT ROUND(100.0 * COUNT(DISTINCT k.id_lote_producto) /
                              NULLIF(
                                (SELECT COUNT(*) FROM lote_producto lp
                                 WHERE lp.estado = 'Activo'
                                   $whereLote
                                ), 0), 1)
                   FROM kardex_producto k
                   WHERE k.id_lote_producto IS NOT NULL
                     $whereKardex
                ) AS ctl_porcentaje,

                -- Stock valorizado total
                (SELECT ROUND(SUM(lp.stock_actual_base * IFNULL(lp.costo_por_unidad_base,0)), 2)
                   FROM lote_producto lp
                   WHERE lp.estado = 'Activo'
                     AND lp.stock_actual_base > 0
                     $whereLote
                ) AS stock_valorizado_total,

                -- Alertas de stock (CRITICO)
                (SELECT COUNT(*) FROM (
                   SELECT lp.id_producto
                   FROM lote_producto lp
                   INNER JOIN producto pr ON pr.id = lp.id_producto
                   WHERE lp.estado = 'Activo' AND pr.estado = 'Activo'
                     $whereLote
                   GROUP BY lp.id_producto, pr.stock_minimo_base
                   HAVING COALESCE(SUM(lp.stock_actual_base),0) <= IFNULL(pr.stock_minimo_base,0)
                ) AS criticos) AS alertas_stock_critico,

                -- Tasa de anulacion (ultimos 30 dias)
                (SELECT ROUND(100.0 * SUM(CASE WHEN estado = 'Anulado' THEN 1 ELSE 0 END) /
                              NULLIF(COUNT(*), 0), 1)
                   FROM requerimiento_almacen
                   WHERE COALESCE(fecha_solicitud, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     $whereReq
                ) AS tasa_anulacion_pct,

                -- Ultimo movimiento de kardex
                (SELECT MAX(created_at) FROM kardex_producto WHERE 1=1 $whereKardex) AS tr_ultimo_movimiento_kardex
        ";

        // Combinar parametros en el orden correcto de las subconsultas:
        // 1. req_ultimos_30_dias (whereReq) -> $paramsReq
        // 2. req_cerrados_total (whereReq) -> $paramsReq
        // 3. tda (whereReq) -> $paramsReq
        // 4. edi (whereReq) -> $paramsReq
        // 5. ctl_whereKardex + ctl_whereLote (2 params) + total count (1 param)
        // 6. stock_valorizado (whereLote)
        // 7. alertas (whereLote)
        // 8. tasa_anulacion (whereReq)
        // 9. tr_ultimo (whereKardex)
        $params = [];
        if ($idAlmacen !== null) {
            // req_ultimos_30_dias, req_30_dias_anteriores, req_cerrados_total,
            // tda, edi, tasa_anulacion (6 parametros whereReq)
            $params = array_merge(
                $params,
                $paramsReq, $paramsReq, $paramsReq, $paramsReq, $paramsReq, $paramsReq,
            );
            // ctl: 1 parametro del kardex + 1 parametro del lote count
            $params = array_merge($params, $paramsKardex, $paramsLote);
        // stock: 1 parametro del lote
            $params = array_merge($params, $paramsLote);
            // alertas: 1 parametro del lote
            $params = array_merge($params, $paramsLote);
            // tr: 1 parametro del kardex
            $params = array_merge($params, $paramsKardex);
        }

        $rows = DB::select($sql, $params);
        if (empty($rows)) {
            return [[
                'req_ultimos_30_dias' => 0,
                'req_cerrados_total' => 0,
                'tda_minutos_promedio' => null,
                'edi_porcentaje' => null,
                'ctl_porcentaje' => 0,
                'stock_valorizado_total' => 0,
                'alertas_stock_critico' => 0,
                'tasa_anulacion_pct' => 0,
                'tr_ultimo_movimiento_kardex' => null,
            ]];
        }
        return $rows;
    }

    /**
     * Stock valorizado por almacen.
     */
    public static function get_stock_valorizado(?int $idAlmacen = null): array
    {
        [$sql, $params] = self::aplicarFiltroAlmacen(
            'SELECT * FROM v_bi_stock_valorizado',
            $idAlmacen,
        );
        return DB::select($sql, $params);
    }

    /**
     * Top productos mas despachados. Parametrizable por dias (default 30).
     */
    public static function get_top_productos(int $dias = 30, ?int $idAlmacen = null): array
    {
        $where = $idAlmacen !== null ? ' AND lp.id_almacen = ? ' : '';
        $params = $idAlmacen !== null ? [$dias, $idAlmacen] : [$dias];
        $sql = "
            SELECT
                pr.id              AS id_producto,
                pr.nombre          AS producto,
                unib.abreviatura   AS unidad_base_abv,
                SUM(k.cantidad_movimiento_base) AS total_despachado_base,
                COUNT(*) AS num_movimientos,
                ROUND(SUM(IFNULL(k.costo, 0)), 2) AS costo_total
            FROM kardex_producto k
            INNER JOIN lote_producto lp ON lp.id = k.id_lote_producto
            INNER JOIN producto pr ON pr.id = lp.id_producto
            INNER JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
            WHERE k.tipo_movimiento = 'Salida'
              AND k.tipo_origen IN ('Entrega','Reingreso')
              AND k.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              $where
            GROUP BY pr.id, pr.nombre, unib.abreviatura
            ORDER BY total_despachado_base DESC
            LIMIT 10
        ";
        return DB::select($sql, $params);
    }

    /**
     * Rotacion mensual de los ultimos N meses (default 12).
     */
    public static function get_rotacion_mensual(int $meses = 12, ?int $idAlmacen = null): array
    {
        $where = $idAlmacen !== null ? ' AND k.id_almacen = ? ' : '';
        $params = $idAlmacen !== null ? [$meses, $idAlmacen] : [$meses];
        $sql = "
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') AS mes,
                SUM(CASE WHEN tipo_movimiento = 'Ingreso' THEN cantidad_movimiento_base ELSE 0 END) AS ingresos_base,
                SUM(CASE WHEN tipo_movimiento = 'Salida'  THEN cantidad_movimiento_base ELSE 0 END) AS salidas_base,
                ROUND(SUM(CASE WHEN tipo_movimiento = 'Ingreso' THEN IFNULL(costo,0) ELSE 0 END), 2) AS costo_ingresos,
                ROUND(SUM(CASE WHEN tipo_movimiento = 'Salida'  THEN IFNULL(costo,0) ELSE 0 END), 2) AS costo_salidas,
                ROUND(
                    SUM(CASE WHEN tipo_movimiento = 'Ingreso' THEN IFNULL(costo,0) ELSE 0 END) -
                    SUM(CASE WHEN tipo_movimiento = 'Salida'  THEN IFNULL(costo,0) ELSE 0 END),
                    2
                ) AS variacion_costo
            FROM kardex_producto k
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
              $where
            GROUP BY EXTRACT(YEAR_MONTH FROM created_at), DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY mes ASC
        ";
        return DB::select($sql, $params);
    }

    /**
     * Alertas de stock (SIN STOCK / CRITICO / BAJO) desglosadas por almacen.
     */
    public static function get_alertas_stock(?int $idAlmacen = null): array
    {
        [$sql, $params] = self::aplicarFiltroAlmacen(
            'SELECT * FROM v_bi_alertas_stock',
            $idAlmacen,
        );
        return DB::select($sql, $params);
    }

    /**
     * TAP por almacen.
     */
    public static function get_tap_por_almacen(?int $idAlmacen = null): array
    {
        if ($idAlmacen !== null) {
            return DB::select(
                'SELECT * FROM v_bi_tap_por_almacen WHERE id_almacen = ?',
                [$idAlmacen],
            );
        }
        return DB::select('SELECT * FROM v_bi_tap_por_almacen');
    }

    /**
     * Tendencias mensuales (ultimos 6 meses) para sparklines.
     */
    public static function get_tendencias_mensuales(?int $idAlmacen = null): array
    {
        $whereReq = $idAlmacen !== null ? ' AND ra.id_almacen_destino = ? ' : '';
        $whereKar = $idAlmacen !== null ? ' AND k.id_almacen = ? ' : '';
        $params = $idAlmacen !== null ? [$idAlmacen, $idAlmacen] : [];
        $sql = "
            SELECT
                meses.mes,
                COALESCE(req.total_req, 0) AS total_req,
                COALESCE(req.entregas_mes, 0) AS entregas_mes,
                COALESCE(req.en_despacho, 0) AS en_despacho,
                COALESCE(kar.ingresos_base, 0) AS ingresos_base,
                COALESCE(kar.salidas_base, 0) AS salidas_base,
                -- Diferencia del mes (ingresos - salidas del mes, sin acumular)
                COALESCE(kar.ingresos_base, 0) - COALESCE(kar.salidas_base, 0) AS stock_neto_mes,
                -- Acumulado historico (para referencia)
                SUM(COALESCE(kar.ingresos_base, 0) - COALESCE(kar.salidas_base, 0))
                    OVER (ORDER BY meses.mes) AS stock_neto_acum_base
            FROM (
                SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL n MONTH), '%Y-%m') AS mes
                  FROM (
                      SELECT 0 AS n UNION SELECT 1 UNION SELECT 2
                      UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
                  ) m
            ) meses
            LEFT JOIN (
                SELECT DATE_FORMAT(COALESCE(ra.fecha_solicitud, ra.created_at), '%Y-%m') AS mes,
                       COUNT(*) AS total_req,
                       SUM(CASE WHEN ra.estado IN ('Completado','Cerrado') THEN 1 ELSE 0 END) AS entregas_mes,
                       SUM(CASE WHEN ra.estado = 'En Despacho' THEN 1 ELSE 0 END) AS en_despacho
                  FROM requerimiento_almacen ra
                  WHERE COALESCE(ra.fecha_solicitud, ra.created_at) >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                  $whereReq
                GROUP BY DATE_FORMAT(COALESCE(ra.fecha_solicitud, ra.created_at), '%Y-%m')
            ) req ON req.mes = meses.mes
            LEFT JOIN (
                SELECT DATE_FORMAT(k.created_at, '%Y-%m') AS mes,
                       SUM(CASE WHEN k.tipo_movimiento = 'Ingreso' THEN k.cantidad_movimiento_base ELSE 0 END) AS ingresos_base,
                       SUM(CASE WHEN k.tipo_movimiento = 'Salida' THEN k.cantidad_movimiento_base ELSE 0 END) AS salidas_base
                  FROM kardex_producto k
                  WHERE k.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                  $whereKar
                GROUP BY DATE_FORMAT(k.created_at, '%Y-%m')
            ) kar ON kar.mes = meses.mes
            ORDER BY meses.mes ASC
        ";
        return DB::select($sql, $params);
    }
}