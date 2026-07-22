<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Exceptions;

/**
 * Lanzada cuando un ítem de la solicitud referencia un espécimen que no existe,
 * ya no está disponible, o pide más individuos de los que hay en la colección.
 */
final class EspecimenNoSolicitableException extends ApplicationException
{
    public static function noDisponible(string $especimenId): self
    {
        return new self(sprintf(
            'El espécimen "%s" ya no está disponible en la colección.',
            $especimenId
        ));
    }

    public static function cantidadExcedida(string $codigoCatalogo, int $solicitada, int $disponibles): self
    {
        return new self(sprintf(
            'Se solicitaron %d individuos del espécimen "%s", pero solo hay %d disponibles.',
            $solicitada,
            $codigoCatalogo,
            $disponibles
        ));
    }
}
