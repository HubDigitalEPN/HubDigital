<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarVerificacionEntrega;

/**
 * Datos de entrada para registrar la verificación de entrega.
 */
final readonly class RegistrarVerificacionEntregaInput
{
    /**
     * @param  array<array{itemPrestamoId: string, descripcion: string}>  $observaciones
     */
    public function __construct(
        public string $prestamoId,
        public string $estadoEnvio,
        public array $observaciones,
    ) {}
}
