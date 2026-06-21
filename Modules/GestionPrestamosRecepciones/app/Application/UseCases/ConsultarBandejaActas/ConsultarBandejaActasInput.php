<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaActas;

/**
 * Datos de entrada para consultar la bandeja de actas del curador.
 *
 * {@see ConsultarBandejaActasHandler}
 */
final readonly class ConsultarBandejaActasInput
{
    public function __construct(
        public string $busqueda = '',
        public string $estado = '',
        public string $busquedaInvestigador = '',
        public ?string $investigadorPropio = null,
        public string $ordenCampo = 'fecha',
        public string $ordenDireccion = 'desc',
    ) {}
}
