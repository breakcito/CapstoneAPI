<?php

namespace App\Data;

use App\Models\Empleado;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\Contrato\EstadoContrato;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmpleadosData
{
    /**
     * Obtener listado simple de empleados
     */
    public static function get_empleados(
        ?int $id_empleado = null,
        ?EstadoBase $estado = EstadoBase::Activo,
        ?int $id_almacen_excluyente = null,
        ?int $id_mina_excluyente = null,
        ?bool $con_cuenta = null,
        ?bool $solo_con_contrato_vigente = null,
        ?string $fecha_fin_programacion = null,
        ?int $id_lugar = null,
        ?string $tipo_lugar = null
    ) {
        $campo_lugar = match ($tipo_lugar) {
            'almacen' => 'id_almacen',
            'labor' => 'id_labor',
            'oficina' => 'id_oficina',
            default => null,
        };

        $expressions = '
            emp.id as id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) as nombre_completo,
            emp.dni,
            emp.ruc,
            emp.url_foto,
            emp.qr_token,
            emp.genero,
            emp.con_contrato,
            emp.direccion,
            emp.telefono,
            emp.email,
            emp.id_contrato_vigente,
            emp.id_cargo,
            ct.estado AS contrato_estado,
            ct.tipo_contrato AS tipo_contrato_vigente,
            ct.por_tiempo_indefinido AS contrato_indefinido,
            ct.fecha_fin AS contrato_fecha_fin,
            0 AS matchea_lugar
        ';

        $query = DB::table('empleado as emp')
            ->selectRaw($expressions)
            ->leftJoin('contrato_trabajo as ct', 'ct.id', '=', 'emp.id_contrato_vigente')
            ->where('emp.es_contratista', 0)
            ->where('emp.estado', $estado->value);

        // filtro por id
        if ($id_empleado !== null) {
            $query->where('emp.id', $id_empleado);

            return $query->first() ?? [];
        }

        // filtro por empleados ya asignados a un almacén
        if ($id_almacen_excluyente !== null) {
            $query->whereNotExists(function ($subquery) use ($id_almacen_excluyente) {
                $subquery->select(DB::raw(1))
                    ->from('responsable_almacen as res')
                    ->whereColumn('res.id_empleado', 'emp.id')
                    ->where('res.id_almacen', $id_almacen_excluyente)
                    ->where('res.estado', EstadoBase::Activo->value);
            });
        }

        // filtro por empleados ya asignados a una mina
        if ($id_mina_excluyente !== null) {
            $query->whereNotExists(function ($subquery) use ($id_mina_excluyente) {
                $subquery->select(DB::raw(1))
                    ->from('responsable_mina as res')
                    ->whereColumn('res.id_empleado', 'emp.id')
                    ->where('res.id_mina', $id_mina_excluyente)
                    ->where('res.estado', EstadoBase::Activo->value);
            });
        }

        // filtro listar solo empleados con/sin cuenta
        if ($con_cuenta !== null) {
            if ($con_cuenta == false) {
                $query->whereNotExists(function ($subquery) {
                    $subquery->select(DB::raw(1))
                        ->from('usuario as u')
                        ->whereColumn('u.id_empleado', 'emp.id');
                });
            } else {
                $query->whereExists(function ($subquery) {
                    $subquery->select(DB::raw(1))
                        ->from('usuario as u')
                        ->whereColumn('u.id_empleado', 'emp.id');
                });
            }
        }

        // filtro listar solo empleados con contrato vigente Vigente.
        // Se exige: con_contrato = 1, id_contrato_vigente NOT NULL, contrato.estado = 'Vigente'.
        if ($solo_con_contrato_vigente === true) {
            $query->where('emp.con_contrato', 1)
                ->whereNotNull('emp.id_contrato_vigente')
                ->where('ct.estado', EstadoContrato::Vigente->value);
        }

        // Si se indica lugar, calcular matchea_lugar_calculado (1 si coincide, 0 si no)
        // y ordenar primero los que pertenecen a ese lugar, sin excluir a los de otros lugares.
        if ($id_lugar !== null && $campo_lugar !== null) {
            $idLugarSafe = (int) $id_lugar;
            $query->addSelect(DB::raw("CASE WHEN ct.{$campo_lugar} = {$idLugarSafe} THEN 1 ELSE 0 END AS matchea_lugar_calculado"));
            $query->orderByRaw('matchea_lugar_calculado DESC, emp.nombre ASC, emp.apellido ASC');
        } else {
            $query->orderByRaw('CONCAT(emp.nombre, " ", emp.apellido) ASC');
        }

        $rows = $query
            ->get();

        return $rows
            ->map(function ($row) use ($fecha_fin_programacion) {
                $row = (array) $row;
                // Cast manual: la query builder no aplica los $casts del modelo.
                $row['con_contrato'] = (bool) ($row['con_contrato'] ?? 0);
                $row['contrato_indefinido'] = (bool) ($row['contrato_indefinido'] ?? 0);
                $row['matchea_lugar'] = isset($row['matchea_lugar_calculado'])
                    ? (bool) $row['matchea_lugar_calculado']
                    : false;
                unset($row['matchea_lugar_calculado']);

                if ($fecha_fin_programacion !== null && $fecha_fin_programacion !== '') {
                    $contrato_indefinido = $row['contrato_indefinido'];
                    $contrato_fecha_fin = $row['contrato_fecha_fin'] ?? null;
                    $row['puede_cubrir'] = $contrato_indefinido
                        || $contrato_fecha_fin === null
                        || (string) $contrato_fecha_fin >= (string) $fecha_fin_programacion;
                } else {
                    $row['puede_cubrir'] = true;
                }

                return $row;
            })
            ->toArray();
    }

    /**
     * Verificar si ya existe un empleado con el mismo documento
     */
    public static function ya_existe(
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null
    ): bool {
        return Empleado::query()
            ->where('es_contratista', 0)
            ->where(function ($q) use ($dni, $ruc, $carnet_extranjeria, $pasaporte) {
                $q->when($dni !== null, fn ($q) => $q->orWhere('dni', $dni))
                    ->when($ruc !== null, fn ($q) => $q->orWhere('ruc', $ruc))
                    ->when(
                        $carnet_extranjeria !== null,
                        fn ($q) => $q->orWhere('carnet_extranjeria', $carnet_extranjeria)
                    )
                    ->when($pasaporte !== null, fn ($q) => $q->orWhere('pasaporte', $pasaporte));
            })
            ->exists();
    }

    /**
     * Crear un nuevo empleado
     */
    public static function crear_empleado(
        int $id_cargo,
        string $nombre,
        string $apellido,
        bool $con_contrato = false,
        ?int $id_contrato_vigente = null,
        ?string $genero = null,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?string $url_foto = null,
        ?string $qr_token = null,
        ?int $id_empresa = null,
    ) {
        $qr_token = ! empty($qr_token) ? $qr_token : (string) Str::uuid();

        return Empleado::insertGetId([
            'id_cargo' => $id_cargo,
            'id_contrato_vigente' => $id_contrato_vigente,
            'id_empresa' => $id_empresa,
            'qr_token' => $qr_token,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => $dni,
            'genero' => $genero,
            'ruc' => $ruc,
            'carnet_extranjeria' => $carnet_extranjeria,
            'pasaporte' => $pasaporte,
            'fecha_nacimiento' => $fecha_nacimiento,
            'con_contrato' => $con_contrato,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'email' => $email,
            'url_foto' => $url_foto,
            'es_contratista' => 0,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Metodo para consultar datos dinamicos de uno o varios empleados a la vez
     */
    public static function get_empleado_dinamico_by_id(int|array $id_empleado, array $columnas): ?array
    {
        $esArray = is_array($id_empleado);
        $ids = $esArray ? $id_empleado : [$id_empleado];
        // Forzamos la inclusión del ID con su alias
        if (! in_array('id as id_empleado', $columnas)) {
            $columnas[] = 'id as id_empleado';
        }
        $query = Empleado::where('es_contratista', 0)->whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }

        return $query->first()?->toArray();
    }

    /**
     * Actualizar foto de un empleado
     */
    public static function actualizar_foto(
        int $id_empleado,
        ?string $url_foto = null
    ) {
        return Empleado::where('id', $id_empleado)->update([
            'url_foto' => $url_foto,
        ]);
    }

    /**
     * Mapeo campo_bd => [label visible, tipo] para el log de cambios.
     */
    private const EMPLEADO_CAMBIOS_FIELDS = [
        'id_cargo'         => ['Cargo',         'int'],
        'id_empresa'       => ['Empresa',       'int'],
        'nombre'           => ['Nombre',        'string'],
        'apellido'         => ['Apellido',      'string'],
        'genero'           => ['Género',        'string'],
        'dni'              => ['DNI',           'string'],
        'fecha_nacimiento' => ['Fecha de Nacimiento', 'date'],
        'direccion'        => ['Dirección',     'string'],
        'telefono'         => ['Teléfono',      'string'],
        'email'            => ['Email',         'string'],
    ];

    /**
     * Normaliza un valor al tipo canónico del campo para comparaciones fiables.
     */
    private static function normalizarEmpleadoParaComparar(mixed $valor, string $tipo): mixed
    {
        if ($valor === null) {
            return null;
        }
        return match ($tipo) {
            'int'    => (int) $valor,
            'date'   => is_string($valor) ? substr($valor, 0, 10) : (string) $valor,
            default  => (string) $valor,
        };
    }

    /**
     * Calcula el diff entre el empleado previo y el nuevo estado.
     * Si hay cambios, apendea una entrada al array cambios_log.
     * Si no hay cambios, devuelve el log previo sin modificar.
     */
    public static function calcularDiffCambiosEmpleado(
        ?object $original,
        array $nuevoEstado,
        ?int $idEmpleadoLog,
        ?string $nombreEmpleadoLog
    ): ?array {
        if ($idEmpleadoLog === null || $nombreEmpleadoLog === null) {
            return null;
        }

        $logPrevio = [];
        if ($original !== null && ! empty($original->cambios_log)) {
            $raw = $original->cambios_log;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $logPrevio = is_array($decoded) ? $decoded : [];
            } elseif (is_array($raw)) {
                $logPrevio = $raw;
            }
        }

        $cambios = [];
        foreach (self::EMPLEADO_CAMBIOS_FIELDS as $campoBd => [$label, $tipo]) {
            if (! array_key_exists($campoBd, $nuevoEstado)) {
                continue;
            }
            $valorAnterior = $original !== null ? ($original->{$campoBd} ?? null) : null;
            $valorNuevo = $nuevoEstado[$campoBd];

            $anteriorNorm = self::normalizarEmpleadoParaComparar($valorAnterior, $tipo);
            $nuevoNorm = self::normalizarEmpleadoParaComparar($valorNuevo, $tipo);

            if ($anteriorNorm !== $nuevoNorm) {
                $cambios[] = [
                    'campo_bd' => $campoBd,
                    'campo' => $label,
                    // Guardamos los valores normalizados para que el log
                    // muestre formatos consistentes (ej. fechas sin hora
                    // y zona horaria) y el frontend no tenga que re-normalizar.
                    'valor_anterior' => $anteriorNorm,
                    'valor_nuevo' => $nuevoNorm,
                ];
            }
        }

        if (count($cambios) === 0) {
            return $logPrevio;
        }

        $logPrevio[] = [
            'id_empleado' => $idEmpleadoLog,
            'nombre_empleado' => $nombreEmpleadoLog,
            'motivo' => null,
            'update_at' => now()->toDateTimeString(),
            'cambios' => $cambios,
        ];

        return $logPrevio;
    }

    /**
     * Actualizar campos editables de un empleado (no contratista).
     * La foto NO se persiste aquí — va por el endpoint dedicado `/foto/{id}`.
     *
     * `id_cargo` e `id_empresa` son opcionales con sentido: si vienen
     * `null`, se omiten del UPDATE (preservan la referencia que pueda
     * tener el contrato vigente).
     */
    public static function actualizar_empleado(
        int $id_empleado,
        string $nombre,
        string $apellido,
        ?string $genero = null,
        ?string $dni = null,
        ?string $fecha_nacimiento = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?int $id_cargo = null,
        ?int $id_empresa = null,
        ?int $idEmpleadoLog = null,
        ?string $nombreEmpleadoLog = null,
    ): bool {
        $data = [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'genero' => $genero,
            'dni' => $dni,
            'fecha_nacimiento' => $fecha_nacimiento,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'email' => $email,
        ];

        if ($id_cargo !== null) {
            $data['id_cargo'] = $id_cargo;
        }
        if ($id_empresa !== null) {
            $data['id_empresa'] = $id_empresa;
        }

        // Calcular y persistir cambios_log si hay info del editor.
        // Solo incluimos id_cargo / id_empresa en el diff si vinieron
        // en el payload (no null). Usamos DB::table (no el Model
        // Eloquent) para evitar que Carbon reformatee fechas con
        // T05:00:00.000000Z al leer.
        if ($idEmpleadoLog !== null && $nombreEmpleadoLog !== null) {
            $original = DB::table('empleado')->where('id', $id_empleado)->first();
            $nuevoEstado = $data;
            if ($id_cargo !== null) {
                $nuevoEstado['id_cargo'] = $id_cargo;
            }
            if ($id_empresa !== null) {
                $nuevoEstado['id_empresa'] = $id_empresa;
            }
            $nuevoLog = self::calcularDiffCambiosEmpleado(
                $original,
                $nuevoEstado,
                $idEmpleadoLog,
                $nombreEmpleadoLog
            );
            if ($nuevoLog !== null) {
                $data['cambios_log'] = json_encode($nuevoLog, JSON_UNESCAPED_UNICODE);
            }
        }

        return Empleado::where('id', $id_empleado)->update($data) >= 0;
    }

    /**
     * Borrado logico de un empleado (cambia estado a Inactivo).
     * Preserva integridad referencial con contratos, cuentas, fotochecks, etc.
     */
    public static function eliminar_empleado(int $id_empleado): bool
    {
        return Empleado::where('id', $id_empleado)
            ->where('estado', '!=', 'Inactivo')
            ->update(['estado' => 'Inactivo']) > 0;
    }
}
