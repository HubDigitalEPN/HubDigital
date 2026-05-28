<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ConfiguracionGlobalRecordatoriosRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ConfiguracionGlobalRecordatoriosId;

final class InMemoryConfiguracionGlobalRecordatoriosRepository implements ConfiguracionGlobalRecordatoriosRepositoryInterface
{
    private ?ConfiguracionGlobalRecordatorios $store = null;

    public function guardar(ConfiguracionGlobalRecordatorios $configuracion): void
    {
        $this->store = $configuracion;
    }

    public function obtenerUnica(): ?ConfiguracionGlobalRecordatorios
    {
        return $this->store;
    }

    public function nextIdentity(): ConfiguracionGlobalRecordatoriosId
    {
        return ConfiguracionGlobalRecordatoriosId::generate();
    }
}
