<?php
namespace App\Services;

use App\Data\ProductosData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Enums\_Generic\TipoBien;
use App\Shared\Responses\ApiResponse;

class ProductosService
{
    /**
     * Listar productos
     */
    public static function get_productos(
        ?EstadoBase $estado = EstadoBase::Activo,
        ?TipoBien $tipo_bien_excluido = null,
        ?TipoBien $tipo_bien = null,
        ?bool $para_mantenimiento = null
    ) {
        $productos = ProductosData::get_productos(
            estado: $estado,
            tipo_bien_excluido: $tipo_bien_excluido,
            tipo_bien: $tipo_bien,
            para_mantenimiento: $para_mantenimiento,
        );

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
        Moneda $moneda = Moneda::PEN,
        ?bool $return_object = false
    ) {
        // 1. Validar nombre único
        if (ProductosData::existe_nombre($nombre)) {
            return ApiResponse::error('Ya existe un producto registrado con este nombre.');
        }

        // 2. Procesar perecibilidad
        if (!$es_perecible) {
            $tiempo_espera_vencimiento = null;
            $periodo_espera_vencimiento = null;
            $dias_espera_vencimiento = null;
        } else {
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

        // 3. Crear
        $id_producto = ProductosData::crear_producto(
            id_categoria: $id_categoria,
            id_unidad_medida_base: $id_unidad_medida_base,
            nombre: $nombre,
            prefijo: $prefijo,
            es_auditable: $es_auditable,
            es_perecible: $es_perecible,
            para_mantenimiento: $para_mantenimiento,
            stock_minimo_base: $stock_minimo_base,
            costo_promedio_base: $costo_promedio_base,
            tiempo_espera_vencimiento: $tiempo_espera_vencimiento,
            periodo_espera_vencimiento: $periodo_espera_vencimiento,
            dias_espera_vencimiento: $dias_espera_vencimiento,
            moneda: $moneda
        );

        if ($return_object) {
            $producto = ProductosData::get_productos(id_producto: $id_producto);

            return ApiResponse::success($producto, 'Producto registrado correctamente');
        }

        return ApiResponse::success($id_producto, 'Producto registrado correctamente');
    }
}