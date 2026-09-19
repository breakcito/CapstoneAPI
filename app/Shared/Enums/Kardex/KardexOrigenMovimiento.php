<?php

namespace App\Shared\Enums\Kardex;

enum KardexOrigenMovimiento: string
{
    /**
     * -------------------------------------
     * Ingresos
     * -------------------------------------
     */

    /**
     * Cuando se registra un lote manualmente
     */
    case NuevoLote = 'Nuevo Lote';

    /**
     * Cuando se recepciona mas stock por
     * - una solicitud de reabastecimiento (el almacen pequeño recepciona del almacen principal)
     * - la recepcion de una orden de compra (el proveedor entrega al almacen - principal o pequeño)
     */
    case Recepcion = 'Recepcion';

    /**
     * -------------------------------------
     * Salidas
     * -------------------------------------
     */

    /**
     * Cuando se realiza una entrega por:
     * - un requerimiento de almacen (el almacen pequeño entrega al minero solicitante)
     * - una solicitud de reabastecimiento (el almacen principal entrega al almacen pequeño)
     * - un prestamo de almacen (el almacen principal entrega al almacen pequeño)
     */
    case Entrega = 'Entrega';

    /**
     * Cuando se realiza una reposicion, tipicamente luego de que logistica
     * reponga stock a un almacen que previamente le presto a otro que lo necesitaba
     */
    case Reposicion = 'Reposición';

    /**
     * -------------------------------------
     * Mixtos
     * -------------------------------------
     */

    /**
     * Cuando se realiza un ajuste de stock, ya sea manual o automatico
     */
    case AjusteStock = 'Ajuste de Stock';

    /**
     * Cuando se realiza un movimiento interno usado para salidas/ingresos de activos fijos
     */
    case MovimientoInterno = 'Movimiento Interno';

    /**
     * Cuando se tuvo que retornar el stock de previamente salio de almacen
     */
    case Reingreso = 'Reingreso';

    /**
     *  Cuando se registra el consumo de un producto de forma directa
     */
    case Consumo = 'Consumo';
}
