<?php

namespace App\Modules\IA\Service;

use App\Modules\IA\Prompts\SystemPrompt;
use App\Modules\IA\Tools\ToolRegistry;
use InvalidArgumentException;
use Throwable;

/**
 * Orquesta una peticion de chat: arma el prompt, corre el loop de
 * herramientas y va emitiendo eventos hacia el cliente.
 *
 * El servicio es STATELESS. No guarda nada: el frontend reenvia el historial
 * completo en cada request. Por eso este metodo nunca toca la base de datos
 * directamente (README: regla 1) — la unica excepcion es SchemaService, que
 * solo lee el esquema y ya cachea.
 */
class IAService
{
    /**
     * @param  array<int, array{role: string, content: mixed}>  $mensajes  Historial del cliente.
     * @param  array<int, array<string, string>>  $adjuntos
     * @param  callable(string, array<string, mixed>): void  $emitir  Emisor de eventos SSE.
     */
    public static function streamChat(array $mensajes, array $adjuntos, callable $emitir): void
    {
        $registry = new ToolRegistry;

        $historial = [
            ['role' => 'system', 'content' => SystemPrompt::build(SchemaService::resumen())],
        ];

        foreach ($mensajes as $mensaje) {
            $historial[] = [
                'role' => (string) ($mensaje['role'] ?? 'user'),
                'content' => $mensaje['content'] ?? '',
            ];
        }

        // Los adjuntos se anexan al ultimo turno del usuario y se quedan ahi
        // durante todo el loop: si el modelo consulta la BD despues de mirar
        // la imagen, tiene que seguir viendo la imagen.
        if ($adjuntos !== []) {
            $procesados = AdjuntoExtractor::procesar($adjuntos);
            $indiceUltimo = self::indiceUltimoUsuario($historial);

            if ($indiceUltimo !== null) {
                $historial[$indiceUltimo]['content'] = self::adjuntar(
                    $historial[$indiceUltimo]['content'],
                    $procesados,
                );
            }
        }

        $maxIteraciones = (int) config('ia.max_iteraciones');
        $iteracion = 0;

        while ($iteracion < $maxIteraciones) {
            $iteracion++;

            $client = new MiniMaxClient('', $registry->definiciones());

            $texto = '';
            $razonamiento = [];
            $toolCalls = [];

            try {
                foreach ($client->stream($historial) as $evento) {
                    match ($evento['tipo']) {
                        'texto' => $texto .= $evento['texto'],
                        'razonamiento' => $emitir('razonamiento', ['texto' => $evento['texto']]),
                        'razonamiento_final' => $razonamiento = $evento['detalles'],
                        'tool_call' => $toolCalls[] = [
                            'id' => $evento['id'],
                            'nombre' => $evento['nombre'],
                            'argumentos' => $evento['argumentos'],
                        ],
                        default => null,
                    };
                }
            } catch (Throwable $e) {
                $emitir('error', ['mensaje' => $e->getMessage()]);
                $emitir('fin', []);

                return;
            }

            if ($texto !== '') {
                $emitir('texto', ['texto' => $texto]);
            }

            // Sin tool calls la conversacion termino.
            if ($toolCalls === []) {
                $emitir('fin', []);

                return;
            }

            $historial[] = self::mensajeAsistente($texto, $razonamiento, $toolCalls);

            foreach ($toolCalls as $llamada) {
                $args = self::decodificarArgumentos($llamada['argumentos']);

                if ($args === null) {
                    $emitir('tool', [
                        'nombre' => $llamada['nombre'],
                        'estado' => 'error',
                        'preview' => 'Los argumentos no son JSON valido.',
                    ]);

                    $historial[] = [
                        'role' => 'tool',
                        'tool_call_id' => $llamada['id'],
                        'content' => '[ERROR] Los argumentos de la herramienta no son JSON valido.',
                    ];

                    continue;
                }

                $resultado = $registry->ejecutar($llamada['nombre'], $args);
                $esError = str_starts_with($resultado, '[ERROR]');

                // El usuario nunca ve el nombre de la herramienta ni su
                // preview: solo ve "ok" / "error" cuando algo fallo y debe
                // reintentarse. El system prompt fija las reglas del modelo.
                $emitir('tool', [
                    'nombre' => $llamada['nombre'],
                    'estado' => $esError ? 'error' : 'ok',
                    'preview' => '',
                ]);

                // Un reporte va directo al archivo del usuario: se genera en
                // el servidor y lo descarga. No hay nada que revisar aqui.
                $historial[] = [
                    'role' => 'tool',
                    'tool_call_id' => $llamada['id'],
                    'content' => $llamada['nombre'] === 'generar_reporte'
                        ? $resultado
                        : self::revisar($resultado, $args),
                ];

                // El modelo adjunto un archivo que el usuario puede bajar.
                $reporte = self::detectarReporte($resultado);

                if ($reporte !== null) {
                    $emitir('archivo', [
                        'uuid' => $reporte['uuid'],
                        'nombre' => $reporte['nombre'],
                        'bytes' => $reporte['bytes'],
                    ]);
                }
            }
        }

        $emitir('aviso', [
            'mensaje' => "Se alcanzo el limite de {$maxIteraciones} pasos de analisis. "
                .'La respuesta puede estar incompleta; reformula la pregunta acotando el alcance.',
        ]);
        $emitir('fin', []);
    }

    /**
     * Pasa el resultado por el critico antes de que el modelo lo muestre.
     *
     * Si el resultado no le sirve a un administrador, el problema se le
     * devuelve al modelo para que rehaga la consulta. El bucle de
     * herramientas ya lo vuelve a invocar, asi que la correccion entra sola
     * en la siguiente vuelta.
     */
    private static function revisar(string $resultado, array $args): string
    {
        if (str_starts_with($resultado, '[ERROR]')) {
            return $resultado;
        }

        $datos = json_decode($resultado, true);

        if (! is_array($datos) || ! isset($datos['columnas'], $datos['filas'])) {
            return $resultado;
        }

        if ($datos['filas'] === []) {
            return $resultado;
        }

        $critica = SqlCritic::evaluar(
            (string) ($args['sql'] ?? ''),
            $datos['columnas'],
            $datos['filas'],
        );

        if ($critica['util']) {
            return $resultado;
        }

        return $resultado."\n\n[REVISION DE CALIDAD: este resultado NO le sirve a un "
            .'administrador todavia. Problemas detectados: ]'.PHP_EOL
            .implode(PHP_EOL, array_map(
                static fn (string $p): string => '- '.$p,
                $critica['problemas'],
            ))
            .PHP_EOL.PHP_EOL
            .'Reformula la consulta y vuelve a intentarlo. No le muestres esto al usuario.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $procesados
     * @return array<int, array<string, mixed>>
     */
    private static function adjuntar(mixed $contenido, array $procesados): array
    {
        $partes = [['type' => 'text', 'text' => (string) $contenido]];

        foreach ($procesados as $procesado) {
            if ($procesado['tipo'] === 'imagen') {
                $partes[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $procesado['contenido']],
                ];

                continue;
            }

            $partes[0]['text'] .= "\n\n".$procesado['contenido'];
        }

        return $partes;
    }

    /**
     * @param  array<int, array{role?: string}>  $historial
     */
    private static function indiceUltimoUsuario(array $historial): ?int
    {
        for ($i = count($historial) - 1; $i >= 0; $i--) {
            if (($historial[$i]['role'] ?? null) === 'user') {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, string>>  $toolCalls
     * @return array<string, mixed>
     */
    private static function mensajeAsistente(string $texto, array $razonamiento, array $toolCalls): array
    {
        $mensaje = [
            'role' => 'assistant',
            'content' => $texto,
            'tool_calls' => array_map(static fn (array $c) => [
                'id' => $c['id'],
                'type' => 'function',
                'function' => [
                    'name' => $c['nombre'],
                    'arguments' => $c['argumentos'] === '' ? '{}' : $c['argumentos'],
                ],
            ], $toolCalls),
        ];

        // MiniMax-M3 razona entre llamadas: sin devolver el bloque de
        // razonamiento la cadena de pensamiento se corta en el siguiente turno.
        if ($razonamiento !== []) {
            $mensaje['reasoning_details'] = $razonamiento;
        }

        return $mensaje;
    }

    /**
     * Reservado por compatibilidad: el usuario ya no ve este resumen en la UI.
     * Se conserva el metodo por si vuelve a hacer falta para un panel admin o
     * para el sistema de logs. Devuelve string vacio para no contaminar el chat.
     */
    private static function resumirHerramienta(string $nombre, string $resultado): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodificarArgumentos(string $json): ?array
    {
        if (trim($json) === '') {
            return [];
        }

        $decodificado = json_decode($json, true);

        return is_array($decodificado) ? $decodificado : null;
    }

    /**
     * @return array{uuid: string, nombre: string, bytes: int}|null
     */
    private static function detectarReporte(string $resultado): ?array
    {
        if (str_starts_with($resultado, '[ERROR]')) {
            return null;
        }

        $datos = json_decode($resultado, true);

        if (! is_array($datos) || ! isset($datos['uuid'], $datos['nombre'])) {
            return null;
        }

        return [
            'uuid' => (string) $datos['uuid'],
            'nombre' => (string) $datos['nombre'],
            'bytes' => (int) ($datos['bytes'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $adjuntos
     *
     * @throws InvalidArgumentException si un adjunto no es procesable
     */
    public static function validarAdjuntos(array $adjuntos): void
    {
        AdjuntoExtractor::procesar($adjuntos);
    }
}
