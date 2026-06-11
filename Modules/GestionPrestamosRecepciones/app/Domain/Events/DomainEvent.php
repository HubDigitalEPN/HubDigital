<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

/**
 * Clase base de los eventos de dominio del módulo.
 *
 * Registra automáticamente el instante en que ocurre el evento y obliga a las
 * subclases a exponer un nombre estable ({@see nombreEvento()}) usado al publicarlo.
 * Nota: parte de los eventos del módulo son DTOs `readonly` independientes que no
 * extienden esta clase; ambos estilos conviven.
 */
abstract class DomainEvent
{
    private \DateTimeImmutable $ocurridoEn;

    public function __construct()
    {
        $this->ocurridoEn = new \DateTimeImmutable;
    }

    /** Instante en que se creó (ocurrió) el evento. */
    final public function ocurridoEn(): \DateTimeImmutable
    {
        return $this->ocurridoEn;
    }

    /** Nombre estable del evento (p. ej. `solicitud_deposito.creada`) usado al publicarlo. */
    abstract public function nombreEvento(): string;
}
