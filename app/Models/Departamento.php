<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla de ubigeo de departamentos.
 */
class Departamento extends Model
{
    protected $table = 'departamento';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo',
    ];
}
