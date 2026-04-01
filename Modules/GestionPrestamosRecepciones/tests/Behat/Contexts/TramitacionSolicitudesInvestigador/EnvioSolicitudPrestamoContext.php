<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

/**
 * Contexto para: envio_solicitud_prestamo.feature
 *
 * Steps propios de esta feature — implementar aquí:
 *   - "el investigador ha ingresado información en una solicitud"
 *   - "el investigador registra la solicitud"
 *   - "la solicitud queda registrada en estado borrador"
 *   - "existe una solicitud en estado borrador"
 *   - "el investigador tiene acceso a dicha solicitud"
 *   - "el investigador actualiza la información de la solicitud"
 *   - "la solicitud refleja la información actualizada"
 *   - "la solicitud permanece en estado borrador"
 *   - "existe una solicitud en estado :estado_previo con su información requerida completa"
 *   - "el investigador envía la solicitud"
 *   - "la solicitud queda en estado enviada"
 *   - "existe una solicitud en estado :estado_previo con información incompleta"
 *   - "la solicitud permanece en estado :estado_previo"
 */
final class EnvioSolicitudPrestamoContext extends BaseContext
{
}
