<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacenEntrega extends Model
{
    protected $table = 'requerimiento_almacen_entrega';

    public $timestamps = false;

    /**
     * Nota sobre el modelo dual de "quien entrega / recibe":
     * - id_empleado_entrega: empleado que registra la entrega (siempre
     *   es un empleado logueado).
     * - id_empleado_recibe: empleado que recibe. Como los contratistas
     *   viven en la tabla empleado con `es_contratista = 1`, este campo
     *   cubre tanto empleados internos como contratistas receptores.
     */
    protected $fillable = [
        'id_requerimiento_almacen',
        'id_empleado_entrega',
        'id_empleado_recibe',
        'correlativo',
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias',
        'created_at',
        'estado',
    ];
}
