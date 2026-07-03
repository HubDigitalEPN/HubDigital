<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Repositories;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\Patente;

/**
 * Repositorio de la patente anual del laboratorio (una por año).
 */
interface PatenteAnualRepositoryInterface
{
    /**
     * Persiste (o reemplaza) la patente del año indicado.
     */
    public function guardarParaAnio(int $anio, Patente $codigo, string $curadorId): void;

    /**
     * Devuelve el código de patente registrado para el año, o null si no existe.
     */
    public function buscarCodigoPorAnio(int $anio): ?string;
}
