<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla de ubigeo de provincias.
 */
class Provincia extends Model
{
    protected $table = 'provincia';

    public $timestamps = false;

    protected $fillable = [
        'id_departamento',
        'nombre',
        'codigo',
    ];
}
