<?php

namespace App\Modules\IA\Tools;

/**
 * Contrato de una herramienta que el modelo puede invocar.
 *
 * ejecutar() devuelve SIEMPRE un string. Un rechazo (SQL invalido, tabla
 * inexistente) es un resultado, no una excepcion: el modelo necesita leer el
 * motivo para corregir su siguiente intento.
 */
interface ToolInterface
{
    /**
     * Definicion en el formato de function calling de la API.
     */
    public function definicion(): array;

    /**
     * @param  array<string, mixed>  $args  Argumentos ya decodificados del JSON.
     */
    public function ejecutar(array $args): string;

    /**
     * Nombre con el que el modelo invoca la herramienta.
     */
    public function nombre(): string;
}
