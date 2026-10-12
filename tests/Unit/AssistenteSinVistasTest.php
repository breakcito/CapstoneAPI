<?php

namespace Tests\Unit;

use App\Modules\IA\Prompts\SystemPrompt;
use App\Modules\IA\Service\SqlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El asistente escribe el SQL de cada reporte sobre las tablas del dominio.
 *
 * Estes tests fijan esa decision. Regresan a rojo si alguien vuelve a meter las
 * vistas BI en el prompt, en las herramientas o en el esquema.
 *
 * Contexto: un usuario pidio "reporte de inventario" y recibio un Excel con
 * una fila por almacen (almacen, productos_activos, unidades_base_total,
 * valorizado, lotes_sin_stock). El modelo habia hecho SELECT * sobre la vista
 * de stock valorizado, que agrupa por almacen y no tiene dimension de producto.
 */
class AssistenteSinVistasTest extends TestCase
{
    private static function prompt(): string
    {
        return SystemPrompt::build([
            ['tabla' => 'lote_producto', 'tipo' => 'BASE TABLE', 'filas_estimadas' => 120, 'columnas' => ['id', 'stock_actual_base']],
            ['tabla' => 'producto', 'tipo' => 'BASE TABLE', 'filas_estimadas' => 80, 'columnas' => ['id', 'nombre']],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* El prompt */
    /* ------------------------------------------------------------------ */

    public function test_el_prompt_no_menciona_ninguna_vista(): void
    {
        $this->assertStringNotContainsStringIgnoringCase(
            'v_bi_',
            self::prompt(),
            'El prompt sigue empujando al modelo hacia las vistas BI.'
        );
    }

    public function test_el_prompt_no_les_dice_que_use_las_vistas(): void
    {
        $prompt = self::prompt();

        $this->assertStringNotContainsStringIgnoringCase('Vistas BI', $prompt);
        $this->assertStringNotContainsStringIgnoringCase('usalas primero', $prompt);
        $this->assertStringNotContainsStringIgnoringCase('preferelas', $prompt);
    }

    public function test_el_prompt_le_dice_que_escriba_su_propio_sql(): void
    {
        $this->assertStringContainsStringIgnoringCase('Escribes TU el SQL', self::prompt());
        $this->assertStringContainsStringIgnoringCase('NO uses vistas', self::prompt());
    }

    public function test_el_prompt_define_reporte_de_inventario_como_una_fila_por_producto(): void
    {
        $this->assertStringContainsStringIgnoringCase(
            'UNA FILA POR PRODUCTO',
            self::prompt(),
            'Sin esta regla el modelo vuelve a agregar por almacen.'
        );
    }

    public function test_el_prompt_avisa_del_join_multiplicado(): void
    {
        $this->assertStringContainsStringIgnoringCase(
            'JOIN MULTIPLICADOS',
            self::prompt(),
            'Unir lote_producto con kardex_producto infla todos los SUM. Sin aviso, el modelo lo hace.'
        );
    }

    public function test_el_prompt_manda_usar_las_columnas_en_unidad_base(): void
    {
        $this->assertStringContainsStringIgnoringCase('cantidad_base', self::prompt());
        $this->assertStringContainsStringIgnoringCase('_base', self::prompt());
    }

    public function test_el_prompt_exige_filtrar_por_estado_activo(): void
    {
        $this->assertStringContainsStringIgnoringCase(
            "estado = 'Activo'",
            self::prompt(),
            'Los lotes Inactivos siguen teniendo filas e inflan los totales.'
        );
    }

    /* ------------------------------------------------------------------ */
    /* La barrera: SqlGuard */
    /* ------------------------------------------------------------------ */

    #[DataProvider('consultasConVistas')]
    public function test_el_guard_rechaza_consultas_que_leen_una_vista(string $sql): void
    {
        $error = SqlGuard::validar($sql);

        $this->assertNotNull($error, "Debio rechazar: {$sql}");
        $this->assertStringContainsString('no puede usar la vista', $error);
    }

    public static function consultasConVistas(): array
    {
        return [
            'el reporte que salio mal' => ['SELECT * FROM v_bi_stock_valorizado'],
            'con alias' => ['SELECT sv.almacen FROM v_bi_stock_valorizado AS sv'],
            'con join' => ['SELECT p.nombre FROM producto p JOIN v_bi_alertas_stock a ON a.id_producto = p.id'],
            'en subconsulta' => ['SELECT * FROM (SELECT * FROM v_bi_top_productos) t'],
            'con where' => ['SELECT almacen FROM v_bi_stock_valorizado WHERE valorizado > 100'],
        ];
    }

    #[DataProvider('consultasSobreTablas')]
    public function test_el_guard_deja_pasar_el_sql_sobre_tablas(string $sql): void
    {
        $this->assertNull(
            SqlGuard::validar($sql),
            "No deberia rechazar una consulta sobre tablas: {$sql}"
        );
    }

    public static function consultasSobreTablas(): array
    {
        return [
            'inventario por producto' => ['SELECT p.nombre, SUM(lp.stock_actual_base) FROM lote_producto lp INNER JOIN producto p ON p.id = lp.id_producto WHERE lp.estado = \'Activo\' GROUP BY p.id, p.nombre'],
            'kardex' => ['SELECT * FROM kardex_producto WHERE tipo_movimiento = \'Salida\''],
            'pedidos' => ['SELECT correlativo, estado FROM requerimiento_almacen'],
        ];
    }

    public function test_el_mensaje_de_rechazo_le_dice_que_escriba_sobre_las_tablas(): void
    {
        $error = SqlGuard::validar('SELECT * FROM v_bi_stock_valorizado');

        $this->assertStringContainsString('una fila por producto', $error);
    }
}
