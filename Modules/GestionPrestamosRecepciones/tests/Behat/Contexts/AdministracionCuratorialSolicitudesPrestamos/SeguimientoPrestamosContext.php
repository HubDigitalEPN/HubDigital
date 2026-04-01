<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

/**
 * Contexto para: seguimiento_prestamos.feature
 *
 * Steps propios de esta feature — implementar aquí:
 *   - "existe una solicitud en estado :estado"           (Dado)
 *   - "el curador solicita la información de la solicitud"
 *   - "existe un préstamo en estado :estado"            (Dado)
 *   - "el curador solicita la información del préstamo"
 *   - "existe una solicitud con :condicion"
 *   - "el curador solicita el historial de la solicitud"
 *   - "el historial es retornado :resultado"
 *   - "existe un préstamo con :condicion"
 *   - "el curador solicita el historial del préstamo"
 *
 * NO definir aquí (viven en SeguimientoSolicitudesContext):
 *   - "la solicitud es retornada con estado :estado"
 *   - "el préstamo es retornado con estado :estado"
 */
final class SeguimientoPrestamosContext extends BaseContext
{
}
