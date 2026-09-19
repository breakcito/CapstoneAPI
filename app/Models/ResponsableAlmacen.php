<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResponsableAlmacen extends Model
{
    protected $table = 'responsable_almacen';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen',
        'id_empleado',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];
}
