<?php

namespace App\Modules\IA\Tools;

/**
 * Instala las 4 herramientas del modulo y las despacha por nombre.
 *
 * Sin provider ni binding de contenedor: las clases son concretas y las
 * construye aqui, asi que el contenedor no tiene nada que resolver.
 */
class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    public function __construct()
    {
        foreach ([
            new ListarTablasTool,
            new DescribirTablaTool,
            new EjecutarSQLTool,
            new GenerarReporteTool,
        ] as $tool) {
            $this->tools[$tool->nombre()] = $tool;
        }
    }

    /**
     * Todas las definiciones, listas para el parametro "tools" de MiniMax.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definiciones(): array
    {
        return array_values(array_map(
            static fn (ToolInterface $t) => $t->definicion(),
            $this->tools,
        ));
    }

    public function tiene(string $nombre): bool
    {
        return isset($this->tools[$nombre]);
    }

    /**
     * @return array<int, string>
     */
    public function nombres(): array
    {
        return array_keys($this->tools);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function ejecutar(string $nombre, array $args): string
    {
        if (! $this->tiene($nombre)) {
            return "[ERROR] Herramienta desconocida: {$nombre}";
        }

        try {
            return $this->tools[$nombre]->ejecutar($args);
        } catch (\Throwable $e) {
            return '[ERROR] '.$e->getMessage();
        }
    }
}
