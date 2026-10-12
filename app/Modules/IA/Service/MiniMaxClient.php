<?php

namespace App\Modules\IA\Service;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de MiniMax en formato compatible con OpenAI, con streaming.
 *
 * Por que reasoning_split: MiniMax-M3 razona siempre. Sin este parametro el
 * razonamiento llega metido dentro de `content` envuelto en etiquetas <think> y
 * se paintaria en pantalla. Con el, sale limpio en `reasoning_details` y
 * `content` es solo la respuesta.
 *
 * Por que se acumula tool_calls por indice: el stream llega partido, el id y el
 * nombre en un primer delta y los argumentos en fragmentos JSON sueltos.
 */
class MiniMaxClient
{
    /** Bytes que se leen del stream en cada vuelta. */
    private const TAMANO_CHUNK = 4096;

    /** @var array<int, array<string, mixed>> */
    private array $tools;

    public function __construct(private string $systemPrompt, array $tools = [])
    {
        $this->tools = $tools;
    }

    /**
     * Envia la conversacion y emite los eventos conforme llegan.
     *
     * @param  array<int, array<string, mixed>>  $messages  Ya incluye el system.
     * @return Generator<int, array<string, mixed>>
     *
     * @throws RuntimeException si el proveedor falla o no hay credenciales
     */
    public function stream(array $messages): Generator
    {
        // El system va obligatoriamente primero; si el caller ya lo puso, se respeta.
        if (($messages[0]['role'] ?? null) !== 'system') {
            array_unshift($messages, ['role' => 'system', 'content' => $this->systemPrompt]);
        }

        $payload = [
            'model' => config('ia.model'),
            'messages' => $messages,
            'stream' => true,
            'reasoning_split' => true,
            'max_tokens' => 8192,
            'temperature' => 0.2,
        ];

        if ($this->tools !== []) {
            $payload['tools'] = $this->tools;
        }

        $body = $this->enviar($payload);
        $buffer = '';
        $toolCalls = [];
        $razonamiento = [];
        $finishReason = null;

        while (! $body->eof()) {
            $buffer .= $body->read(self::TAMANO_CHUNK);

            // Los eventos SSE se separan por una linea en blanco.
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $bloque = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $dato = $this->extraerDato($bloque);

                if ($dato === null) {
                    continue;
                }

                if ($dato === '[DONE]') {
                    break 2;
                }

                $evento = json_decode($dato, true);

                if (! is_array($evento)) {
                    continue;
                }

                $this->verificarErrorProveedor($evento);

                $opciones = $evento['choices'][0] ?? [];
                $delta = $opciones['delta'] ?? [];

                if (($opciones['finish_reason'] ?? null) !== null) {
                    $finishReason = (string) $opciones['finish_reason'];
                }

                // El razonamiento llega troceado igual que el texto.
                foreach ((array) ($delta['reasoning_details'] ?? []) as $detalle) {
                    $texto = (string) ($detalle['text'] ?? '');
                    if ($texto !== '') {
                        $razonamiento[] = $detalle;
                        yield ['tipo' => 'razonamiento', 'texto' => $texto];
                    }
                }

                // Algunos despliegues no separan y mandan el campo directo.
                if (! empty($delta['reasoning_content'])) {
                    $razonamiento[] = ['type' => 'reasoning.text', 'text' => $delta['reasoning_content']];
                    yield ['tipo' => 'razonamiento', 'texto' => (string) $delta['reasoning_content']];
                }

                if (! empty($delta['content'])) {
                    yield ['tipo' => 'texto', 'texto' => (string) $delta['content']];
                }

                foreach ((array) ($delta['tool_calls'] ?? []) as $llamada) {
                    $this->acumularToolCall($toolCalls, $llamada);
                }
            }
        }

        foreach ($toolCalls as $llamada) {
            yield [
                'tipo' => 'tool_call',
                'id' => $llamada['id'],
                'nombre' => $llamada['nombre'],
                'argumentos' => $llamada['argumentos'],
            ];
        }

        if ($razonamiento !== []) {
            yield ['tipo' => 'razonamiento_final', 'detalles' => $razonamiento];
        }

        yield ['tipo' => 'fin', 'razon' => $finishReason];
    }

    /**
     * Reensambla un tool_call partido a lo largo de varios deltas.
     *
     * @param  array<int|string, array<string, string>>  $acumulado
     * @param  array<string, mixed>  $llamada
     */
    private function acumularToolCall(array &$acumulado, array $llamada): void
    {
        $indice = (string) ($llamada['index'] ?? 0);

        if (! isset($acumulado[$indice])) {
            $acumulado[$indice] = ['id' => '', 'nombre' => '', 'argumentos' => ''];
        }

        if (! empty($llamada['id'])) {
            $acumulado[$indice]['id'] .= (string) $llamada['id'];
        }

        $funcion = (array) ($llamada['function'] ?? []);

        if (! empty($funcion['name'])) {
            $acumulado[$indice]['nombre'] .= (string) $funcion['name'];
        }

        if (! empty($funcion['arguments'])) {
            $acumulado[$indice]['argumentos'] .= (string) $funcion['arguments'];
        }
    }

    /**
     * Devuelve la linea data: de un bloque SSE, o null si no la tiene.
     */
    private function extraerDato(string $bloque): ?string
    {
        foreach (explode("\n", $bloque) as $linea) {
            $linea = trim($linea);

            if (str_starts_with($linea, 'data:')) {
                $dato = trim(substr($linea, 5));
                if ($dato !== '') {
                    return $dato;
                }
            }
        }

        return null;
    }

    /**
     * MiniMax reporta errores con HTTP 200 y base_resp.status_code != 0.
     *
     * @param  array<string, mixed>  $evento
     */
    private function verificarErrorProveedor(array $evento): void
    {
        $base = $evento['base_resp'] ?? null;

        if (is_array($base) && (int) ($base['status_code'] ?? 0) !== 0) {
            throw new RuntimeException(sprintf(
                'MiniMax respondio con error %s: %s',
                (string) ($base['status_code'] ?? '?'),
                (string) ($base['status_msg'] ?? 'sin mensaje'),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return \Psr\Http\Message\StreamInterface
     */
    private function enviar(array $payload)
    {
        $key = (string) config('ia.api_key');

        if ($key === '') {
            throw new RuntimeException('Falta IA_API_KEY en el archivo .env.');
        }

        $url = rtrim((string) config('ia.base_url'), '/').'/chat/completions';

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$key}",
                'Content-Type' => 'application/json',
            ])
                ->connectTimeout(15)
                // Sin timeout global: una respuesta larga es legitima y el corte
                // lo controla el cliente con el boton de detener.
                ->timeout(600)
                ->withOptions(['stream' => true])
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('No se pudo conectar con el proveedor de IA: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'El proveedor de IA respondio %d: %s',
                $response->status(),
                mb_substr($response->body(), 0, 500),
            ));
        }

        return $response->toPsrResponse()->getBody();
    }
}
