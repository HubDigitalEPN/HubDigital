<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

/**
 * Contexto para: resolucion_solicitudes_prestamo.feature
 *
 * Steps propios de esta feature — implementar aquí:
 *   - "el curador registra la aprobación de la solicitud con condiciones"
 *   - "la solicitud queda en estado aprobada"
 *   - "la solicitud queda asociada a las condiciones establecidas"
 *   - "se crea un préstamo en estado activo"
 *   - "el curador registra la observación de la solicitud con un comentario"
 *   - "la solicitud queda en estado observada"
 *   - "el curador registra el rechazo de la solicitud con un comentario"
 *   - "la solicitud queda en estado rechazada"
 *   - "el curador intenta registrar el rechazo de la solicitud sin comentario"
 *   - "la solicitud permanece en estado enviada"
 *
 * AUTORIDAD COMPARTIDA — definir aquí (usados también en definicion_recordatorios_devolucion.feature):
 *   - "existe una solicitud en estado enviada"
 *   - "el curador registra la aprobación de la solicitud"
 *
 * NO definir en DefinicionRecordatoriosDevolucionContext: los dos steps de arriba.
 * Como todos los contextos del suite se cargan para todos los escenarios,
 * definicion_recordatorios_devolucion.feature encontrará esos steps aquí.
 */
final class ResolucionSolicitudesPrestamoContext extends BaseContext
{
}
