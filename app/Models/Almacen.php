<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    protected $table = 'almacen';

    public $timestamps = false;

    protected $fillable = [
        'id_departamento',
        'id_provincia',
        'id_distrito',
        'nombre',
        'direccion',
        'estado',
    ];
}
