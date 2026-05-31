<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico;

final readonly class ActualizarRecordatoriosPrestamoEspecificoInput
{
    /**
     * @param  list<int>  $diasAntes
     */
    public function __construct(
        public string $prestamoId,
        public string $curadorId,
        public array $diasAntes,
    ) {}
}
