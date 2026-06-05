<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEntregaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\VerificacionEntregaId;

interface VerificacionEntregaPrestamoRepositoryInterface
{
    public function guardar(VerificacionEntregaPrestamo $verificacion): void;

    public function buscarPorPrestamoId(PrestamoId $prestamoId): ?VerificacionEntregaPrestamo;

    public function nextIdentity(): VerificacionEntregaId;
}
