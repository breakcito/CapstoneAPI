<?php

namespace App\Data;

use App\Models\Producto;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\TipoProducto;
use Illuminate\Support\Facades\DB;

class ProductosData
{
    /**
     * Listado de productos
     */
    public static function get_productos(
        ?int $id_producto = null,
        ?EstadoBase $estado = EstadoBase::Activo,
        ?TipoProducto $tipo_producto_excluido = null,
        ?TipoProducto $tipo_producto = null,
    ) {
        $sql = '
        SELECT
            p.id as id_producto,
            p.nombre as nombre,
            p.tipo_producto,
            p.stock_minimo_base,
            
            -- unidad base
            p.id_unidad_medida_base,
            um_base.nombre as unidad_medida_base,
            um_base.abreviatura as unidad_medida_base_abv,
            
            -- indicadores del producto
            p.es_perecible,
            p.tiempo_espera_vencimiento,
            p.periodo_espera_vencimiento,
            p.dias_espera_vencimiento,
            p.estado
        FROM producto p
        INNER JOIN unidad_medida um_base ON
            um_base.id = p.id_unidad_medida_base
        WHERE
            1 = 1
        ';

        $params = [];

        if ($estado !== null) {
            $sql .= ' AND p.estado = :estado';
            $params['estado'] = $estado->value;
        }

        if ($id_producto !== null) {
            $sql .= ' AND p.id = :id_producto';
            $params['id_producto'] = $id_producto;
            return DB::selectOne($sql, $params);
        }

        if ($tipo_producto_excluido !== null) {
            $sql .= ' AND p.tipo_producto != :tipo_producto_excluido';
            $params['tipo_producto_excluido'] = $tipo_producto_excluido->value;
        }

        if ($tipo_producto !== null) {
            $sql .= ' AND p.tipo_producto = :tipo_producto';
            $params['tipo_producto'] = $tipo_producto->value;
        }

        $sql .= ' ORDER BY p.nombre ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtiene el costo promedio del producto calculado desde sus lotes
     */
    public static function get_costo_promedio_producto(int $id_producto): float
    {
        $sql = '
        SELECT
            AVG(costo_por_unidad_base) as costo_promedio
        FROM
            lote_producto
        WHERE
            id_producto = :id_producto
            AND costo_por_unidad_base > 0
        ';

        $resultado = DB::selectOne($sql, [
            'id_producto' => $id_producto
        ]);

        return (float) ($resultado?->costo_promedio ?? 0.0);
    }

    /**
     * Obtiene información dinámica de uno o varios productos.
     * @param int|array<int> $id_producto
     * @param array<string> $columnas
     * @return array<mixed>|null
     */
    public static function get_producto_by_id(int|array $id_producto, array $columnas): ?array
    {
        $esArray = is_array($id_producto);
        $ids = $esArray ? $id_producto : [$id_producto];
        if (!in_array('id as id_producto', $columnas, true) && !in_array('id', $columnas, true)) {
            $columnas[] = 'id as id_producto';
        }
        $query = Producto::whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }
        return $query->first()?->toArray();
    }

    /**
     * Obtiene el stock total de uno o varios productos en un almacén específico.
     * @param array<int> $ids_productos
     * @return array<mixed>
     */
    public static function get_stock_total_almacen_por_productos(int $id_almacen, array $ids_productos)
    {
        if (empty($ids_productos)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids_productos), '?'));

        $sql = "
        SELECT
            lp.id_producto,
            pr.stock_minimo_base,
            SUM(lp.stock_actual_base) AS stock_total_base
        FROM
            lote_producto lp
        INNER JOIN producto pr ON pr.id = lp.id_producto
        WHERE
            lp.id_almacen = ? AND 
            lp.id_producto IN ($placeholders) AND 
            lp.stock_actual_base > 0 AND 
            lp.estado = 'Activo' AND
            (lp.fecha_vencimiento IS NULL OR DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) >= 0)
        GROUP BY
            lp.id_producto, pr.stock_minimo_base
        ";

        $params = array_merge([$id_almacen], $ids_productos);

        return DB::select($sql, $params);
    }

    /**
     * Crear un nuevo producto
     */
    public static function crear_producto(
        int $id_unidad_medida_base,
        string $nombre,
        ?string $tipo_producto = null,
        bool $es_perecible = false,
        float $stock_minimo_base = 0.0,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null,
        ?int $dias_espera_vencimiento = null
    ): int {
        return Producto::insertGetId([
            'id_unidad_medida_base' => $id_unidad_medida_base,
            'nombre' => $nombre,
            'tipo_producto' => $tipo_producto,
            'es_perecible' => $es_perecible ? 1 : 0,
            'stock_minimo_base' => $stock_minimo_base,
            'tiempo_espera_vencimiento' => $tiempo_espera_vencimiento,
            'periodo_espera_vencimiento' => $periodo_espera_vencimiento,
            'dias_espera_vencimiento' => $dias_espera_vencimiento,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar si ya existe un producto con el mismo nombre
     */
    public static function existe_nombre(string $nombre, ?int $excluir_id = null): bool
    {
        return Producto::where('nombre', $nombre)
            ->where('estado', '!=', EstadoBase::Inactivo->value)
            ->when($excluir_id !== null, fn($q) => $q->where('id', '!=', $excluir_id))
            ->exists();
    }
}
