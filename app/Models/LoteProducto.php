<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoteProducto extends Model
{
    protected $table = 'lote_producto';

    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_unidad_medida', // unidad de medida del lote
        'id_almacen', // a que almacen le pertenece este lote
        //
        'correlativo', // LT-
        'numero_correlativo',
        //
        // Para saber de que compra provino en caso no haya venido desde el modulo de ordenes de compra
        'comprobante_compra',// opcional
        //
        'stock_actual', // segun la unidad del lote
        'contenido_por_presentacion', // cuantas unidades del producto hay en una unidad del lote: Ej. 12KG x Saco
        'stock_actual_base', // segun la unidad base del producto
        //
        'costo_por_unidad', // Cuanto costó realmente una unidad del lote en la orden de compra de donde provino
        'costo_por_unidad_base', // Cuanto costó realmente una unidad base del producto
        //
        'fecha_hora_ingreso',
        'fecha_vencimiento',
        //
        'created_at',
        'estado',
    ];
}
