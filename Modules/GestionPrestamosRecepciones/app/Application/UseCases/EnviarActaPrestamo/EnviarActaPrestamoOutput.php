<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;

/**
 * Output DTO para el caso de uso de enviar un acta de préstamo.
 */
final readonly class EnviarActaPrestamoOutput
{
    /**
     * @param string $actaId ID del acta de préstamo enviada.
     * @param string $numeroPrestamo Número de préstamo asignado.
     * @param string $estadoActa Estado actual del acta tras el envío.
     * @param string $pdfRuta Ruta del archivo PDF del acta.
     * @param bool $notificacionEnviada Indica si la notificación fue enviada exitosamente.
     */
    public function __construct(
        public string $actaId,
        public string $numeroPrestamo,
        public string $estadoActa,
        public string $pdfRuta,
        public bool $notificacionEnviada,
    ) {}

    /**
     * Crea una instancia a partir de la entidad ActaPrestamo y el estado de la notificación.
     *
     * @param ActaPrestamo $acta Entidad ActaPrestamo.
     * @param bool $notificacionEnviada Estado de la notificación.
     * @return self
     */
    public static function fromPrimitives(ActaPrestamo $acta, bool $notificacionEnviada): self
    {
        return new self(
            actaId: (string) $acta->id(),
            numeroPrestamo: (string) $acta->numeroPrestamo(),
            estadoActa: $acta->estado()->value,
            pdfRuta: $acta->pdfRuta(),
            notificacionEnviada: $notificacionEnviada,
        );
    }
}
