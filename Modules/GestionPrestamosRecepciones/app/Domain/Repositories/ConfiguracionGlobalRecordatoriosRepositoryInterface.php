<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ConfiguracionGlobalRecordatorios;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ConfiguracionGlobalRecordatoriosId;

/**
 * Puerto de persistencia para el agregado {@see ConfiguracionGlobalRecordatorios}.
 *
 * Solo existe una configuración global en el sistema; de ahí {@see obtenerUnica()}.
 */
interface ConfiguracionGlobalRecordatoriosRepositoryInterface
{
    /** Persiste (inserta o actualiza) la configuración global. */
    public function guardar(ConfiguracionGlobalRecordatorios $configuracion): void;

    /** Recupera la única configuración global existente, o null si aún no se ha definido. */
    public function obtenerUnica(): ?ConfiguracionGlobalRecordatorios;

    /** Genera un identificador nuevo para la configuración. */
    public function nextIdentity(): ConfiguracionGlobalRecordatoriosId;
}
