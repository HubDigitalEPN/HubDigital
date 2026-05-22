<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

interface PrestamoRepositoryInterface
{
    public function guardar(Prestamo $prestamo): void;

    public function buscarPorId(PrestamoId $id): ?Prestamo;

    public function nextIdentity(): PrestamoId;
}
