<?php

namespace Tests\Support\Fakes;

/**
 * Constructor de consultas en memoria: reemplaza al Query Builder de Eloquent
 * para los dobles de los modelos.
 *
 * Soporta el subconjunto de metodos que realmente usa la capa Data del
 * proyecto. Cualquier metodo no implementado lanza BadMethodCallException a
 * proposito: es preferible que un test reviente a que un mock devuelva null y
 * el test pase sin verificar nada.
 */
class ConsultaFalsa
{
    /** @var array<int, callable(array):bool> */
    private array $filtros = [];

    private ?string $ordenColumna = null;

    private ?int $limite = null;

    /** @var array<string, string>|null null significa '*' (sin proyeccion) */
    private ?array $proyeccion = null;

    public function __construct(private readonly string $modelo, private readonly ?TablaFalsa $tabla = null) {}

    /**
     * MySQL resuelve 'tabla.columna' contra el alias; la tabla en memoria solo
     * guarda 'columna'. Se conserva el ultimo segmento.
     */
    private function columna(string $nombre): string
    {
        return str_contains($nombre, '.') ? substr($nombre, strrpos($nombre, '.') + 1) : $nombre;
    }

    /** @return array<int, array> */
    private function filasBase(): array
    {
        return $this->tabla !== null
            ? $this->tabla->leer()
            : ModeloFalso::filasDe($this->modelo);
    }

    private function guardarBase(array $filas): void
    {
        if ($this->tabla !== null) {
            $this->tabla->escribir($filas);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Filtros */
    /* ------------------------------------------------------------------ */

    public function where(string|array $columna, mixed $operador = null, mixed $valor = null): static
    {
        if (is_array($columna)) {
            foreach ($columna as $col => $val) {
                $this->where($col, '=', $val);
            }

            return $this;
        }

        // Misma regla que el Query Builder: si el segundo argumento no es un
        // operador conocido, es el valor. Cubre where('col', $v) y
        // where('col', $op, $v), con la ambiguedad de where('col', 'Entregado').
        $esOperador = is_string($operador)
            && in_array(strtolower($operador), ['=', '!=', '<>', '>', '>=', '<', '<='], true);

        if (! $esOperador) {
            $valor = $operador;
            $operador = '=';
        }

        $operador = match (strtolower((string) $operador)) {
            '=' => '=',
            '!=', '<>' => '!=',
            '>' => '>',
            '>=' => '>=',
            '<' => '<',
            '<=' => '<=',
            default => throw new \BadMethodCallException("ConsultaFalsa: operador no soportado '{$operador}'."),
        };
        $columna = $this->columna($columna);

        $this->filtros[] = fn (array $fila): bool => $this->comparar(
            $fila[$columna] ?? null,
            $operador,
            $valor
        );

        return $this;
    }

    public function orWhere(string $columna, mixed $operador = null, mixed $valor = null): static
    {
        // El proyecto no usa OR en estos flujos: se ignora a proposito.
        return $this;
    }

    public function whereIn(string $columna, mixed $valores): static
    {
        $valores = (array) $valores;
        $columna = $this->columna($columna);

        $this->filtros[] = fn (array $fila): bool => in_array(
            $fila[$columna] ?? null,
            $valores,
            false
        );

        return $this;
    }

    public function whereNotIn(string $columna, mixed $valores): static
    {
        $valores = (array) $valores;
        $columna = $this->columna($columna);

        $this->filtros[] = fn (array $fila): bool => ! in_array(
            $fila[$columna] ?? null,
            $valores,
            false
        );

        return $this;
    }

    public function whereNull(string $columna): static
    {
        $columna = $this->columna($columna);

        $this->filtros[] = fn (array $fila): bool => ($fila[$columna] ?? null) === null;

        return $this;
    }

    public function whereNotNull(string $columna): static
    {
        $columna = $this->columna($columna);

        $this->filtros[] = fn (array $fila): bool => ($fila[$columna] ?? null) !== null;

        return $this;
    }

    public function whereDate(string $columna, mixed $fecha): static
    {
        $columna = $this->columna($columna);

        $this->filtros[] = fn (array $fila): bool => substr((string) ($fila[$columna] ?? ''), 0, 10) === substr((string) $fecha, 0, 10);

        return $this;
    }

    public function whereYear(string $columna, int $anio): static
    {
        $columna = $this->columna($columna);

        $this->filtros[] = fn (array $fila): bool => (int) date('Y', strtotime((string) ($fila[$columna] ?? ''))) === $anio;

        return $this;
    }

    public function whereMonth(string $columna, int $mes): static
    {
        $columna = $this->columna($columna);

        $this->filtros[] = fn (array $fila): bool => (int) date('n', strtotime((string) ($fila[$columna] ?? ''))) === $mes;

        return $this;
    }

    public function whereBetween(string $columna, array $rango): static
    {
        $columna = $this->columna($columna);

        $this->filtros[] = fn (array $fila): bool => (string) ($fila[$columna] ?? '') >= (string) $rango[0]
            && (string) ($fila[$columna] ?? '') <= (string) $rango[1];

        return $this;
    }

    /** Joins y subconsultas no se emulan: el doble filtra en memoria. */
    public function join(...$args): static
    {
        return $this;
    }

    public function leftJoin(...$args): static
    {
        return $this;
    }

    public function whereNotExists($callback): static
    {
        return $this;
    }

    public function whereExists($callback): static
    {
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): static
    {
        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* Orden y paginacion */
    /* ------------------------------------------------------------------ */

    public function orderBy(string $columna, string $direccion = 'asc'): static
    {
        $this->ordenColumna = $columna;

        return $this;
    }

    public function latest(string $columna = 'created_at'): static
    {
        $this->ordenColumna = $columna;

        return $this;
    }

    public function oldest(string $columna = 'created_at'): static
    {
        $this->ordenColumna = $columna;

        return $this;
    }

    public function limit(int $n): static
    {
        $this->limite = $n;

        return $this;
    }

    public function take(int $n): static
    {
        return $this->limit($n);
    }

    public function offset(int $n): static
    {
        return $this;
    }

    public function select(string $columnas = '*'): static
    {
        $this->proyeccion = $columnas === '*' ? null : $this->parsearProyeccion($columnas);

        return $this;
    }

    public function selectRaw(string $expresion, array $bindings = []): static
    {
        // El proyecto solo usa selectRaw para expresiones tipo CONCAT(...).
        // Se acepta la llamada y se devuelve la fila sin proyectar: si un test
        // depende del alias, debe sembrar esa clave en la fila.
        $this->proyeccion = null;

        return $this;
    }

    public function addSelect(string $columna): static
    {
        $this->proyeccion = null;

        return $this;
    }

    /**
     * Traduce la lista de columnas del proyecto (soporta 'id as id_lote') a
     * un mapa alias => columna.
     *
     * @return array<string, string>
     */
    private function parsearProyeccion(string $columnas): array
    {
        $proyeccion = [];

        foreach (explode(',', $columnas) as $spec) {
            $spec = trim($spec);

            if ($spec === '') {
                continue;
            }

            if (stripos($spec, ' as ') !== false) {
                [$columna, $alias] = preg_split('/\s+as\s+/i', $spec);
                $proyeccion[trim((string) $alias)] = $this->columna(trim((string) $columna));

                continue;
            }

            $proyeccion[$spec] = $this->columna($spec);
        }

        return $proyeccion;
    }

    private function proyectar(array $fila): array
    {
        if ($this->proyeccion === null) {
            return $fila;
        }

        $salida = [];

        foreach ($this->proyeccion as $alias => $columna) {
            $salida[$alias] = $fila[$columna] ?? null;
        }

        return $salida;
    }

    /* ------------------------------------------------------------------ */
    /* Lectura */
    /* ------------------------------------------------------------------ */

    public function get($columnas = ['*']): \Illuminate\Support\Collection
    {
        ModeloFalso::registrarConsulta($this->modelo, $columnas);

        $esArreglo = is_array($columnas);
        if ($esArreglo && ! in_array('*', $columnas, true)) {
            $this->proyeccion ??= $this->parsearProyeccionListado($columnas);
        }

        $filas = $this->aplicarFiltros();

        if ($this->ordenColumna !== null) {
            $columna = $this->columna($this->ordenColumna);
            usort($filas, fn (array $a, array $b) => ($a[$columna] ?? null) <=> ($b[$columna] ?? null));
        }

        if ($this->limite !== null) {
            $filas = array_slice($filas, 0, $this->limite);
        }

        return new \Illuminate\Support\Collection(array_map(
            fn (array $f) => new Fila($this->proyectar($f)),
            $filas
        ));
    }

    /** @return array<string, string> */
    private function parsearProyeccionListado(array $columnas): array
    {
        $proyeccion = [];

        foreach ($columnas as $spec) {
            if (! is_string($spec)) {
                continue;
            }

            if (stripos($spec, ' as ') !== false) {
                [$columna, $alias] = preg_split('/\s+as\s+/i', $spec);
                $proyeccion[trim((string) $alias)] = $this->columna(trim((string) $columna));

                continue;
            }

            $proyeccion[$spec] = $this->columna($spec);
        }

        return $proyeccion;
    }

    public function first($columnas = ['*']): ?Fila
    {
        return $this->get($columnas)->first();
    }

    public function find(int|string $id, $columnas = ['*']): ?Fila
    {
        return $this->where('id', '=', $id)->first($columnas);
    }

    public function value(string $columna): mixed
    {
        $fila = $this->first();

        return $fila[$this->columna($columna)] ?? null;
    }

    public function insertGetId(array $datos): int
    {
        if ($this->tabla === null) {
            throw new \BadMethodCallException(
                'insertGetId() solo se emula sobre DB::table(). Para insertar en un '
                .'modelo usa el propio doble del modelo.'
            );
        }

        $this->tabla->registrarEscritura($datos);
        $datos['id'] ??= count($this->tabla->leer()) + 1;
        $filas = $this->tabla->leer();
        $filas[] = $datos;
        $this->tabla->escribir($filas);

        return (int) $datos['id'];
    }

    public function insert(array $datos): bool
    {
        if ($this->tabla === null) {
            throw new \BadMethodCallException('insert() solo se emula sobre DB::table().');
        }

        $this->tabla->registrarEscritura($datos);
        $filas = $this->tabla->leer();
        $filas[] = $datos;
        $this->tabla->escribir($filas);

        return true;
    }

    public function pluck(string $columna, ?string $clave = null): \Illuminate\Support\Collection
    {
        $columna = $this->columna($columna);

        if ($clave !== null) {
            $clave = $this->columna($clave);
        }

        $coleccion = $this->get()->pluck($columna, $clave);

        return $coleccion instanceof \Illuminate\Support\Collection
            ? $coleccion
            : new \Illuminate\Support\Collection($coleccion);
    }

    public function sum(string $columna): float|int
    {
        return (float) $this->get()->sum($this->columna($columna));
    }

    public function max(string $columna): mixed
    {
        $columna = $this->columna($columna);
        $valores = array_filter(
            $this->get()->pluck($columna)->all(),
            static fn ($v) => $v !== null
        );

        return $valores === [] ? null : max($valores);
    }

    public function min(string $columna): mixed
    {
        $columna = $this->columna($columna);
        $valores = array_filter(
            $this->get()->pluck($columna)->all(),
            static fn ($v) => $v !== null
        );

        return $valores === [] ? null : min($valores);
    }

    public function count(string $columnas = '*'): int
    {
        return $this->get()->count();
    }

    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /* ------------------------------------------------------------------ */
    /* Escritura */
    /* ------------------------------------------------------------------ */

    public function update(array $datos): int
    {
        $ids = $this->aplicarFiltrosIds();

        if ($this->tabla !== null) {
            $this->tabla->registrarEscritura($datos);
            $filas = $this->filasBase();
            $afectadas = 0;

            foreach ($filas as $i => $fila) {
                $filas[$i] = array_merge($fila, $datos);
                $afectadas++;
            }

            $this->guardarBase($filas);

            return $afectadas;
        }

        return ModeloFalso::actualizarFilas($this->modelo, $ids, $datos);
    }

    public function delete(): int
    {
        if ($this->tabla !== null) {
            $this->tabla->registrarEscritura(['delete' => true]);

            return 0;
        }

        return ModeloFalso::borrarFilas($this->modelo, $this->aplicarFiltrosIds());
    }

    /* ------------------------------------------------------------------ */

    private function aplicarFiltros(): array
    {
        $filas = $this->filasBase();

        foreach ($this->filtros as $filtro) {
            $filas = array_values(array_filter($filas, $filtro));
        }

        return $filas;
    }

    private function aplicarFiltrosIds(): array
    {
        return array_map(
            static fn (array $f) => (int) ($f['id'] ?? 0),
            $this->aplicarFiltros()
        );
    }

    private function comparar(mixed $actual, string $operador, mixed $esperado): bool
    {
        return match ($operador) {
            '=' => $actual == $esperado,
            '!=' => $actual != $esperado,
            '>' => $actual > $esperado,
            '>=' => $actual >= $esperado,
            '<' => $actual < $esperado,
            '<=' => $actual <= $esperado,
            default => throw new \BadMethodCallException(
                "ConsultaFalsa: operador no soportado '{$operador}'."
            ),
        };
    }

    /**
     * @throws \BadMethodCallException si el proyecto empieza a usar un metodo
     *                                 que el doble todavia no emula.
     */
    public function __call(string $metodo, array $argumentos): mixed
    {
        throw new \BadMethodCallException(sprintf(
            'ConsultaFalsa no emula el metodo "%s()". '
            .'O el doble esta incompleto, o el codigo bajo prueba cambio de forma. '
            .'Implementalo en %s antes de asumir que el test pasa.',
            $metodo,
            self::class
        ));
    }
}
