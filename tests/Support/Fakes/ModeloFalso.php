<?php

namespace Tests\Support\Fakes;

/**
 * Base de los dobles de los modelos Eloquent.
 *
 * Sustituye a `App\Models\*` durante las pruebas. No es un mock de framework:
 * es una tabla en memoria con el mismo contrato de metodos estaticos que el
 * proyecto usa (`Model::where()`, `Model::insertGetId()`, ...), que registra
 * cada escritura para poder asertar sobre ella.
 *
 * El estado vive en un registro estatico indexado por clase, de modo que cada
 * modelo tiene su propio almacen sin repetir declaraciones de propiedades.
 */
abstract class ModeloFalso
{
    /** @var array<class-string, array{filas: array, inserts: array, updates: array, deletes: array, consultas: array, siguienteId: int}> */
    private static array $estado = [];

    /** @var array<class-string> */
    private static array $registrados = [];

    private function __construct() {}

    /* ------------------------------------------------------------------ */
    /* Gestion del estado (usado por los tests) */
    /* ------------------------------------------------------------------ */

    public static function sembrar(array $filas): void
    {
        $estado = self::estado();

        foreach ($filas as $fila) {
            $fila['id'] = $fila['id'] ?? $estado['siguienteId']++;
            $estado['filas'][] = $fila;
        }

        self::__setState(static::class, $estado);
    }

    /** @return array<int, array> */
    public static function filas(): array
    {
        return self::estado()['filas'];
    }

    public static function fila(int $id): ?array
    {
        foreach (self::estado()['filas'] as $fila) {
            if ((int) ($fila['id'] ?? 0) === $id) {
                return $fila;
            }
        }

        return null;
    }

    /** @return array<int, array> filas insertadas, en orden */
    public static function inserts(): array
    {
        return self::estado()['inserts'];
    }

    /** @return array<int, array{id:int, datos:array}> */
    public static function updates(): array
    {
        return self::estado()['updates'];
    }

    public static function deletes(): array
    {
        return self::estado()['deletes'];
    }

    /** @return array<int, array> */
    public static function consultas(): array
    {
        return self::estado()['consultas'];
    }

    public static function limpiar(): void
    {
        unset(self::$estado[static::class]);
        self::registrar(static::class);
    }

    /** Limpia todos los dobles de una vez (usar en setUp). */
    public static function limpiarTodo(): void
    {
        foreach (array_keys(self::$estado) as $modelo) {
            unset(self::$estado[$modelo]);
        }

        foreach (self::$registrados as $modelo) {
            self::estado($modelo);
        }
    }

    /** @return array<class-string> */
    public static function modelosRegistrados(): array
    {
        return self::$registrados;
    }

    /* ------------------------------------------------------------------ */
    /* Contrato estatico de Eloquent que usa la capa Data */
    /* ------------------------------------------------------------------ */

    public static function query(): ConsultaFalsa
    {
        return new ConsultaFalsa(static::class);
    }

    public static function where(string|array $columna, mixed $operador = null, mixed $valor = null): ConsultaFalsa
    {
        return self::query()->where($columna, $operador, $valor);
    }

    public static function whereIn(string $columna, mixed $valores): ConsultaFalsa
    {
        return self::query()->whereIn($columna, $valores);
    }

    public static function whereNotExists($callback): ConsultaFalsa
    {
        return self::query()->whereNotExists($callback);
    }

    public static function whereNull(string $columna): ConsultaFalsa
    {
        return self::query()->whereNull($columna);
    }

    public static function whereNotNull(string $columna): ConsultaFalsa
    {
        return self::query()->whereNotNull($columna);
    }

    public static function whereDate(string $columna, mixed $fecha): ConsultaFalsa
    {
        return self::query()->whereDate($columna, $fecha);
    }

    public static function whereYear(string $columna, int $anio): ConsultaFalsa
    {
        return self::query()->whereYear($columna, $anio);
    }

    public static function whereMonth(string $columna, int $mes): ConsultaFalsa
    {
        return self::query()->whereMonth($columna, $mes);
    }

    public static function whereBetween(string $columna, array $rango): ConsultaFalsa
    {
        return self::query()->whereBetween($columna, $rango);
    }

    public static function whereRaw(string $sql, array $bindings = []): ConsultaFalsa
    {
        return self::query()->whereRaw($sql, $bindings);
    }

    public static function orderBy(string $columna, string $direccion = 'asc'): ConsultaFalsa
    {
        return self::query()->orderBy($columna, $direccion);
    }

    public static function select(string $columnas = '*'): ConsultaFalsa
    {
        return self::query()->select($columnas);
    }

    public static function selectRaw(string $expresion, array $bindings = []): ConsultaFalsa
    {
        return self::query()->selectRaw($expresion, $bindings);
    }

    public static function latest(string $columna = 'created_at'): ConsultaFalsa
    {
        return self::query()->latest($columna);
    }

    public static function oldest(string $columna = 'created_at'): ConsultaFalsa
    {
        return self::query()->oldest($columna);
    }

    public static function limit(int $n): ConsultaFalsa
    {
        return self::query()->limit($n);
    }

    public static function join(...$args): ConsultaFalsa
    {
        return self::query()->join(...$args);
    }

    public static function get($columnas = ['*']): \Illuminate\Support\Collection
    {
        return self::query()->get($columnas);
    }

    public static function all($columnas = ['*']): \Illuminate\Support\Collection
    {
        return self::query()->get($columnas);
    }

    public static function first($columnas = ['*']): ?Fila
    {
        return self::query()->first($columnas);
    }

    public static function firstOrFail($columnas = ['*']): Fila
    {
        return self::query()->first($columnas)
            ?? throw new \RuntimeException(static::class.' no encontrado.');
    }

    public static function find(int|string $id, $columnas = ['*']): ?Fila
    {
        return self::query()->find($id, $columnas);
    }

    public static function findOrFail(int|string $id, $columnas = ['*']): Fila
    {
        return self::query()->find($id, $columnas)
            ?? throw new \RuntimeException(static::class." #{$id} no encontrado.");
    }

    public static function findMany(array $ids, $columnas = ['*']): \Illuminate\Support\Collection
    {
        return self::query()->whereIn('id', $ids)->get($columnas);
    }

    public static function pluck(string $columna, ?string $clave = null): \Illuminate\Support\Collection
    {
        return self::query()->pluck($columna, $clave);
    }

    public static function sum(string $columna): float|int
    {
        return self::query()->sum($columna);
    }

    public static function max(string $columna): mixed
    {
        return self::query()->max($columna);
    }

    public static function count(string $columnas = '*'): int
    {
        return self::query()->count($columnas);
    }

    public static function exists(): bool
    {
        return self::query()->exists();
    }

    public static function insert(array $datos): bool
    {
        $estado = self::estado();
        $datos['id'] = $datos['id'] ?? $estado['siguienteId']++;
        $estado['inserts'][] = $datos;
        $estado['filas'][] = $datos;
        self::__setState(static::class, $estado);

        return true;
    }

    public static function insertGetId(array $datos, ?string $secuencia = null): int
    {
        $estado = self::estado();
        $id = $datos['id'] ?? $estado['siguienteId']++;
        $datos['id'] = $id;
        $estado['inserts'][] = $datos;
        $estado['filas'][] = $datos;
        self::__setState(static::class, $estado);

        return (int) $id;
    }

    public static function create(array $datos): Fila
    {
        return new Fila(self::insertGetId($datos));
    }

    public static function update(array $datos): int
    {
        $ids = array_map(
            static fn (array $f) => (int) $f['id'],
            self::filas()
        );

        return self::actualizarFilas(static::class, $ids, $datos);
    }

    public static function delete(): int
    {
        return self::borrarFilas(
            static::class,
            array_map(static fn (array $f) => (int) $f['id'], self::filas())
        );
    }

    /* ------------------------------------------------------------------ */
    /* Puentes que usa ConsultaFalsa */
    /* ------------------------------------------------------------------ */

    /** @return array<int, array> */
    public static function filasDe(string $modelo): array
    {
        return self::estado($modelo)['filas'];
    }

    public static function registrarConsulta(string $modelo, mixed $columnas): void
    {
        $estado = self::estado($modelo);
        $estado['consultas'][] = is_array($columnas) ? $columnas : [$columnas];
        self::__setState($modelo, $estado);
    }

    public static function actualizarFilas(string $modelo, array $ids, array $datos): int
    {
        $estado = self::estado($modelo);
        $afectadas = 0;

        foreach ($estado['filas'] as $i => $fila) {
            if (in_array((int) ($fila['id'] ?? 0), array_map('intval', $ids), true)) {
                $estado['filas'][$i] = array_merge($fila, $datos);
                $estado['updates'][] = ['id' => (int) $fila['id'], 'datos' => $datos];
                $afectadas++;
            }
        }

        self::__setState($modelo, $estado);

        return $afectadas;
    }

    public static function borrarFilas(string $modelo, array $ids): int
    {
        $estado = self::estado($modelo);
        $ids = array_map('intval', $ids);

        $estado['filas'] = array_values(array_filter(
            $estado['filas'],
            static fn (array $f) => ! in_array((int) ($f['id'] ?? 0), $ids, true)
        ));

        foreach ($ids as $id) {
            $estado['deletes'][] = $id;
        }

        self::__setState($modelo, $estado);

        return count($ids);
    }

    /** @param array<int, array> $filas */
    public static function reemplazarFilas(string $modelo, array $filas): void
    {
        $estado = self::estado($modelo);
        $estado['filas'] = array_values($filas);
        self::__setState($modelo, $estado);
    }

    /* ------------------------------------------------------------------ */

    private static function estado(?string $modelo = null): array
    {
        $modelo ??= static::class;

        if (! isset(self::$estado[$modelo])) {
            self::$estado[$modelo] = [
                'filas' => [],
                'inserts' => [],
                'updates' => [],
                'deletes' => [],
                'consultas' => [],
                'siguienteId' => 1,
            ];
            self::registrar($modelo);
        }

        return self::$estado[$modelo];
    }

    private static function registrar(string $modelo): void
    {
        if (! in_array($modelo, self::$registrados, true)) {
            self::$registrados[] = $modelo;
        }
    }

    /** Asignador interno: el estado se guarda como referencia y se reescribe. */
    private static function __setState(string $modelo, array $estado): void
    {
        self::$estado[$modelo] = $estado;
    }
}
