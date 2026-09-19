<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla de ubigeo de distritos.
 */
class Distrito extends Model
{
    protected $table = 'distrito';

    public $timestamps = false;

    protected $fillable = [
        'id_provincia',
        'id_departamento',
        'nombre',
        'codigo',
    ];
}
