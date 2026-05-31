<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ConfiguracionGlobalRecordatoriosId;

final readonly class ConfiguracionGlobalRecordatoriosActualizada
{
    /**
     * @param  list<int>  $diasAntes
     */
    public function __construct(
        public ConfiguracionGlobalRecordatoriosId $configuracionId,
        public string $curadorId,
        public array $diasAntes,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
