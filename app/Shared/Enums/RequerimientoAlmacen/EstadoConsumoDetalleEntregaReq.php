<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

// El estado de consumo de un detalle de entrega de un requerimiento de almacen
enum EstadoConsumoDetalleEntregaReq: string
{
    case ConsumoParcial = "Consumo Parcial"; // cuando se consumio una parte de lo entregado
    case ConsumoTotal = "Consumo Total"; // cuando se ha consumido todo lo entregado
}
