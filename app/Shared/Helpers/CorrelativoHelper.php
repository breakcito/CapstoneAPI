<?php

namespace App\Shared\Helpers;

use App\Shared\Enums\_Generic\Periodo;
use Illuminate\Support\Facades\DB;
use Closure;

class CorrelativoHelper
{
    public static function generar(
        string $tabla,
        string $prefijo,
        array $filtros = [],
        int $longitudCeros = 5,
        Periodo $reseteo = Periodo::Anual,
        string $columnaFecha = 'created_at',
        ?Closure $queryModifier = null,
        ?string $alias = null,
        ?string $columnaNumeroCorrelativo = null
    ): array {
        // Configuramos la tabla principal con su alias en el constructor de la consulta
        $tablaQuery = $alias ? "{$tabla} as {$alias}" : $tabla;
        $query = DB::table($tablaQuery);

        // Definimos qué prefijo usar para las columnas
        $prefijoTabla = $alias ?? $tabla;

        // Columna a usar para el cálculo del siguiente correlativo. Por defecto
        // es "numero_correlativo" (compatibilidad hacia atrás); permite usar
        // "numero_correlativo_auditoria" u otra.
        $columnaNumeroCompleta = str_contains($columnaNumeroCorrelativo ?? '', '.')
            ? $columnaNumeroCorrelativo
            : "{$prefijoTabla}." . ($columnaNumeroCorrelativo ?? 'numero_correlativo');

        // 1. Aplicamos los JOINs si el closure fue proporcionado
        if ($queryModifier) {
            $queryModifier($query);
        }

        $now = now();

        $columnaFechaCompleta = str_contains($columnaFecha, '.') ? $columnaFecha : "{$prefijoTabla}.{$columnaFecha}";

        // 2. Filtro por periodo
        match ($reseteo) {
            Periodo::Diario => $query->whereDate($columnaFechaCompleta, $now->toDateString()),
            Periodo::Semanal => $query->whereBetween($columnaFechaCompleta, [
                $now->startOfWeek()->startOfDay(),
                $now->endOfWeek()->endOfDay(),
            ]),
            Periodo::Mensual => $query
                ->whereYear($columnaFechaCompleta, $now->year)
                ->whereMonth($columnaFechaCompleta, $now->month),
            Periodo::Anual => $query->whereYear($columnaFechaCompleta, $now->year),
            Periodo::Ninguno => null,
        };

        // 3. Otros filtros
        foreach ($filtros as $col => $val) {
            $columnaFiltro = str_contains($col, '.') ? $col : "{$prefijoTabla}.{$col}";
            $query->where($columnaFiltro, $val);
        }

        // 4. Obtenemos el máximo usando el prefijo correcto
        $siguienteNumero = ($query->max($columnaNumeroCompleta) ?? 0) + 1;

        $numeroFormateado = str_pad($siguienteNumero, $longitudCeros, '0', STR_PAD_LEFT);

        $segmentoFecha = match ($reseteo) {
            Periodo::Diario => $now->format('d') . '-' . $now->format('m') . '-' . $now->format('y'),
            Periodo::Semanal => $now->weekOfMonth . '-' . $now->format('m') . '-' . $now->format('y'),
            Periodo::Mensual => $now->format('m') . '-' . $now->format('y'),
            Periodo::Anual => $now->format('y'),
            Periodo::Ninguno => null,
        };

        $correlativo = $segmentoFecha
            ? "{$prefijo}-{$segmentoFecha}-{$numeroFormateado}"
            : "{$prefijo}-{$numeroFormateado}";

        return [
            'correlativo' => $correlativo,
            'numero_correlativo' => $siguienteNumero,
        ];
    }
}