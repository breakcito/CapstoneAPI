<?php

namespace App\Shared\Enums\_Generic;

enum Periodo: string
{
    case Diario = 'Diario';
    case Semanal = 'Semanal';
    case Mensual = 'Mensual';
    case Anual = 'Anual';
    case Ninguno = 'Ninguno';
    case Dias = 'Días';
    case Meses = 'Meses';
    case Anos = 'Años';
}
