<?php

namespace App\Modules\Cuentas\Data;

use App\Models\Usuario;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class CuentasData
{
    /**
     * Listar todas las cuentas de usuario con información de empleado y rol
     */
    public static function get_cuentas(?int $id_usuario = null)
    {
        $sql = "
            SELECT 
                u.id as id_usuario,
                u.username,
                u.estado,
                u.id_rol,
                u.id_empleado,
                r.nombre as nombre_rol,
                e.nombre as nombre_empleado,
                e.apellido as apellido_empleado,
                IFNULL(e.id_empresa, ct.id_empresa) as id_empresa_pertenece,
                e.url_foto,
                emp.razon_social as empresa_pertenece
            FROM usuario u
            INNER JOIN empleado e ON e.id = u.id_empleado
            LEFT JOIN contrato_trabajo ct ON ct.id = e.id_contrato_vigente
            LEFT JOIN empresa emp ON emp.id = IFNULL(e.id_empresa, ct.id_empresa)
            INNER JOIN rol r ON r.id = u.id_rol
            WHERE 1=1
        ";

        $params = [];
        if ($id_usuario !== null) {
            $sql .= " AND u.id = ?";
            $params[] = $id_usuario;
            $res = DB::selectOne($sql, $params);
            if ($res) {
                if ($res->url_foto && !str_starts_with($res->url_foto, 'http')) {
                    $res->url_foto = asset('storage/' . $res->url_foto);
                }
            }
            return $res;
        }

        $sql .= " ORDER BY e.apellido ASC";

        $cuentas = DB::select($sql);
        foreach ($cuentas as $c) {
            if ($c->url_foto && !str_starts_with($c->url_foto, 'http')) {
                $c->url_foto = asset('storage/' . $c->url_foto);
            }
        }

        return $cuentas;
    }

    /**
     * Insertar un nuevo usuario
     */
    public static function insert_usuario(
        int $id_rol,
        int $id_empleado,
        string $username,
        string $password_hash
    ): int {
        return Usuario::insertGetId([
            'id_rol' => $id_rol,
            'id_empleado' => $id_empleado,
            'username' => $username,
            'password' => $password_hash,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Actualizar datos del usuario
     */
    public static function update_usuario(int $id_usuario, array $data): bool
    {
        return (bool) Usuario::where('id', $id_usuario)
            ->update($data);
    }

    /**
     * Verificar si ya existe una cuenta con el mismo username
     */
    public static function ya_existe(string $username, ?int $id_usuario = null): bool
    {
        return (bool) Usuario::where('username', $username)
            ->when($id_usuario !== null, function ($query) use ($id_usuario) {
                $query->where('id', '!=', $id_usuario);
            })
            ->exists();
    }
}
