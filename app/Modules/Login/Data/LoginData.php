<?php

namespace App\Modules\Login\Data;

use App\Models\Usuario;
use App\Shared\Enums\_Generic\EstadoBase;

class LoginData
{
    /**
     * Obtener usuario en base a su username
     */
    public static function get_usuario_by_username(string $username)
    {
        return Usuario::where('username', $username)
            ->first(['id', 'password']);
    }

    /**
     * Obtener información del usuario
     */
    public static function getInfoUsuarioById(int $id_usuario)
    {
        return Usuario::getInfoUsuarioById($id_usuario);
    }
}
