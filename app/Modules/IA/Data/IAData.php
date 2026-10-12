<?php

namespace App\Modules\IA\Data;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Unica capa del modulo IA con acceso a SQL (README: reglas 1 y 2).
 *
 * Expone el esquema (information_schema) y ejecuta consultas de solo lectura.
 * Quien llama ya paso antes por SqlGuard.
 */
class IAData
{
    /**
     * Esquemas del motor que jamas se le muestran al modelo.
     */
    private const ESQUEMAS_OCULTOS = [
        'information_schema',
        'performance_schema',
        'mysql',
        'sys',
    ];

    /**
     * Tablas y vistas de la base de datos, con su tipo.
     */
    public static function listarTablas(): array
    {
        $sql = '
        SELECT
            table_name AS nombre,
            table_type AS tipo,
            table_rows AS filas_estimadas,
            table_comment AS comentario
        FROM information_schema.tables
        WHERE table_schema = ?
          AND table_type IN ("BASE TABLE", "VIEW")
        ORDER BY table_name
        ';

        $ocultos = "'".implode("','", self::ESQUEMAS_OCULTOS)."'";

        $filas = DB::select($sql." AND table_schema NOT IN ({$ocultos})", [
            self::nombreBaseDatos(),
        ]);

        return array_map(static fn ($f) => [
            'nombre' => (string) $f->nombre,
            'tipo' => (string) $f->tipo,
            'filas_estimadas' => (int) ($f->filas_estimadas ?? 0),
            'comentario' => (string) ($f->comentario ?? ''),
        ], $filas);
    }

    /**
     * Columnas, indices y volumen estimado de una tabla o vista.
     *
     * @throws InvalidArgumentException si el nombre no existe en el esquema.
     */
    public static function describirTabla(string $tabla): array
    {
        self::verificarTablaExiste($tabla);

        $sqlColumnas = '
        SELECT
            column_name AS nombre,
            column_type AS tipo,
            is_nullable AS nullable,
            column_key AS clave,
            column_default AS valor_defecto,
            column_comment AS comentario,
            ordinal_position AS orden
        FROM information_schema.columns
        WHERE table_schema = ? AND table_name = ?
        ORDER BY ordinal_position
        ';

        $sqlIndices = '
        SELECT DISTINCT index_name AS nombre
        FROM information_schema.statistics
        WHERE table_schema = ? AND table_name = ?
        ORDER BY index_name
        ';

        $columnas = DB::select($sqlColumnas, [self::nombreBaseDatos(), $tabla]);
        $indices = DB::select($sqlIndices, [self::nombreBaseDatos(), $tabla]);

        return [
            'columnas' => array_map(static fn ($c) => [
                'nombre' => (string) $c->nombre,
                'tipo' => (string) $c->tipo,
                'nullable' => $c->nullable === 'YES',
                'clave' => (string) ($c->clave ?? ''),
                'valor_defecto' => $c->valor_defecto,
                'comentario' => (string) ($c->comentario ?? ''),
            ], $columnas),
            'indices' => array_map(static fn ($i) => (string) $i->nombre, $indices),
        ];
    }

    /**
     * Columnas e indices de TODO el esquema en dos consultas.
     *
     * Existe separada de describirTabla() por rendimiento: contra un MySQL
     * remoto, 37 tablas x 2 consultas son 74 round-trips (~46 s) y PHP mata el
     * proceso a los 30 s. Con estas dos consultas el resumen baja a ~1 s.
     *
     * @return array<string, array{columnas: array<int, string>, indices: array<int, string>}>
     */
    public static function describirEsquema(): array
    {
        $base = self::nombreBaseDatos();
        $ocultos = "'".implode("','", self::ESQUEMAS_OCULTOS)."'";

        $columnas = DB::select(
            'SELECT table_name AS tabla, column_name AS nombre, ordinal_position AS orden
             FROM information_schema.columns
             WHERE table_schema = ? AND table_schema NOT IN ('.$ocultos.')
             ORDER BY table_name, ordinal_position',
            [$base],
        );

        $indices = DB::select(
            'SELECT DISTINCT table_name AS tabla, index_name AS nombre
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_schema NOT IN ('.$ocultos.')
             ORDER BY table_name, index_name',
            [$base],
        );

        $esquema = [];

        foreach ($columnas as $fila) {
            $esquema[(string) $fila->tabla]['columnas'][] = (string) $fila->nombre;
        }

        foreach ($indices as $fila) {
            $esquema[(string) $fila->tabla]['indices'][] = (string) $fila->nombre;
        }

        foreach ($esquema as $tabla => $datos) {
            $esquema[$tabla] += ['indices' => []];
        }

        return $esquema;
    }

    /**
     * Ejecuta una consulta de solo lectura y devuelve columnas + filas.
     *
     * @return array{columnas: string[], filas: array<int, array<string, mixed>>}
     */
    public static function ejecutar(string $sql): array
    {
        $resultado = DB::select($sql);

        if ($resultado === []) {
            return ['columnas' => [], 'filas' => []];
        }

        // El orden de claves de la primera fila define el orden de columnas.
        $columnas = array_keys((array) $resultado[0]);
        $filas = [];

        foreach ($resultado as $fila) {
            $filas[] = (array) $fila;
        }

        return ['columnas' => $columnas, 'filas' => $filas];
    }

    /**
     * Impide que un nombre de tabla manipule information_schema.
     */
    private static function verificarTablaExiste(string $tabla): void
    {
        $existe = false;

        foreach (self::listarTablas() as $t) {
            if ($t['nombre'] === $tabla) {
                $existe = true;
                break;
            }
        }

        if (! $existe) {
            throw new InvalidArgumentException("La tabla o vista '{$tabla}' no existe en el esquema.");
        }
    }

    private static function nombreBaseDatos(): string
    {
        return (string) DB::connection()->getDatabaseName();
    }
}
