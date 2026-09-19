<?php

namespace App\Modules\Contratistas\Service;

use App\Data\ContratistasData as ContratistasDataGlobal;
use App\Models\Empleado;
use App\Models\LaborContratista;
use App\Modules\Contratistas\Data\ContratistasData;
use App\Services\ContratistasService as ContratistasServiceGlobal;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ContratistasService
{
    /**
     * Listar contratistas con su mina y labores asignadas
     */
    public static function get_contratistas(?int $id_mina = null)
    {
        $contratistas = ContratistasData::get_contratistas(id_mina: $id_mina);

        return ApiResponse::success($contratistas);
    }

    /**
     * Registrar un nuevo contratista
     * @param array $ids_labor IDs de las labores a asignar como activas desde hoy
     */
    public static function crear_contratista(
        string $nombre,
        string $apellido,
        ?int $id_mina = null,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?string $genero = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?UploadedFile $foto = null,
        array $ids_labor = []
    ) {
        $response = ContratistasServiceGlobal::crear_contratista(
            id_mina: $id_mina,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            ruc: $ruc,
            carnet_extranjeria: $carnet_extranjeria,
            pasaporte: $pasaporte,
            fecha_nacimiento: $fecha_nacimiento,
            genero: $genero,
            direccion: $direccion,
            telefono: $telefono,
            email: $email,
            foto: $foto,
            ids_labor: $ids_labor,
            return_object: false
        );

        if ($response['success']) {
            $id = $response['data'];

            $contratista = ContratistasData::get_contratistas(id_contratista: $id);
            if ($contratista) {
                $contratista->labores_asignadas = $contratista->labores_asignadas ?? [];
            }

            return ApiResponse::success($contratista, 'Contratista registrado correctamente');
        }

        return ApiResponse::error($response['message']);
    }

    /**
     * Actualizar la foto del contratista
     */
    public static function actualizar_foto(int $id_contratista, ?UploadedFile $nueva_foto = null): array|object
    {
        $emp = ContratistasData::get_contratista_dinamico_by_id($id_contratista, ['url_foto']);
        $url_foto_old = ! empty($emp['url_foto']) ? $emp['url_foto'] : null;

        // Caso: eliminar foto (sin nueva)
        if (is_null($nueva_foto)) {
            if ($url_foto_old) {
                ArchivoHelper::eliminarArchivo($url_foto_old);
                ContratistasData::actualizar_foto(id_contratista: $id_contratista, url_foto: null);

                return ApiResponse::success(null, 'Foto eliminada correctamente.');
            }

            return ApiResponse::success(null, 'No hay foto para eliminar.');
        }

        // Caso: actualizar o agregar foto
        if ($url_foto_old) {
            $resultado = ArchivoHelper::reemplazarArchivo($url_foto_old, 'fotos-contratistas', $nueva_foto);
        } else {
            $resultado = ArchivoHelper::guardarArchivos('fotos-contratistas', [$nueva_foto]);
        }
        $foto = $resultado[0] ?? null;
        $url_foto = $foto['url'] ?? null;

        if (empty($url_foto)) {
            return ApiResponse::error('Error al procesar el archivo.');
        }

        ContratistasData::actualizar_foto(id_contratista: $id_contratista, url_foto: $url_foto);

        return ApiResponse::success($url_foto, 'Foto actualizada correctamente.');
    }

    /**
     * Sincroniza las labores activas de un contratista con la selección enviada.
     * - Si la mina cambió, se inactivan todas las asignaciones previas.
     * - Las IDs presentes en la selección y no activas se crean como nuevas.
     * - Las IDs activas que ya no están en la selección se inactivan con fecha_fin = hoy.
     * @param array $ids_labor Selección final de IDs de labores activas deseadas
     */
    public static function asignar_labores(int $id_contratista, int $id_mina, array $ids_labor)
    {
        return DB::transaction(function () use ($id_contratista, $id_mina, $ids_labor) {

            // 1. Obtener la mina actual asignada al contratista
            $contratistaActual = Empleado::find($id_contratista);
            $esCambioDeMina = $contratistaActual->id_mina !== $id_mina;

            // 2. IDs de las labores que están activas AHORA
            $laboresActivasIds = LaborContratista::where('id_contratista', $id_contratista)
                ->whereNull('fecha_fin')
                ->pluck('id_labor')
                ->map(fn($id) => (int) $id)
                ->toArray();

            // 3. Si cambió la mina, inactivamos TODAS las asignaciones previas
            //    y vaciamos la lista de activas (serán tratadas como nuevas después)
            if ($esCambioDeMina) {
                ContratistasData::inactivar_labores_asignadas($id_contratista);
                ContratistasData::update_mina($id_contratista, $id_mina);
                $laboresActivasIds = [];
            }

            // 4. Normalizar la selección del usuario
            $idsSolicitados = array_values(array_unique(array_map('intval', $ids_labor)));

            // 5. Diff: qué crear y qué desactivar
            $paraCrear = array_values(array_diff($idsSolicitados, $laboresActivasIds));
            $paraDesactivar = array_values(array_diff($laboresActivasIds, $idsSolicitados));

            // 6. Desactivar las que ya no se quieren (de la misma mina)
            if (!empty($paraDesactivar)) {
                ContratistasData::desactivar_labores($id_contratista, $paraDesactivar);
            }

            // 7. Crear las nuevas asignaciones (con fecha_inicio = hoy)
            if (!empty($paraCrear)) {
                ContratistasDataGlobal::asignar_labor(
                    id_contratista: $id_contratista,
                    ids_labor: $paraCrear
                );
            }

            $editado = ContratistasData::get_contratistas(id_contratista: $id_contratista);

            return ApiResponse::success($editado, 'Labores actualizadas correctamente');
        });
    }

    /**
     * Actualizar datos personales + contacto de un contratista.
     * Foto y mina/labores NO se editan aquí (van por sus endpoints propios).
     */
    public static function actualizar_contratista(
        int $id_contratista,
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?string $genero = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?int $idEmpleadoLog = null,
        ?string $nombreEmpleadoLog = null,
    ) {
        return ContratistasServiceGlobal::actualizar_contratista(
            id_contratista: $id_contratista,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            ruc: $ruc,
            carnet_extranjeria: $carnet_extranjeria,
            pasaporte: $pasaporte,
            fecha_nacimiento: $fecha_nacimiento,
            genero: $genero,
            direccion: $direccion,
            telefono: $telefono,
            email: $email,
            idEmpleadoLog: $idEmpleadoLog,
            nombreEmpleadoLog: $nombreEmpleadoLog,
        );
    }

    /**
     * Borrado logico de un contratista (cambia estado a Inactivo).
     */
    public static function eliminar_contratista(int $id_contratista)
    {
        return ContratistasServiceGlobal::eliminar_contratista(id_contratista: $id_contratista);
    }
}
