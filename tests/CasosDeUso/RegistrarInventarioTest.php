<?php

namespace Tests\CasosDeUso;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Services\LotesProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use Tests\Support\Fakes\DBFalsa;
use Tests\TestCase;

/**
 * CASO DE USO: registrar inventario.
 *
 * Ingreso de un lote nuevo al almacen. El contrato que hay que proteger:
 * todo ingreso de stock deja fila en Kardex (README regla 7) y el stock se
 * mide en unidad base (README regla 6).
 */
class RegistrarInventarioTest extends TestCase
{
    private function sembrarCorrelativo(int $ultimoNumero): void
    {
        DBFalsa::tabla('lote_producto', [[
            'id' => 99,
            'numero_correlativo' => $ultimoNumero,
            'created_at' => now()->toDateTimeString(),
        ]]);
    }

    private function registrar(
        float $contenido = 12.0,
        float $stock = 5.0,
        ?float $costo = 120.0,
        ?string $comprobante = 'F001-0001',
    ) {
        return LotesProductosService::crear_lote(
            id_producto: 7,
            id_unidad_medida: 2,
            id_almacen: 3,
            contenido_por_presentacion: $contenido,
            stock_inicial: $stock,
            fecha_hora_ingreso: '2026-10-01 08:00:00',
            fecha_vencimiento: '2027-10-01 00:00:00',
            comprobante_compra: $comprobante,
            costo_por_unidad: $costo,
        );
    }

    public function test_registra_el_lote_y_responde_con_exito(): void
    {
        $this->sembrarCorrelativo(41);

        $respuesta = $this->registrar();

        $this->assertTrue($respuesta['success']);
        $this->assertSame('Lote registrado correctamente e ingresado al Kardex.', $respuesta['message']);
        $this->assertIsInt($respuesta['data']['id_lote']);
        $this->assertGreaterThan(0, $respuesta['data']['id_lote']);
        $this->assertSame('LT-'.now()->format('y').'-00042', $respuesta['data']['correlativo']);
    }

    public function test_el_ingreso_siempre_deja_una_fila_en_kardex(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar();

        $kardex = KardexProducto::inserts();

        $this->assertCount(1, $kardex, 'Todo ingreso de lote debe generar exactamente un movimiento de kardex.');
        $this->assertSame(KardexTipoMovimiento::Ingreso->value, $kardex[0]['tipo_movimiento']);
        $this->assertSame(KardexOrigenMovimiento::NuevoLote->value, $kardex[0]['tipo_origen']);
    }

    public function test_kardex_guarda_el_stock_anterior_en_cero_y_el_resultante(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar(contenido: 12.0, stock: 5.0);

        $kardex = KardexProducto::inserts()[0];

        $this->assertSame(0.0, (float) $kardex['stock_anterior']);
        $this->assertSame(0.0, (float) $kardex['stock_anterior_base']);
        $this->assertSame(5.0, (float) $kardex['cantidad_movimiento']);
        $this->assertSame(60.0, (float) $kardex['cantidad_movimiento_base']);
        $this->assertSame(5.0, (float) $kardex['stock_resultante']);
        $this->assertSame(60.0, (float) $kardex['stock_resultante_base']);
    }

    public function test_el_stock_se_mide_en_unidad_base(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar(contenido: 12.0, stock: 5.0);

        $lote = LoteProducto::inserts()[0];

        $this->assertSame(5.0, (float) $lote['stock_actual']);
        $this->assertSame(60.0, (float) $lote['stock_actual_base'], 'cantidad_base = cantidad x contenido_por_presentacion');
    }

    public function test_el_costo_por_unidad_base_se_deriva_del_costo_del_lote(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar(contenido: 12.0, stock: 5.0, costo: 120.0);

        $lote = LoteProducto::inserts()[0];

        $this->assertSame(120.0, (float) $lote['costo_por_unidad']);
        $this->assertSame(10.0, (float) $lote['costo_por_unidad_base']);
    }

    public function test_el_costo_por_unidad_base_se_redondea_a_cuatro_decimales(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar(contenido: 3.0, stock: 1.0, costo: 1.0);

        $lote = LoteProducto::inserts()[0];

        $this->assertSame(0.3333, (float) $lote['costo_por_unidad_base']);
    }

    public function test_un_contenido_por_presentacion_en_cero_no_provoca_division_por_cero(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar(contenido: 0.0, stock: 5.0, costo: 120.0);

        $lote = LoteProducto::inserts()[0];

        $this->assertSame(120.0, (float) $lote['costo_por_unidad_base'], 'Sin contenido no se puede convertir el costo; debe sobrevivir sin error.');
    }

    public function test_un_costo_nulo_deja_el_costo_base_en_cero(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar(contenido: 12.0, stock: 5.0, costo: null);

        $lote = LoteProducto::inserts()[0];

        $this->assertSame(0.0, (float) $lote['costo_por_unidad']);
        $this->assertSame(0.0, (float) $lote['costo_por_unidad_base']);
    }

    public function test_la_descripcion_del_kardex_hace_trae_correlativo_y_comprobante(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar(comprobante: 'F001-0001');

        $descripcion = KardexProducto::inserts()[0]['descripcion'];

        $this->assertStringContainsString('LT-'.now()->format('y').'-00001', $descripcion);
        $this->assertStringContainsString('F001-0001', $descripcion);
    }

    public function test_sin_comprobante_la_descripcion_no_deja_parentesis_vacios(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar(comprobante: null);

        $descripcion = KardexProducto::inserts()[0]['descripcion'];

        $this->assertStringNotContainsString('()', $descripcion);
    }

    public function test_las_escrituras_quedan_dentro_de_una_transaccion(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar();

        $this->assertCount(1, DBFalsa::transacciones());
    }

    public function test_el_correlativo_continua_desde_el_ultimo_registrado(): void
    {
        $this->sembrarCorrelativo(9998);

        $respuesta = $this->registrar();

        $this->assertSame('LT-'.now()->format('y').'-09999', $respuesta['data']['correlativo']);
    }

    public function test_sin_correlativos_previos_arranca_en_uno(): void
    {
        $this->sembrarCorrelativo(0);

        $respuesta = $this->registrar();

        $this->assertSame('LT-'.now()->format('y').'-00001', $respuesta['data']['correlativo']);
    }

    public function test_el_lote_nace_activo_y_con_fecha_de_ingreso(): void
    {
        $this->sembrarCorrelativo(0);

        $this->registrar();

        $lote = LoteProducto::inserts()[0];

        $this->assertSame('Activo', $lote['estado']);
        $this->assertSame('2026-10-01 08:00:00', $lote['fecha_hora_ingreso']);
        $this->assertSame(3, (int) $lote['id_almacen']);
        $this->assertSame(7, (int) $lote['id_producto']);
    }
}
