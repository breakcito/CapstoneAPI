<?php

namespace App\Modules\Productos\Service;

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
        int $id_unidad_medida_base,
        string $nombre,
        ?string $tipo_producto = null,
        bool $es_perecible = false,
        float $stock_minimo_base = 0,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null
    ) {
        $response = ProductosServiceGlobal::crear_producto(
            id_unidad_medida_base: $id_unidad_medida_base,
            nombre: $nombre,
            tipo_producto: $tipo_producto,
            es_perecible: $es_perecible,
            stock_minimo_base: $stock_minimo_base,
            tiempo_espera_vencimiento: $tiempo_espera_vencimiento,
            periodo_espera_vencimiento: $periodo_espera_vencimiento,
            return_object: false
        );

        if ($response['success'] == false) {
            return $response;
        }

        $id = (int) $response['data'];
        $producto = ProductosData::get_productos(id_producto: $id);

        return ApiResponse::success($producto, 'Producto registrado correctamente');
    }

    /**
     * Actualizar un producto existente.
     */
    public static function actualizar_producto(
        int $id_producto,
        int $id_unidad_medida_base,
        string $nombre,
        ?string $tipo_producto = null,
        bool $es_perecible = false,
        float $stock_minimo_base = 0,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null
    ) {
        $existe = ProductosData::get_productos(id_producto: $id_producto);
        if (!$existe) {
            return ApiResponse::error('El producto que intenta editar no existe.');
        }

        if (ProductosData::existe_nombre($nombre, excluir_id: $id_producto)) {
            return ApiResponse::error('Ya existe otro producto registrado con este nombre.');
        }

        $dias_espera_vencimiento = null;
        if (!$es_perecible) {
            $tiempo_espera_vencimiento = null;
            $periodo_espera_vencimiento = null;
        } else {
            if ($tiempo_espera_vencimiento && $periodo_espera_vencimiento) {
                $p = mb_strtolower($periodo_espera_vencimiento);
                $factor = match (true) {
                    str_contains($p, 'dia') => 1,
                    str_contains($p, 'semana') => 7,
                    str_contains($p, 'mes') => 30,
                    str_contains($p, 'ano') || str_contains($p, 'año') => 365,
                    default => 1,
                };
                $dias_espera_vencimiento = $tiempo_espera_vencimiento * $factor;
            }
        }

        ProductosData::actualizar_producto(
            id_producto: $id_producto,
            id_unidad_medida_base: $id_unidad_medida_base,
            nombre: $nombre,
            tipo_producto: $tipo_producto,
            es_perecible: $es_perecible,
            stock_minimo_base: $stock_minimo_base,
            tiempo_espera_vencimiento: $tiempo_espera_vencimiento,
            periodo_espera_vencimiento: $periodo_espera_vencimiento,
            dias_espera_vencimiento: $dias_espera_vencimiento,
        );

        $producto = ProductosData::get_productos(id_producto: $id_producto);

        return ApiResponse::success($producto, 'Producto actualizado correctamente');
    }

    /**
     * Desactivar un producto del catálogo (soft delete).
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
