<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoRequerimientoDetalleLog: string
{
    case EsperandoAprobacion = "Esperando Aprobación";
    case Rechazado = "Rechazado";
    case Aprobado = "Aprobado";
    case ConsultaLogistica = "Consultando a Logística";
    case RechazadoLogistica = "Rechazado por Logística";
    case AprobadoLogistica = "Aprobado por Logística";
    case EnDespacho = "En Despacho";
    case Cerrado = "Cerrado";
    case Completado = "Completado";
    case NuevaEntrega = "Nueva Entrega";

    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::EsperandoAprobacion => "Esperando Aprobación",
            self::Rechazado => "Rechazado",
            self::Aprobado => "Aprobado",
            self::ConsultaLogistica => "Consultando a Logística",
            self::RechazadoLogistica => "Rechazado por Logística",
            self::AprobadoLogistica => "Aprobado por Logística",
            self::EnDespacho => "En Despacho",
            self::Cerrado => "Cerrado",
            self::Completado => "Completado",
            self::NuevaEntrega => $dinamico ? "Se han entregado $dinamico productos" : "Nueva Entrega",
        };
    }
}
