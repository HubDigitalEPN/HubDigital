<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Régimen de tenencia con el que un espécimen entró a la colección.
 *
 * Es un eje distinto del de {@see EstadoEspecimen}: aquel describe la circulación
 * (si está disponible, prestado, observado o extraviado) y este la titularidad. Un
 * mismo espécimen puede estar {@see Temporal} y a la vez `en_prestamo`.
 *
 * Se queda en null para los especímenes heredados de la carga masiva del catálogo,
 * que no provienen de un trámite de depósito y por tanto no tienen régimen declarado.
 *
 * Refleja el `EstadoColeccion` que emite GestionPrestamosRecepciones al aprobar la
 * recepción física; los valores se mantienen idénticos a propósito para que el
 * traspaso entre módulos no necesite traducción.
 */
enum EstadoCustodia: string
{
    /** Depósito: custodia temporal, el material debe devolverse al depositante. */
    case Temporal = 'Temporal';

    /** Donación: cesión definitiva al patrimonio de la colección. */
    case Permanente = 'Permanente';

    /** Ingresó con observaciones sanitarias y permanece aislado hasta su revisión. */
    case Cuarentena = 'Cuarentena';

    /**
     * El material volvió a manos del depositante y ya no está en la colección.
     *
     * Estado terminal. La fila no se borra: en una colección científica el rastro de
     * qué estuvo bajo custodia y cuándo salió es parte del patrimonio documental, y el
     * Acta de Recepción sigue apuntando a estos especímenes.
     */
    case Devuelto = 'Devuelto';

    public function equals(self $other): bool
    {
        return $this === $other;
    }

    /** Indica si el material es devolutivo y no forma parte del patrimonio. */
    public function esDevolutivo(): bool
    {
        return $this === self::Temporal;
    }

    /** El material ya no está físicamente en la colección. */
    public function salioDeLaColeccion(): bool
    {
        return $this === self::Devuelto;
    }
}
