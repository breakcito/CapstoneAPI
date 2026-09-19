<?php

namespace App\Modules\Productos\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use Illuminate\Support\Facades\DB;

class ProductosData
{
    /**
     * Listar todos los productos del catálogo con su categoría y unidad de medida
     */
    public static function get_productos(?int $id_producto = null)
    {
        $sql = '
            SELECT
                p.id AS id_producto,
                p.nombre,
                -- 
                p.id_categoria,
                c.nombre as categoria,
                c.clasificacion_bien,
                --
                p.id_unidad_medida_base,
                um.nombre as unidad_medida_base,
                um.abreviatura as unidad_medida_base_abreviatura,
                -- 
                p.prefijo,
                -- 
                p.es_auditable,
                p.es_perecible,
                p.para_mantenimiento,
                -- 
                p.stock_minimo_base,
                p.moneda,
                p.costo_promedio_base,
                p.costo_promedio_base_log,
                -- 
                p.tiempo_espera_vencimiento,
                p.periodo_espera_vencimiento,
                p.dias_espera_vencimiento,
                --
                p.cambios_log,
                -- 
                p.estado
            FROM
                producto p
            INNER JOIN categoria c ON c.id = p.id_categoria
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
     * Mapeo campo_bd => nombre visible para el log de cambios.
     * Mantener sincronizado con los campos actualizables de actualizar_producto().
     */
    private const PRODUCTO_CAMBIOS_LABELS = [
        'id_categoria' => 'Categoría',
        'id_unidad_medida_base' => 'Unidad de Medida',
        'nombre' => 'Nombre',
        'prefijo' => 'Prefijo',
        'es_auditable' => 'Es Auditable',
        'es_perecible' => 'Es Perecible',
        'para_mantenimiento' => 'Para Mantenimiento',
        'stock_minimo_base' => 'Stock Mínimo Base',
        'moneda' => 'Moneda',
        'costo_promedio_base' => 'Costo Promedio Base',
        'tiempo_espera_vencimiento' => 'Tiempo Espera Vencimiento',
        'periodo_espera_vencimiento' => 'Periodo Espera Vencimiento',
        'dias_espera_vencimiento' => 'Días Espera Vencimiento',
    ];

    /**
     * Tipo PHP esperado por cada campo. Se usa SOLO para la normalización del diff,
     * de modo que `false !== 0` (bool vs int) no genere falsos positivos.
     */
    private const PRODUCTO_CAMBIOS_TIPOS = [
        'id_categoria' => 'int',
        'id_unidad_medida_base' => 'int',
        'nombre' => 'string',
        'prefijo' => 'string',
        'es_auditable' => 'bool',
        'es_perecible' => 'bool',
        'para_mantenimiento' => 'bool',
        'stock_minimo_base' => 'float',
        'moneda' => 'string',
        'costo_promedio_base' => 'float',
        'tiempo_espera_vencimiento' => 'int',
        'periodo_espera_vencimiento' => 'string',
        'dias_espera_vencimiento' => 'int',
    ];

    /**
     * Normaliza un valor al tipo canónico del campo para comparaciones fiables.
     * Evita falsos positivos por diferencias de tipo (bool vs int, float como string, etc.).
     */
    private static function normalizarParaComparar(mixed $valor, string $tipo): mixed
    {
        if ($valor === null) {
            return null;
        }
        return match ($tipo) {
            'bool' => ((bool) $valor) ? 1 : 0,
            'int' => (int) $valor,
            'float' => (float) $valor,
            default => (string) $valor,
        };
    }

    /**
     * Actualizar un producto existente manteniendo inalterable su historial de costos.
     * Si recibe id_empleado + nombre_empleado, calcula el diff entre el producto
     * previo y el nuevo y lo apendea al array cambios_log (JSON).
     */
    public static function actualizar_producto(
        int $id_producto,
        int $id_categoria,
        int $id_unidad_medida_base,
        string $nombre,
        bool $es_auditable,
        bool $es_perecible,
        bool $para_mantenimiento,
        float $stock_minimo_base,
        float $costo_promedio_base,
        ?string $prefijo = null,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null,
        ?int $dias_espera_vencimiento = null,
        Moneda $moneda = Moneda::PEN,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ): int {
        $nuevoEstado = [
            'id_categoria' => $id_categoria,
            'id_unidad_medida_base' => $id_unidad_medida_base,
            'nombre' => $nombre,
            'prefijo' => $prefijo,
            'es_auditable' => $es_auditable,
            'es_perecible' => $es_perecible,
            'para_mantenimiento' => $para_mantenimiento,
            'stock_minimo_base' => $stock_minimo_base,
            'moneda' => $moneda->value,
            'costo_promedio_base' => $costo_promedio_base,
            'tiempo_espera_vencimiento' => $tiempo_espera_vencimiento,
            'periodo_espera_vencimiento' => $periodo_espera_vencimiento,
            'dias_espera_vencimiento' => $dias_espera_vencimiento,
        ];

        $cambiosLog = null;
        if ($id_empleado !== null && $nombre_empleado !== null) {
            $original = self::get_productos(id_producto: $id_producto);
            $cambiosLog = self::calcularDiffCambiosProducto($original, $nuevoEstado, $id_empleado, $nombre_empleado);
        }

        $updatePayload = $nuevoEstado;
        if ($cambiosLog !== null) {
            $updatePayload['cambios_log'] = json_encode($cambiosLog, JSON_UNESCAPED_UNICODE);
        }

        $affected = DB::table('producto')
            ->where('id', $id_producto)
            ->update($updatePayload);

        return (int) $affected;
    }

    /**
     * Compara el producto previo (object|array) con el nuevo estado (array) y devuelve
     * el array de cambios_log listo para persistir (incluye la entrada existente solo
     * si hay al menos un campo modificado).
     */
    private static function calcularDiffCambiosProducto(
        $original,
        array $nuevoEstado,
        int $id_empleado,
        string $nombre_empleado
    ): array {
        $logPrevio = [];
        if ($original !== null && !empty($original->cambios_log)) {
            $raw = $original->cambios_log;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $logPrevio = is_array($decoded) ? $decoded : [];
            } elseif (is_array($raw)) {
                $logPrevio = $raw;
            }
        }

        $cambios = [];
        foreach (self::PRODUCTO_CAMBIOS_LABELS as $campoBd => $label) {
            if (!array_key_exists($campoBd, $nuevoEstado)) {
                continue;
            }
            $valorAnterior = $original !== null ? ($original->{$campoBd} ?? null) : null;
            $valorNuevo = $nuevoEstado[$campoBd];

            $tipo = self::PRODUCTO_CAMBIOS_TIPOS[$campoBd] ?? 'string';
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
     * Desactivar (soft delete) un producto cambiando su estado a Inactivo.
     * No se elimina físicamente para preservar la integridad referencial con Kardex y Lotes.
     */
    public static function eliminar_producto(int $id_producto): int
    {
        $affected = DB::table('producto')
            ->where('id', $id_producto)
            ->update([
                'estado' => EstadoBase::Inactivo->value,
            ]);

        return (int) $affected;
    }

    /**
     * Verificar si ya existe un producto activo con el mismo nombre, excluyendo opcionalmente un ID concreto
     */
    public static function existe_nombre(string $nombre, ?int $excluir_id = null): bool
    {
        $query = DB::table('producto')
            ->where('nombre', $nombre)
            ->where('estado', '!=', EstadoBase::Inactivo->value);

        if ($excluir_id !== null) {
            $query->where('id', '!=', $excluir_id);
        }

        return $query->exists();
    }
}
