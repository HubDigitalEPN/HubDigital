<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo;

/**
 * DTO de presentación para un ítem (espécimen) de un préstamo, listo para la vista.
 */
final readonly class ItemPrestamoVista
{
    /**
     * @param string $itemPrestamoId Identificador del ítem de préstamo.
     * @param string $codigoExterno Código externo legible del espécimen.
     * @param int $cantidadSolicitada Cantidad de individuos/lotes solicitados.
     * @param string|null $nombre Nombre legible del espécimen tomado del snapshot, si existe.
     * @param string|null $condicionesEspecificas Condiciones específicas fijadas por el curador, si existen.
     */
    public function __construct(
        public string $itemPrestamoId,
        public string $codigoExterno,
        public int $cantidadSolicitada,
        public ?string $nombre = null,
        public ?string $condicionesEspecificas = null,
    ) {}
}
