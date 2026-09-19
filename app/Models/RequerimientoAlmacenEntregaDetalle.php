<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property float $cantidad_base
 */
class RequerimientoAlmacenEntregaDetalle extends Model
{
    protected $table = 'requerimiento_almacen_entrega_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen_entrega',
        'id_requerimiento_almacen_detalle',
        'id_lote_producto', // si se entrego un lote: completo o parcial
        'para_produccion', // bool 
        //
        'cantidad_base',
        'cantidad_lote',
        'cantidad_requerimiento',
        //
        'costo',
    ];
}
