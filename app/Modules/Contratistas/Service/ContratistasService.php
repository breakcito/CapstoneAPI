<?php

namespace App\Modules\Contratistas\Service;

use App\Services\ContratistasService as ContratistasServiceGlobal;
use Illuminate\Http\UploadedFile;

class ContratistasService
{
    public static function get_contratistas(?int $id_mina = null, ?int $id_contratista = null)
    {
        return ContratistasServiceGlobal::get_contratistas(id_mina: $id_mina, id_contratista: $id_contratista);
    }

    public static function crear_contratista(
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?UploadedFile $foto = null
    ) {
        return ContratistasServiceGlobal::crear_contratista(
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            foto: $foto,
            return_object: true
        );
    }

    public static function actualizar_contratista(
        int $id_contratista,
        string $nombre,
        string $apellido,
        ?string $dni = null
    ) {
        return ContratistasServiceGlobal::actualizar_contratista(
            id_contratista: $id_contratista,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni
        );
    }

    public static function actualizar_foto(int $id_contratista, ?UploadedFile $foto = null)
    {
        return ContratistasServiceGlobal::actualizar_foto(id_contratista: $id_contratista, foto: $foto);
    }

    public static function eliminar_contratista(int $id_contratista)
    {
        return ContratistasServiceGlobal::eliminar_contratista(id_contratista: $id_contratista);
    }
}
