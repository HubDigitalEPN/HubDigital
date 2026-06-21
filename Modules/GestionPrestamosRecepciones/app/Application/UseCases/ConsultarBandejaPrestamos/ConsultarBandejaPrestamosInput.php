<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaPrestamos;

/**
 * Datos de entrada para consultar una bandeja de préstamos.
 *
 * Compartido por la bandeja del curador (filtra por nombre de solicitante) y la
 * del investigador (se restringe a sus propios préstamos vía $investigadorPropio).
 *
 * {@see ConsultarBandejaPrestamosHandler}
 */
final readonly class ConsultarBandejaPrestamosInput
{
    public function __construct(
        public string $busqueda = '',
        public string $estado = '',
        public string $busquedaInvestigador = '',
        public ?string $investigadorPropio = null,
        public string $ordenCampo = 'fecha_inicio',
        public string $ordenDireccion = 'desc',
    ) {}
}
