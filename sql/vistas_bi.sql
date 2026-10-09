-- =============================================================================
-- VISTAS DE BUSINESS INTELLIGENCE - Proyecto Capstone Cupper & Hannia
-- =============================================================================
-- Modulo: Dashboard de Indicadores (BI)
-- Stack destino: MySQL 8.0 (VPS Contabo)
-- Convencion:
--   - Todas las vistas son CREATE OR REPLACE (idempotente: correlas varias veces).
--   - Sin SELECT INTO. Sin cursores. Solo SELECT.
--   - Compatible con sql_mode=only_full_group_by de MySQL 8.
--
-- COMO EJECUTAR ESTE SCRIPT:
--   1. Abrir phpMyAdmin: http://158.220.108.211/phpmyadmin
--   2. Iniciar sesion con el usuario 'capstone' sobre la BD 'dbCapstone'.
--   3. Ir a la pestana "SQL".
--   4. Pegar TODO el contenido de este archivo.
--   5. Click en "Continuar" (o Ctrl+Enter).
--   6. Verificar: en la pestana "Bases de datos > dbCapstone > Vistas"
--      deben aparecer las 6 vistas nuevas (v_bi_*).
-- =============================================================================


-- -----------------------------------------------------------------------------
-- Vista 1: KPIs principales para el home (cabecera del dashboard)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_bi_kpis_principales AS
SELECT
    (SELECT COUNT(*)
       FROM requerimiento_almacen
       WHERE estado NOT IN ('Anulado','Cerrado')
         AND COALESCE(fecha_solicitud, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ) AS req_ultimos_30_dias,

    (SELECT COUNT(*)
       FROM requerimiento_almacen
       WHERE estado IN ('Completado','Cerrado')
    ) AS req_cerrados_total,

    -- Tiempo promedio de despacho en minutos
    -- (desde que se crea el requerimiento hasta la FECHA REAL de su primera entrega)
    -- Usamos fecha_hora_entrega (no created_at) para que refleje el momento
    -- real del despacho, no cuando se guardo la fila en la BD.
    (SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, COALESCE(ra.fecha_solicitud, ra.created_at), primera.fecha_hora_entrega)), 1)
       FROM requerimiento_almacen ra
       INNER JOIN (
           SELECT rae.id_requerimiento_almacen, MIN(rae.fecha_hora_entrega) AS fecha_hora_entrega
           FROM requerimiento_almacen_entrega rae
           WHERE rae.estado = 'Entregado'
           GROUP BY rae.id_requerimiento_almacen
       ) primera ON primera.id_requerimiento_almacen = ra.id
       WHERE ra.estado IN ('Completado','Cerrado','En Despacho','Atendido Parcial')
         AND COALESCE(ra.fecha_solicitud, ra.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ) AS tda_minutos_promedio,

    -- Indice de Registro de Exactitud (proxy)
    -- Para cada detalle de un requerimiento Completado/Cerrado, calculamos
    -- 1 - |despachado - solicitado| / solicitado, y promediamos en %.
    -- Si no hay req Completados, devuelve NULL.
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
         SELECT raed.id_requerimiento_almacen_detalle,
                SUM(raed.cantidad_base) AS total_despachado_base
         FROM requerimiento_almacen_entrega_detalle raed
         INNER JOIN requerimiento_almacen_entrega rae
             ON rae.id = raed.id_requerimiento_almacen_entrega
         WHERE rae.estado = 'Entregado'
         GROUP BY raed.id_requerimiento_almacen_detalle
     ) despachado ON despachado.id_requerimiento_almacen_detalle = rad.id
     WHERE ra.estado IN ('Completado','Cerrado')
    ) AS edi_porcentaje,

    -- Cobertura de Trazabilidad por Lotes
    -- Porcentaje de lotes activos que tienen al menos 1 movimiento en Kardex.
    (SELECT ROUND(100.0 * COUNT(DISTINCT k.id_lote_producto) /
                  NULLIF((SELECT COUNT(*) FROM lote_producto WHERE estado = 'Activo'), 0), 1)
       FROM kardex_producto k
       WHERE k.id_lote_producto IS NOT NULL
         AND k.id_lote_producto IN (SELECT id FROM lote_producto WHERE estado = 'Activo')
    ) AS ctl_porcentaje,

    -- Stock valorizado total (en unidades base, multiplicado por costo unitario base)
    (SELECT ROUND(SUM(lp.stock_actual_base * IFNULL(lp.costo_por_unidad_base,0)), 2)
       FROM lote_producto lp
       WHERE lp.estado = 'Activo' AND lp.stock_actual_base > 0
    ) AS stock_valorizado_total,

    -- Numero de alertas activas (productos con stock <= stock_minimo_base)
    (SELECT COUNT(*) FROM (
       SELECT lp.id_producto
       FROM lote_producto lp
       INNER JOIN producto pr ON pr.id = lp.id_producto
       WHERE lp.estado = 'Activo' AND pr.estado = 'Activo'
       GROUP BY lp.id_producto, pr.stock_minimo_base
       HAVING COALESCE(SUM(lp.stock_actual_base),0) <= IFNULL(pr.stock_minimo_base,0)
    ) AS criticos) AS alertas_stock_critico,

    -- Tasa de anulacion sobre requerimientos de los ultimos 30 dias
    (SELECT ROUND(100.0 * SUM(CASE WHEN estado = 'Anulado' THEN 1 ELSE 0 END) /
                  NULLIF(COUNT(*), 0), 1)
       FROM requerimiento_almacen
       WHERE COALESCE(fecha_solicitud, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ) AS tasa_anulacion_pct,

    -- Ultimo movimiento registrado en Kardex (proxy de actividad reciente)
    (SELECT MAX(created_at) FROM kardex_producto) AS tr_ultimo_movimiento_kardex
;


-- -----------------------------------------------------------------------------
-- Vista 2: Stock valorizado por almacen (grafico de barras)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_bi_stock_valorizado AS
SELECT
    alm.id              AS id_almacen,
    alm.nombre          AS almacen,
    COUNT(DISTINCT lp.id_producto) AS productos_activos,
    COALESCE(SUM(lp.stock_actual_base), 0) AS unidades_base_total,
    ROUND(COALESCE(SUM(lp.stock_actual_base * IFNULL(lp.costo_por_unidad_base,0)), 0), 2) AS valorizado,
    SUM(CASE WHEN lp.stock_actual_base IS NULL OR lp.stock_actual_base <= 0 THEN 1 ELSE 0 END) AS lotes_sin_stock
FROM almacen alm
LEFT JOIN lote_producto lp
       ON lp.id_almacen = alm.id
      AND lp.estado = 'Activo'
WHERE alm.estado = 'Activo'
GROUP BY alm.id, alm.nombre
ORDER BY valorizado DESC;


-- -----------------------------------------------------------------------------
-- Vista 3: Top productos mas despachados (ultimos 30 dias)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_bi_top_productos AS
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
  AND k.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY pr.id, pr.nombre, unib.abreviatura
ORDER BY total_despachado_base DESC
LIMIT 10;


-- -----------------------------------------------------------------------------
-- Vista 4: Rotacion mensual (Ingresos vs Salidas por mes, ultimos 12 meses)
-- -----------------------------------------------------------------------------
-- Importante: usa EXTRACT(YEAR_MONTH FROM created_at) para evitar el
-- error "only_full_group_by" en MySQL 8.
-- Tambien quitamos fecha_inicio para simplificar.
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_bi_rotacion_mensual AS
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
FROM kardex_producto
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY EXTRACT(YEAR_MONTH FROM created_at), DATE_FORMAT(created_at, '%Y-%m')
ORDER BY mes ASC;


-- -----------------------------------------------------------------------------
-- Vista 5: Alertas de stock desglosadas por producto y almacen
-- -----------------------------------------------------------------------------
-- Niveles (segun el stock real vs stock minimo):
--   - SIN STOCK: stock actual <= 0
--   - CRITICO:   stock actual <= stock_minimo_base (pero > 0)
--   - BAJO:      stock actual <= stock_minimo_base * 1.5 (pero > minimo)
--   - NORMAL:    stock actual > stock_minimo_base * 1.5 (NO es alerta)
-- El HAVING filtra para mostrar solo alertas reales (SIN STOCK/CRITICO/BAJO).
-- Una fila por (producto, almacen) para que la UI pueda filtrar por almacen.
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_bi_alertas_stock AS
SELECT
    pr.id              AS id_producto,
    pr.nombre          AS producto,
    unib.abreviatura   AS unidad_base_abv,
    IFNULL(pr.stock_minimo_base, 0) AS stock_minimo_base,
    COALESCE(lp.id_almacen, 0)    AS id_almacen,
    COALESCE(alm.nombre, 'SIN ALMACEN ESPECIFICO') AS almacen,
    COALESCE(SUM(lp.stock_actual_base), 0) AS stock_actual_base,
    -- deficit_base: positivo cuando FALTA stock, negativo cuando SOBRA
    IFNULL(pr.stock_minimo_base, 0) - COALESCE(SUM(lp.stock_actual_base), 0) AS deficit_base,
    CASE
        WHEN COALESCE(SUM(lp.stock_actual_base), 0) <= 0 THEN 'SIN STOCK'
        WHEN COALESCE(SUM(lp.stock_actual_base), 0) <= IFNULL(pr.stock_minimo_base, 0) THEN 'CRITICO'
        WHEN COALESCE(SUM(lp.stock_actual_base), 0) <= IFNULL(pr.stock_minimo_base, 0) * 1.5 THEN 'BAJO'
        ELSE 'NORMAL'
    END AS nivel_alerta
FROM producto pr
INNER JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
LEFT JOIN lote_producto lp
       ON lp.id_producto = pr.id
      AND lp.estado = 'Activo'
LEFT JOIN almacen alm ON alm.id = lp.id_almacen
WHERE pr.estado = 'Activo'
GROUP BY pr.id, pr.nombre, unib.abreviatura, pr.stock_minimo_base, lp.id_almacen, alm.nombre
-- ============================================================
-- FIX: Solo alertar productos que TIENEN al menos un lote activo.
-- Un producto del catalogo sin lotes NUNCA se ha comprado,
-- asi que NO es una alerta (es solo info de catalogo).
-- ============================================================
HAVING nivel_alerta IN ('SIN STOCK','CRITICO','BAJO')
   AND COUNT(DISTINCT lp.id) > 0
ORDER BY stock_actual_base ASC, deficit_base DESC;


-- -----------------------------------------------------------------------------
-- Vista 6: TAP (Tiempo Promedio Despacho) por almacen
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_bi_tap_por_almacen AS
SELECT
    alm.id              AS id_almacen,
    alm.nombre          AS almacen,
    COUNT(DISTINCT ra.id) AS req_atendidos,
    ROUND(AVG(TIMESTAMPDIFF(MINUTE, COALESCE(ra.fecha_solicitud, ra.created_at), primera.fecha_hora_entrega)), 1) AS tap_minutos,
    CASE
        WHEN AVG(TIMESTAMPDIFF(MINUTE, COALESCE(ra.fecha_solicitud, ra.created_at), primera.fecha_hora_entrega)) <= 12 THEN 'CUMPLE'
        WHEN AVG(TIMESTAMPDIFF(MINUTE, COALESCE(ra.fecha_solicitud, ra.created_at), primera.fecha_hora_entrega)) <= 20 THEN 'EN RANGO'
        ELSE 'FUERA DE META'
    END AS cumplimiento_tda
FROM requerimiento_almacen ra
INNER JOIN almacen alm ON alm.id = ra.id_almacen_destino
INNER JOIN (
    SELECT rae.id_requerimiento_almacen, MIN(rae.fecha_hora_entrega) AS fecha_hora_entrega
    FROM requerimiento_almacen_entrega rae
    WHERE rae.estado = 'Entregado'
    GROUP BY rae.id_requerimiento_almacen
) primera ON primera.id_requerimiento_almacen = ra.id
WHERE ra.estado IN ('Completado','Cerrado','En Despacho','Atendido Parcial')
  AND COALESCE(ra.fecha_solicitud, ra.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY alm.id, alm.nombre
ORDER BY tap_minutos ASC;


-- -----------------------------------------------------------------------------
-- Vista 7: Tendencias mensuales (ultimos 6 meses) para sparklines
-- -----------------------------------------------------------------------------
-- Genera una fila por cada uno de los ultimos 6 meses con:
--   - total_req        : pedidos creados
--   - entregas_mes     : pedidos completados (entregados al menos 1 vez)
--   - ingresos_base    : unidades base que entraron al almacen
--   - salidas_base     : unidades base que salieron del almacen
--   - stock_neto_acum  : flujo neto acumulado en unidades base (proxy del stock)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_bi_tendencias_mensuales AS
SELECT
    mes,
    total_req,
    entregas_mes,
    en_despacho,
    ingresos_base,
    salidas_base,
    -- Diferencia del mes (ingresos - salidas del mes, sin acumular)
    ingresos_base - salidas_base AS stock_neto_mes,
    -- Acumulado historico (para reportes que lo necesiten)
    SUM(ingresos_base - salidas_base) OVER (ORDER BY mes) AS stock_neto_acum_base
FROM (
    SELECT
        meses.mes,
        COALESCE(req.total_req, 0) AS total_req,
        COALESCE(req.entregas_mes, 0) AS entregas_mes,
        COALESCE(req.en_despacho, 0) AS en_despacho,
        COALESCE(kar.ingresos_base, 0) AS ingresos_base,
        COALESCE(kar.salidas_base, 0) AS salidas_base
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
        GROUP BY DATE_FORMAT(COALESCE(ra.fecha_solicitud, ra.created_at), '%Y-%m')
    ) req ON req.mes = meses.mes
    LEFT JOIN (
        SELECT DATE_FORMAT(k.created_at, '%Y-%m') AS mes,
               SUM(CASE WHEN k.tipo_movimiento = 'Ingreso' THEN k.cantidad_movimiento_base ELSE 0 END) AS ingresos_base,
               SUM(CASE WHEN k.tipo_movimiento = 'Salida' THEN k.cantidad_movimiento_base ELSE 0 END) AS salidas_base
          FROM kardex_producto k
          WHERE k.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(k.created_at, '%Y-%m')
    ) kar ON kar.mes = meses.mes
) datos
ORDER BY mes ASC;