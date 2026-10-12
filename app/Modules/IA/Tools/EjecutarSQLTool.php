<?php

namespace App\Modules\IA\Tools;

use App\Modules\IA\Data\IAData;
use App\Modules\IA\Service\SqlGuard;

/**
 * Consulta de solo lectura contra la base de datos.
 *
 * Todo SQL pasa por SqlGuard (3 capas) y por el tope de filas. Un rechazo se
 * devuelve como texto para que el modelo corrija, nunca se lanza.
 */
class EjecutarSQLTool implements ToolInterface
{
    public function nombre(): string
    {
        return 'ejecutar_sql';
    }

    public function definicion(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->nombre(),
                'description' => 'Ejecuta una consulta SQL de SOLO LECTURA (SELECT, WITH) y devuelve las filas. '
                    .'Reglas: (1) solo SELECT/WITH, nunca INSERT/UPDATE/DELETE/DROP ni multiples comandos separados por ";". '
                    .'(2) las consultas se leen desde el servidor de replicas, asi que pueden estar retrasadas. '
                    .'(3) el resultado se trunca al tope de filas configurado: si necesitas totales, usa COUNT, SUM o GROUP BY en lugar de traer la tabla entera. '
                    .'(4) escribe el SQL a medida de lo que el usuario pidio, sobre las tablas del dominio. No consultes vistas.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sql' => [
                            'type' => 'string',
                            'description' => 'Consulta SQL de solo lectura. Empieza por SELECT o WITH. Sin punto y coma final.',
                        ],
                    ],
                    'required' => ['sql'],
                ],
            ],
        ];
    }

    public function ejecutar(array $args): string
    {
        $sql = trim((string) ($args['sql'] ?? ''));

        $error = SqlGuard::validar($sql);
        if ($error !== null) {
            return '[ERROR] SQL rechazado: '.$error;
        }

        $max = (int) config('ia.max_filas');
        $limitado = SqlGuard::forzarLimite($sql, $max);

        try {
            $resultado = IAData::ejecutar($limitado);
        } catch (\Throwable $e) {
            return '[ERROR] SQL invalido: '.$e->getMessage();
        }

        $salida = [
            'columnas' => $resultado['columnas'],
            'filas' => $resultado['filas'],
            'total_filas_devueltas' => count($resultado['filas']),
        ];

        // Sin esto el modelo concluye que la tabla solo tiene esas filas.
        if (count($resultado['filas']) >= $max) {
            $salida['aviso'] = "Se devolvieron las primeras {$max} filas, que es el tope permitido. "
                .'El resultado esta truncado: no concluyas que no hay mas registros. '
                .'Si el usuario necesita un total o un ranking, usa COUNT, SUM o GROUP BY en el SQL.';
        }

        return json_encode($salida, JSON_UNESCAPED_UNICODE);
    }
}
