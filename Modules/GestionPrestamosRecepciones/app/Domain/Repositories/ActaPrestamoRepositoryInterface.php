<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

interface ActaPrestamoRepositoryInterface
{
    public function guardar(ActaPrestamo $acta): void;

    public function buscarPorId(ActaPrestamoId $id): ?ActaPrestamo;

    public function buscarPorSolicitudId(SolicitudPrestamoId $solicitudId): ?ActaPrestamo;

    public function nextIdentity(): ActaPrestamoId;
}
