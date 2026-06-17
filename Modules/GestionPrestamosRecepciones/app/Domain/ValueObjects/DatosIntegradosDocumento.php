<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Value object inmutable que agrupa los datos extraídos de la documentación oficial
 * de una solicitud de depósito (permisos, provincia, grupo animal, etc.).
 *
 * Cada campo es nullable: un valor null indica que ese dato no pudo extraerse del
 * documento y deberá completarse manualmente. Lo consume
 * {@see \Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito::integrarDatosDeDocumentos()}.
 */
final readonly class DatosIntegradosDocumento
{
    public function __construct(
        public readonly ?string $nroPermisoRecoleccion,
        public readonly ?string $nroPermisoMovilizacion,
        public readonly ?string $grupoAnimal,
        public readonly ?string $provinciaOrigen,
        public readonly ?string $localidad,
        public readonly ?string $origenDonacion,
        public readonly ?string $nombreInvestigador = null,
    ) {}
}
