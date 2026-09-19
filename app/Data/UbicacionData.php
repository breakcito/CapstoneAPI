<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

/**
 * Acceso a datos de ubicación geográfica del Perú.
 * Las tablas `departamento`, `provincia` y `distrito` son catálogos de
 * solo lectura para la app (datos provistos por el estado peruano).
 *
 * La regla de no usar Foreign Keys en este proyecto hace que la
 * integridad referencial se mantenga por aplicación; las columnas
 * `id_departamento` / `id_provincia` se filtran aquí como simples
 * predicados `WHERE`.
 */
class UbicacionData
{
    /**
     * Departamentos.
     * Filtros opcionales: id_departamento (single row).
     */
    public static function get_departamentos(?int $id_departamento = null)
    {
        $sql = 'SELECT id, codigo, nombre FROM departamento WHERE 1=1';
        $params = [];

        if ($id_departamento) {
            $sql .= ' AND id = :id_departamento';
            $params['id_departamento'] = $id_departamento;
            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY nombre ASC';
        return DB::select($sql, $params);
    }

    /**
     * Provincias.
     * Filtros opcionales: id_provincia (single row), id_departamento (lista).
     */
    public static function get_provincias(?int $id_provincia = null, ?int $id_departamento = null)
    {
        $sql = 'SELECT id, codigo, nombre, id_departamento FROM provincia WHERE 1=1';
        $params = [];

        if ($id_provincia) {
            $sql .= ' AND id = :id_provincia';
            $params['id_provincia'] = $id_provincia;
            return DB::selectOne($sql, $params);
        }

        if ($id_departamento) {
            $sql .= ' AND id_departamento = :id_departamento';
            $params['id_departamento'] = $id_departamento;
        }

        $sql .= ' ORDER BY nombre ASC';
        return DB::select($sql, $params);
    }

    /**
     * Distritos.
     * Filtros opcionales: id_distrito (single row), id_provincia, id_departamento.
     */
    public static function get_distritos(?int $id_distrito = null, ?int $id_provincia = null, ?int $id_departamento = null)
    {
        $sql = 'SELECT id, codigo, nombre, id_provincia, id_departamento FROM distrito WHERE 1=1';
        $params = [];

        if ($id_distrito) {
            $sql .= ' AND id = :id_distrito';
            $params['id_distrito'] = $id_distrito;
            return DB::selectOne($sql, $params);
        }

        if ($id_provincia) {
            $sql .= ' AND id_provincia = :id_provincia';
            $params['id_provincia'] = $id_provincia;
        }

        if ($id_departamento) {
            $sql .= ' AND id_departamento = :id_departamento';
            $params['id_departamento'] = $id_departamento;
        }

        $sql .= ' ORDER BY nombre ASC';
        return DB::select($sql, $params);
    }
}