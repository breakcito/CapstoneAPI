<?php

namespace App\Middlewares;

use Closure;
use App\Models\Usuario;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{

    public function handle(Request $request, Closure $next): Response
    {

        try {
            // Capturar el header Authorization completo
            $authHeader = $request->header('Authorization');

            // Verificar si el header existe
            if (!$authHeader) {
                return response()->json(ApiResponse::error('Token no proporcionado. Header Authorization faltante.'), 401);
            }

            // Verificar el formato del header
            if (!str_starts_with($authHeader, 'Bearer ')) {
                return response()->json(ApiResponse::error('Formato de token inválido. Debe ser: Bearer {token}'), 401);
            }

            // Extraer el token (sin el prefijo "Bearer ")
            $tokenString = substr($authHeader, 7);

            // Verificar si el token está vacío o tiene caracteres extraños
            if (empty($tokenString) || strlen($tokenString) !== strlen(trim($tokenString))) {
                return response()->json(ApiResponse::error('Token contiene espacios o está vacío'), 401);
            }

            // Intentar parsear el token
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();
            $id_usuario = $payload->get('sub');

            if (!$id_usuario) {
                return response()->json(ApiResponse::error('Token inválido: falta el identificador de usuario'), 401);
            }

            $infoUsuario = Usuario::getInfoUsuarioById($id_usuario);

            if (!$infoUsuario) {
                return response()->json(ApiResponse::error('Usuario no encontrado'), 401);
            }

            if ($infoUsuario->estado_usuario !== EstadoBase::Activo->value) {
                return response()->json(ApiResponse::error('Su cuenta de usuario no se encuentra activa'), 401);
            }

            if ($infoUsuario->estado_empleado !== EstadoBase::Activo->value) {
                return response()->json(ApiResponse::error('Su estado de empleado no se encuentra activo'), 401);
            }

            // Usar attributes para que el controlador pueda acceder
            $request->attributes->set('auth_user', $infoUsuario);

            return $next($request);
        } catch (TokenExpiredException $e) {
            return response()->json(ApiResponse::error('Token expirado'), 401);
        } catch (JWTException $e) {
            return response()->json(ApiResponse::error('Error JWT: ' . $e->getMessage()), 401);
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error de autenticación: ' . $e->getMessage()), 401);
        }
    }
}
