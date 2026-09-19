<?php

namespace App\Modules\Login;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\Hash;
use App\Shared\Responses\ApiResponse;
use App\Modules\Login\Data\LoginData;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class LoginService
{
    // Autenticar usuario y generar token JWT.
    public static function login(string $username, string $password): array
    {
        // Buscamos al usuario
        $user = LoginData::get_usuario_by_username($username);

        if (!$user) {
            return ApiResponse::error('Usuario incorreto');
        }

        // Comparamos las contraseñas
        if (!Hash::check($password, $user->password)) {
            return ApiResponse::error('Contraseña incorrecta');
        }

        // Si todo salio bien, obtenemos su informacion
        $infoUsuario = LoginData::getInfoUsuarioById($user->id);
        if (!$infoUsuario) {
            return ApiResponse::error('Error al obtener información del usuario');
        }

        if ($infoUsuario->estado_usuario !== EstadoBase::Activo->value) {
            return ApiResponse::error('Su cuenta de usuario no se encuentra activa');
        }

        if ($infoUsuario->estado_empleado !== EstadoBase::Activo->value) {
            return ApiResponse::error('Su estado de empleado no se encuentra activo');
        }

        $token = JWTAuth::fromUser($user, [
            'id_usuario' => $infoUsuario->id_usuario,
            'id_rol' => $infoUsuario->id_rol,
            'id_empleado' => $infoUsuario->id_empleado,
        ]);

        return ApiResponse::success([
            'token' => $token,
            'usuario' => $infoUsuario,
        ]);
    }
}
