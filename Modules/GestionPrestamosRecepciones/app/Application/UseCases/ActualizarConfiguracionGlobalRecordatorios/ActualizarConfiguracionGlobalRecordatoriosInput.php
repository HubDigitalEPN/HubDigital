<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarConfiguracionGlobalRecordatorios;

final readonly class ActualizarConfiguracionGlobalRecordatoriosInput
{
    /**
     * @param  list<int>  $diasAntes
     */
    public function __construct(
        public string $configuracionId,
        public string $curadorId,
        public array $diasAntes,
    ) {}
}
