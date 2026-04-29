<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

interface SolicitudPrestamoRepositoryInterface
{
    public function guardar(SolicitudPrestamo $solicitud): void;

    public function buscarPorId(SolicitudPrestamoId $id): ?SolicitudPrestamo;

    public function nextIdentity(): SolicitudPrestamoId;
}
