<?php

namespace Tests\CasosDeUso;

use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Helpers\CorrelativoHelper;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Fakes\DBFalsa;
use Tests\TestCase;

/**
 * REGISTRO DE BUGS CAPTURADOS POR TESTS.
 *
 * Cada test afirma el comportamiento CORRECTO del sistema. Mientras el
 * bug este vivo, el test esta en rojo; cuando el codigo se corrige, el
 * test se apaga solo (pasa a verde). NO se "arregla" ajustando la
 * asercion: si un test de este grupo vuelve a fallar, hay que mirar el
 * codigo, no el test.
 *
 * Convenciones del archivo:
 *  - Patron AAA explicito (Arrange / Act / Assert) con bloques de
 *    comentarios. Cada bloque describe QUE se prepara, QUE se ejecuta
 *    y QUE se verifica, para que el reporte de phpunit sea legible sin
 *    abrir el codigo.
 *  - Grupo `bug`. Para correr solo estos:
 *      ./vendor/bin/phpunit --group bug
 *  - Si todos los tests del grupo quedan en verde, los bugs conocidos
 *    estan cerrados; cualquier regresion los volvera a fallar.
 */
class BugsCapturadosTest extends TestCase
{
    /**
     * CorrelativoHelper::generar() tiene dos `match` sobre el enum
     * Periodo (filtro de fecha y segmento del correlativo) y ninguno
     * cubria Dias / Meses / Anos: PHP lanzaba UnhandledMatchError.
     * Los tres casos existen en el enum, asi que cualquier llamada
     * con ellos reventaba la peticion entera.
     */
    #[Group('bug')]
    public function test_correlativo_con_periodo_dias_debe_generar_numero(): void
    {
        // ============================================================
        // Arrange
        // ============================================================
        // Cargamos una fila previa con numero_correlativo=5 emitida
        // hoy en la tabla que atendera el helper. Esto fuerza al
        // calculo del "siguiente" tomando 5 como maximo dentro del
        // filtro diario: si la logica fuera correcta, devolveria 6.
        // Cualquier excepcion o respuesta sin la clave 'correlativo'
        // evidencia que el codigo explota antes de producir el valor.

        DBFalsa::tabla('lote_producto', [[
            'id' => 1,
            'numero_correlativo' => 5,
            'created_at' => now()->toDateTimeString(),
        ]]);

        // ============================================================
        // Act
        // ============================================================
        // Solicitamos al helper generar el siguiente correlativo con
        // reseteo Periodo::Dias (forma plural, alias semantico de
        // Diario). Antes del fix, el match interno no cubria este
        // caso y disparaba UnhandledMatchError, abortando la
        // peticion completa.

        $resultado = CorrelativoHelper::generar(
            tabla: 'lote_producto',
            prefijo: 'LT',
            reseteo: Periodo::Dias,
        );

        // ============================================================
        // Assert
        // ============================================================
        // La salida debe incluir al menos la clave 'correlativo': es
        // el contrato publico del helper. Si el match no se completo,
        // la ejecucion ni siquiera llega aqui; si se completo mal,
        // devolveria un array sin esa clave.

        $this->assertArrayHasKey('correlativo', $resultado);
    }

    #[Group('bug')]
    public function test_correlativo_con_periodo_meses_debe_generar_numero(): void
    {
        // ============================================================
        // Arrange
        // ============================================================
        // Mismo escenario que Dias pero el caso Periodo::Meses
        // (forma plural, alias de Mensual). Si la rama no existia en
        // el match, reventaba aqui tambien.

        DBFalsa::tabla('lote_producto', [[
            'id' => 1,
            'numero_correlativo' => 5,
            'created_at' => now()->toDateTimeString(),
        ]]);

        // ============================================================
        // Act
        // ============================================================
        // Forzamos el camino del filtro mensual sobre la misma tabla
        // sembrada arriba.

        $resultado = CorrelativoHelper::generar(
            tabla: 'lote_producto',
            prefijo: 'LT',
            reseteo: Periodo::Meses,
        );

        // ============================================================
        // Assert
        // ============================================================
        // Mismo contrato que el caso Diario/Dias: el helper debe
        // devolver un array con la clave 'correlativo'.

        $this->assertArrayHasKey('correlativo', $resultado);
    }

    #[Group('bug')]
    public function test_correlativo_con_periodo_anos_debe_generar_numero(): void
    {
        // ============================================================
        // Arrange
        // ============================================================
        // Mismo escenario que Dias/Meses pero el caso Periodo::Anos
        // (forma plural, alias de Anual). Cubre la tercera rama del
        // match que originalmente estaba sin manejar.

        DBFalsa::tabla('lote_producto', [[
            'id' => 1,
            'numero_correlativo' => 5,
            'created_at' => now()->toDateTimeString(),
        ]]);

        // ============================================================
        // Act
        // ============================================================
        // Forzamos el camino del filtro anual sobre la misma tabla.

        $resultado = CorrelativoHelper::generar(
            tabla: 'lote_producto',
            prefijo: 'LT',
            reseteo: Periodo::Anos,
        );

        // ============================================================
        // Assert
        // ============================================================
        // Mismo contrato que los dos casos anteriores.

        $this->assertArrayHasKey('correlativo', $resultado);
    }

    /**
     * El costo unitario de la linea de entrega se perdia.
     *
     * EntregaService solia pedir en el SELECT del lote un conjunto de
     * columnas utiles para validar stock pero NO incluia
     * `costo_por_unidad`; la lectura posterior del costo, en cambio,
     * si dependia de esa columna. El cast a `(float) ($lote[...] ?? 0)`
     * devolvia 0, el subtotal persistido quedaba en 0.0 y la linea de
     * entrega quedaba sin valorizar aunque el kardex si lo estuviera.
     */
    #[Group('bug')]
    public function test_el_costo_de_la_linea_de_entrega_no_debe_perderse(): void
    {
        // ============================================================
        // Arrange
        // ============================================================
        // Sembramos el escenario minimo para registrar una entrega
        // exitosa:
        //  - cabecera de requerimiento 'En Despacho' (estado exigido
        //    por el flujo);
        //  - detalle aprobado por 60 unidades base;
        //  - lote activo con stock suficiente (120 base = 10 lotes)
        //    y costo_por_unidad conocido (15.0), para poder valorizar
        //    y comparar contra un valor exacto en el assert;
        //  - empleado que entrega (id=4) firmando la operacion.
        // El SELECT que arma EntregaService debe incluir
        // `costo_por_unidad` para que el calculo del subtotal
        // (cantidad_lote * costo_por_unidad) llegue al INSERT.

        DBFalsa::tablaEnModelo('requerimiento_almacen', \App\Models\RequerimientoAlmacen::class);
        DBFalsa::tablaEnModelo('requerimiento_almacen_detalle', \App\Models\RequerimientoAlmacenDetalle::class);
        DBFalsa::tabla('requerimiento_almacen_entrega', [[
            'id' => 50,
            'numero_correlativo' => 2,
            'created_at' => now()->toDateTimeString(),
        ]]);
        DBFalsa::tabla('empleado', [['id' => 4, 'nombre_completo' => 'Ana Quispe']]);
        DBFalsa::tabla('requerimiento_almacen_detalle_log');
        DBFalsa::tabla('requerimiento_almacen_detalle as rad', [[
            'id' => 10,
            'estado' => 'Entregado',
            'cantidad_base' => 60.0,
        ]]);

        \App\Models\Producto::sembrar([['id' => 7, 'nombre' => 'Guantes de cuero']]);
        \App\Models\LoteProducto::sembrar([[
            'id' => 5, 'id_producto' => 7, 'id_almacen' => 3,
            'correlativo' => 'LT-26-00001', 'contenido_por_presentacion' => 12.0,
            'stock_actual' => 10.0, 'stock_actual_base' => 120.0,
            'costo_por_unidad' => 15.0,
        ]]);
        \App\Models\RequerimientoAlmacen::sembrar([[
            'id' => 1, 'correlativo' => 'REQ-26-00001', 'estado' => 'En Despacho',
        ]]);
        \App\Models\RequerimientoAlmacenDetalle::sembrar([[
            'id' => 10, 'id_requerimiento_almacen' => 1, 'id_producto' => 7,
            'cantidad_solicitada_base' => 60.0, 'estado' => 'Aprobado',
        ]]);

        // ============================================================
        // Act
        // ============================================================
        // Registramos la entrega de 5 lotes (= 60 base) contra el
        // detalle. Esto ejercita las dos lineas involucradas en el
        // bug: el SELECT de lotes (donde faltaba la columna) y la
        // lectura del costo unitario para construir el subtotal.

        \App\Modules\RequerimientosAlmacenAtencion\Service\EntregaService::registrar_entrega(
            id_empleado_entrega: 4,
            id_requerimiento: 1,
            id_empleado_recibe: 9,
            fecha_entrega: '2026-10-06 10:00:00',
            observacion: null,
            evidencias: null,
            detalles: [[
                'id_requerimiento_almacen_detalle' => 10,
                'id_lote_producto' => 5,
                'id_producto' => 7,
                'cantidad_base' => 60.0,
                'cantidad_lote' => 5.0,
                'cantidad_requerimiento' => 5.0,
            ]],
        );

        // ============================================================
        // Assert
        // ============================================================
        // Leemos la primera linea persistida del detalle de entrega.
        // El campo `costo` debe ser 75.0 = 5 lotes * 15 por unidad.
        // Si la columna `costo_por_unidad` no llego del SELECT, el
        // cast ??0 deja el subtotal en 0.0 y la asercion falla con un
        // mensaje que apunta exactamente al origen del bug.

        $linea = \App\Models\RequerimientoAlmacenEntregaDetalle::inserts()[0];

        $this->assertSame(
            75.0,
            (float) $linea['costo'],
            'BUG: la columna costo_por_unidad no viene en el SELECT de lotes '
            .'(EntregaService:208) pero se lee en la 252. El subtotal queda en 0.'
        );
    }
}
