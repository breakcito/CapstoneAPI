<?php

namespace App\Modules\Productos\Service;

use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Responses\ApiResponse;
use App\Services\ProductosService as ProductosServiceGlobal;
use App\Modules\Productos\Data\ProductosData;

class ProductosService
{
    /**
     * Listar todos los productos del catálogo
     */
    public static function get_productos()
    {
        $productos = ProductosData::get_productos();

        return ApiResponse::success($productos);
    }

    /**
     * Registrar un nuevo producto
     */
    public static function crear_producto(
        int $id_categoria,
        int $id_unidad_medida_base,
        string $nombre,
        bool $es_auditable = false,
        bool $es_perecible = false,
        bool $para_mantenimiento = false,
        float $stock_minimo_base = 0,
        float $costo_promedio_base = 0,
        ?string $prefijo = null,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null,
        Moneda $moneda = Moneda::PEN
    ) {
        $response = ProductosServiceGlobal::crear_producto(
            id_categoria: $id_categoria,
            id_unidad_medida_base: $id_unidad_medida_base,
            nombre: $nombre,
            es_auditable: $es_auditable,
            es_perecible: $es_perecible,
            para_mantenimiento: $para_mantenimiento,
            stock_minimo_base: $stock_minimo_base,
            costo_promedio_base: $costo_promedio_base,
            prefijo: $prefijo,
            tiempo_espera_vencimiento: $tiempo_espera_vencimiento,
            periodo_espera_vencimiento: $periodo_espera_vencimiento,
            moneda: $moneda,
            return_object: false
        );

        if ($response['success'] == false) {
            return $response;
        }

        $id = $response['data'];
        $producto = ProductosData::get_productos(id_producto: $id);

        return ApiResponse::success($producto, 'Producto registrado correctamente');
    }

    /**
     * Actualizar un producto existente.
     * El costo_promedio_base se persiste tal cual lo envía el cliente para no romper
     * la trazabilidad: la fuente de verdad histórica es el Kardex y la Lógica de Compras.
     */
    public static function actualizar_producto(
        int $id_producto,
        int $id_categoria,
        int $id_unidad_medida_base,
        string $nombre,
        bool $es_auditable = false,
        bool $es_perecible = false,
        bool $para_mantenimiento = false,
        float $stock_minimo_base = 0,
        float $costo_promedio_base = 0,
        ?string $prefijo = null,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null,
        Moneda $moneda = Moneda::PEN,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ) {
        // 1. Validar que el producto exista
        $existe = ProductosData::get_productos(id_producto: $id_producto);
        if (!$existe) {
            return ApiResponse::error('El producto que intenta editar no existe.');
        }

        // 2. Validar nombre único (excluyendo el propio producto)
        if (ProductosData::existe_nombre($nombre, excluir_id: $id_producto)) {
            return ApiResponse::error('Ya existe otro producto registrado con este nombre.');
        }

        // 3. Procesar perecibilidad (mismo criterio que en el registro)
        if (!$es_perecible) {
            $tiempo_espera_vencimiento = null;
            $periodo_espera_vencimiento = null;
            $dias_espera_vencimiento = null;
        } else {
            $dias_espera_vencimiento = null;
            if ($tiempo_espera_vencimiento && $periodo_espera_vencimiento) {
                $factor = match ($periodo_espera_vencimiento) {
                    'diario' => 1,
                    'semanal' => 7,
                    'mensual' => 30,
                    'anual' => 365,
                    default => 0,
                };
                $dias_espera_vencimiento = $tiempo_espera_vencimiento * $factor;
            }
        }

        // 4. Persistir cambios
        ProductosData::actualizar_producto(
            id_producto: $id_producto,
            id_categoria: $id_categoria,
            id_unidad_medida_base: $id_unidad_medida_base,
            nombre: $nombre,
            es_auditable: $es_auditable,
            es_perecible: $es_perecible,
            para_mantenimiento: $para_mantenimiento,
            stock_minimo_base: $stock_minimo_base,
            costo_promedio_base: $costo_promedio_base,
            prefijo: $prefijo,
            tiempo_espera_vencimiento: $tiempo_espera_vencimiento,
            periodo_espera_vencimiento: $periodo_espera_vencimiento,
            dias_espera_vencimiento: $dias_espera_vencimiento,
            moneda: $moneda,
            id_empleado: $id_empleado,
            nombre_empleado: $nombre_empleado,
        );

        // 5. Devolver el producto ya refrescado (mismo shape que listar)
        $producto = ProductosData::get_productos(id_producto: $id_producto);

        return ApiResponse::success($producto, 'Producto actualizado correctamente');
    }

    /**
     * Desactivar un producto del catálogo (soft delete).
     * Devuelve el producto con su estado final Inactivo para que el front pueda
     * removerlo de la lista visible con la misma forma de objeto que listar.
     */
    public static function eliminar_producto(int $id_producto)
    {
        $existe = ProductosData::get_productos(id_producto: $id_producto);
        if (!$existe) {
            return ApiResponse::error('El producto que intenta eliminar no existe.');
        }

        ProductosData::eliminar_producto(id_producto: $id_producto);

        $producto = ProductosData::get_productos(id_producto: $id_producto);

        return ApiResponse::success($producto, 'Producto eliminado correctamente');
    }
}
