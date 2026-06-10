<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;

/**
 * Output DTO para el caso de uso de devolver un acta de préstamo para refirmar.
 */
final readonly class DevolverActaParaRefirmarOutput
{
    /**
     * @param string $actaId ID del acta de préstamo.
     * @param string $numeroPrestamo Número del préstamo.
     * @param string $estadoActa Estado actualizado del acta.
     */
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public string $estadoActa,
    ) {}

    /**
     * Crea una instancia de salida a partir de una entidad ActaPrestamo.
     *
     * @param ActaPrestamo $acta Entidad ActaPrestamo.
     * @return self
     */
    public static function fromPrimitives(ActaPrestamo $acta): self
    {
        return new self(
            actaId: (string) $acta->id(),
            numeroPrestamo: (string) $acta->numeroPrestamo(),
            estadoActa: $acta->estado()->value,
        );
    }
}
