<?php

namespace App\Enums;

enum RolUsuario: string
{
    case PRESTAMISTA = 'PRESTAMISTA';
    case DEPOSITANTE = 'DEPOSITANTE';
    case CURADOR = 'CURADOR';
}
