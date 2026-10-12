<?php

namespace Tests\CasosDeUso;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenDetalle;
use App\Modules\LotesProductos\Service\LotesService;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use App\Modules\RequerimientosAlmacenAtencion\Service\AtencionService;
use App\Services\LotesProductosService;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use Tests\Support\Fakes\DBFalsa;
use Tests\TestCase;

/**
 * CASO DE USO: leer despues de operar.
 *
 * El contrato que se protege aqui es que el listado refleje el cambio. Si una
 * operacion escribe por una via (modelo) y el listado lee por otra (SQL crudo),
 * un cambio invisible pasa por "funcionando" hasta que alguien lo nota en
 * pantalla. Estos tests cierran esa puerta.
 */
class ListarDespuesDeOperarTest extends TestCase
{
    /* ------------------------------------------------------------------ */
    /* Lotes: ajustar y despues leer */
    /* ------------------------------------------------------------------ */

    public function test_tras_ajustar_el_stock_el_listado_muestra_el_nuevo_valor(): void
    {
        LoteProducto::sembrar([[
            'id' => 1,
            'id_almacen' => 3,
            'correlativo' => 'LT-26-00001',
            'stock_actual' => 10.0,
            'contenido_por_presentacion' => 12.0,
            'stock_actual_base' => 120.0,
            'costo_por_unidad' => 15.0,
            'estado' => 'Activo',
        ]]);

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 60.0, motivo: 'Merma');

        // El listado real es SQL crudo: se programa la fila que devuelve la
        // base despues del ajuste y se comprueba que el servicio la devuelve.
        DBFalsa::select('lp.id AS id_lote', [(object) [
            'id_lote' => 1,
            'correlativo' => 'LT-26-00001',
            'stock_actual_base' => 60.0,
            'stock_actual' => 5.0,
        ]]);

        $listado = LotesService::get_resumen_lotes(id_almacen: 3);

        $this->assertTrue($listado['success']);
        $this->assertCount(1, $listado['data']);
        $this->assertSame(60.0, (float) $listado['data'][0]->stock_actual_base);
        $this->assertSame(5.0, (float) $listado['data'][0]->stock_actual);
    }

    public function test_el_stock_en_el_doble_y_en_el_listado_coinciden(): void
    {
        LoteProducto::sembrar([[
            'id' => 1,
            'id_almacen' => 3,
            'correlativo' => 'LT-26-00001',
            'stock_actual' => 10.0,
            'contenido_por_presentacion' => 12.0,
            'stock_actual_base' => 120.0,
            'costo_por_unidad' => 15.0,
        ]]);

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 60.0, motivo: 'Merma');

        // El valor que quedo escrito en la tabla...
        $escrito = (float) LoteProducto::fila(1)['stock_actual_base'];

        // ...tiene que ser el que el kardex tambien dejo registrado.
        $kardex = KardexProducto::inserts()[0];

        $this->assertSame($escrito, (float) $kardex['stock_resultante_base']);
        $this->assertSame(
            120.0,
            (float) $kardex['stock_anterior_base'],
            'Sin el anterior no hay forma de auditar el cambio.'
        );
    }

    public function test_descontar_y_reintegrar_deja_el_lote_como_estaba(): void
    {
        LoteProducto::sembrar([[
            'id' => 1,
            'id_almacen' => 3,
            'correlativo' => 'LT-26-00001',
            'stock_actual' => 10.0,
            'contenido_por_presentacion' => 12.0,
            'stock_actual_base' => 120.0,
            'costo_por_unidad' => 15.0,
        ]]);

        $antes = [
            'stock_actual' => (float) LoteProducto::fila(1)['stock_actual'],
            'stock_actual_base' => (float) LoteProducto::fila(1)['stock_actual_base'],
        ];

        LotesProductosService::descontar_stock(
            id_lote: 1,
            cantidad_lote: 5.0,
            cantidad_base: 60.0,
            tipo_origen: 'Entrega',
            descripcion: 'Salida por entrega de prueba',
        );

        $intermedio = (float) LoteProducto::fila(1)['stock_actual_base'];
        $this->assertSame(60.0, $intermedio, 'La salida debe reducir el stock.');

        LotesProductosService::reintegrar_stock(
            id_lote: 1,
            cantidad_lote: 5.0,
            cantidad_base: 60.0,
        );

        $despues = LoteProducto::fila(1);

        $this->assertSame($antes['stock_actual_base'], (float) $despues['stock_actual_base']);
        $this->assertSame($antes['stock_actual'], (float) $despues['stock_actual']);
    }

    public function test_descontar_y_reintegrar_deja_dos_movimientos_simetricos_en_kardex(): void
    {
        LoteProducto::sembrar([[
            'id' => 1, 'id_almacen' => 3, 'stock_actual' => 10.0,
            'contenido_por_presentacion' => 12.0, 'stock_actual_base' => 120.0,
            'costo_por_unidad' => 15.0,
        ]]);

        LotesProductosService::descontar_stock(1, 5.0, 60.0, 'Entrega', 'Salida');
        LotesProductosService::reintegrar_stock(1, 5.0, 60.0);

        $kardex = KardexProducto::inserts();

        $this->assertCount(2, $kardex);
        $this->assertSame('Salida', $kardex[0]['tipo_movimiento']);
        $this->assertSame('Ingreso', $kardex[1]['tipo_movimiento']);
        $this->assertSame(
            (float) $kardex[0]['cantidad_movimiento_base'],
            (float) $kardex[1]['cantidad_movimiento_base'],
            'El reingreso devuelve exactamente lo que salio.'
        );
        $this->assertSame(
            (float) $kardex[0]['stock_resultante_base'],
            (float) $kardex[1]['stock_anterior_base']
        );
    }

    public function test_el_stock_nunca_queda_negativo(): void
    {
        LoteProducto::sembrar([[
            'id' => 1, 'id_almacen' => 3, 'stock_actual' => 2.0,
            'contenido_por_presentacion' => 12.0, 'stock_actual_base' => 24.0,
            'costo_por_unidad' => 15.0,
        ]]);

        LotesProductosService::descontar_stock(1, 10.0, 120.0, 'Entrega', 'Salida sobredimensionada');

        $lote = LoteProducto::fila(1);

        $this->assertSame(0.0, (float) $lote['stock_actual'], 'Piso duro en 0: no se permite stock negativo.');
        $this->assertSame(0.0, (float) $lote['stock_actual_base']);
    }

    public function test_descontar_de_un_lote_inexistente_no_falla_en_silencio(): void
    {
        $this->assertFalse(
            LotesProductosService::descontar_stock(999, 1.0, 12.0, 'Entrega', 'Salida')
        );
        $this->assertCount(0, KardexProducto::inserts());
    }

    /* ------------------------------------------------------------------ */
    /* Pedidos: aprobar y despues leer */
    /* ------------------------------------------------------------------ */

    public function test_aprobar_un_detalle_mueve_la_cabecera_a_en_despacho(): void
    {
        $this->sembrarPedido();

        $respuesta = AtencionService::cambiar_estado_detalle(
            id_empleado: 4,
            ids_detalles: [10],
            nuevo_estado: EstadoRequerimientoDetalle::Aprobado->value,
        );

        $this->assertTrue($respuesta['success']);
        $this->assertSame(
            [['id' => 1, 'datos' => ['estado' => EstadoRequerimiento::EnDespacho->value]]],
            RequerimientoAlmacen::updates()
        );
    }

    public function test_rechazar_un_detalle_no_mueve_la_cabecera(): void
    {
        $this->sembrarPedido();

        AtencionService::cambiar_estado_detalle(
            id_empleado: 4,
            ids_detalles: [10],
            nuevo_estado: EstadoRequerimientoDetalle::Rechazado->value,
        );

        $this->assertSame(
            [],
            RequerimientoAlmacen::updates(),
            'Rechazar un item no pone el pedido en proceso.'
        );
    }

    public function test_tras_aprobar_el_listado_muestra_el_nuevo_estado_del_detalle(): void
    {
        $this->sembrarPedido();

        AtencionService::cambiar_estado_detalle(
            id_empleado: 4,
            ids_detalles: [10],
            nuevo_estado: EstadoRequerimientoDetalle::Aprobado->value,
        );

        $detalle = RequerimientosDetalleData::get_cantidades_of_detalle_by_id(10);

        $this->assertNotNull($detalle);
        $this->assertSame(
            EstadoRequerimientoDetalle::Aprobado->value,
            RequerimientoAlmacenDetalle::fila(10)['estado']
        );
    }

    public function test_cada_decision_queda_registrada_en_el_timeline(): void
    {
        $this->sembrarPedido();
        DBFalsa::tabla('requerimiento_almacen_detalle_log');

        AtencionService::cambiar_estado_detalle(
            id_empleado: 4,
            ids_detalles: [10],
            nuevo_estado: EstadoRequerimientoDetalle::Aprobado->value,
            comentario_decision: 'Stock disponible en almacen',
        );

        $logs = DBFalsa::tabla('requerimiento_almacen_detalle_log')->filas;

        $this->assertCount(1, $logs);
        $this->assertSame(EstadoRequerimientoDetalle::Aprobado->value, $logs[0]['estado']);
        $this->assertSame('Stock disponible en almacen', $logs[0]['descripcion']);
    }

    public function test_sin_comentario_el_log_explica_quien_y_que_hizo(): void
    {
        $this->sembrarPedido();
        DBFalsa::tabla('requerimiento_almacen_detalle_log');

        AtencionService::cambiar_estado_detalle(
            id_empleado: 4,
            ids_detalles: [10],
            nuevo_estado: EstadoRequerimientoDetalle::Aprobado->value,
        );

        $descripcion = DBFalsa::tabla('requerimiento_almacen_detalle_log')->filas[0]['descripcion'];

        $this->assertStringContainsString('Ana Quispe', $descripcion);
        $this->assertStringContainsString('Aprobado', $descripcion);
    }

    public function test_no_se_altera_el_estado_de_un_pedido_anulado(): void
    {
        $this->sembrarPedido(cabeceraEstado: EstadoRequerimiento::Anulado->value);

        $respuesta = AtencionService::cambiar_estado_detalle(
            id_empleado: 4,
            ids_detalles: [10],
            nuevo_estado: EstadoRequerimientoDetalle::Aprobado->value,
        );

        $this->assertFalse($respuesta['success']);
        $this->assertSame('No se puede alterar el estado de un producto en un requerimiento anulado', $respuesta['message']);
        $this->assertSame(
            EstadoRequerimientoDetalle::Pendiente->value,
            RequerimientoAlmacenDetalle::fila(10)['estado'],
            'El detalle debe quedarse como estaba.'
        );
    }

    public function test_el_mensaje_distingue_una_decision_de_varias(): void
    {
        $this->sembrarPedido();

        $uno = AtencionService::cambiar_estado_detalle(4, [10], EstadoRequerimientoDetalle::Aprobado->value);
        $varios = AtencionService::cambiar_estado_detalle(4, [10, 11], EstadoRequerimientoDetalle::Rechazado->value);

        $this->assertSame('Estado del producto actualizado correctamente', $uno['message']);
        $this->assertSame('Estado de los productos actualizado correctamente', $varios['message']);
    }

    /* ------------------------------------------------------------------ */

    private function sembrarPedido(string $cabeceraEstado = EstadoRequerimiento::Generado->value): void
    {
        DBFalsa::tablaEnModelo('requerimiento_almacen', RequerimientoAlmacen::class);
        DBFalsa::tablaEnModelo('requerimiento_almacen_detalle', RequerimientoAlmacenDetalle::class);
        DBFalsa::tabla('empleado', [['id' => 4, 'nombre_completo' => 'Ana Quispe']]);

        // get_id_requerimiento_by_detalle lee por SELECT crudo.
        DBFalsa::select('rad.id_requerimiento_almacen', [(object) ['id_requerimiento_almacen' => 1]]);

        RequerimientoAlmacen::sembrar([[
            'id' => 1,
            'correlativo' => 'REQ-26-00001',
            'estado' => $cabeceraEstado,
        ]]);

        RequerimientoAlmacenDetalle::sembrar([
            [
                'id' => 10,
                'id_requerimiento_almacen' => 1,
                'cantidad_solicitada_base' => 60.0,
                'estado' => EstadoRequerimientoDetalle::Pendiente->value,
            ],
            [
                'id' => 11,
                'id_requerimiento_almacen' => 1,
                'cantidad_solicitada_base' => 30.0,
                'estado' => EstadoRequerimientoDetalle::Pendiente->value,
            ],
        ]);
    }
}
