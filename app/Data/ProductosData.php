<?php

namespace App\Data;

use App\Models\Producto;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Enums\_Generic\TipoBien;
use Illuminate\Support\Facades\DB;

class ProductosData
{

    /**
     * Listado de productos
     */
    public static function get_productos(
        ?int $id_producto = null,
        ?EstadoBase $estado = EstadoBase::Activo,
        ?TipoBien $tipo_bien_excluido = null,
        ?TipoBien $tipo_bien = null,
        ?bool $para_mantenimiento = null,
    ) {
        $sql = '
        SELECT
            p.id as id_producto,
            p.nombre as nombre,
            p.prefijo,
            -- categoria
            p.id_categoria,
            c.nombre AS categoria,
            c.es_consumible,
            c.clasificacion_bien as tipo_bien,
            c.para_transporte,
            
            p.stock_minimo_base, -- cuanto deberia tener como minimo
            
            -- unidad base
            p.id_unidad_medida_base,
            um_base.nombre as unidad_medida_base,
            um_base.abreviatura as unidad_medida_base_abv,
            
            -- indicadores del producto
            p.es_perecible,
            p.es_auditable,
            p.para_mantenimiento,
            
            -- costos
            p.moneda,
            p.costo_promedio_base,

            -- cuantos dias antes debemos alertar el vencimiento de productos
            p.dias_espera_vencimiento
        FROM producto p
        INNER JOIN categoria c ON
            c.id = p.id_categoria
        INNER JOIN unidad_medida um_base ON
            um_base.id = p.id_unidad_medida_base
        WHERE
            p.estado = :estado
        ';

        $params = [];

        $params['estado'] = $estado->value;

        if ($id_producto != null) {
            $sql .= ' AND p.id = :id_producto';
            $params['id_producto'] = $id_producto;
            return DB::selectOne($sql, $params);
        }

        if ($tipo_bien_excluido != null) {
            $sql .= ' AND c.clasificacion_bien != :tipo_bien_excluido';
            $params['tipo_bien_excluido'] = $tipo_bien_excluido->value;
        }

        if ($tipo_bien != null) {
            $sql .= ' AND c.clasificacion_bien = :tipo_bien';
            $params['tipo_bien'] = $tipo_bien->value;
        }

        if ($para_mantenimiento != null) {
            $sql .= ' AND p.para_mantenimiento = :para_mantenimiento';
            $params['para_mantenimiento'] = $para_mantenimiento ? 1 : 0;
        }

        $sql .= ' ORDER BY p.nombre ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtiene el costo promedio del producto
     */
    public static function get_costo_promedio_producto(int $id_producto): float
    {
        $sql = '
        SELECT
            pr.costo_promedio_base
        FROM
            producto pr
        WHERE pr.id = :id_producto
        ';

        $resultado = DB::selectOne($sql, [
            'id_producto' => $id_producto
        ]);

        return (float) ($resultado?->costo_promedio_base ?? 0.0);
    }

    /**
     * Actualiza el costo promedio por unidad base de un producto al registrar una nueva compra.
     *
     * Fórmula:
     *   nuevo_promedio = (costo_actual + suma(nuevos_costos)) / (1 + cantidad_nuevos)
     *
     * @param int   $id_producto       ID del producto a actualizar.
     * @param array $nuevos_costos_base Lista de precios por unidad base de la nueva compra.
     *                                  Ej: [3.2, 3.2] si se compraron 2 lotes del mismo producto.
     */
    public static function actualizar_costo_promedio(int $id_producto, array $nuevos_costos_base): void
    {
        if (empty($nuevos_costos_base)) {
            return;
        }

        // 1. Obtener el producto actual con su log
        $producto = Producto::find($id_producto);
        if (!$producto) {
            return;
        }

        $costo_actual = (float) $producto->costo_promedio_base;

        // 2. Calcular nuevo promedio: (actual + nuevo1 + nuevo2 + ...) / (1 + N)
        $suma_nuevos = array_sum($nuevos_costos_base);
        $divisor = 1 + count($nuevos_costos_base);
        $nuevo_promedio = round(($costo_actual + $suma_nuevos) / $divisor, 4);

        // 3. Si hay variación, registrar en el log
        $data_update = [
            'costo_promedio_base' => $nuevo_promedio
        ];

        if ($costo_actual !== (float) $nuevo_promedio) {
            $log_actual = $producto->costo_promedio_base_log ?? [];
            $nuevo_registro = [
                'costo_promedio_anterior' => $costo_actual,
                'costo_promedio_resultante' => $nuevo_promedio,
                'created_at' => now()->toDateTimeString(),
            ];

            $log_actual[] = $nuevo_registro;
            $data_update['costo_promedio_base_log'] = $log_actual;
        }

        $producto->update($data_update);
    }

    /**
     * Obtiene información dinámica de uno o varios productos.
     * Permite especificar las columnas exactas a consultar mediante un array.
     * @param array $columnas Array de strings con los nombres de las columnas a recuperar.
     * @return array|null Retorna un array con los resultados o null si no se encuentra el registro.
     */
    public static function get_producto_by_id(int|array $id_producto, array $columnas): ?array
    {
        $esArray = is_array($id_producto);
        $ids = $esArray ? $id_producto : [$id_producto];
        // Forzamos la inclusión del ID con su alias
        if (!in_array('id as id_producto', $columnas)) {
            $columnas[] = 'id as id_producto';
        }
        $query = Producto::whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }
        return $query->first()?->toArray();
    }


    /**
     * Obtiene el stock total de uno o varios productos en un almacén específico..
     */
    public static function get_stock_total_almacen_por_productos(int $id_almacen, array $ids_productos)
    {
        // Validación de seguridad para evitar errores SQL si el array viene vacío
        if (empty($ids_productos)) {
            return [];
        }

        // 1. Creamos los placeholders (?,?,?)
        $placeholders = implode(',', array_fill(0, count($ids_productos), '?'));

        $sql = "
        SELECT
            u.id_producto,
            u.stock_minimo_base,
            SUM(u.stock_total_base) AS stock_total_base
        FROM (
            SELECT
                lp.id_producto,
                pr.stock_minimo_base,
                SUM(lp.stock_actual_base) AS stock_total_base
            FROM
                lote_producto lp
            INNER JOIN producto pr on pr.id = lp.id_producto
            WHERE
                lp.id_almacen = ? AND 
                lp.id_producto IN ($placeholders) AND 
                lp.stock_actual_base > 0 AND 
                lp.estado = 'Activo' AND
                -- no sumar stock de lotes vencidos
                (lp.fecha_vencimiento IS NULL OR DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) >= 0)
            GROUP BY
                lp.id_producto, pr.stock_minimo_base

            UNION ALL

            SELECT
                act.id_producto,
                pr.stock_minimo_base,
                CAST(COUNT(act.id) AS DECIMAL(15,4)) AS stock_total_base
            FROM
                activo_fijo act
            INNER JOIN producto pr on pr.id = act.id_producto
            WHERE
                act.id_almacen = ? AND
                act.id_producto IN ($placeholders) AND
                act.estado = 'En Almacén'
            GROUP BY
                act.id_producto, pr.stock_minimo_base
        ) u
        GROUP BY
            u.id_producto,
            u.stock_minimo_base
        ";

        $params = array_merge([$id_almacen], $ids_productos, [$id_almacen], $ids_productos);

        return DB::select($sql, $params);
    }



    /**
     * Crear un nuevo producto con parámetros explícitos
     */
    public static function crear_producto(
        int $id_categoria,
        int $id_unidad_medida_base,
        string $nombre,
        bool $es_auditable,
        bool $es_perecible,
        float $stock_minimo_base,
        float $costo_promedio_base,
        bool $para_mantenimiento = false,
        ?string $prefijo = null,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null,
        ?int $dias_espera_vencimiento = null,
        Moneda $moneda = Moneda::PEN
    ) {
        return Producto::insertGetId([
            'id_categoria' => $id_categoria,
            'id_unidad_medida_base' => $id_unidad_medida_base,
            'nombre' => $nombre,
            'prefijo' => $prefijo,
            'es_auditable' => $es_auditable,
            'es_perecible' => $es_perecible,
            'para_mantenimiento' => $para_mantenimiento,
            'stock_minimo_base' => $stock_minimo_base,
            'moneda' => $moneda->value,
            'costo_promedio_base' => $costo_promedio_base,
            'costo_promedio_base_log' => null,
            'tiempo_espera_vencimiento' => $tiempo_espera_vencimiento,
            'periodo_espera_vencimiento' => $periodo_espera_vencimiento,
            'dias_espera_vencimiento' => $dias_espera_vencimiento,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar si ya existe un producto con el mismo nombre
     */
    public static function existe_nombre(string $nombre): bool
    {
        return Producto::where('nombre', $nombre)
            ->where('estado', '!=', EstadoBase::Inactivo->value)
            ->exists();
    }
}
