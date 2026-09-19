<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submenu extends Model
{
    protected $table = 'submenu';
    public $timestamps = false;
    protected $fillable = [
        'id_menu',
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
