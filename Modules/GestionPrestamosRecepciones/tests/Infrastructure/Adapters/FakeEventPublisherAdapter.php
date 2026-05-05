<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;

final class FakeEventPublisherAdapter implements EventPublisherPort
{
    /** @var list<object> */
    private array $published = [];

    public function publish(object $event): void
    {
        $this->published[] = $event;
    }

    /** @return list<object> */
    public function publishedEvents(): array
    {
        return $this->published;
    }
}
