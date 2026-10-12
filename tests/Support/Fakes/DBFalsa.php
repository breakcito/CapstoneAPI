<?php

namespace Tests\Support\Fakes;

use Illuminate\Support\Facades\DB;

/**
 * Cableado del facade `DB`.
 *
 * El proyecto escribe con dos vias distintas:
 *   1. los modelos Eloquent (ver ModeloFalso) -> no pasa por este facade;
 *   2. SQL crudo: DB::table(), DB::select(), DB::transaction() -> si.
 *
 * Este helper instala las expectativas de la segunda via. Lo que no se
 * declara aqui revienta con una excepcion de Mockery en vez de devolver null
 * en silencio.
 */
class DBFalsa
{
    /** @var array<string, TablaFalsa> */
    private static array $tablas = [];

    /** @var array<int, array{corte: string, resultado: mixed}> */
    private static array $selects = [];

    /** @var array<int, array> transacciones ejecutadas */
    private static array $transacciones = [];

    public static function limpiar(): void
    {
        self::$tablas = [];
        self::$selects = [];
        self::$transacciones = [];
    }

    /** @return array<int, array> */
    public static function transacciones(): array
    {
        return self::$transacciones;
    }

    public static function instalar(): void
    {
        DB::shouldReceive('transaction')
            ->andReturnUsing(function (callable $callback, int $intentos = 1) {
                self::$transacciones[] = ['intentos' => $intentos];

                return $callback();
            });

        DB::shouldReceive('beginTransaction')->andReturn(true);
        DB::shouldReceive('commit')->andReturn(true);
        DB::shouldReceive('rollBack')->andReturn(true);

        DB::shouldReceive('table')->andReturnUsing(
            static fn (string $tabla) => (self::$tablas[$tabla] ?? new TablaFalsa)->consulta($tabla)
        );

        DB::shouldReceive('select')->andReturnUsing(
            static fn (string $sql, array $bindings = []) => self::resolverSelect($sql)
        );

        DB::shouldReceive('selectOne')->andReturnUsing(
            static function (string $sql, array $bindings = []) {
                $r = self::resolverSelect($sql);

                return is_array($r) ? ($r[0] ?? null) : null;
            }
        );

        DB::shouldReceive('insert')->andReturn(true);
        DB::shouldReceive('update')->andReturn(1);
        DB::shouldReceive('delete')->andReturn(1);
    }

    /** Registra las filas que `DB::table($nombre)` debe devolver. */
    public static function tabla(string $nombre, array $filas = []): TablaFalsa
    {
        // Reutiliza la tabla si ya existe: pedirla de nuevo no debe borrar
        // lo que el flujo bajo prueba ya escribio.
        if (isset(self::$tablas[$nombre])) {
            if ($filas !== []) {
                self::$tablas[$nombre]->filas = $filas;
            }

            return self::$tablas[$nombre];
        }

        return self::$tablas[$nombre] = new TablaFalsa($filas);
    }

    /**
     * Conecta `DB::table($nombre)` con el almacen de un doble de modelo.
     * Ambos caminos ven las mismas filas, como en MySQL.
     */
    public static function tablaEnModelo(string $nombre, string $modelo): TablaFalsa
    {
        return self::$tablas[$nombre] = new TablaDeModeloFalsa($modelo);
    }

    /**
     * Encola un resultado para los `DB::select()` cuyo SQL contenga $corte.
     * No se consume: el mismo SQL puede repetirse en el mismo flujo (por
     * ejemplo al validar y luego al actualizar).
     */
    public static function select(string $corte, mixed $resultado): void
    {
        self::$selects[] = ['corte' => $corte, 'resultado' => $resultado];
    }

    private static function resolverSelect(string $sql): mixed
    {
        foreach (self::$selects as $programado) {
            if (str_contains($sql, $programado['corte'])) {
                return $programado['resultado'];
            }
        }

        return [];
    }
}
