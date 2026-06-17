<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ConfiguracionGlobalRecordatoriosId;

/**
 * Evento de dominio emitido cuando el curador define por primera vez la configuración global de recordatorios.
 */
final readonly class ConfiguracionGlobalRecordatoriosDefinida
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
