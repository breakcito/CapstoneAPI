<?php

namespace App\Modules\IA\Tools;

use App\Modules\IA\Data\IAData;

/**
 * Punto de entrada del modelo al esquema: que tablas y vistas existen.
 *
 * Las vistas v_bi_* salen junto a las tablas, y el system prompt las prioriza.
 */
class ListarTablasTool implements ToolInterface
{
    public function nombre(): string
    {
        return 'listar_tablas';
    }

    public function definicion(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->nombre(),
                'description' => 'Lista las tablas de la base de datos con su tipo y numero aproximado de filas. Usa esto primero si no sabes que tablas existen. Cada reporte se arma con SQL propio sobre estas tablas.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                    'required' => [],
                ],
            ],
        ];
    }

    public function ejecutar(array $args): string
    {
        $tablas = IAData::listarTablas();

        if ($tablas === []) {
            return 'El esquema no devolvio ninguna tabla.';
        }

        return json_encode(['tablas' => $tablas], JSON_UNESCAPED_UNICODE);
    }
}
