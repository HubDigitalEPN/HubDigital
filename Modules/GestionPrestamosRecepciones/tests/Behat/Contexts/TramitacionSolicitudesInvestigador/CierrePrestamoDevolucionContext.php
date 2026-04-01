<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

/**
 * Contexto para: cierre_prestamo_devolucion.feature
 *
 * Steps propios de esta feature — implementar aquí:
 *   - "existe un préstamo activo asociado al investigador"
 *   - "el investigador registra la devolución del préstamo"
 *   - "el préstamo queda en estado pendiente de verificación"
 *   - "existe un préstamo del investigador en estado pendiente de verificación"
 *   - "la devolución del préstamo es verificada con resultado :resultado"
 *   - "el investigador recibe una notificación por correo informando el cierre del préstamo con :resultado"
 */
final class CierrePrestamoDevolucionContext extends BaseContext
{
}
