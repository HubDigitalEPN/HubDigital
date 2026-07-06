<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\RecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecepcionLoteRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecepcionLoteId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class InMemoryRecepcionLoteRepository implements RecepcionLoteRepositoryInterface
{
    /** @var array<string, RecepcionLote> */
    private array $store = [];

    public function nextIdentity(): RecepcionLoteId
    {
        return RecepcionLoteId::generate();
    }

    public function guardar(RecepcionLote $lote): void
    {
        $this->store[(string) $lote->id()] = $lote;
    }

    public function buscarPorId(RecepcionLoteId $id): ?RecepcionLote
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorSolicitudId(SolicitudDepositoId $solicitudId): ?RecepcionLote
    {
        foreach ($this->store as $lote) {
            if ($lote->solicitudId()->equals($solicitudId)) {
                return $lote;
            }
        }

        return null;
    }

    public function buscarPorCodigoQR(CodigoQRLote $codigoQR): ?RecepcionLote
    {
        foreach ($this->store as $lote) {
            if ($lote->codigoQR()->equals($codigoQR)) {
                return $lote;
            }
        }

        return null;
    }
}
