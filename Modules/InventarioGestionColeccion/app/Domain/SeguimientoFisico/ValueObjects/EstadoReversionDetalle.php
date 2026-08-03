<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Qué pasó con una fila concreta al deshacer una edición masiva.
 *
 * Deshacer no es "todo o nada": si alguien tocó ese campo DESPUÉS de la
 * edición, revertirlo destruiría un cambio más reciente que nadie pidió
 * descartar. Esas filas se dejan como están y se reportan.
 */
enum EstadoReversionDetalle: string
{
    /** Aún no se ha intentado deshacer esta edición. */
    case Pendiente = 'pendiente';

    /** Se devolvió el valor previo. */
    case Revertido = 'revertido';

    /** El campo cambió después de la edición: se respetó el valor actual. */
    case Conflicto = 'conflicto';

    /** El espécimen ya no existe. */
    case Desaparecido = 'desaparecido';
}
