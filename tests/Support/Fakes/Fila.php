<?php

namespace Tests\Support\Fakes;

/**
 * Fila falsa: se comporta como un modelo Eloquent en los dos sentidos que
 * usa la capa Data del proyecto.
 *
 * - subscripto por columna  -> $fila['stock_actual']
 * - propiedad magica        -> $fila->correlativo
 * - ->toArray()             -> lo que consumen los Services
 */
class Fila implements \ArrayAccess
{
    public function __construct(public array $atributos = []) {}

    public function toArray(): array
    {
        return $this->atributos;
    }

    public function __get(string $clave): mixed
    {
        return $this->atributos[$clave] ?? null;
    }

    public function __set(string $clave, mixed $valor): void
    {
        $this->atributos[$clave] = $valor;
    }

    public function __isset(string $clave): bool
    {
        return isset($this->atributos[$clave]);
    }

    public function offsetExists(mixed $clave): bool
    {
        return isset($this->atributos[$clave]);
    }

    public function offsetGet(mixed $clave): mixed
    {
        return $this->atributos[$clave] ?? null;
    }

    public function offsetSet(mixed $clave, mixed $valor): void
    {
        if ($clave === null) {
            $this->atributos[] = $valor;
        } else {
            $this->atributos[$clave] = $valor;
        }
    }

    public function offsetUnset(mixed $clave): void
    {
        unset($this->atributos[$clave]);
    }

    public function __toString(): string
    {
        return (string) ($this->atributos['id'] ?? '');
    }
}
