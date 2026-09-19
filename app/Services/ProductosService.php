<?php

namespace App\Services;

use App\Data\ProductosData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\TipoProducto;
use App\Shared\Responses\ApiResponse;

class ProductosService
{
    /**
     * Listar productos
     */
    public static function get_productos(
        ?EstadoBase $estado = EstadoBase::Activo,
        ?TipoProducto $tipo_producto_excluido = null,
        ?TipoProducto $tipo_producto = null,
    ) {
        $productos = ProductosData::get_productos(
            estado: $estado,
            tipo_producto_excluido: $tipo_producto_excluido,
            tipo_producto: $tipo_producto,
        );

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
        ?string $periodo_espera_vencimiento = null,
        ?bool $return_object = false
    ) {
        // 1. Validar nombre único
        if (ProductosData::existe_nombre($nombre)) {
            return ApiResponse::error('Ya existe un producto registrado con este nombre.');
        }

        // 2. Procesar perecibilidad
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

        // 3. Crear
        $id_producto = ProductosData::crear_producto(
            id_unidad_medida_base: $id_unidad_medida_base,
            nombre: $nombre,
            tipo_producto: $tipo_producto,
            es_perecible: $es_perecible,
            stock_minimo_base: $stock_minimo_base,
            tiempo_espera_vencimiento: $tiempo_espera_vencimiento,
            periodo_espera_vencimiento: $periodo_espera_vencimiento,
            dias_espera_vencimiento: $dias_espera_vencimiento,
        );

        if ($return_object) {
            $producto = ProductosData::get_productos(id_producto: $id_producto);

            return ApiResponse::success($producto, 'Producto registrado correctamente');
        }

        return ApiResponse::success($id_producto, 'Producto registrado correctamente');
    }
}