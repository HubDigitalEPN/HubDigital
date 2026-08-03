<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Qué clase de edición dejó registrada una entrada de la bitácora.
 *
 * La edición de una sola celda comparte tabla y mecánica de deshacer con las
 * masivas: es una edición masiva de tamaño uno. Distinguirla sirve solo para
 * que el historial se lea con sentido.
 */
enum TipoEdicionMasiva: string
{
    case FijarValor = 'fijar_valor';
    case ReemplazarTexto = 'reemplazar_texto';
    case EdicionCelda = 'edicion_celda';

    public function etiqueta(): string
    {
        return match ($this) {
            self::FijarValor => 'Valor fijado',
            self::ReemplazarTexto => 'Texto reemplazado',
            self::EdicionCelda => 'Celda editada',
        };
    }
}
