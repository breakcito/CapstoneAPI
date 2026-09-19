<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'producto';

    public $timestamps = false;

    protected $fillable = [
        'id_unidad_medida_base',
        //
        'nombre',
        'tipo_producto',
        //
        'es_perecible',
        //
        'stock_minimo_base',
        //
        'tiempo_espera_vencimiento',
        'periodo_espera_vencimiento',
        'dias_espera_vencimiento',
        //
        'estado',
    ];
}
