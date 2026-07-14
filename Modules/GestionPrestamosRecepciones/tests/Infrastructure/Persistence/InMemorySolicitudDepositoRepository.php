<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class InMemorySolicitudDepositoRepository implements SolicitudDepositoRepositoryInterface
{
    /** @var array<string, SolicitudDeposito> */
    private array $store = [];

    private int $contadorNumero = 0;

    public function nextIdentity(): SolicitudDepositoId
    {
        return SolicitudDepositoId::generate();
    }

    public function nextNumero(): NumeroSolicitudDeposito
    {
        $this->contadorNumero++;

        return NumeroSolicitudDeposito::fromSecuencia($this->contadorNumero);
    }

    public function guardar(SolicitudDeposito $solicitud): void
    {
        $this->store[(string) $solicitud->id()] = $solicitud;
    }

    public function buscarPorId(SolicitudDepositoId $id): ?SolicitudDeposito
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorCodigoQR(CodigoQRLote $codigoQR): ?SolicitudDeposito
    {
        foreach ($this->store as $solicitud) {
            if ($solicitud->codigoQR()?->equals($codigoQR)) {
                return $solicitud;
            }
        }

        return null;
    }

    public function contarPorInvestigadorYTipoEnAnioActual(string $investigadorId, string $tipoTramite): int
    {
        $count = 0;

        foreach ($this->store as $solicitud) {
            if ($solicitud->investigadorId() === $investigadorId
                && $solicitud->tipoTramite() === $tipoTramite) {
                $count++;
            }
        }

        return $count;
    }

    public function eliminarBorradoresDe(string $investigadorId): void
    {
        foreach ($this->store as $key => $solicitud) {
            if ($solicitud->investigadorId() === $investigadorId
                && $solicitud->estado()->value === 'En Borrador') {
                unset($this->store[$key]);
            }
        }
    }
}
