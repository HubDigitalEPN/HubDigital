<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Secuencia esperada de familias definida por el curador.
 *
 * El orden de familias NO es alfabético (a diferencia de subfamilia/género/especie):
 * es una decisión curatorial sobre qué bloque de familia va antes que otro a lo largo
 * de la colección (p. ej. Formicidae antes que Coleoptera). Sirve de referencia para
 * EvaluadorOrdenTaxonomico al comparar cajas vecinas de distinta familia.
 *
 * Los nombres se normalizan (trim) y se deduplican preservando el orden de inserción.
 */
final readonly class OrdenEsperadoFamilias
{
    /**
     * @param  string[]  $familias  secuencia ordenada de nombres de familia normalizados y únicos
     */
    private function __construct(private array $familias) {}

    /**
     * Construye la secuencia normalizando cada nombre (trim) y deduplicando de forma
     * case-insensitive, preservando el orden de inserción dado por el curador.
     *
     * @param  string[]  $familias
     */
    public static function desde(array $familias): self
    {
        $normalizadas = [];

        foreach ($familias as $familia) {
            $valor = self::normalizar($familia);

            if ($valor === null) {
                continue;
            }

            $yaPresente = false;
            foreach ($normalizadas as $existente) {
                if (strcasecmp($existente, $valor) === 0) {
                    $yaPresente = true;
                    break;
                }
            }

            if (! $yaPresente) {
                $normalizadas[] = $valor;
            }
        }

        return new self(array_values($normalizadas));
    }

    /** Crea una secuencia vacía, útil cuando el curador aún no ha definido un orden. */
    public static function vacio(): self
    {
        return new self([]);
    }

    /**
     * Posición (0-based) de la familia en la secuencia esperada, o null si no está.
     * Comparación case-insensitive.
     */
    public function posicionDe(?string $familia): ?int
    {
        $valor = self::normalizar($familia);

        if ($valor === null) {
            return null;
        }

        foreach ($this->familias as $i => $f) {
            if (strcasecmp($f, $valor) === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function familias(): array
    {
        return $this->familias;
    }

    /** Indica si la secuencia no contiene ninguna familia. */
    public function estaVacio(): bool
    {
        return $this->familias === [];
    }

    /** Recorta el valor y lo convierte a null cuando queda vacío. */
    private static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $trimmed = trim($valor);

        return $trimmed === '' ? null : $trimmed;
    }
}
