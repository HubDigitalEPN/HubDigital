<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Resultado genérico de una verificación de especímenes (tanto en recepción como
 * en devolución): sin novedades o con novedades. Este último obliga a registrar al
 * menos una novedad (por espécimen o en la nota general).
 */
enum ResultadoVerificacion: string
{
    case SinNovedades = 'sin_novedades';
    case ConNovedades = 'con_novedades';
}
