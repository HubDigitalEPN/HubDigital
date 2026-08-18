<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCustodia;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;

/**
 * Qué espécimen de la colección puede salir en préstamo.
 *
 * Es una regla de esta colección, no del módulo que gestiona los préstamos. Vivía
 * duplicada en el adaptador de GestionPrestamosRecepciones como tres constantes sueltas,
 * y por eso se le pasó por alto el régimen de custodia: material ya devuelto a su
 * depositante seguía ofreciéndose para préstamo, porque `estado` (circulación) y
 * `estado_custodia` (tenencia) son ejes distintos y aquel filtro solo miraba el primero.
 *
 * Se comprueban cuatro cosas, y las cuatro tienen que dar el visto bueno:
 *
 *  1. **Circulación**: solo `disponible`. Uno prestado, observado o extraviado no sale.
 *  2. **Custodia**: ni devuelto (ya no está en el museo) ni en cuarentena (aislado hasta
 *     su revisión sanitaria). El material en depósito temporal SÍ es prestable: sigue
 *     bajo custodia del museo mientras dure el trámite.
 *  3. **Situación Darwin Core**: `loaned` y `destroyed` sacan al espécimen aunque su
 *     `estado` siga diciendo `disponible` — la importación del CSV dejó ese valor por
 *     defecto sin mirar `occurrenceStatus`.
 *  4. **Existencia**: quedan individuos contados, o el registro declara el espécimen
 *     presente aunque nadie los haya contado. Un conteo en cero nunca alcanza.
 *
 * Servicio puro y sin estado: las mismas entradas dan siempre la misma respuesta, y la
 * consulta SQL equivalente vive en el repositorio traduciendo exactamente estas reglas.
 */
final class EspecimenPrestable
{
    /** Único estado de circulación que permite prestar. */
    public const ESTADO_PRESTABLE = 'disponible';

    /**
     * Situaciones Darwin Core que sacan al espécimen de la colección prestable.
     *
     * @var string[]
     */
    public const OCCURRENCE_STATUS_NO_PRESTABLE = ['loaned', 'destroyed'];

    /** Única constancia Darwin Core de que el espécimen sigue en la colección. */
    public const OCCURRENCE_STATUS_PRESENTE = 'present';

    /**
     * Regímenes de tenencia que impiden el préstamo.
     *
     * @var string[]
     */
    public const CUSTODIAS_NO_PRESTABLES = [
        EstadoCustodia::Devuelto->value,
        EstadoCustodia::Cuarentena->value,
    ];

    public function puedePrestarse(Especimen $especimen): bool
    {
        return $this->cumple(
            estado: $especimen->estado(),
            custodia: $especimen->estadoCustodia(),
            occurrenceStatus: $especimen->occurrenceStatus(),
            individualCount: $especimen->individualCount(),
        );
    }

    /**
     * Igual que {@see puedePrestarse()} pero sobre valores sueltos.
     *
     * Lo necesitan las proyecciones que no hidratan la entidad completa.
     */
    public function cumple(
        EstadoEspecimen $estado,
        ?EstadoCustodia $custodia,
        ?string $occurrenceStatus,
        ?int $individualCount,
    ): bool {
        if ($estado->value !== self::ESTADO_PRESTABLE) {
            return false;
        }

        if ($this->custodiaImpidePrestamo($custodia)) {
            return false;
        }

        if ($occurrenceStatus !== null && in_array($occurrenceStatus, self::OCCURRENCE_STATUS_NO_PRESTABLE, true)) {
            return false;
        }

        return $this->constaEnLaColeccion($occurrenceStatus, $individualCount);
    }

    /**
     * El régimen de tenencia impide prestarlo.
     *
     * `Devuelto` usa la pregunta que el propio value object ya sabía responder y que
     * hasta ahora nadie hacía.
     */
    public function custodiaImpidePrestamo(?EstadoCustodia $custodia): bool
    {
        if ($custodia === null) {
            return false;
        }

        return $custodia->salioDeLaColeccion()
            || $custodia === EstadoCustodia::Cuarentena;
    }

    /** Motivo legible del rechazo, o null si sí puede prestarse. */
    public function motivoNoPrestable(Especimen $especimen): ?string
    {
        $custodia = $especimen->estadoCustodia();

        if ($custodia?->salioDeLaColeccion() === true) {
            return 'fue devuelto a su depositante y ya no está en la colección';
        }

        if ($custodia === EstadoCustodia::Cuarentena) {
            return 'está en cuarentena hasta su revisión sanitaria';
        }

        if ($especimen->estado()->value !== self::ESTADO_PRESTABLE) {
            return sprintf('su estado es "%s" y no "disponible"', $especimen->estado()->value);
        }

        $occurrenceStatus = $especimen->occurrenceStatus();

        if ($occurrenceStatus !== null && in_array($occurrenceStatus, self::OCCURRENCE_STATUS_NO_PRESTABLE, true)) {
            return sprintf('su situación Darwin Core es "%s"', $occurrenceStatus);
        }

        if (! $this->constaEnLaColeccion($occurrenceStatus, $especimen->individualCount())) {
            return 'no consta ningún individuo en la colección';
        }

        return null;
    }

    private function constaEnLaColeccion(?string $occurrenceStatus, ?int $individualCount): bool
    {
        if ($individualCount !== null) {
            return $individualCount > 0;
        }

        return $occurrenceStatus === self::OCCURRENCE_STATUS_PRESENTE;
    }
}
