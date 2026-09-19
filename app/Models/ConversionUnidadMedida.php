<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// esta tabla registrara todas las conversiones entre 
// unidades de medida universales, por ejemplo: 1 pulgada = 2.54 centimetros
class ConversionUnidadMedida extends Model
{
    protected $table = 'conversion_unidad_medida';

    public $timestamps = false;

    protected $fillable = [
        'id_unidad_medida_a', // Centimetro
        'id_unidad_medida_b', // Metro
        'factor_conversion', // En una unidad B (metro) hay N (100) unidades A (centimetros)
    ];
}
