<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DefinirConfiguracionGlobalRecordatorios;

final readonly class DefinirConfiguracionGlobalRecordatoriosInput
{
    /**
     * @param  list<int>  $diasAntes
     */
    public function __construct(
        public string $curadorId,
        public array $diasAntes,
    ) {}
}
