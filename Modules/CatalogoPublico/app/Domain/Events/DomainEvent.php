<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Events;

use DateTimeImmutable;
abstract class DomainEvent
{
    public readonly DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = new DateTimeImmutable;
    }

    abstract public function eventName(): string;
}
