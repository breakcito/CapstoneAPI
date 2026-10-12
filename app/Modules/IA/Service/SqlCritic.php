<?php

namespace App\Modules\IA\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Revisa si el resultado de una consulta le sirve a un dueño de negocio.
 *
 * El modelo escribe SQL con facilidad, pero tiende a devolver lo que la base
 * tiene, no lo que la persona necesita. Sin esta revision, el administrador
 * recibe una tabla de identificadores y nombres de columna tecnicos y no le
 * sirve para tomar una decision.
 *
 * La revision NO reescribe el SQL: solo juzga y enumera que esta mal. El
 * Feedback vuelve al propio modelo, que es quien sabe reformular la consulta.
 *
 * Es una llamada extra al proveedor por cada consulta revisada. Por eso es
 * opcional: si falla o no hay credenciales, el resultado pasa igual. Un
 * revisor caido jamas debe tumbar una consulta que si funcionaba.
 */
class SqlCritic
{
    /** Columnas que un dueño de negocio nunca pidio explicitamente. */
    private const COLUMNAS_RUIDO = [
        'id',
        'id_producto',
        'id_lote',
        'id_almacen',
        'id_unidad_medida',
        'id_empleado',
        'created_at',
        'updated_at',
    ];

    /** Mapeo de nombres tecnicos a como lo diría un administrador. */
    private const NOMBRES_CLAROS = [
        'contenido_por_presentacion' => 'Unidades base por presentación',
        'contenido_x_presentacion' => 'Unidades base por presentación',
        'stock_actual_base' => 'Stock (unidad base)',
        'stock_anterior_base' => 'Stock anterior (unidad base)',
        'stock_resultante_base' => 'Stock resultante (unidad base)',
        'cantidad_movimiento_base' => 'Cantidad movida (unidad base)',
        'costo_por_unidad_base' => 'Costo unitario (unidad base)',
        'valorizado' => 'Valor del inventario (S/)',
        'valor_stock' => 'Valor del inventario (S/)',
    ];

    /**
     * @param  array<int, string>  $columnas
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{util: bool, problemas: array<int, string>}
     */
    public static function evaluar(string $pregunta, array $columnas, array $filas): array
    {
        // Revision barata primero: si hay ruido obvio, no gastamos llamada.
        $problemas = self::problemasEvidentes($columnas);

        $remoto = self::preguntarAlModelo($pregunta, $columnas, $filas);

        if ($remoto !== null) {
            foreach ($remoto as $problema) {
                if (! in_array($problema, $problemas, true)) {
                    $problemas[] = $problema;
                }
            }
        }

        return ['util' => $problemas === [], 'problemas' => $problemas];
    }

    /**
     * Revision local: no necesita red y ya detecta lo mas comun.
     *
     * @param  array<int, string>  $columnas
     * @return array<int, string>
     */
    public static function problemasEvidentes(array $columnas): array
    {
        $problemas = [];

        $ruido = array_values(array_filter(
            $columnas,
            static fn (string $c): bool => in_array(strtolower($c), self::COLUMNAS_RUIDO, true),
        ));

        if ($ruido !== []) {
            $problemas[] = 'Estas columnas son identificadores internos y no le dicen nada '
                .'a un administrador: '.implode(', ', $ruido)
                .'. Quitalas o reemplazalas por el nombre del producto, almacen o lote.';
        }

        foreach ($columnas as $columna) {
            $legible = self::nombreClaro($columna);

            if ($legible !== $columna && $legible !== null) {
                $problemas[] = "La columna '{$columna}' tiene un nombre tecnico. En el reporte "
                    ."deberia llamarse '{$legible}'.";
            }
        }

        return $problemas;
    }

    /**
     * Nombre como lo diria un administrador, o null si ya es legible.
     */
    public static function nombreClaro(string $columna): ?string
    {
        $base = strtolower(trim($columna));

        if (isset(self::NOMBRES_CLAROS[$base])) {
            return self::NOMBRES_CLAROS[$base];
        }

        // snake_case sin un nombre conocido = sospechoso, no legible.
        if (preg_match('/^[a-z0-9]+(_[a-z0-9]+)+$/', $base)) {
            $partes = explode('_', $base);

            // Palabras tecnicas que nunca deberian llegar al administrador.
            $tecnicas = ['id', 'abv', 'uuid', 'hash', 'token', 'created', 'updated', 'deleted'];

            if (array_intersect(array_slice($partes, 0, 1), $tecnicas)) {
                return null;
            }

            return null;
        }

        return null;
    }

    /**
     * Consulta al modelo: que le falta al resultado para servirle a alguien.
     *
     * @param  array<int, string>  $columnas
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<int, string>|null
     */
    private static function preguntarAlModelo(string $pregunta, array $columnas, array $filas): ?array
    {
        $key = (string) config('ia.api_key');

        if ($key === '') {
            return null;
        }

        $muestra = array_slice($filas, 0, 5);

        $payload = [
            'model' => config('ia.model'),
            'stream' => false,
            'max_tokens' => 900,
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un critico de reportes de inventario. Tu unico trabajo es '
                        .'decir que le falta a un resultado para que un administrador de un '
                        .'almacen pueda tomar una decision con el. Eres severo y concreto. '
                        .'Responde SOLO con un arreglo JSON de strings. Si el resultado ya '
                        .'sirve, responde con un arreglo vacio: [].',
                ],
                [
                    'role' => 'user',
                    'content' => "El administrador pidio: \"{$pregunta}\"\n\n"
                        .'El asistente consulto y obtuvo estas columnas: '.implode(', ', $columnas)."\n\n"
                        .'Primeras filas: '.json_encode($muestra, JSON_UNESCAPED_UNICODE)."\n\n"
                        .'Que le falta a este resultado para que le sirva de verdad? Lista cada '
                        .'problema en una frase corta. Si le sirve tal cual, responde [].',
                ],
            ],
        ];

        try {
            $respuesta = Http::withHeaders(['Authorization' => "Bearer {$key}"])
                ->connectTimeout(10)
                ->timeout(60)
                ->post(rtrim((string) config('ia.base_url'), '/').'/chat/completions', $payload);

            if ($respuesta->failed()) {
                return null;
            }

            $texto = $respuesta->json('choices.0.message.content') ?? '';

            if (! is_string($texto) || trim($texto) === '') {
                return null;
            }

            // El modelo puede envolver el JSON en ```json ... ```.
            $limpio = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($texto)) ?? $texto);
            $problemas = json_decode($limpio, true);

            if (! is_array($problemas)) {
                return null;
            }

            return array_values(array_filter(array_map(
                static fn ($p): string => trim((string) $p),
                $problemas,
            )));
        } catch (\Throwable $e) {
            Log::warning('SqlCritic no pudo revisar el resultado: '.$e->getMessage());

            return null;
        }
    }
}
