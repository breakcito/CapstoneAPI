<?php

namespace App\Modules\Roles\Data;

use App\Models\Rol;
use App\Shared\Enums\_Generic\EstadoBase;

class RolesData
{
    /**
     * Listar roles activos
     */
    public static function get_roles()
    {
        return Rol::where('estado', EstadoBase::Activo->value)
            ->orderBy('nombre', 'ASC')
            ->get();
    }

    /**
     * Obtener un rol por su ID
     */
    public static function get_rol_by_id(int $id)
    {
        return Rol::find($id);
    }

    /**
     * Guardar un nuevo rol en la base de datos
     */
    public static function crear_rol(array $data): int
    {
        $rol = Rol::create($data);
        return $rol->id;
    }
}
