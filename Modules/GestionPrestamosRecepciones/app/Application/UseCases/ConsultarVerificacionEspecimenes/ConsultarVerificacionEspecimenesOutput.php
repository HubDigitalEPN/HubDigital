<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoVerificacion;

/**
 * Output DTO con la verificación de especímenes lista para renderizar: incluye las
 * novedades por espécimen con su código externo ya resuelto.
 */
final readonly class ConsultarVerificacionEspecimenesOutput
{
    /**
     * @param bool $existe Indica si hay una verificación registrada para el préstamo y tipo.
     * @param ResultadoVerificacion|null $resultado Resultado de la inspección, o null si no existe.
     * @param string|null $observacionGeneral Nota general opcional.
     * @param list<ObservacionEspecimenVista> $observaciones Novedades por espécimen con código resuelto.
     */
    public function __construct(
        public bool $existe,
        public ?ResultadoVerificacion $resultado,
        public ?string $observacionGeneral,
        public array $observaciones,
    ) {}
}
