<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

/**
 * Contexto para: seguimiento_solicitudes.feature
 *
 * Steps propios de esta feature — implementar aquí:
 *   - "existe una solicitud del investigador en estado :estado"
 *   - "el investigador solicita la información de la solicitud"
 *   - "existe un préstamo del investigador en estado :estado"
 *   - "el investigador solicita la información del préstamo"
 *
 * AUTORIDAD COMPARTIDA — definir aquí (usados también en seguimiento_prestamos.feature):
 *   - "la solicitud es retornada con estado :estado"
 *   - "el préstamo es retornado con estado :estado"
 *
 * NO definir en SeguimientoPrestamosContext: los dos steps de arriba.
 * Como todos los contextos del suite se cargan para todos los escenarios,
 * seguimiento_prestamos.feature encontrará esos steps aquí.
 */
final class SeguimientoSolicitudesContext extends BaseContext
{
}
