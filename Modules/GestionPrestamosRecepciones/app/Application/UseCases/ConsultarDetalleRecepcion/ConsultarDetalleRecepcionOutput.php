<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion;

use DateTimeImmutable;

/** DTO de lectura para la pantalla de recepción física: datos del lote/solicitud + estado de la recepción. */
final readonly class ConsultarDetalleRecepcionOutput
{
    /**
     * @param  array<int, array{item: string, resultado: string}>  $itemsVerificacion
     * @param  list<string>  $observaciones
     */
    public function __construct(
        public string $numeroSolicitud,
        public string $tipoTramite,
        public string $investigadorId,
        public ?string $codigoQR,
        public ?string $nroLotes,
        public ?string $nroIndividuos,
        public ?string $nroMorfoespecies,
        public ?string $grupoAnimal,
        public ?string $localidad,
        public ?string $nroPermisoRecoleccion,
        public ?string $nroPermisoMovilizacion,
        public ?string $curadorResponsable,
        public ?DateTimeImmutable $aprobadaEn,
        public bool $recepcionIniciada,
        public ?string $estadoRecepcion,
        public ?string $estadoColeccion,
        public array $itemsVerificacion,
        public array $observaciones,
        public ?string $motivoFallo,
        public ?string $accionCorrectiva,
        public bool $actaEmitida,
        public bool $actaFirmada,
        public ?string $actaFirmadaRuta,
    ) {}
}
