<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\Ports\GeneradorCodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;

/**
 * Implementación del generador de códigos de préstamo respaldada por una tabla
 * contadora de una sola fila.
 *
 * Reserva el siguiente número correlativo dentro de una transacción con
 * `lockForUpdate()`, garantizando atomicidad ante solicitudes concurrentes (dos
 * peticiones simultáneas nunca obtienen el mismo número). El año se toma del
 * momento de la generación; la secuencia es global continua.
 */
final class EloquentGeneradorCodigoPrestamoAdapter implements GeneradorCodigoPrestamo
{
    private const TABLA = 'prestamos.secuencias_codigo_prestamo';

    public function siguiente(): CodigoPrestamo
    {
        return DB::transaction(function (): CodigoPrestamo {
            $fila = DB::table(self::TABLA)->lockForUpdate()->first();

            $siguiente = ((int) ($fila->ultimo_numero ?? 0)) + 1;

            DB::table(self::TABLA)->update([
                'ultimo_numero' => $siguiente,
                'updated_at' => now(),
            ]);

            return CodigoPrestamo::fromParts((int) date('Y'), $siguiente);
        });
    }
}
