<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoRequerimiento: string
{
    case Generado = "Generado";
    case EnDespacho = "En Despacho";
    case Anulado = "Anulado";
    case Cerrado = "Cerrado";
    case Completado = "Completado";
}
