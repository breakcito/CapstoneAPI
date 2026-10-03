<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Data;

use App\Models\RequerimientoAlmacenDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
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
        ]);
    }

    /**
     * Obtiene los logs de trazabilidad de un detalle, ordenados del mas
     * reciente al mas antiguo.
     *
     * @return array Lista de eventos con forma
     *   { id_log, id_requerimiento_almacen_detalle, id_empleado, estado,
     *     descripcion, created_at, empleado }
     */
    public static function get_detalle_logs(int $id_detalle)
    {
        $sql = "
        SELECT
            log.id AS id_log,
            log.id_requerimiento_almacen_detalle,
            log.id_empleado,
            log.estado,
            log.descripcion,
            log.created_at,
            CONCAT(emp.nombre, ' ', emp.apellido) AS empleado
        FROM requerimiento_almacen_detalle_log log
        LEFT JOIN empleado emp ON emp.id = log.id_empleado
        WHERE log.id_requerimiento_almacen_detalle = :id_detalle
        ORDER BY log.created_at ASC, log.id ASC
        ";
        return DB::select($sql, ['id_detalle' => $id_detalle]);
    }

    /**
     * Inserta un log de trazabilidad para un detalle.
     *
     * Helper: se llama desde AtencionService, EntregaService y los
     * controllers que cambian el estado de un detalle o lo asocian a
     * una entrega, para que la pantalla "Seguimiento del requerimiento"
     * tenga datos que mostrar.
     */
    public static function insert_detalle_log(
        int $id_detalle,
        ?int $id_empleado,
        string $estado,
        ?string $descripcion = null
    ) {
        return DB::table('requerimiento_almacen_detalle_log')->insertGetId([
            'id_requerimiento_almacen_detalle' => $id_detalle,
            'id_empleado' => $id_empleado,
            'estado' => $estado,
            'descripcion' => $descripcion,
            'created_at' => now(),
        ]);
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
     *
     * NOTA: la tabla `requerimiento_almacen_detalle` NO tiene columnas
     * `cantidad_entregada` ni `cantidad_entregada_base`. Esos valores
     * se calculan en runtime con un SUM sobre las entregas activas
     * (ver `RequerimientoAlmacenDetalle::get_detalles()`).
     *
     * Este metodo queda obsoleto: lo conservamos momentaneamente para no
     * romper imports legacy, pero NO debe llamarse. La entrega activa
     * ya se contabiliza automaticamente al persistir el detalle de la
     * entrega.
     *
     * @deprecated Eliminar cuando se confirme que no hay callers externos.
     */
    public static function increment_detalle_entregado(int $id_detalle, float $cantidad_req, float $cantidad_base)
    {
        // Sin efecto: las cantidades entregadas se derivan de
        // `requerimiento_almacen_entrega_detalle` con JOIN por
        // `requerimiento_almacen_entrega.estado = 'Entregado'`.
        return 0;
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
     * Devuelve true si el detalle tiene al menos una entrega ACTIVA
     * (estado de la cabecera de entrega = 'Entregado'). Se usa en
     * lugar de leer una columna `cantidad_entregada_base` que NO
     * existe en la tabla: esa cantidad se calcula en runtime.
     */
    public static function tiene_entregas_activas(int $id_detalle): bool
    {
        $count = DB::table('requerimiento_almacen_entrega_detalle as ed')
            ->join('requerimiento_almacen_entrega as e', 'e.id', '=', 'ed.id_requerimiento_almacen_entrega')
            ->where('ed.id_requerimiento_almacen_detalle', $id_detalle)
            ->where('e.estado', 'Entregado')
            ->count();
        return $count > 0;
    }

    /**
     * Actualiza los campos editables de un detalle. Recalcula
     * `cantidad_solicitada_base` segun el modelo de magnitud del item.
     *
     * White-list alineada al esquema actual de `requerimiento_almacen_detalle`.
     * Se omiten campos que ya no existen: `para_mantenimiento`, `id_activo_fijo_destino`.
     */
    public static function update_detalle_editable(int $id_detalle, array $campos)
    {
        $permitidos = [
            'id_unidad_medida',
            'cantidad_solicitada',
            'contenido_por_presentacion',
            'cantidad_solicitada_base',
            'comentario',
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

        return RequerimientoAlmacenDetalle::where('id', $id_detalle)
            ->update($updateData);
    }

    /**
     * Elimina un detalle solo si NO tiene entregas activas (estado='Entregado')
     * apuntando a el. Devuelve true si elimino, false si bloqueo por seguridad.
     */
    public static function delete_detalle_si_no_entregado(int $id_detalle): bool
    {
        $fila = self::get_detalle_raw($id_detalle);
        if (!$fila) {
            return false;
        }
        // Verificamos si existe AL MENOS una entrega activa contra este detalle.
        $entregasActivas = DB::table('requerimiento_almacen_entrega_detalle as ed')
            ->join('requerimiento_almacen_entrega as e', 'e.id', '=', 'ed.id_requerimiento_almacen_entrega')
            ->where('ed.id_requerimiento_almacen_detalle', $id_detalle)
            ->where('e.estado', 'Entregado')
            ->count();
        if ($entregasActivas > 0) {
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
            'comentario' => $comentario,
            'con_magnitud' => $con_magnitud ? 1 : 0,
            'cantidad_items' => $cantidad_items ?? 0,
            'valor_magnitud' => $valor_magnitud ?? 0,
            'valor_magnitud_base' => $valor_magnitud_base ?? 0,
            'estado' => EstadoRequerimientoDetalle::Pendiente->value,
        ]);
    }

    public static function registrar_trazabilidad(
        int $id_detalle,
        int $id_empleado_registro
    ) {
        return null;
    }
}
