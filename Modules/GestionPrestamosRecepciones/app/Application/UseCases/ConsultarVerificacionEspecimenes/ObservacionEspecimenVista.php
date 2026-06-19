<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes;

/**
 * DTO de presentación para una novedad registrada sobre un espécimen, con el
 * código externo legible ya resuelto para mostrarse directamente en la vista.
 */
final readonly class ObservacionEspecimenVista
{
    /**
     * @param string $codigoEspecimen Código externo legible del espécimen (con fallback al ID del ítem).
     * @param string $descripcion Texto de la novedad reportada.
     */
    public function __construct(
        public string $codigoEspecimen,
        public string $descripcion,
    ) {}
}
