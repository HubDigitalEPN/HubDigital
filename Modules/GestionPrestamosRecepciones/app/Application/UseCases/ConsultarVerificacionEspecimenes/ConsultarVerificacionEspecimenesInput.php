<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;

/**
 * Input DTO para consultar la verificación de especímenes de un préstamo.
 */
final readonly class ConsultarVerificacionEspecimenesInput
{
    /**
     * @param string $prestamoId Identificador del préstamo.
     * @param TipoVerificacion $tipo Momento del ciclo de vida a consultar (recepción o devolución).
     */
    public function __construct(
        public string $prestamoId,
        public TipoVerificacion $tipo,
    ) {}
}
