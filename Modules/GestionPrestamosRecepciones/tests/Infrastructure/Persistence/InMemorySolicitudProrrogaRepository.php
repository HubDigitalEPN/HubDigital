<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudProrroga;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudProrrogaRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudProrroga;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudProrrogaId;

final class InMemorySolicitudProrrogaRepository implements SolicitudProrrogaRepositoryInterface
{
    /** @var array<string, SolicitudProrroga> */
    private array $store = [];

    public function guardar(SolicitudProrroga $solicitud): void
    {
        $this->store[(string) $solicitud->id()] = $solicitud;
    }

    public function buscarPorId(SolicitudProrrogaId $id): ?SolicitudProrroga
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPendientePorPrestamo(PrestamoId $prestamoId): ?SolicitudProrroga
    {
        foreach ($this->store as $solicitud) {
            if ((string) $solicitud->prestamoId() === (string) $prestamoId
                && $solicitud->estado()->equals(EstadoSolicitudProrroga::Solicitada)) {
                return $solicitud;
            }
        }

        return null;
    }

    public function nextIdentity(): SolicitudProrrogaId
    {
        return SolicitudProrrogaId::generate();
    }
}
