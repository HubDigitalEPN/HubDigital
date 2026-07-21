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
     * @param string|null $especimenId Identificador en el catálogo de inventario. Null en ítems anteriores a la conexión con el catálogo.
     * @param int|null $individualesDisponibles Disponibles según el snapshot tomado al solicitar; sirve de tope al reeditar el borrador.
     */
    public function __construct(
        public string $itemPrestamoId,
        public string $codigoExterno,
        public int $cantidadSolicitada,
        public ?string $nombre = null,
        public ?string $condicionesEspecificas = null,
        public ?string $especimenId = null,
        public ?int $individualesDisponibles = null,
    ) {}
}
