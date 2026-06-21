<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarConfiguracionGlobalRecordatorios;

/**
 * Resultado de la consulta de la configuración global de recordatorios.
 *
 * Cuando aún no se ha definido ninguna configuración, $configuracionId y
 * $diasAntes son null.
 *
 * {@see ConsultarConfiguracionGlobalRecordatoriosHandler}
 */
final readonly class ConsultarConfiguracionGlobalRecordatoriosOutput
{
    /**
     * @param array<int, int>|null $diasAntes
     */
    public function __construct(
        public ?string $configuracionId,
        public ?array $diasAntes,
    ) {}
}
