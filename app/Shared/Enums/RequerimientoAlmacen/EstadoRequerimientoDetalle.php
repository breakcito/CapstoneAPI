<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoRequerimientoDetalle: string
{
    case Pendiente = "Pendiente";
    case Rechazado = "Rechazado";
    case Aprobado = "Aprobado";
    case EnDespacho = "En Despacho";
    case Cerrado = "Cerrado";
    case Completado = "Completado";

    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::Pendiente => "Pendiente",
            self::Rechazado => "Rechazado",
            self::Aprobado => "Aprobado",
            self::EnDespacho => "En Despacho",
            self::Cerrado => "Cerrado",
            self::Completado => "Completado",
        };
    }
}
