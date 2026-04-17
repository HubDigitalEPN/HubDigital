<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos;

use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

/**
 * Contexto para: definicion_recordatorios_devolucion.feature
 *
 * Steps propios de esta feature — implementar aquí:
 *   - "no existe una configuración global de recordatorios definida"
 *   - "el curador registra la configuración global de recordatorios"
 *   - "todos los préstamos quedan sujetos a la configuración definida"
 *   - "existe una configuración global de recordatorios previamente definida"
 *   - "el curador actualiza la configuración global de recordatorios"
 *   - "todos los préstamos quedan sujetos a la configuración actualizada"
 *   - "existe un préstamo activo con recordatorios configurados"
 *   - "el curador actualiza la configuración de recordatorios del préstamo"
 *   - "el préstamo queda configurado con los recordatorios actualizados"
 *   - "existe una configuración global de recordatorios definida"
 *   - "se generan los recordatorios de devolución según la configuración global"
 *
 * NO definir aquí (viven en ResolucionSolicitudesPrestamoContext):
 *   - "existe una solicitud en estado enviada"
 *   - "el curador registra la aprobación de la solicitud"
 */
final class DefinicionRecordatoriosDevolucionContext extends BaseContext
{
}
