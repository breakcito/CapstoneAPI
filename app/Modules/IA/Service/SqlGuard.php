<?php

namespace App\Modules\IA\Service;

/**
 * Guardia de solo lectura para el SQL que propone el modelo.
 *
 * Tres capas independientes, en este orden:
 *   1. Estructura  -> solo se admite un unico comando que empiece por una
 *                     palabra de lectura. Descarta multi-statement.
 *   2. Lista negra -> palabras clave de escritura y funciones de MySQL que
 *                     permiten exfiltrar a disco o denegar servicio.
 *   3. Tope de filas-> forzado aparte, en forzarLimite().
 *
 * Ninguna excepcion escapa: devolver un string es lo unico que hace este
 * metodo. El llamador lo devuelve al modelo como resultado de la herramienta.
 */
class SqlGuard
{
    /**
     * Devuelve null si el SQL puede ejecutarse, o el motivo del rechazo.
     */
    public static function validar(string $sql): ?string
    {
        $sql = self::normalizar($sql);

        if ($sql === '') {
            return 'La consulta esta vacia.';
        }

        $error = self::validarEstructura($sql);
        if ($error !== null) {
            return $error;
        }

        $error = self::validarTablasProhibidas($sql);
        if ($error !== null) {
            return $error;
        }

        return self::validarListaNegra($sql);
    }

    /**
     * Capa 2: las tablas que el asistente no puede consultar.
     *
     * Las vistas BI quedan fuera a proposito: el reporte se arma con SQL
     * propio sobre las tablas del dominio. Sin este chequeo, el modelo elige
     * la vista de inventario y devuelve un agregado por almacen cuando le
     * pidieron el detalle por producto.
     */
    private static function validarTablasProhibidas(string $sql): ?string
    {
        foreach (config('ia.tablas_prohibidas', []) as $prefijo) {
            if (preg_match('/\b(?:FROM|JOIN)\s+`?('.preg_quote($prefijo, '/').'[^`\s(,]*)/i', $sql, $c)) {
                return "La consulta no puede usar la vista {$c[1]}. "
                    .'Escribe el SQL sobre las tablas del dominio, una fila por producto.';
            }
        }

        return null;
    }

    /**
     * Quita comentarios y delimitadores sobrantes para no validar sobre texto
     * que el motor ignoraria pero que un regex podria dejar pasar.
     */
    private static function normalizar(string $sql): string
    {
        $sql = preg_replace('~/\*.*?\*/~s', ' ', $sql) ?? $sql;
        $sql = preg_replace('/(?:--|#)[^\n]*/', ' ', $sql) ?? $sql;
        $sql = trim($sql);
        $sql = rtrim($sql, "; \t\n\r\0\x0B;");

        return trim($sql);
    }

    /**
     * Capa 1: un solo comando, y de lectura.
     */
    private static function validarEstructura(string $sql): ?string
    {
        $permitidas = 'SELECT|WITH|SHOW|DESCRIBE|DESC|EXPLAIN';

        if (! preg_match('/^('.$permitidas.')\b/i', $sql)) {
            return 'Solo se permiten consultas de lectura que empiecen por SELECT, WITH, SHOW, DESCRIBE o EXPLAIN.';
        }

        // Un ";" sin limpiar significa que hay un segundo comando encadenado.
        if (str_contains($sql, ';')) {
            return 'No se permite encadenar varios comandos en una misma consulta.';
        }

        return null;
    }

    /**
     * Capa 2: palabras clave y funciones peligrosas.
     */
    private static function validarListaNegra(string $sql): ?string
    {
        foreach (config('ia.sql_palabras_prohibidas') as $palabra) {
            if (preg_match('/\b'.$palabra.'\b/i', $sql)) {
                return "La palabra reservada {$palabra} no esta permitida en una consulta de solo lectura.";
            }
        }

        foreach (config('ia.sql_funciones_prohibidas') as $patron) {
            if (preg_match('/\b'.$patron.'\b/i', $sql)) {
                return 'La consulta usa una funcion no permitida (escritura a disco o ejecucion diferida).';
            }
        }

        // REPLACE() sobre columnas es legitimo; REPLACE INTO es una escritura.
        if (preg_match('/\bREPLACE\s+INTO\b/i', $sql)) {
            return 'REPLACE INTO es una escritura y no esta permitido.';
        }

        // Bloqueos de fila: Toma el lock del kardex mientras dura la consulta.
        if (preg_match('/\bFOR\s+UPDATE\b|\bLOCK\s+IN\s+SHARE\s+MODE\b|\bFOR\s+SHARE\b/i', $sql)) {
            return 'No se permiten bloqueos de fila (FOR UPDATE / LOCK IN SHARE MODE).';
        }

        return null;
    }

    /**
     * Capa 3: deja el LIMIT en max, o lo baja si el modelo pidió menos.
     *
     * Si el modelo pide "LIMIT 10" y le devolvemos 500 filas, el mismo cree
     * que se truncó a 10 y se contradice con su propia conclusion. Por eso un
     * LIMIT mas pequeno que el tope se respeta tal cual.
     *
     * La forma "LIMIT offset, count" siempre se reemplaza: al recortarla el
     * offset queda fuera de rango y MySQL la rechaza.
     */
    public static function forzarLimite(string $sql, ?int $max = null): string
    {
        $max = $max ?? (int) config('ia.max_filas');
        $sql = self::normalizar($sql);

        if (preg_match('/\s+LIMIT\s+(\d+)\s*$/i', $sql, $coincidencia) === 1) {
            if ((int) $coincidencia[1] <= $max) {
                return $sql;
            }
        }

        $sql = preg_replace('/\s+LIMIT\s+\d+(?:\s*,\s*\d+)?\s*$/i', '', $sql) ?? $sql;

        return trim($sql).' LIMIT '.$max;
    }
}
