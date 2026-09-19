<?php

namespace App\Modules\Contratistas\Data;

use App\Data\ContratistasData as ContratistasDataGlobal;

class ContratistasData
{
    public static function get_contratistas(?int $id_contratista = null)
    {
        return ContratistasDataGlobal::get_contratistas(id_contratista: $id_contratista);
    }
}
