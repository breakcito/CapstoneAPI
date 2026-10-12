<?php

namespace App\Modules\IA\Tools;

use App\Modules\IA\Data\IAData;
use InvalidArgumentException;

/**
 * Columnas, tipos e indices de una tabla o vista concreta.
 */
class DescribirTablaTool implements ToolInterface
{
    public function nombre(): string
    {
        return 'describir_tabla';
    }

    public function definicion(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->nombre(),
                'description' => 'Describe una tabla o vista: columnas con su tipo, si admite nulos, clave primaria, valor por defecto y comentario, mas los indices disponibles. Llama a listar_tablas antes si dudas del nombre exacto.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tabla' => [
                            'type' => 'string',
                            'description' => 'Nombre exacto de la tabla o vista, tal como aparece en listar_tablas.',
                        ],
                    ],
                    'required' => ['tabla'],
                ],
            ],
        ];
    }

    public function ejecutar(array $args): string
    {
        $tabla = trim((string) ($args['tabla'] ?? ''));

        if ($tabla === '') {
            return '[ERROR] Falta el parametro "tabla".';
        }

        try {
            $descripcion = IAData::describirTabla($tabla);
        } catch (InvalidArgumentException $e) {
            return '[ERROR] '.$e->getMessage().' Usa listar_tablas para ver los nombres validos.';
        }

        return json_encode([
            'tabla' => $tabla,
            'descripcion' => $descripcion,
        ], JSON_UNESCAPED_UNICODE);
    }
}
