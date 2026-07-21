<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo;

/**
 * DTO de presentación para un ítem (espécimen) de un préstamo, listo para la vista.
 */
final readonly class ItemPrestamoVista
{
    /**
     * @param  string  $itemPrestamoId  Identificador del ítem de préstamo.
     * @param  string  $codigoExterno  Código externo legible del espécimen.
     * @param  int  $cantidadSolicitada  Cantidad de individuos/lotes solicitados.
     * @param  string|null  $nombre  Nombre legible del espécimen tomado del snapshot, si existe.
     * @param  string|null  $condicionesEspecificas  Condiciones específicas fijadas por el curador, si existen.
     * @param  string|null  $especimenId  Identificador en el catálogo de inventario. Null en ítems anteriores a la conexión con el catálogo.
     * @param  int|null  $individualesDisponibles  Disponibles según el snapshot tomado al solicitar; sirve de tope al reeditar el borrador.
     * @param  string|null  $familia  Familia taxonómica del espécimen. Solo la llena el documento del acta.
     * @param  string|null  $sexo  Sexo del espécimen, ya traducido. Solo lo llena el documento del acta.
     * @param  string|null  $especie  Nombre científico del espécimen. Solo la llena el documento del acta.
     * @param  string|null  $provincia  Provincia de colecta. Solo la llena el documento del acta.
     * @param  string|null  $localidad  Localidad de colecta. Solo la llena el documento del acta.
     */
    public function __construct(
        public string $itemPrestamoId,
        public string $codigoExterno,
        public int $cantidadSolicitada,
        public ?string $nombre = null,
        public ?string $condicionesEspecificas = null,
        public ?string $especimenId = null,
        public ?int $individualesDisponibles = null,
        public ?string $familia = null,
        public ?string $sexo = null,
        public ?string $especie = null,
        public ?string $provincia = null,
        public ?string $localidad = null,
    ) {}
}
