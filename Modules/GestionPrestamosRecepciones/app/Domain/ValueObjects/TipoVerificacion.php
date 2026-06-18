<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Momento del ciclo de vida del préstamo en que se realiza una verificación de
 * especímenes:
 *
 * - Recepcion: el investigador verifica los especímenes al recibirlos.
 * - Devolucion: el curador verifica los especímenes devueltos al cerrar el préstamo.
 */
enum TipoVerificacion: string
{
    case Recepcion = 'recepcion';
    case Devolucion = 'devolucion';
}
