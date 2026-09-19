<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequerimientoAlmacenEntrega extends Model
{
    protected $table = 'requerimiento_almacen_entrega';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen',
        'correlativo', // SL
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias', // texto con formato json cuya forma es la de la clase App\Shared\Classes\Evidencia
        'created_at',
        'estado',
    ];
}
