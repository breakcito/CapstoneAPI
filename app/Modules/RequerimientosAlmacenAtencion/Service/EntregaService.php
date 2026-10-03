<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Service;

use App\Data\LotesProductosData;
use App\Services\LotesProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoEntrega;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Helpers\EmpleadoHelper;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Data\EntregasData;
use App\Modules\RequerimientosAlmacenAtencion\Data\EntregasDetalleData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use Illuminate\Support\Facades\DB;

class EntregaService
{

    /**
     * Obtiene el historial de entregas y sus detalles.
     */
    public static function obtener_historial_entregas(int $id_requerimiento)
    {
        $data = EntregasData::get_historial_entregas(id_requerimiento: $id_requerimiento);

        foreach ($data as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasDetalleData::get_detalles_entrega(
                id_entrega: (int) $entrega->id_requerimiento_almacen_entrega
            );
        }

        return ApiResponse::success($data);
    }

    /**
     * Anular una entrega existente.
     *
     * Esto:
     * 1. Recorre cada detalle y REINTEGRA el stock al lote original.
     * 2. Registra el movimiento inverso en Kardex (Ingreso / Reingreso)
     *    para mantener la trazabilidad contable.
     * 3. Cambia el estado de la cabecera de la entrega a "Anulado".
     *    Como `cantidad_entregada_base` en el detalle del requerimiento
     *    se calcula en runtime con un SUM sobre las entregas activas
     *    (`JOIN` por `requerimiento_almacen_entrega.estado = 'Entregado'`),
     *    NO hay que decrementar contadores: la subconsulta del modelo
     *    dejara de contar esta entrega automaticamente.
     * 4. Si NO quedan entregas activas en el requerimiento, devuelve
     *    el requerimiento a estado "Generado" para que pueda recibir
     *    nuevas entregas.
     */
    public static function anular_entrega(int $id_entrega, ?string $motivo = null)
    {
        return DB::transaction(function () use ($id_entrega, $motivo) {
            $entrega = EntregasData::get_entrega_by_id($id_entrega);
            if (!$entrega) {
                return ApiResponse::error('Entrega no encontrada', 404);
            }
            if ($entrega->estado === EstadoRequerimientoEntrega::Anulado->value) {
                return ApiResponse::error('La entrega ya se encuentra anulada');
            }

            $id_requerimiento = (int) $entrega->id_requerimiento_almacen;

            $requerimientoCabecera = DB::table('requerimiento_almacen')->where('id', $id_requerimiento)->first();
            if ($requerimientoCabecera && ($requerimientoCabecera->estado === EstadoRequerimiento::Anulado->value || (string) $requerimientoCabecera->estado === 'Anulado')) {
                return ApiResponse::error('No se pueden alterar las entregas de un requerimiento anulado');
            }

            // Obtenemos el correlativo del requerimiento para incluirlo en
            // la descripcion del Kardex inverso, asi queda registrada la
            // trazabilidad: que entrega X del requerimiento Y fue anulada.
            $requerimientoCorrelativo = DB::table('requerimiento_almacen')
                ->where('id', $id_requerimiento)
                ->value('correlativo') ?? '';

            // 1. Reintegrar stock de cada item de la entrega
            $detalles = EntregasData::get_detalles_para_reintegrar($id_entrega);
            foreach ($detalles as $det) {
                $id_lote = (int) ($det->id_lote_producto ?? 0);
                if ($id_lote <= 0) {
                    // No habia lote (caso activo fijo). No hay stock que devolver.
                    continue;
                }

                LotesProductosService::reintegrar_stock(
                    id_lote: $id_lote,
                    cantidad_lote: (float) $det->cantidad_lote,
                    cantidad_base: (float) $det->cantidad_base,
                    descripcion: sprintf(
                        "Reingreso por anulacion de entrega N° %s (Req. %s)%s",
                        $entrega->correlativo,
                        $requerimientoCorrelativo,
                        $motivo ? " - {$motivo}" : ''
                    )
                );
            }

            // 2. Marcar la entrega como Anulada.
            //    Al marcar la cabecera como Anulada, la subconsulta
            //    `SUM(cantidad_base) FROM requerimiento_almacen_entrega_detalle
            //    JOIN requerimiento_almacen_entrega WHERE estado='Entregado'`
            //    dejara de contar esta entrega en el detalle del requerimiento.
            EntregasData::anular_entrega($id_entrega);

            // 3. Si NO quedan entregas activas en el requerimiento,
            //    devolverlo a estado "Generado".
            $hayEntregasActivas = DB::table('requerimiento_almacen_entrega')
                ->where('id_requerimiento_almacen', $id_requerimiento)
                ->where('estado', EstadoRequerimientoEntrega::Entregado->value)
                ->exists();

            if (!$hayEntregasActivas) {
                RequerimientosData::update_requerimiento_estado(
                    $id_requerimiento,
                    \App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento::Generado->value
                );
            }

            // 4. Registrar evento de trazabilidad para cada detalle que
            //    estaba en esta entrega. Asi el Seguimiento del requerimiento
            //    muestra "Entrega anulada" con el motivo.
            foreach ($detalles as $det) {
                RequerimientosDetalleData::insert_detalle_log(
                    id_detalle: (int) $det->id_requerimiento_almacen_detalle,
                    id_empleado: $id_empleado_entrega,
                    estado: \App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle::Pendiente->value,
                    descripcion: sprintf(
                        'Entrega %s anulada por %s. Stock reintegrado.%s',
                        $entrega->correlativo,
                        EmpleadoHelper::nombre_completo($id_empleado_entrega),
                        $motivo ? " Motivo: {$motivo}" : ''
                    )
                );
            }

            return ApiResponse::success(null, 'Entrega anulada correctamente. Stock reintegrado al lote.');
        });
    }

    /**
     * Registra una entrega física de materiales.
     *
     * Modelo dual:
     * - id_empleado_entrega: usuario logueado que registra
     * - id_empleado_recibe: empleado/contratista que recibe. Como los
     *   contratistas viven en la tabla empleado con es_contratista=1,
     *   este mismo campo cubre ambos casos.
     *
     * detalles: [
     *  {
     *   id_requerimiento_almacen_detalle,
     *   id_lote_producto,
     *   cantidad_base,
     *   cantidad_lote,
     *   cantidad_requerimiento
     *  }
     * ]
     */
    public static function registrar_entrega(
        int $id_empleado_entrega,
        int $id_requerimiento,
        ?int $id_empleado_recibe,
        string $fecha_entrega,
        ?string $observacion,
        ?array $evidencias, // archivos
        array $detalles
    ) {
        return DB::transaction(function () use ($id_empleado_entrega, $id_requerimiento, $id_empleado_recibe, $fecha_entrega, $observacion, $evidencias, $detalles) {
            $reqCabecera = DB::table('requerimiento_almacen')->where('id', $id_requerimiento)->first();
            if (!$reqCabecera) {
                return ApiResponse::error('Requerimiento no encontrado', 404);
            }
            if ($reqCabecera->estado === EstadoRequerimiento::Anulado->value || (string) $reqCabecera->estado === 'Anulado') {
                return ApiResponse::error('No se pueden registrar entregas para un requerimiento anulado');
            }

            // Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('requerimientos_almacen_entregas', $evidencias);
            }

            // Pre-cargar todos los lotes en una sola consulta (solo los ítems de productos comunes)
            $items_con_lote = array_filter($detalles, fn($i) => empty($i['id_activo_fijo']));
            $ids_lotes = array_map(fn($i) => (int) $i['id_lote_producto'], $items_con_lote);

            $lotesMap = !empty($ids_lotes)
                ? collect(LotesProductosData::get_lote_dinamico_by_id(
                    id_lote: $ids_lotes,
                    columnas: ['stock_actual_base', 'correlativo', 'contenido_por_presentacion']
                ))->keyBy('id_lote')
                : collect();

            // Validar Stock solo para productos comunes
            foreach ($items_con_lote as $item) {
                $lote = $lotesMap->get((int) $item['id_lote_producto']);
                if (!$lote || $lote['stock_actual_base'] < $item['cantidad_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . ($lote['correlativo']));
                }
            }

            // Generar Correlativo
            // $requerimiento = RequerimientosData::get_almacen_destino_by_requerimiento($id_requerimiento);
            $correlativoData = EntregasData::get_nuevo_correlativo();

            // Crear Cabecera de Entrega
            $id_entrega = EntregasData::crear_entrega(
                id_requerimiento: $id_requerimiento,
                id_empleado_entrega: $id_empleado_entrega,
                id_empleado_recibe: $id_empleado_recibe,
                correlativo: $correlativoData['correlativo'],
                numero_correlativo: $correlativoData['numero_correlativo'],
                fecha_hora_entrega: $fecha_entrega,
                observacion: $observacion,
                evidencias: $evidenciasData,
            );

            // Track por cada detalle cuanto se ha entregado en total
            // (sumando esta entrega mas las anteriores). Esto decide
            // si el detalle queda "En Despacho" (parcial) o "Completado"
            // (100%). Antes, el codigo ponia siempre Completado al
            // registrar CUALQUIER entrega, lo que hacia aparecer el
            // requerimiento como 100% despachado con la primera entrega.
            $entregaResumen = [];

            foreach ($detalles as $item) {
                $id_rad = $item['id_requerimiento_almacen_detalle'];
                // --- Camino: Producto Común con Lote ---
                $id_lote = (int) $item['id_lote_producto'];
                $lote = $lotesMap->get($id_lote);

                $costo_unitario = (float) ($lote['costo_por_unidad'] ?? 0);
                $subtotal = $costo_unitario * (float) $item['cantidad_lote'];

                // Crear Detalle de Entrega
                EntregasDetalleData::crear_detalle_entrega(
                    $id_entrega,
                    $id_rad,
                    $id_lote,
                    (float) $item['cantidad_base'],
                    (float) $item['cantidad_lote'],
                    (float) $item['cantidad_requerimiento'],
                    $subtotal
                );

                // Descontar Stock y registrar Kardex (Salida)
                LotesProductosService::descontar_stock(
                    id_lote: $id_lote,
                    cantidad_lote: (float) $item['cantidad_lote'],
                    cantidad_base: (float) $item['cantidad_base'],
                    tipo_origen: KardexOrigenMovimiento::Entrega->value,
                    descripcion: "Salida por entrega N° {$correlativoData['correlativo']}"
                );

                // Acumular cuanto se ha entregado (incluyendo esta entrega
                // mas las anteriores) para decidir el estado final del
                // detalle. Es importante: NO marcamos Completado al
                // instante; primero vemos la suma total.
                $entregadoBaseAcum = (float) DB::table('requerimiento_almacen_detalle as rad')
                    ->join('requerimiento_almacen_entrega_detalle as raed', 'raed.id_requerimiento_almacen_detalle', '=', 'rad.id')
                    ->join('requerimiento_almacen_entrega as rae', 'rae.id', '=', 'raed.id_requerimiento_almacen_entrega')
                    ->where('rad.id', $id_rad)
                    ->where('rae.estado', EstadoRequerimientoEntrega::Entregado->value)
                    ->sum('raed.cantidad_base');

                $solicitadoBase = (float) DB::table('requerimiento_almacen_detalle')
                    ->where('id', $id_rad)
                    ->value('cantidad_solicitada_base');

                $nuevoEstadoDetalle = $entregadoBaseAcum + 0.0001 >= $solicitadoBase
                    ? EstadoRequerimientoDetalle::Completado->value
                    : EstadoRequerimientoDetalle::EnDespacho->value;

                RequerimientosDetalleData::update_detalle_estado(
                    $id_rad,
                    $nuevoEstadoDetalle,
                    $id_empleado_entrega
                );

                // Log de trazabilidad: la entrega de este item quedo
                // registrada. Mostrar el correlativo de la entrega y el
                // nombre del producto / empleado para que sea legible
                // en la UI de Seguimiento.
                RequerimientosDetalleData::insert_detalle_log(
                    id_detalle: (int) $id_rad,
                    id_empleado: $id_empleado_entrega,
                    estado: $nuevoEstadoDetalle,
                    descripcion: sprintf(
                        '%s: Entrega %s registrada (%.2f base despachados, total %.2f de %.2f) por %s.',
                        EmpleadoHelper::producto_nombre((int) ($item['id_producto'] ?? 0)),
                        $correlativoData['correlativo'],
                        (float) $item['cantidad_base'],
                        $entregadoBaseAcum,
                        $solicitadoBase,
                        EmpleadoHelper::nombre_completo($id_empleado_entrega)
                    )
                );

                $entregaResumen[$id_rad] = [
                    'entregado_base' => $entregadoBaseAcum,
                    'solicitado_base' => $solicitadoBase,
                    'completo' => $nuevoEstadoDetalle === EstadoRequerimientoDetalle::Completado->value,
                ];
            }

            // Estado del requerimiento: solo pasa a "Completado" si
            // TODOS los detalles del requerimiento estan Completados.
            // Si hay entregas parciales, queda en "En Despacho" para
            // que el operador pueda seguir registrando entregas.
            $estadosDetalles = DB::table('requerimiento_almacen_detalle')
                ->where('id_requerimiento_almacen', $id_requerimiento)
                ->pluck('estado')
                ->all();

            $todosCompletados = !empty($estadosDetalles) && collect($estadosDetalles)
                ->every(fn($e) => $e === EstadoRequerimientoDetalle::Completado->value);

            $estadoRequerimiento = $todosCompletados
                ? \App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento::Completado->value
                : \App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento::EnDespacho->value;

            RequerimientosData::update_requerimiento_estado(
                $id_requerimiento,
                $estadoRequerimiento
            );

            return ApiResponse::success(
                $correlativoData['correlativo'],
                "Entrega N° {$correlativoData['correlativo']} registrada exitosamente"
            );
        });
    }
}
