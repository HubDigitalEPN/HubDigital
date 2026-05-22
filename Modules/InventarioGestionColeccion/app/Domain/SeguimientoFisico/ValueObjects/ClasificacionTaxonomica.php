<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Encapsula los 7 niveles taxonómicos relevantes para la colección entomológica.
 * Todos los niveles son opcionales ya que un especimen puede estar parcialmente clasificado.
 *
 * Los niveles subfamilia y genero son los que usa EvaluadorOrdenTaxonomico
 * para la comparación alfabética entre cajas vecinas.
 */
final readonly class ClasificacionTaxonomica
{
    private function __construct(
        private ?string $orden,
        private ?string $suborden,
        private ?string $superfamilia,
        private ?string $familia,
        private ?string $subfamilia,
        private ?string $genero,
        private ?string $especie,
    ) {}

    public static function desde(
        ?string $orden = null,
        ?string $suborden = null,
        ?string $superfamilia = null,
        ?string $familia = null,
        ?string $subfamilia = null,
        ?string $genero = null,
        ?string $especie = null,
    ): self {
        return new self(
            orden: self::normalizar($orden),
            suborden: self::normalizar($suborden),
            superfamilia: self::normalizar($superfamilia),
            familia: self::normalizar($familia),
            subfamilia: self::normalizar($subfamilia),
            genero: self::normalizar($genero),
            especie: self::normalizar($especie),
        );
    }

    /**
     * Retorna true si todos los niveles son null.
     * Una clasificación vacía equivale a "sin clasificación asignable".
     */
    public function estaVacia(): bool
    {
        return $this->orden === null
            && $this->suborden === null
            && $this->superfamilia === null
            && $this->familia === null
            && $this->subfamilia === null
            && $this->genero === null
            && $this->especie === null;
    }

    /**
     * Compara estrictamente campo a campo.
     */
    public function equals(self $other): bool
    {
        return $this->orden === $other->orden
            && $this->suborden === $other->suborden
            && $this->superfamilia === $other->superfamilia
            && $this->familia === $other->familia
            && $this->subfamilia === $other->subfamilia
            && $this->genero === $other->genero
            && $this->especie === $other->especie;
    }

    public function orden(): ?string
    {
        return $this->orden;
    }

    public function suborden(): ?string
    {
        return $this->suborden;
    }

    public function superfamilia(): ?string
    {
        return $this->superfamilia;
    }

    public function familia(): ?string
    {
        return $this->familia;
    }

    public function subfamilia(): ?string
    {
        return $this->subfamilia;
    }

    public function genero(): ?string
    {
        return $this->genero;
    }

    public function especie(): ?string
    {
        return $this->especie;
    }

    private static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $trimmed = trim($valor);

        return $trimmed === '' ? null : $trimmed;
    }
}
