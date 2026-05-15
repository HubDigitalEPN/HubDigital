<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

interface SolicitudDepositoRepositoryInterface
{
    public function nextIdentity(): SolicitudDepositoId;

    public function guardar(SolicitudDeposito $solicitud): void;

    public function buscarPorId(SolicitudDepositoId $id): ?SolicitudDeposito;

    public function contarPorInvestigadorYTipoEnAnioActual(string $investigadorId, string $tipoTramite): int;

    public function eliminarBorradoresDe(string $investigadorId): void;
}
