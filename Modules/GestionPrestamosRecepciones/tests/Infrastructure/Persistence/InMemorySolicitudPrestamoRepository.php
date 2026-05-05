<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class InMemorySolicitudPrestamoRepository implements SolicitudPrestamoRepositoryInterface
{
    /** @var array<string, SolicitudPrestamo> */
    private array $store = [];

    public function guardar(SolicitudPrestamo $solicitud): void
    {
        $this->store[(string) $solicitud->id()] = $solicitud;
    }

    public function buscarPorId(SolicitudPrestamoId $id): ?SolicitudPrestamo
    {
        return $this->store[(string) $id] ?? null;
    }

    public function nextIdentity(): SolicitudPrestamoId
    {
        return SolicitudPrestamoId::generate();
    }
}
