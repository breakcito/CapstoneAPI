<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $table = 'empleado';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'apellido',
        'dni',
        'url_foto',
        'es_contratista',
        'estado',
    ];
}
