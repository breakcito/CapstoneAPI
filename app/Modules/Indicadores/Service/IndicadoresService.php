<?php

namespace App\Modules\Indicadores\Service;

use App\Modules\Indicadores\Data\IndicadoresData;
use App\Shared\Responses\ApiResponse;

class IndicadoresService
{
    public static function get_generales(?int $idAlmacen = null)
    {
        $rows = IndicadoresData::get_kpis_principales($idAlmacen);
        $kpis = $rows[0] ?? [];
        return ApiResponse::success($kpis);
    }

    public static function get_stock_valorizado(?int $idAlmacen = null)
    {
        return ApiResponse::success(
            IndicadoresData::get_stock_valorizado($idAlmacen),
        );
    }

    public static function get_top_productos(int $dias = 30, ?int $idAlmacen = null)
    {
        $dias = max(1, min(365, $dias));
        return ApiResponse::success(
            IndicadoresData::get_top_productos($dias, $idAlmacen),
        );
    }

    public static function get_rotacion_mensual(int $meses = 12, ?int $idAlmacen = null)
    {
        $meses = max(1, min(36, $meses));
        return ApiResponse::success(
            IndicadoresData::get_rotacion_mensual($meses, $idAlmacen),
        );
    }

    public static function get_alertas_stock(?int $idAlmacen = null)
    {
        return ApiResponse::success(
            IndicadoresData::get_alertas_stock($idAlmacen),
        );
    }

    public static function get_tap_por_almacen(?int $idAlmacen = null)
    {
        return ApiResponse::success(
            IndicadoresData::get_tap_por_almacen($idAlmacen),
        );
    }

    public static function get_tendencias_mensuales(?int $idAlmacen = null)
    {
        return ApiResponse::success(
            IndicadoresData::get_tendencias_mensuales($idAlmacen),
        );
    }
}