<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

interface EsquemaMatrizPort
{
    public function leer(): array;

    /** Actualiza una sola columna, conservando los cambios concurrentes en las demás. */
    public function guardarColumna(array $columna, ?string $campoAnterior, string $usuarioId): void;
}
