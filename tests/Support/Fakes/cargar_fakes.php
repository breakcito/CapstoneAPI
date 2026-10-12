<?php

/*
|--------------------------------------------------------------------------
| Dobles de los modelos Eloquent
|--------------------------------------------------------------------------
|
| Este archivo declara las clases `App\Models\*` que el proyecto usa. Al
| declararlas aqui, antes de que el proceso de pruebas intente autocargarlas,
| PHP nunca pide los archivos de app/Models/ y los dobles mandan.
|
| Fuera de las pruebas (artisan, frankenphp, cola, phpstan) este archivo no se
| carga nunca: vive en tests/ y solo lo referencia tests/bootstrap.php.
|
| No se usa Mockery::mock('alias:...'): el doble de alias solo se puede
| declarar una vez por proceso y PHPUnit comparte el proceso entre tests.
|
*/

namespace App\Models {

    use Tests\Support\Fakes\ModeloFalso;

    class LoteProducto extends ModeloFalso {}

    class KardexProducto extends ModeloFalso {}

    class Producto extends ModeloFalso {}

    class Empleado extends ModeloFalso {}

    class RequerimientoAlmacen extends ModeloFalso
    {
        /** Espejo de App\Models\RequerimientoAlmacen::get_requerimientos(). */
        public static function get_requerimientos(
            ?int $id_requerimiento = null,
            ?int $id_almacen_destino = null,
            ?string $mes = null,
            ?string $yearcito = null
        ) {
            $filas = self::filas();

            if ($id_requerimiento !== null) {
                $filas = array_values(array_filter(
                    $filas,
                    static fn (array $f) => (int) ($f['id'] ?? 0) === $id_requerimiento
                ));
            }

            if ($id_almacen_destino !== null) {
                $filas = array_values(array_filter(
                    $filas,
                    static fn (array $f) => (int) ($f['id_almacen_destino'] ?? 0) === $id_almacen_destino
                ));
            }

            // El modelo real corta con selectOne cuando filtra por id: en ese
            // caso devuelve UN objeto, no una lista.
            if ($id_requerimiento !== null) {
                return $filas === [] ? null : new \Tests\Support\Fakes\Fila($filas[0]);
            }

            return array_map(static fn (array $f) => new \Tests\Support\Fakes\Fila($f), $filas);
        }
    }

    class RequerimientoAlmacenDetalle extends ModeloFalso
    {
        /** Espejo de App\Models\RequerimientoAlmacenDetalle::get_detalles(). */
        public static function get_detalles(
            ?int $id_detalle = null,
            ?int $id_requerimiento = null,
        ) {
            $filas = self::filas();

            if ($id_detalle !== null) {
                $filas = array_values(array_filter(
                    $filas,
                    static fn (array $f) => (int) ($f['id'] ?? 0) === $id_detalle
                ));
            }

            if ($id_requerimiento !== null) {
                $filas = array_values(array_filter(
                    $filas,
                    static fn (array $f) => (int) ($f['id_requerimiento_almacen'] ?? 0) === $id_requerimiento
                ));
            }

            // Igual que el modelo real: filtrado por id devuelve un objeto.
            if ($id_detalle !== null) {
                return $filas === [] ? null : new \Tests\Support\Fakes\Fila($filas[0]);
            }

            return array_map(static fn (array $f) => new \Tests\Support\Fakes\Fila($f), $filas);
        }
    }

    class RequerimientoAlmacenEntrega extends ModeloFalso {}

    class RequerimientoAlmacenEntregaDetalle extends ModeloFalso {}
}
