<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ReabrirSolicitudParaCorreccion;

/**
 * Datos de entrada para reabrir una solicitud de depósito rechazada (subsanable)
 * y permitir que el depositante la corrija.
 */
final readonly class ReabrirSolicitudParaCorreccionInput
{
    public function __construct(
        public string $solicitudId,
        public string $investigadorId,
    ) {}
}
