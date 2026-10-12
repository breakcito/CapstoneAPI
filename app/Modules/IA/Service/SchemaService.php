<?php

namespace App\Modules\IA\Service;

use App\Modules\IA\Data\IAData;

/**
 * Resumen del esquema que se inyecta en el system prompt.
 *
 * Se cachea en un static del proceso con TTL, no con Cache::remember: el
 * proyecto nunca usa la facade Cache y CACHE_STORE apunta a 'database', donde
 * no hay garantia de que exista la tabla. Un static no puede fallar.
 */
class SchemaService
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $cache = null;

    private static int $cacheadoEn = 0;

    /**
     * Tablas del dominio con sus columnas.
     *
     * Las vistas se excluyen a proposito: el asistente escribe el SQL de cada
     * reporte sobre las tablas, no consulta vistas pre-armadas. Exponerlas en
     * el esquema es lo que lo empujaba a devolver un agregado por almacen
     * cuando el usuario pedia el detalle por producto.
     *
     * @return array<int, array{tabla: string, tipo: string, filas_estimadas: int, columnas: array<int, string>}>
     */
    public static function resumen(): array
    {
        $ttl = (int) config('ia.schema_ttl');

        if (self::$cache !== null && (time() - self::$cacheadoEn) < $ttl) {
            return self::$cache;
        }

        $descripciones = IAData::describirEsquema();
        $resumen = [];

        foreach (IAData::listarTablas() as $tabla) {
            if (($tabla['tipo'] ?? '') === 'VIEW') {
                continue;
            }

            $nombre = $tabla['nombre'];

            $resumen[] = [
                'tabla' => $nombre,
                'tipo' => $tabla['tipo'],
                'filas_estimadas' => $tabla['filas_estimadas'],
                'columnas' => $descripciones[$nombre]['columnas'] ?? [],
            ];
        }

        self::$cache = $resumen;
        self::$cacheadoEn = time();

        return $resumen;
    }

    /**
     * @return array<int, string>
     */
    public static function columnasDe(string $tabla): array
    {
        foreach (self::resumen() as $t) {
            if ($t['tabla'] === $tabla) {
                return $t['columnas'];
            }
        }

        return [];
    }

    /**
     * Solo para pruebas: fuerza el siguiente resumen() a releer el esquema.
     */
    public static function olvidarCache(): void
    {
        self::$cache = null;
        self::$cacheadoEn = 0;
    }
}
