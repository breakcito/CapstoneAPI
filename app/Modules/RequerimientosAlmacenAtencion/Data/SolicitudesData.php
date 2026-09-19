<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Data;

use App\Models\SolicitudReabastecimiento;
use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use App\Shared\Enums\_Generic\Premura;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalleLog;

class SolicitudesData
{
    /**
     * --------------------------------------------
     * METODOS PARA LA CABECERA DE UNA SOLICITUD
     * --------------------------------------------
     */



    /**
     * Obtener una o toda la lista de solicitudes
     */
    public static function get_solicitudes(
        int $id_requerimiento,
    ) {
        return SolicitudReabastecimiento::get_solicitudes(
            id_requerimiento_almacen: $id_requerimiento
        );
    }

    /**
     * Funcion helpder que ayuda a crear la cabecera de la solicitud
     */
    public static function crear_solicitud(
        int $id_requerimiento_almacen,
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $correlativo,
        int $numero_correlativo,
        Premura $premura,
        string $fecha_entrega_requerida,
        bool $es_auditable,
        ?string $observacion = null,
    ) {
        return SolicitudReabastecimiento::crear_solicitud(
            id_almacen_solicitante: $id_almacen_solicitante,
            id_empleado_solicitante: $id_empleado_solicitante,
            correlativo: $correlativo,
            numero_correlativo: $numero_correlativo,
            premura: $premura,
            id_requerimiento_almacen: $id_requerimiento_almacen,
            observacion: $observacion,
            fecha_entrega_requerida: $fecha_entrega_requerida,
            es_auditable: $es_auditable,
        );
    }

    /**
     * Helper que ayuda a calcular el siguiente correlativo - reseteo anual
     */
    public static function get_nuevo_correlativo()
    {
        return SolicitudReabastecimiento::get_nuevo_correlativo();
    }



    /**
     * --------------------------------------------
     * METODOS PARA EL DETALLE DE UNA SOLICITUD
     * --------------------------------------------
     */



    /**
     * Obtener el detalle de una solicitud
     */
    public static function get_detalles_solicitud(int $id_solicitud_reabastecimiento)
    {
        return SolicitudReabastecimientoDetalle::get_detalles_solicitud(
            id_solicitud_reabastecimiento: $id_solicitud_reabastecimiento
        );
    }

    /**
     * Funcion helper que ayuda a crear un detalle de solicitud
     */
    public static function crear_detalle_solicitud(
        int $id_requerimiento_detalle,
        int $id_solicitud,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad_solicitada,
        float $contenido_por_presentacion,
        float $cantidad_solicitada_base,
        ?string $comentario = null
    ) {
        return SolicitudReabastecimientoDetalle::crear_detalle(
            id_solicitud_reabastecimiento: $id_solicitud,
            id_producto: $id_producto,
            id_unidad_medida: $id_unidad_medida,
            cantidad_solicitada: $cantidad_solicitada,
            cantidad_solicitada_base: $cantidad_solicitada_base,
            contenido_por_presentacion: $contenido_por_presentacion,
            id_requerimiento_almacen_detalle: $id_requerimiento_detalle,
            comentario: $comentario
        );
    }




    /**
     * -----------------------------------------------------------
     * METODOS PARA LA TRAZABILIDAD DEL DETALLE DE UNA SOLICITUD
     * -----------------------------------------------------------
     */



    /**
     * Registrar en trazabilidad el cambio de estado de un detalle 
     * de solicitud de reabastecimiento
     */
    public static function insert_detalle_log(
        int $id_solicitud_detalle,
        int $id_empleado,
        string $descripcion,
        EstadoSolicitudDetalleLog $estado
    ) {
        return SolicitudReabastecimientoDetalleLog::crear_log(
            id_solicitud_detalle: $id_solicitud_detalle,
            id_empleado: $id_empleado,
            descripcion: $descripcion,
            estado: $estado
        );
    }
}
