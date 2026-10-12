<?php

namespace Tests\CasosDeUso;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Modules\LotesProductos\Service\LotesService;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use Tests\Support\Fakes\DBFalsa;
use Tests\TestCase;

/**
 * CASO DE USO: editar inventario.
 *
 * Dos caminos distintos, y no se mezclan:
 *  - ajustar_stock(): mueve la cantidad. DEBE generar kardex.
 *  - actualizar_lote(): cambia metadatos (comprobante, fecha, costo).
 *    NO debe generar kardex: no hubo movimiento de material.
 *
 * Ese contraste es exactamente lo que un test de "editar" tiene que fijar,
 * porque el nombre del metodo no lo dice.
 */
class EditarInventarioTest extends TestCase
{
    private function sembrarLote(array $atributos = []): void
    {
        LoteProducto::sembrar([array_merge([
            'id' => 1,
            'id_producto' => 7,
            'id_unidad_medida' => 2,
            'id_almacen' => 3,
            'correlativo' => 'LT-26-00001',
            'stock_actual' => 10.0,
            'contenido_por_presentacion' => 12.0,
            'stock_actual_base' => 120.0,
            'costo_por_unidad' => 15.0,
            'costo_por_unidad_base' => 1.25,
            'estado' => 'Activo',
        ], $atributos)]);
    }

    /* ------------------------------------------------------------------ */
    /* Ajustar stock */
    /* ------------------------------------------------------------------ */

    public function test_subir_el_stock_genera_un_ingreso_en_kardex(): void
    {
        $this->sembrarLote();

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 240.0, motivo: 'Conteo fisico');

        $kardex = KardexProducto::inserts();

        $this->assertCount(1, $kardex);
        $this->assertSame(KardexTipoMovimiento::Ingreso->value, $kardex[0]['tipo_movimiento']);
        $this->assertSame(KardexOrigenMovimiento::AjusteStock->value, $kardex[0]['tipo_origen']);
    }

    public function test_bajar_el_stock_genera_una_salida_en_kardex(): void
    {
        $this->sembrarLote();

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 60.0, motivo: 'Merma');

        $kardex = KardexProducto::inserts();

        $this->assertCount(1, $kardex);
        $this->assertSame(KardexTipoMovimiento::Salida->value, $kardex[0]['tipo_movimiento']);
    }

    public function test_el_kardex_conserva_el_stock_anterior_y_el_resultante(): void
    {
        $this->sembrarLote();

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 60.0, motivo: 'Merma');

        $kardex = KardexProducto::inserts()[0];

        $this->assertSame(120.0, (float) $kardex['stock_anterior_base']);
        $this->assertSame(60.0, (float) $kardex['stock_resultante_base']);
        $this->assertSame(60.0, (float) $kardex['cantidad_movimiento_base']);
    }

    public function test_el_stock_del_lote_queda_actualizado(): void
    {
        $this->sembrarLote();

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 60.0, motivo: 'Merma');

        $lote = LoteProducto::fila(1);

        $this->assertSame(60.0, (float) $lote['stock_actual_base']);
        $this->assertSame(5.0, (float) $lote['stock_actual']);
    }

    public function test_el_movimiento_en_unidad_del_lote_se_deriva_de_la_unidad_base(): void
    {
        $this->sembrarLote(['stock_actual' => 10.0, 'contenido_por_presentacion' => 12.0]);

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 66.0, motivo: 'Ajuste');

        $kardex = KardexProducto::inserts()[0];

        $this->assertSame(5.5, (float) $kardex['stock_resultante'], '66 base / 12 por presentacion = 5.5');
        $this->assertSame(4.5, (float) $kardex['cantidad_movimiento'], 'El lote baja de 10 a 5.5 unidades: se movieron 4.5.');
    }

    public function test_un_contenido_en_cero_no_provoca_division_por_cero(): void
    {
        $this->sembrarLote(['contenido_por_presentacion' => 0.0]);

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 60.0, motivo: 'Ajuste');

        $kardex = KardexProducto::inserts()[0];

        $this->assertSame(60.0, (float) $kardex['stock_resultante'], 'Sin contenido, base y lote son la misma cifra.');
    }

    public function test_el_costo_del_movimiento_es_cantidad_por_costo_unitario(): void
    {
        $this->sembrarLote(['stock_actual' => 10.0, 'costo_por_unidad' => 15.0]);

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 60.0, motivo: 'Merma');

        $kardex = KardexProducto::inserts()[0];

        $this->assertSame(5.0, (float) $kardex['cantidad_movimiento']);
        $this->assertSame(75.0, (float) $kardex['costo'], '5 unidades x 15 = 75');
    }

    public function test_un_ajuste_igual_al_stock_actual_se_rechaza_sin_tocar_kardex(): void
    {
        $this->sembrarLote();

        $respuesta = LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 120.0, motivo: 'Ajuste');

        $this->assertFalse($respuesta['success']);
        $this->assertSame('El nuevo stock es igual al actual', $respuesta['message']);
        $this->assertCount(0, KardexProducto::inserts(), 'Un ajuste que no mueve nada no debe generar kardex.');
        $this->assertCount(0, LoteProducto::updates());
    }

    public function test_un_lote_inexistente_no_registra_kardex(): void
    {
        $respuesta = LotesService::ajustar_stock(id_lote: 999, nuevo_stock_base: 60.0, motivo: 'Ajuste');

        $this->assertFalse($respuesta['success']);
        $this->assertSame('Lote no encontrado', $respuesta['message']);
        $this->assertCount(0, KardexProducto::inserts());
    }

    public function test_sin_motivo_el_kardex_usa_la_descripcion_por_defecto(): void
    {
        $this->sembrarLote();

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 60.0);

        $this->assertSame('Ajuste de stock manual', KardexProducto::inserts()[0]['descripcion']);
    }

    public function test_el_motivo_del_ajuste_llega_al_kardex(): void
    {
        $this->sembrarLote();

        LotesService::ajustar_stock(id_lote: 1, nuevo_stock_base: 60.0, motivo: 'Difference por conteo fisico');

        $this->assertSame('Difference por conteo fisico', KardexProducto::inserts()[0]['descripcion']);
    }

    /* ------------------------------------------------------------------ */
    /* Editar metadatos (sin movimiento de material) */
    /* ------------------------------------------------------------------ */

    public function test_editar_metadatos_no_debe_generar_kardex(): void
    {
        $this->sembrarLote();
        DBFalsa::select('lp.id AS id_lote', [(object) ['id_lote' => 1, 'correlativo' => 'LT-26-00001']]);

        LotesService::actualizar_lote(
            id_lote: 1,
            comprobante_compra: 'F001-0999',
            fecha_hora_ingreso: '2026-09-01 10:00:00',
            costo_por_unidad: 18.5,
        );

        $this->assertCount(0, KardexProducto::inserts(), 'Cambiar el comprobante no es un movimiento de stock.');
    }

    public function test_editar_metadatos_solo_pisa_los_campos_enviados(): void
    {
        $this->sembrarLote();
        DBFalsa::select('lp.id AS id_lote', [(object) ['id_lote' => 1]]);
        $loteTabla = DBFalsa::tabla('lote_producto');

        LotesService::actualizar_lote(
            id_lote: 1,
            comprobante_compra: 'F001-0999',
            fecha_hora_ingreso: null,
            costo_por_unidad: null,
        );

        $escrituras = $loteTabla->escrituras;

        $this->assertCount(1, $escrituras);
        $this->assertSame(['comprobante_compra' => 'F001-0999'], $escrituras[0]);
    }

    public function test_editar_un_lote_inexistente_falla_con_mensaje(): void
    {
        DBFalsa::select('lp.id AS id_lote', []);

        $respuesta = LotesService::actualizar_lote(
            id_lote: 999,
            comprobante_compra: 'X',
            fecha_hora_ingreso: null,
            costo_por_unidad: null,
        );

        $this->assertFalse($respuesta['success']);
        $this->assertSame('El lote que intenta editar no existe.', $respuesta['message']);
    }

    /* ------------------------------------------------------------------ */
    /* Eliminar (soft delete) */
    /* ------------------------------------------------------------------ */

    public function test_eliminar_un_lote_es_un_borrado_logico(): void
    {
        $this->sembrarLote();
        DBFalsa::select('lp.id AS id_lote', [(object) ['id_lote' => 1]]);
        $loteTabla = DBFalsa::tabla('lote_producto');

        $respuesta = LotesService::eliminar_lote(id_lote: 1);

        $this->assertTrue($respuesta['success']);
        $this->assertSame(
            [['estado' => EstadoBase::Inactivo->value]],
            $loteTabla->escrituras,
            'Eliminar un lote es un UPDATE de estado, nunca un DELETE.'
        );
    }

    public function test_eliminar_un_lote_inexistente_falla_con_mensaje(): void
    {
        DBFalsa::select('lp.id AS id_lote', []);
        $loteTabla = DBFalsa::tabla('lote_producto');

        $respuesta = LotesService::eliminar_lote(id_lote: 999);

        $this->assertFalse($respuesta['success']);
        $this->assertSame('El lote que intenta eliminar no existe.', $respuesta['message']);
        $this->assertSame([], $loteTabla->escrituras);
    }
}
