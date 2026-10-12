<?php

namespace Tests\CasosDeUso;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Models\Producto;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenEntrega;
use App\Models\RequerimientoAlmacenEntregaDetalle;
use App\Modules\RequerimientosAlmacenAtencion\Service\EntregaService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoEntrega;
use Tests\Support\Fakes\DBFalsa;
use Tests\TestCase;

/**
 * CASO DE USO: registrar una entrega.
 *
 * Es el unico flujo del proyecto que mueve stock Y kardex Y estado de pedido
 * a la vez. Tres reglas tienen que quedar fijadas:
 *
 *  1. Toda entrega descuenta stock del lote y deja un Salida en kardex.
 *  2. Un detalle pasa a Completado cuando lo entregado (en unidad base)
 *     alcanza lo solicitado; hay una tolerancia de 0.0001 para el error de
 *     punto flotante.
 *  3. La cabecera pasa a Completado SOLO si todos sus detalles quedaron
 *     Completados; si no, sigue en En Despacho para que el operador pueda
 *     seguir despachando.
 */
class RegistrarEntregaTest extends TestCase
{
    private const ID_REQUERIMIENTO = 1;

    private const ID_DETALLE = 10;

    private const ID_LOTE = 5;

    protected function setUp(): void
    {
        parent::setUp();

        // El flujo lee la cabecera por DB::table() y escribe su estado por el
        // modelo. Puentear los dos caminos evita que el test vea dos almacenes
        // separados para la misma tabla.
        DBFalsa::tablaEnModelo('requerimiento_almacen', RequerimientoAlmacen::class);

        // Mismo puente para el detalle: el flujo lee 'cantidad_solicitada_base'
        // y despues el estado de TODOS los detalles por DB::table(), asi que
        // tiene que ver lo que el propio flujo acaba de escribir.
        DBFalsa::tablaEnModelo('requerimiento_almacen_detalle', RequerimientoAlmacenDetalle::class);

        DBFalsa::tabla('requerimiento_almacen_entrega', [[
            'id' => 50,
            'numero_correlativo' => 2,
            'created_at' => now()->toDateTimeString(),
        ]]);

        DBFalsa::tabla('empleado', [['id' => 4, 'nombre_completo' => 'Ana Quispe']]);
        DBFalsa::tabla('requerimiento_almacen_detalle_log');

        // SUM de lo entregado en las entregas activas. El flujo filtra por
        // 'rae.estado' sobre el join, asi que la fila debe traer ambas claves.
        DBFalsa::tabla('requerimiento_almacen_detalle as rad', [[
            'id' => self::ID_DETALLE,
            'estado' => EstadoRequerimientoEntrega::Entregado->value,
            'cantidad_base' => 60.0,
        ]]);

        Producto::sembrar([['id' => 7, 'nombre' => 'Guantes de cuero']]);

        LoteProducto::sembrar([[
            'id' => self::ID_LOTE,
            'id_producto' => 7,
            'id_almacen' => 3,
            'correlativo' => 'LT-26-00001',
            'contenido_por_presentacion' => 12.0,
            'stock_actual' => 10.0,
            'stock_actual_base' => 120.0,
            'costo_por_unidad' => 15.0,
            'estado' => 'Activo',
        ]]);

        RequerimientoAlmacen::sembrar([[
            'id' => self::ID_REQUERIMIENTO,
            'estado' => EstadoRequerimiento::EnDespacho->value,
            'correlativo' => 'REQ-26-00001',
        ]]);

        RequerimientoAlmacenDetalle::sembrar([
            [
                'id' => self::ID_DETALLE,
                'id_requerimiento_almacen' => self::ID_REQUERIMIENTO,
                'id_producto' => 7,
                'cantidad_solicitada_base' => 60.0,
                'estado' => EstadoRequerimientoDetalle::Aprobado->value,
            ],
            // Segunda linea del pedido, todavia sin despachar. La cabecera no
            // puede cerrarse mientras exista.
            [
                'id' => 11,
                'id_requerimiento_almacen' => self::ID_REQUERIMIENTO,
                'id_producto' => 7,
                'cantidad_solicitada_base' => 30.0,
                'estado' => EstadoRequerimientoDetalle::Aprobado->value,
            ],
        ]);
    }

    private function entregar(float $cantidadLote = 5.0, float $cantidadBase = 60.0): array
    {
        return EntregaService::registrar_entrega(
            id_empleado_entrega: 4,
            id_requerimiento: self::ID_REQUERIMIENTO,
            id_empleado_recibe: 9,
            fecha_entrega: '2026-10-06 10:00:00',
            observacion: null,
            evidencias: null,
            detalles: [[
                'id_requerimiento_almacen_detalle' => self::ID_DETALLE,
                'id_lote_producto' => self::ID_LOTE,
                'id_producto' => 7,
                'cantidad_base' => $cantidadBase,
                'cantidad_lote' => $cantidadLote,
                'cantidad_requerimiento' => $cantidadLote,
            ]],
        );
    }

    /* ------------------------------------------------------------------ */
    /* La entregaDescuenta stock y deja kardex */
    /* ------------------------------------------------------------------ */

    public function test_registra_la_cabecera_de_la_entrega_como_entregada(): void
    {
        $respuesta = $this->entregar();

        $this->assertTrue($respuesta['success'], json_encode($respuesta['message']));

        $entregas = RequerimientoAlmacenEntrega::inserts();

        $this->assertCount(1, $entregas);
        $this->assertSame(EstadoRequerimientoEntrega::Entregado->value, $entregas[0]['estado']);
        $this->assertSame(self::ID_REQUERIMIENTO, (int) $entregas[0]['id_requerimiento_almacen']);
        $this->assertSame(4, (int) $entregas[0]['id_empleado_entrega']);
        $this->assertSame(9, (int) $entregas[0]['id_empleado_recibe']);
    }

    public function test_descuenta_el_stock_del_lote(): void
    {
        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $lote = LoteProducto::fila(self::ID_LOTE);

        $this->assertSame(5.0, (float) $lote['stock_actual']);
        $this->assertSame(60.0, (float) $lote['stock_actual_base']);
    }

    public function test_la_entrega_deja_un_salida_en_kardex(): void
    {
        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $kardex = KardexProducto::inserts();

        $this->assertCount(1, $kardex);
        $this->assertSame(KardexTipoMovimiento::Salida->value, $kardex[0]['tipo_movimiento']);
        $this->assertSame(KardexOrigenMovimiento::Entrega->value, $kardex[0]['tipo_origen']);
    }

    public function test_el_kardex_conserva_el_stock_anterior_para_la_trazabilidad(): void
    {
        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $kardex = KardexProducto::inserts()[0];

        $this->assertSame(120.0, (float) $kardex['stock_anterior_base']);
        $this->assertSame(60.0, (float) $kardex['stock_resultante_base']);
        $this->assertSame(60.0, (float) $kardex['cantidad_movimiento_base']);
        $this->assertSame(75.0, (float) $kardex['costo'], '5 unidades x 15 = 75');
    }

    public function test_la_descripcion_del_kardex_cita_el_correlativo_de_la_entrega(): void
    {
        $this->entregar();

        $descripcion = KardexProducto::inserts()[0]['descripcion'];

        $this->assertStringContainsString('ENT-'.now()->format('y').'-00003', $descripcion);
    }

    public function test_registra_el_detalle_de_la_entrega(): void
    {
        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $detalles = RequerimientoAlmacenEntregaDetalle::inserts();

        $this->assertCount(1, $detalles);
        $this->assertSame(self::ID_LOTE, (int) $detalles[0]['id_lote_producto']);
        $this->assertSame(60.0, (float) $detalles[0]['cantidad_base']);
        $this->assertSame(5.0, (float) $detalles[0]['cantidad_lote']);
    }

    public function test_todo_la_entrega_queda_dentro_de_una_transaccion(): void
    {
        $this->entregar();

        $this->assertCount(1, DBFalsa::transacciones());
    }

    public function test_el_kardex_si_valoriza_correctamente_el_mismo_material(): void
    {
        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $this->assertSame(
            75.0,
            (float) KardexProducto::inserts()[0]['costo'],
            'El kardex valoriza bien; el detalle de entrega no. Misma operacion, dos verificaciones distintas.'
        );
    }

    /* ------------------------------------------------------------------ */
    /* Regla 2: el detalle completa al alcanzar lo solicitado */
    /* ------------------------------------------------------------------ */

    public function test_el_detalle_queda_completado_al_entregar_todo_lo_solicitado(): void
    {
        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $this->assertSame(
            EstadoRequerimientoDetalle::Completado->value,
            RequerimientoAlmacenDetalle::fila(self::ID_DETALLE)['estado']
        );
    }

    public function test_una_entrega_parcial_deja_el_detalle_en_despacho(): void
    {
        // Solo 30 de 60 base entregados.
        DBFalsa::tabla('requerimiento_almacen_detalle as rad', [['id' => self::ID_DETALLE, 'estado' => EstadoRequerimientoEntrega::Entregado->value, 'cantidad_base' => 30.0]]);

        $this->entregar(cantidadLote: 2.5, cantidadBase: 30.0);

        $this->assertSame(
            EstadoRequerimientoDetalle::EnDespacho->value,
            RequerimientoAlmacenDetalle::fila(self::ID_DETALLE)['estado']
        );
    }

    public function test_la_tolerancia_de_flotante_cierra_el_detalle_a_un_pelo(): void
    {
        // Faltan 0.00005 base: por error de punto flotante debe contar como
        // entregado. Si este test falla, alguien toco el epsilon.
        DBFalsa::tabla('requerimiento_almacen_detalle as rad', [['id' => self::ID_DETALLE, 'estado' => EstadoRequerimientoEntrega::Entregado->value, 'cantidad_base' => 59.99995]]);

        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $this->assertSame(
            EstadoRequerimientoDetalle::Completado->value,
            RequerimientoAlmacenDetalle::fila(self::ID_DETALLE)['estado']
        );
    }

    public function test_un_faltante_real_no_cierra_el_detalle(): void
    {
        DBFalsa::tabla('requerimiento_almacen_detalle as rad', [['id' => self::ID_DETALLE, 'estado' => EstadoRequerimientoEntrega::Entregado->value, 'cantidad_base' => 59.0]]);

        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $this->assertSame(
            EstadoRequerimientoDetalle::EnDespacho->value,
            RequerimientoAlmacenDetalle::fila(self::ID_DETALLE)['estado']
        );
    }

    public function test_el_log_de_trazabilidad_refleja_lo_despachado_hasta_ahora(): void
    {
        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $logs = DBFalsa::tabla('requerimiento_almacen_detalle_log')->filas;

        $this->assertCount(1, $logs);
        $this->assertStringContainsString('Guantes de cuero', $logs[0]['descripcion']);
        $this->assertStringContainsString('60.00 base despachados', $logs[0]['descripcion']);
        $this->assertStringContainsString('total 60.00 de 60.00', $logs[0]['descripcion']);
        $this->assertSame(EstadoRequerimientoDetalle::Completado->value, $logs[0]['estado']);
    }

    /* ------------------------------------------------------------------ */
    /* Regla 3: la cabecera solo cierra si TODOS los detalles cerraron */
    /* ------------------------------------------------------------------ */

    public function test_la_cabecera_pasa_a_completado_si_todos_los_detalles_completaron(): void
    {
        // Pedido de una sola linea: despues de esta entrega no queda nada
        // pendiente, asi que la cabecera debe cerrarse.
        RequerimientoAlmacenDetalle::limpiar();
        RequerimientoAlmacenDetalle::sembrar([[
            'id' => self::ID_DETALLE,
            'id_requerimiento_almacen' => self::ID_REQUERIMIENTO,
            'id_producto' => 7,
            'cantidad_solicitada_base' => 60.0,
            'estado' => EstadoRequerimientoDetalle::Aprobado->value,
        ]]);

        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $this->assertSame(
            [['id' => self::ID_REQUERIMIENTO, 'datos' => ['estado' => EstadoRequerimiento::Completado->value]]],
            RequerimientoAlmacen::updates()
        );
    }

    public function test_la_cabecera_sigue_en_despacho_si_algun_detalte_queda_pendiente(): void
    {
        // El detalle 11 sigue en Aprobado: la cabecera no puede cerrarse.
        $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $this->assertSame(
            [['id' => self::ID_REQUERIMIENTO, 'datos' => ['estado' => EstadoRequerimiento::EnDespacho->value]]],
            RequerimientoAlmacen::updates()
        );
    }

    public function test_la_cabecera_no_se_cierra_si_el_detalle_entregado_queda_a_medias(): void
    {
        DBFalsa::tabla('requerimiento_almacen_detalle as rad', [['id' => self::ID_DETALLE, 'estado' => EstadoRequerimientoEntrega::Entregado->value, 'cantidad_base' => 30.0]]);
        RequerimientoAlmacenDetalle::limpiar();
        RequerimientoAlmacenDetalle::sembrar([[
            'id' => self::ID_DETALLE,
            'id_requerimiento_almacen' => self::ID_REQUERIMIENTO,
            'id_producto' => 7,
            'cantidad_solicitada_base' => 60.0,
            'estado' => EstadoRequerimientoDetalle::Aprobado->value,
        ]]);

        $this->entregar(cantidadLote: 2.5, cantidadBase: 30.0);

        $this->assertSame(
            [['id' => self::ID_REQUERIMIENTO, 'datos' => ['estado' => EstadoRequerimiento::EnDespacho->value]]],
            RequerimientoAlmacen::updates()
        );
    }

    /* ------------------------------------------------------------------ */
    /* Guardas */
    /* ------------------------------------------------------------------ */

    public function test_no_se_puede_entregar_si_el_lote_no_alcanza(): void
    {
        LoteProducto::limpiar();
        LoteProducto::sembrar([[
            'id' => self::ID_LOTE,
            'id_producto' => 7,
            'id_almacen' => 3,
            'correlativo' => 'LT-26-00001',
            'contenido_por_presentacion' => 12.0,
            'stock_actual' => 1.0,
            'stock_actual_base' => 12.0,
            'costo_por_unidad' => 15.0,
        ]]);

        $respuesta = $this->entregar(cantidadLote: 5.0, cantidadBase: 60.0);

        $this->assertFalse($respuesta['success']);
        $this->assertCount(0, RequerimientoAlmacenEntrega::inserts());
        $this->assertCount(0, KardexProducto::inserts());
    }

    public function test_no_se_puede_entregar_a_un_requerimiento_anulado(): void
    {
        RequerimientoAlmacen::limpiar();
        RequerimientoAlmacen::sembrar([[
            'id' => self::ID_REQUERIMIENTO,
            'estado' => EstadoRequerimiento::Anulado->value,
        ]]);

        $respuesta = $this->entregar();

        $this->assertFalse($respuesta['success']);
        $this->assertSame('No se pueden registrar entregas para un requerimiento anulado', $respuesta['message']);
        $this->assertCount(0, RequerimientoAlmacenEntrega::inserts());
    }

    public function test_no_se_puede_entregar_a_un_requerimiento_inexistente(): void
    {
        RequerimientoAlmacen::limpiar();
        RequerimientoAlmacen::sembrar([]);

        $respuesta = $this->entregar();

        $this->assertFalse($respuesta['success']);
        $this->assertSame('Requerimiento no encontrado', $respuesta['message']);
    }
}
