<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

abstract class DomainEvent
{
    private \DateTimeImmutable $ocurridoEn;

    public function __construct()
    {
        $this->ocurridoEn = new \DateTimeImmutable;
    }

    final public function ocurridoEn(): \DateTimeImmutable
    {
        return $this->ocurridoEn;
    }

    abstract public function nombreEvento(): string;
}
