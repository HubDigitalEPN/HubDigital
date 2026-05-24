<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ConfiguracionGlobalRecordatoriosId;

interface ConfiguracionGlobalRecordatoriosRepositoryInterface
{
    public function guardar(ConfiguracionGlobalRecordatorios $configuracion): void;

    public function obtenerUnica(): ?ConfiguracionGlobalRecordatorios;

    public function nextIdentity(): ConfiguracionGlobalRecordatoriosId;
}
