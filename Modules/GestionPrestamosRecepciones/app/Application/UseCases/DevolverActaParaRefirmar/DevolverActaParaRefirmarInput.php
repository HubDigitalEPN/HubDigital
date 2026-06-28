<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar;

/**
 * Input DTO para el caso de uso de devolver un acta de préstamo para ser refirmada.
 */
final readonly class DevolverActaParaRefirmarInput
{
    /**
     * @param string $actaId ID del acta de préstamo.
     * @param string $curadorId ID del curador que devuelve el acta.
     * @param string $motivo Motivo por el cual se devuelve el acta.
     * @param bool $devolverActa Si se devuelve el acta firmada para refirmar.
     * @param bool $devolverIdentidad Si se devuelve el documento de identidad para recargar.
     */
    public function __construct(
        public string $actaId,
        public string $curadorId,
        public string $motivo,
        public bool $devolverActa = true,
        public bool $devolverIdentidad = true,
    ) {}
}
