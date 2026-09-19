<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuloRol extends Model
{
    protected $table = 'modulo_rol';
    public $timestamps = false;
    protected $fillable = [
        'id_menu',
        'id_submenu',
        'id_modulo',
        'id_rol',
    ];
}
