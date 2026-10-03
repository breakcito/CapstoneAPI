<?php

namespace App\Shared\Helpers;

use App\Data\ProductosData;
use Illuminate\Support\Facades\DB;

/**
 * Helpers para resolver informacion legible de empleados / productos
 * a partir de sus ids. Pensado para usar en mensajes de log,
 * trazabilidad, kardex, etc. donde NO conviene mostrar el id crudo.
 */
class EmpleadoHelper
{
    /**
     * Devuelve el nombre completo del empleado. Si no existe,
     * retorna el id como string (fallback para que el log no
     * quede con un placeholder vacio).
     */
    public static function nombre_completo(?int $id_empleado): string
    {
        if (empty($id_empleado)) {
            return 'Sistema';
        }
        $row = DB::table('empleado')
            ->where('id', $id_empleado)
            ->selectRaw("CONCAT(nombre, ' ', apellido) AS nombre_completo")
            ->first();
        if (!$row) {
            return "Empleado #{$id_empleado}";
        }
        return $row->nombre_completo;
    }

    /**
     * Devuelve el nombre del producto. Si no existe, retorna
     * "Producto #{id}".
     */
    public static function producto_nombre(?int $id_producto): string
    {
        if (empty($id_producto)) {
            return 'Producto';
        }
        $row = ProductosData::get_producto_by_id(
            id_producto: $id_producto,
            columnas: ['nombre']
        );
        if (!$row) {
            return "Producto #{$id_producto}";
        }
        return $row['nombre'] ?? "Producto #{$id_producto}";
    }
}
