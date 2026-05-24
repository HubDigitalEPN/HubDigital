<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;

interface MatrizEspeciesRepositoryInterface
{
    public function nextIdentity(): MatrizEspeciesId;

    public function guardar(MatrizEspecies $matriz): void;

    public function buscarPorId(MatrizEspeciesId $matrizId): ?MatrizEspecies;

    public function buscarPorSolicitudId(string $solicitudId): ?MatrizEspecies;
}
