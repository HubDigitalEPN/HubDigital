<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum EstadoEnvio: string
{
    case SinNovedades = 'sin_novedades';
    case ConNovedades = 'con_novedades';
}
