<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $table = 'menu';

    public $timestamps = false;

    protected $fillable = [
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
