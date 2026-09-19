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
            c.nombre AS categoria,
            um_lote.abreviatura AS unidad_medida_abv,
            lp.descripcion,
            lp.correlativo,
            lp.numero_correlativo,
            lp.correlativo_auditoria,
            lp.numero_correlativo_auditoria,
            lp.stock_actual,
            lp.contenido_por_presentacion,
            lp.stock_actual_base,
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            lp.estado,
            lp.serie_factura_compra AS serie_factura_lote,
            lp.numero_factura_compra AS numero_factura_lote,
            lp.cambios_log,
            p.es_perecible,
            p.es_auditable,
            p.stock_minimo_base,
            p.dias_espera_vencimiento,
            /* Cálculo de días restantes */
            CASE
                WHEN lp.fecha_vencimiento IS NOT NULL THEN
                    DATEDIFF(lp.fecha_vencimiento,CURRENT_DATE)
                ELSE NULL
            END AS dias_para_vencer,
            /* Determinación del estado de vencimiento */
            CASE
                WHEN p.es_perecible != 1 THEN "N/A"
                WHEN lp.fecha_vencimiento IS NULL THEN "Sin fecha"
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) < 0 THEN "Vencido"
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= p.dias_espera_vencimiento THEN "Por vencer"
                ELSE "Vigente"
            END AS estado_vencimiento,
            /* Costo y origen de compra */
            lp.costo_por_unidad,
            COALESCE(occ.serie, lp.serie_factura_compra) AS serie_factura_compra,
            COALESCE(occ.numero, lp.numero_factura_compra) AS numero_factura_compra,
            ocd.id_orden_compra,
            occr.id_orden_compra_comprobante,
            lp.id_orden_compra_detalle
        FROM
            lote_producto lp
        INNER JOIN producto p ON
            p.id = lp.id_producto
        LEFT JOIN categoria c ON
            c.id = p.id_categoria
        LEFT JOIN unidad_medida um_base ON
            um_base.id = p.id_unidad_medida_base
        LEFT JOIN unidad_medida um_lote ON
            um_lote.id = lp.id_unidad_medida
        LEFT JOIN orden_compra_detalle ocd ON
            ocd.id = lp.id_orden_compra_detalle
        LEFT JOIN orden_compra_recepcion_detalle ocrd ON
            ocrd.id = lp.id_orden_compra_recepcion_detalle
        LEFT JOIN orden_compra_comprobante_recepcion occr ON
            occr.id_orden_compra_recepcion = ocrd.id_orden_compra_recepcion
        LEFT JOIN orden_compra_comprobante occ ON
            occ.id = occr.id_orden_compra_comprobante
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
     * Mapeo campo_bd => nombre visible para el log de cambios.
     * Mantener sincronizado con los campos actualizables de actualizar_lote().
     * NOTA: 'estado' NO está aquí — la baja lógica se gestiona por eliminar_lote().
     * NOTA: 'fecha_vencimiento' NO está aquí — solo se setea al registrar el lote.
     */
    private const LOTE_CAMBIOS_LABELS = [
        'descripcion' => 'Descripción',
        'serie_factura_compra' => 'Serie Factura',
        'numero_factura_compra' => 'Número Factura',
        'fecha_hora_ingreso' => 'Fecha de Ingreso',
    ];

    /**
     * Tipo PHP esperado por cada campo. Se usa SOLO para la normalización del diff,
     * de modo que "" vs null no genere falsos positivos (string vacio == null).
     */
    private const LOTE_CAMBIOS_TIPOS = [
        'descripcion' => 'string',
        'serie_factura_compra' => 'string',
        'numero_factura_compra' => 'string',
        'fecha_hora_ingreso' => 'string',
    ];

    /**
     * Normaliza un valor al tipo canónico del campo para comparaciones fiables.
     * Trata "" como null para campos string opcionales.
     */
    private static function normalizarParaComparar(mixed $valor, string $tipo): mixed
    {
        if ($tipo === 'string') {
            if ($valor === null) return null;
            $trimmed = trim((string) $valor);
            return $trimmed === '' ? null : $trimmed;
        }
        if ($valor === null) {
            return null;
        }
        return match ($tipo) {
            'int' => (int) $valor,
            'float' => (float) $valor,
            'bool' => ((bool) $valor) ? 1 : 0,
            default => (string) $valor,
        };
    }

    /**
     * Decodifica cambios_log a array. MySQL puede devolverlo como string JSON
     * (cuando se selecciona con DB::select) o como array (algunos drivers).
     */
    private static function decodeCambiosLog(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Actualizar un lote editable (descripcion, serie/numero factura, fecha_hora_ingreso).
     * Stock, identificadores, fecha_vencimiento y estado NO son editables aquí:
     *  - stock/contenido: por Corrección de Inventario (Kardex inmutable).
     *  - fecha_vencimiento: solo se setea al registrar el lote.
     *  - estado: por eliminar_lote() (soft-delete).
     *
     * Si recibe id_empleado + nombre_empleado, calcula el diff entre el lote
     * previo y el nuevo y lo apendea al array cambios_log (JSON).
     */
    public static function actualizar_lote(
        int $id_lote,
        string $descripcion,
        ?string $serie_factura_compra,
        ?string $numero_factura_compra,
        ?string $fecha_hora_ingreso,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ): int {
        $nuevoEstado = [
            'descripcion' => $descripcion,
            'serie_factura_compra' => $serie_factura_compra,
            'numero_factura_compra' => $numero_factura_compra,
            'fecha_hora_ingreso' => $fecha_hora_ingreso,
        ];

        $cambiosLog = null;
        if ($id_empleado !== null && $nombre_empleado !== null) {
            $original = self::get_resumen_lotes(id_lote: $id_lote);
            $cambiosLog = self::calcularDiffCambiosLote($original, $nuevoEstado, $id_empleado, $nombre_empleado);
        }

        $updatePayload = $nuevoEstado;
        if ($cambiosLog !== null) {
            $updatePayload['cambios_log'] = json_encode($cambiosLog, JSON_UNESCAPED_UNICODE);
        }

        $affected = DB::table('lote_producto')
            ->where('id', $id_lote)
            ->update($updatePayload);

        return (int) $affected;
    }

    /**
     * Compara el lote previo (object|array) con el nuevo estado (array) y devuelve
     * el array de cambios_log listo para persistir. Mantiene el log previo y solo
     * agrega entrada si hay al menos un campo modificado.
     */
    private static function calcularDiffCambiosLote(
        $original,
        array $nuevoEstado,
        int $id_empleado,
        string $nombre_empleado
    ): array {
        $logPrevio = [];
        if ($original !== null) {
            $logPrevio = self::decodeCambiosLog($original->cambios_log ?? null);
        }

        $cambios = [];
        foreach (self::LOTE_CAMBIOS_LABELS as $campoBd => $label) {
            if (!array_key_exists($campoBd, $nuevoEstado)) {
                continue;
            }
            $valorAnterior = $original !== null ? ($original->{$campoBd} ?? null) : null;
            $valorNuevo = $nuevoEstado[$campoBd];

            $tipo = self::LOTE_CAMBIOS_TIPOS[$campoBd] ?? 'string';
            $anteriorNorm = self::normalizarParaComparar($valorAnterior, $tipo);
            $nuevoNorm = self::normalizarParaComparar($valorNuevo, $tipo);

            if ($anteriorNorm !== $nuevoNorm) {
                $cambios[] = [
                    'campo_bd' => $campoBd,
                    'campo' => $label,
                    'valor_anterior' => $valorAnterior,
                    'valor_nuevo' => $valorNuevo,
                ];
            }
        }

        if (count($cambios) === 0) {
            return $logPrevio;
        }

        $logPrevio[] = [
            'id_empleado' => $id_empleado,
            'nombre_empleado' => $nombre_empleado,
            'motivo' => null,
            'update_at' => now()->toDateTimeString(),
            'cambios' => $cambios,
        ];

        return $logPrevio;
    }

    /**
     * Desactivar (soft delete) un lote cambiando su estado a Inactivo.
     * Registra la accion en cambios_log para mantener trazabilidad.
     * No se elimina físicamente para preservar la integridad referencial con Kardex.
     */
    public static function eliminar_lote(
        int $id_lote,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ): int {
        $original = self::get_resumen_lotes(id_lote: $id_lote);

        $logPrevio = [];
        if ($original !== null) {
            $logPrevio = self::decodeCambiosLog($original->cambios_log ?? null);
        }

        if ($id_empleado !== null && $nombre_empleado !== null && $original !== null) {
            $estadoAnterior = $original->estado ?? null;
            if ($estadoAnterior !== EstadoBase::Inactivo->value) {
                $logPrevio[] = [
                    'id_empleado' => $id_empleado,
                    'nombre_empleado' => $nombre_empleado,
                    'motivo' => null,
                    'update_at' => now()->toDateTimeString(),
                    'cambios' => [[
                        'campo_bd' => 'estado',
                        'campo' => 'Estado',
                        'valor_anterior' => $estadoAnterior,
                        'valor_nuevo' => EstadoBase::Inactivo->value,
                    ]],
                ];
            }
        }

        $updatePayload = ['estado' => EstadoBase::Inactivo->value];
        if (count($logPrevio) > 0 && $original !== null) {
            $updatePayload['cambios_log'] = json_encode($logPrevio, JSON_UNESCAPED_UNICODE);
        }

        $affected = DB::table('lote_producto')
            ->where('id', $id_lote)
            ->update($updatePayload);

        return (int) $affected;
    }
}
