<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'modulo';
    public $timestamps = false;
    protected $fillable = [
        'id_submenu',
        'nombre',
        'path',
        'numero_orden',
        'es_desplegable',
        'estado',
    ];

    protected $casts = [
        'es_desplegable' => 'boolean',
        'numero_orden'   => 'integer',
    ];
}
