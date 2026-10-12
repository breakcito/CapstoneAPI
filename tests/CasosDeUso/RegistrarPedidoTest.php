<?php

namespace Tests\CasosDeUso;

use App\Models\Producto;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenDetalle;
use App\Modules\RequerimientosAlmacenAtencion\Service\AtencionService;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use Tests\Support\Fakes\DBFalsa;
use Tests\TestCase;

/**
 * CASO DE USO: registrar un pedido (requerimiento de almacen).
 *
 * Lo que hay que fijar:
 *  - la cabecera nace en 'Generado' y cada detalle en 'Pendiente';
 *  - cantidad_solicitada_base = cantidad x contenido (o cantidad_items x
 *    magnitud base cuando el item viene con magnitud);
 *  - cada detalle deja su primer evento de trazabilidad.
 */
class RegistrarPedidoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DBFalsa::tabla('requerimiento_almacen', [[
            'id' => 90,
            'numero_correlativo' => 7,
            'created_at' => now()->toDateTimeString(),
        ]]);

        DBFalsa::tabla('empleado', [[
            'id' => 4,
            'nombre_completo' => 'Ana Quispe',
        ]]);

        // El flujo escribe el timeline con DB::table(...)->insertGetId().
        // Hay que registrar la tabla antes o el doble crea una-descartable.
        DBFalsa::tabla('requerimiento_almacen_detalle_log');

        Producto::sembrar([
            ['id' => 7, 'nombre' => 'Guantes de cuero'],
            ['id' => 8, 'nombre' => 'Casco de seguridad'],
        ]);
    }

    private function registrar(array $detalles): array
    {
        return AtencionService::registrar_requerimiento(
            id_contratista_solicitante: null,
            id_empleado_registro: 4,
            id_almacen_destino: 3,
            detalles: $detalles,
            fecha_solicitud: '2026-10-05',
            observacion: 'Reposicion semanal',
            evidencias: null,
        );
    }

    private function detalleProducto(float $cantidad = 2.0, float $contenido = 12.0): array
    {
        return [
            'id_producto' => 7,
            'id_unidad_medida' => 2,
            'contenido_por_presentacion' => $contenido,
            'cantidad_solicitada' => $cantidad,
            'comentario' => null,
        ];
    }

    public function test_registra_la_cabecera_del_pedido(): void
    {
        $respuesta = $this->registrar([$this->detalleProducto()]);

        $this->assertTrue($respuesta['success']);
        $this->assertSame('Requerimiento generado correctamente', $respuesta['message']);

        $cabeceras = RequerimientoAlmacen::inserts();

        $this->assertCount(1, $cabeceras);
        $this->assertSame(3, (int) $cabeceras[0]['id_almacen_destino']);
        $this->assertSame(4, (int) $cabeceras[0]['id_empleado_registro']);
    }

    public function test_el_pedido_nace_en_estado_generado(): void
    {
        $this->registrar([$this->detalleProducto()]);

        $this->assertSame(
            EstadoRequerimiento::Generado->value,
            RequerimientoAlmacen::inserts()[0]['estado']
        );
    }

    public function test_el_pedido_toma_el_correlativo_del_anio_correspondiente(): void
    {
        $respuesta = $this->registrar([$this->detalleProducto()]);

        $this->assertSame('REQ-'.now()->format('y').'-00008', $respuesta['data']['correlativo']);
        $this->assertSame(8, RequerimientoAlmacen::inserts()[0]['numero_correlativo']);
    }

    public function test_cada_linea_del_pedido_se_registra_como_detalle(): void
    {
        $this->registrar([
            $this->detalleProducto(),
            ['id_producto' => 8, 'id_unidad_medida' => 3, 'contenido_por_presentacion' => 1.0, 'cantidad_solicitada' => 4.0],
        ]);

        $this->assertCount(2, RequerimientoAlmacenDetalle::inserts());
    }

    public function test_los_detalles_nacen_en_estado_pendiente(): void
    {
        $this->registrar([$this->detalleProducto()]);

        $this->assertSame(
            EstadoRequerimientoDetalle::Pendiente->value,
            RequerimientoAlmacenDetalle::inserts()[0]['estado']
        );
    }

    public function test_la_cantidad_solicitada_se_convierte_a_unidad_base(): void
    {
        $this->registrar([$this->detalleProducto(cantidad: 2.0, contenido: 12.0)]);

        $detalle = RequerimientoAlmacenDetalle::inserts()[0];

        $this->assertSame(2.0, (float) $detalle['cantidad_solicitada']);
        $this->assertSame(24.0, (float) $detalle['cantidad_solicitada_base']);
    }

    public function test_un_item_con_magnitud_usa_items_por_magnitud_base(): void
    {
        $detalle = $this->detalleProducto(cantidad: 3.0, contenido: 1.0);
        $detalle['con_magnitud'] = true;
        $detalle['cantidad_items'] = 3.0;
        $detalle['valor_magnitud'] = 0.5;
        $detalle['valor_magnitud_base'] = 0.5;

        $this->registrar([$detalle]);

        $guardado = RequerimientoAlmacenDetalle::inserts()[0];

        $this->assertSame(1.5, (float) $guardado['cantidad_solicitada_base'], '3 items x 0.5 base = 1.5');
        $this->assertSame(3.0, (float) $guardado['cantidad_items']);
        $this->assertSame(1, (int) $guardado['con_magnitud']);
    }

    public function test_un_item_con_magnitud_pero_sin_items_cae_a_la_formula_clasica(): void
    {
        $detalle = $this->detalleProducto(cantidad: 2.0, contenido: 12.0);
        $detalle['con_magnitud'] = true;
        $detalle['cantidad_items'] = 0.0;
        $detalle['valor_magnitud_base'] = 0.5;

        $this->registrar([$detalle]);

        $this->assertSame(24.0, (float) RequerimientoAlmacenDetalle::inserts()[0]['cantidad_solicitada_base']);
    }

    public function test_cada_detalle_abre_su_timeline_de_trazabilidad(): void
    {
        $this->registrar([$this->detalleProducto()]);

        $logs = DBFalsa::tabla('requerimiento_almacen_detalle_log')->filas;

        $this->assertCount(1, $logs);
        $this->assertSame(EstadoRequerimientoDetalle::Pendiente->value, $logs[0]['estado']);
    }

    public function test_el_log_de_trazabilidad_es_legible_en_la_ui(): void
    {
        $this->registrar([$this->detalleProducto(cantidad: 2.0, contenido: 12.0)]);

        $logs = DBFalsa::tabla('requerimiento_almacen_detalle_log')->filas;

        $this->assertStringContainsString('Guantes de cuero', $logs[0]['descripcion']);
        $this->assertStringContainsString('24.00', $logs[0]['descripcion']);
        $this->assertStringContainsString('Ana Quispe', $logs[0]['descripcion']);
    }

    public function test_sin_evidencias_la_cabecera_queda_con_evidencias_nulas(): void
    {
        $this->registrar([$this->detalleProducto()]);

        $this->assertNull(RequerimientoAlmacen::inserts()[0]['evidencias']);
    }

    public function test_todo_el_registro_queda_dentro_de_una_transaccion(): void
    {
        $this->registrar([$this->detalleProducto()]);

        $this->assertCount(1, DBFalsa::transacciones());
    }

    public function test_la_fecha_de_solicitud_se_respeta_cuando_viene(): void
    {
        $this->registrar([$this->detalleProducto()]);

        $this->assertSame('2026-10-05', RequerimientoAlmacen::inserts()[0]['fecha_solicitud']);
    }
}
