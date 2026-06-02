<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarVerificacionEntrega;

final readonly class AprobarVerificacionEntregaInput
{
    public function __construct(
        public string $prestamoId,
        public string $curadorId,
    ) {}
}
