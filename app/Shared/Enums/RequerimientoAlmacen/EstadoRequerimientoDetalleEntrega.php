<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoRequerimientoDetalleEntrega: string
{
    case SinConsumir = "Sin Consumir";
    case ConsumoParcial = "Consumo Parcial";
    case ConsumoTotal = "Consumo Total";
}
