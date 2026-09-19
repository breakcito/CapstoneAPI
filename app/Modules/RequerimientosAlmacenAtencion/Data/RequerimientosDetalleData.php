<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Data;

use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalleLog;
use Illuminate\Support\Facades\DB;

class RequerimientosDetalleData
{

    /**
     * Obtiene los detalles de un requerimiento de almacen
     */
    public static function get_detalles_by_requerimiento(
        int $id_requerimiento
    ) {
        return RequerimientoAlmacenDetalle::get_detalles(
            id_requerimiento: $id_requerimiento
        );
    }

    public static function get_cantidades_of_detalle_by_id(int $id_detalle)
    {
        return RequerimientoAlmacenDetalle::where('id', $id_detalle)->first([
            'cantidad_solicitada_base',
            'cantidad_entregada_base',
        ]);
    }

    /**
     * Obtiene los logs de trazabilidad de un detalle
     */
    public static function get_detalle_logs(int $id_detalle)
    {
        return RequerimientoAlmacenDetalleLog::get_logs(
            id_requerimiento_detalle: $id_detalle
        );
    }

    /**
     * Inserta un log de trazabilidad para un detalle
     */
    public static function insert_detalle_log(
        int $id_detalle,
        int $id_empleado,
        string $descripcion,
        EstadoRequerimientoDetalleLog $estado
    ) {
        return RequerimientoAlmacenDetalleLog::crear_log(
            id_requerimiento_detalle: $id_detalle,
            id_empleado: $id_empleado,
            descripcion: $descripcion,
            estado: $estado
        );
    }

    /**
     * Actualiza el estado de un detalle de requerimiento
     */
    public static function update_detalle_estado(int $id_detalle, string $estado, int $id_empleado, ?string $comentario = null)
    {
        $updateData = [
            'estado' => $estado,
        ];

        if ($comentario !== null) {
            $updateData['comentario_decision'] = $comentario;
        }

        return RequerimientoAlmacenDetalle::where('id', $id_detalle)
            ->update($updateData);
    }


    /**
     * Incrementar cantidades entregadas en el detalle del requerimiento
     */
    public static function increment_detalle_entregado(int $id_detalle, float $cantidad_req, float $cantidad_base)
    {
        return RequerimientoAlmacenDetalle::where('id', $id_detalle)
            ->incrementEach([
                'cantidad_entregada' => $cantidad_req,
                'cantidad_entregada_base' => $cantidad_base
            ]);
    }

    public static function get_id_requerimiento_by_detalle(int $id_detalle)
    {
        return DB::selectOne('
            SELECT
                rad.id_requerimiento_almacen
            FROM
                requerimiento_almacen_detalle rad
            WHERE
                rad.id = :id_detalle
        ', ["id_detalle" => $id_detalle]);
    }

    /**
     * Devuelve la fila cruda del detalle con sus cantidades actuales. Sirve
     * para que `editar_requerimiento` valide que el item aun no tenga entrega
     * iniciada antes de modificarlo.
     */
    public static function get_detalle_raw(int $id_detalle)
    {
        return RequerimientoAlmacenDetalle::where('id', $id_detalle)->first();
    }

    /**
     * Actualiza los campos editables de un detalle. Recalcula
     * `cantidad_solicitada_base` segun el modelo de magnitud del item.
     */
    public static function update_detalle_editable(int $id_detalle, array $campos)
    {
        $permitidos = [
            'id_unidad_medida',
            'cantidad_solicitada',
            'contenido_por_presentacion',
            'cantidad_solicitada_base',
            'comentario',
            'para_mantenimiento',
            'id_activo_fijo_destino',
            'con_magnitud',
            'cantidad_items',
            'valor_magnitud',
            'valor_magnitud_base',
        ];

        $updateData = [];
        foreach ($permitidos as $key) {
            if (array_key_exists($key, $campos)) {
                $updateData[$key] = $campos[$key];
            }
        }

        if (empty($updateData)) {
            return 0;
        }

        // Eloquent convierte boolean a int segun cast, pero al no tener casts
        // declarados, asegurarse manualmente para con_magnitud.
        if (isset($updateData['con_magnitud'])) {
            $updateData['con_magnitud'] = $updateData['con_magnitud'] ? 1 : 0;
        }
        if (isset($updateData['para_mantenimiento'])) {
            $updateData['para_mantenimiento'] = $updateData['para_mantenimiento'] ? 1 : 0;
        }

        return RequerimientoAlmacenDetalle::where('id', $id_detalle)
            ->update($updateData);
    }

    /**
     * Elimina un detalle solo si su cantidad_entregada_base es 0 (aun no
     * tuvo despacho). Devuelve true si elimino, false si bloqueo por seguridad.
     */
    public static function delete_detalle_si_no_entregado(int $id_detalle): bool
    {
        $fila = self::get_detalle_raw($id_detalle);
        if (!$fila) {
            return false;
        }
        if ((float) $fila->cantidad_entregada_base > 0) {
            return false;
        }
        $deleted = RequerimientoAlmacenDetalle::where('id', $id_detalle)->delete();
        return $deleted > 0;
    }

    /**
     * Helper para que `editar_requerimiento` reconstruya
     * `cantidad_solicitada_base` con la misma formula que el registro original.
     */
    public static function recalcular_cantidad_base(
        float $cantidad_solicitada,
        float $contenido_por_presentacion,
        bool $con_magnitud,
        ?float $cantidad_items,
        ?float $valor_magnitud_base
    ): float {
        if ($con_magnitud && $cantidad_items && $valor_magnitud_base) {
            return $cantidad_items * $valor_magnitud_base;
        }
        return $cantidad_solicitada * $contenido_por_presentacion;
    }


    /**
     * Crear el detalle de un requerimiento de almacén.
     */
    public static function crear_detalle(
        int $id_requerimiento,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad,
        float $contenido,
        float $cantidad_base,
        ?string $comentario = null,
        bool $para_mantenimiento = false,
        ?int $id_activo_fijo_destino = null,
        bool $con_magnitud = false,
        ?float $cantidad_items = null,
        ?float $valor_magnitud = null,
        ?float $valor_magnitud_base = null,
    ) {
        return RequerimientoAlmacenDetalle::insertGetId([
            'id_requerimiento_almacen' => $id_requerimiento,
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'cantidad_solicitada' => $cantidad,
            'contenido_por_presentacion' => $contenido,
            'cantidad_solicitada_base' => $cantidad_base,
            'cantidad_entregada' => 0,
            'cantidad_entregada_base' => 0,
            'comentario' => $comentario,
            'para_mantenimiento' => $para_mantenimiento,
            'id_activo_fijo_destino' => $id_activo_fijo_destino,
            'con_magnitud' => $con_magnitud ? 1 : 0,
            'cantidad_items' => $cantidad_items,
            'valor_magnitud' => $valor_magnitud,
            'valor_magnitud_base' => $valor_magnitud_base,
            'estado' => EstadoRequerimientoDetalle::EsperandoAprobacion->value,
        ]);
    }

    /**
     * Registra en la trazbilidad del detalle
     */
    public static function registrar_trazabilidad(
        int $id_detalle,
        int $id_empleado_registro
    ) {
        return RequerimientoAlmacenDetalleLog::crear_log(
            id_requerimiento_detalle: $id_detalle,
            id_empleado: $id_empleado_registro,
            descripcion: EstadoRequerimientoDetalle::EsperandoAprobacion->getGlosa(),
            estado: EstadoRequerimientoDetalleLog::EsperandoAprobacion
        );
    }
}
