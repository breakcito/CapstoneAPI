<?php

namespace App\Modules\Contratistas\Data;

use App\Models\Empleado;
use App\Models\LaborContratista;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Helpers\ArchivoBase64Helper;
use Illuminate\Support\Facades\DB;

class ContratistasData
{
    /**
     * Listar contratistas con su mina y labores asignadas
     *
     * IMPORTANTE: este SELECT es la forma canonica que consume el frontend
     * (`RES_ContratistaResumen`). Debe incluir TODOS los campos editables
     * (`genero`, `direccion`, `telefono`, `email`) porque el modal de
     * edicion se pre-rellena con la fila del listado: si un campo no viene,
     * el form lo envia vacio y el UPDATE lo borra en BD. Tambien incluye
     * `cambios_log` para el historial de cambios de personal.
     */
    public static function get_contratistas(
        ?int $id_mina = null,
        ?int $id_contratista = null
    ) {
        $sql = '
        SELECT
            c.id AS id_contratista,

            c.id_mina,
            mn.nombre AS mina,

            c.qr_token,
            CONCAT(c.nombre, " ", c.apellido) as nombre_completo,
            c.nombre,
            c.apellido,
            c.genero,
            c.dni,
            c.ruc,
            c.carnet_extranjeria,
            c.pasaporte,
            c.fecha_nacimiento,
            c.direccion,
            c.telefono,
            c.email,
            c.url_foto as url_foto,
            c.cambios_log,

            c.con_contrato,
            c.id_contrato_vigente,
            ct.fecha_fin AS contrato_fecha_fin,
            ct.por_tiempo_indefinido AS contrato_por_tiempo_indefinido,
            ct.tipo_contrato AS tipo_contrato_vigente,

            (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        "id_labor_contratista", lc.id,
                        "id_labor", lab.id,
                        "nombre", lab.nombre,
                        "fecha_inicio", lc.fecha_inicio,
                        "fecha_fin", lc.fecha_fin,
                        "estado", lc.estado
                    )
                )
                FROM labor_contratista lc
                INNER JOIN labor lab ON lab.id = lc.id_labor
                WHERE lc.id_contratista = c.id
            ) AS labores_asignadas,

            c.estado

        FROM empleado c
        LEFT JOIN mina mn ON mn.id = c.id_mina
        LEFT JOIN contrato_trabajo ct ON ct.id = c.id_contrato_vigente
        WHERE c.es_contratista = 1
        ';

        $params = [];

        if ($id_contratista) {
            $sql .= ' AND c.id = :id_contratista';
            $params['id_contratista'] = $id_contratista;

        $contratista = DB::selectOne($sql, $params);
        if (! $contratista) {
            return null;
        }
        $contratista->labores_asignadas = json_decode($contratista->labores_asignadas, true);
        $contratista->url_foto = ArchivoBase64Helper::toBase64($contratista->url_foto);

        return $contratista;
    }

        if ($id_mina !== null) {
            $sql .= ' AND c.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY c.apellido ASC, c.nombre ASC';

        $contratistas = DB::select($sql, $params);
        foreach ($contratistas as $contratista) {
            $contratista->labores_asignadas = json_decode($contratista->labores_asignadas, true);
            $contratista->url_foto = ArchivoBase64Helper::toBase64($contratista->url_foto);
        }

        return $contratistas;
    }

    /**
     * Actualizar la foto de un contratista
     */
    public static function actualizar_foto(int $id_contratista, ?string $url_foto): bool
    {
        return (bool) Empleado::where('id', $id_contratista)->update(['url_foto' => $url_foto]);
    }

    /**
     * Metodo para consultar datos dinamicos de uno o varios contratistas a la vez
     */
    public static function get_contratista_dinamico_by_id(int|array $id_contratista, array $columnas): ?array
    {
        $esArray = is_array($id_contratista);
        $ids = $esArray ? $id_contratista : [$id_contratista];
        // Forzamos la inclusión del ID con su alias
        if (! in_array('id as id_contratista', $columnas)) {
            $columnas[] = 'id as id_contratista';
        }
        $query = Empleado::where('es_contratista', 1)->whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }

        return $query->first()?->toArray();
    }

    /**
     * Inactiva las labores activas actuales asignando fecha_fin
     */
    public static function inactivar_labores_asignadas(int $id_contratista): void
    {
        LaborContratista::where('id_contratista', $id_contratista)
            ->whereNull('fecha_fin')
            ->update([
                'estado' => EstadoBase::Inactivo->value,
                'fecha_fin' => now()->toDateString(),
            ]);
    }

    /**
     * Inactiva un subconjunto específico de las labores activas del contratista.
     * @param array $ids_labor IDs de las labores activas que se desean cerrar
     */
    public static function desactivar_labores(int $id_contratista, array $ids_labor): void
    {
        if (empty($ids_labor)) {
            return;
        }

        LaborContratista::where('id_contratista', $id_contratista)
            ->whereNull('fecha_fin')
            ->whereIn('id_labor', $ids_labor)
            ->update([
                'estado' => EstadoBase::Inactivo->value,
                'fecha_fin' => now()->toDateString(),
            ]);
    }

    /**
     * Actualizar mina del contratista
     */
    public static function update_mina(int $id_contratista, int $id_mina)
    {
        return Empleado::where('id', $id_contratista)->update(['id_mina' => $id_mina]);
    }


}
