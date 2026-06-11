<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Estado del envío reportado en la verificación de entrega: sin novedades o con
 * novedades (este último obliga a registrar observaciones).
 */
enum EstadoEnvio: string
{
    case SinNovedades = 'sin_novedades';
    case ConNovedades = 'con_novedades';
}
