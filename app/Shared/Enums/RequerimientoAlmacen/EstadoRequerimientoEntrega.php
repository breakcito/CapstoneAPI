<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoRequerimientoEntrega: string
{
    case Entregado = "Entregado";
    case Anulado = "Anulado";
}
