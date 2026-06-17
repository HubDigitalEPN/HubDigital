<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida;

/**
 * Input DTO para el caso de uso de determinar la documentación requerida para una solicitud de depósito.
 */
final readonly class DeterminarDocumentacionRequeridaInput
{
    /**
     * @param string|null $solicitudId ID de la solicitud (opcional).
     * @param string|null $tipoTramite Tipo de trámite (opcional).
     * @param string|null $origenRecoleccion Origen de recolección (opcional).
     * @param string|null $situacionRegulatoria Situación regulatoria (opcional).
     * @param string|null $provinciaOrigen Provincia de origen (opcional).
     */
    public function __construct(
        public ?string $solicitudId = null,
        public ?string $tipoTramite = null,
        public ?string $origenRecoleccion = null,
        public ?string $situacionRegulatoria = null,
        public ?string $provinciaOrigen = null,
    ) {}
}
