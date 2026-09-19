<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoRequerimientoEntrega: string
{
    case SinConsumir = "Sin Consumir";
    case ConsumoParcial = "Consumo Parcial";
    case ConsumoTotal = "Consumo Total";
    case Anulado = "Anulado";
}
