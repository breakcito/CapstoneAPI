<?php

namespace Tests\Support\Fakes;

/**
 * Tabla en memoria para `DB::table(...)`.
 *
 * El proyecto usa el facade DB para SQL crudo (`DB::select`, `DB::table`) y
 * para `DB::transaction`. Este doble cubre el lado `table()`: el proyecto
 * lee (correlativos, sumas, plucks) y escribe pocas veces.
 */
class TablaFalsa
{
    /** @var array<int, array> */
    public array $filas = [];

    /** @var array<int, array> */
    public array $escrituras = [];

    public function __construct(array $filas = [])
    {
        $this->filas = $filas;
    }

    /** @return array<int, array> */
    public function leer(): array
    {
        return $this->filas;
    }

    /** @param array<int, array> $filas */
    public function escribir(array $filas): void
    {
        $this->filas = $filas;
    }

    public function registrarEscritura(array $datos): void
    {
        $this->escrituras[] = $datos;
    }

    public function consulta(string $modelo = 'sql'): ConsultaFalsa
    {
        return new ConsultaFalsa($modelo, $this);
    }
}

/**
 * Tabla en memoria respaldada por el almacen de un doble de modelo.
 *
 * Existe porque el proyecto llega a la misma tabla por dos vias distintas
 * (por ejemplo `RequerimientoAlmacen::where(...)` y
 * `DB::table('requerimiento_almacen')`). Sin este puente, un test veria dos
 * almacenes separados para la misma tabla y mentiria sobre el estado.
 */
class TablaDeModeloFalsa extends TablaFalsa
{
    public function __construct(private readonly string $modelo)
    {
        parent::__construct();
    }

    public function leer(): array
    {
        return ModeloFalso::filasDe($this->modelo);
    }

    public function escribir(array $filas): void
    {
        ModeloFalso::reemplazarFilas($this->modelo, $filas);
    }
}
