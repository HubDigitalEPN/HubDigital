<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * Encapsula los niveles taxonómicos relevantes para la colección entomológica.
 * Todos los niveles son opcionales ya que un especimen puede estar parcialmente clasificado.
 *
 * Los niveles superiores (orden → familia) y la especie se modelan como valores únicos: una
 * caja vive dentro de una familia y a ese nivel no se acumula multiplicidad. En cambio
 * subfamilia y género se modelan como CONJUNTOS ($subfamilias, $generos), porque una caja real
 * —al agregar la clasificación de sus UnitTrays— puede albergar más de una subfamilia y más de
 * un género. El primer elemento de cada conjunto es el valor DOMINANTE; los accesores escalares
 * subfamilia() y genero() lo devuelven para mantener una vista única conveniente, sin duplicar
 * el dato. Para una clasificación de un solo taxón cada conjunto tiene a lo sumo un elemento.
 *
 * Los niveles subfamilia y género son los que usa EvaluadorOrdenTaxonomico para la comparación
 * (por rango) entre cajas vecinas.
 */
final readonly class ClasificacionTaxonomica
{
    /**
     * @param  string[]  $subfamilias  Subfamilias distintas presentes (dominante primero).
     * @param  string[]  $generos  Géneros distintos presentes (dominante primero).
     */
    private function __construct(
        private ?string $orden,
        private ?string $suborden,
        private ?string $superfamilia,
        private ?string $familia,
        private ?string $especie,
        private array $subfamilias = [],
        private array $generos = [],
    ) {}

    /**
     * Construye la clasificación a partir de los niveles taxonómicos disponibles,
     * normalizando cada uno (cadenas vacías se vuelven null). Todos los argumentos son
     * opcionales porque un espécimen puede estar solo parcialmente clasificado.
     */
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
            especie: self::normalizar($especie),
            subfamilias: self::normalizarLista(self::normalizar($subfamilia), []),
            generos: self::normalizarLista(self::normalizar($genero), []),
        );
    }

    /**
     * Devuelve una copia que conserva la combinación dominante pero amplía los conjuntos de
     * subfamilias y géneros con todos los valores distintos observados. La usa
     * CalculadorClasificacionDominante al agregar la clasificación de una Caja, para que una
     * caja con varios géneros o subfamilias los muestre todos sin perder cuál es el dominante.
     *
     * @param  string[]  $subfamilias
     * @param  string[]  $generos
     */
    public function conSubfamiliasYGeneros(array $subfamilias, array $generos): self
    {
        return new self(
            orden: $this->orden,
            suborden: $this->suborden,
            superfamilia: $this->superfamilia,
            familia: $this->familia,
            especie: $this->especie,
            subfamilias: self::normalizarLista($this->subfamilia(), $subfamilias),
            generos: self::normalizarLista($this->genero(), $generos),
        );
    }

    /**
     * Retorna true si todos los niveles son null/vacíos.
     * Una clasificación vacía equivale a "sin clasificación asignable".
     */
    public function estaVacia(): bool
    {
        return $this->orden === null
            && $this->suborden === null
            && $this->superfamilia === null
            && $this->familia === null
            && $this->especie === null
            && $this->subfamilias === []
            && $this->generos === [];
    }

    /**
     * Compara estrictamente campo a campo, incluyendo los conjuntos de subfamilias y
     * géneros (sin importar el orden ni las mayúsculas).
     */
    public function equals(self $other): bool
    {
        return $this->orden === $other->orden
            && $this->suborden === $other->suborden
            && $this->superfamilia === $other->superfamilia
            && $this->familia === $other->familia
            && $this->especie === $other->especie
            && self::mismasListas($this->subfamilias, $other->subfamilias)
            && self::mismasListas($this->generos, $other->generos);
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

    /** Subfamilia dominante: el primer elemento del conjunto, o null si no hay ninguna. */
    public function subfamilia(): ?string
    {
        return $this->subfamilias[0] ?? null;
    }

    /** Género dominante: el primer elemento del conjunto, o null si no hay ninguno. */
    public function genero(): ?string
    {
        return $this->generos[0] ?? null;
    }

    public function especie(): ?string
    {
        return $this->especie;
    }

    /**
     * Conjunto completo de subfamilias distintas presentes (dominante primero). Para una
     * clasificación de un solo taxón equivale a la subfamilia escalar (o vacío si es null).
     *
     * @return string[]
     */
    public function subfamilias(): array
    {
        return $this->subfamilias;
    }

    /**
     * Conjunto completo de géneros distintos presentes (dominante primero). Para una
     * clasificación de un solo taxón equivale al género escalar (o vacío si es null).
     *
     * @return string[]
     */
    public function generos(): array
    {
        return $this->generos;
    }

    /** Indica si la caja abarca más de una subfamilia o más de un género. */
    public function tieneVariosTaxones(): bool
    {
        return count($this->subfamilias) > 1 || count($this->generos) > 1;
    }

    /** Recorta el valor y lo convierte a null cuando queda vacío, unificando "ausente". */
    private static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $trimmed = trim($valor);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Normaliza una lista de valores a un conjunto sin duplicados (insensible a mayúsculas),
     * anteponiendo el valor dominante para que siempre encabece el resultado.
     *
     * @param  string[]  $valores
     * @return string[]
     */
    private static function normalizarLista(?string $dominante, array $valores): array
    {
        $resultado = [];
        $vistos = [];

        $candidatos = $dominante !== null ? [$dominante, ...$valores] : $valores;

        foreach ($candidatos as $valor) {
            $norm = self::normalizar($valor);
            if ($norm === null) {
                continue;
            }

            $clave = mb_strtolower($norm);
            if (isset($vistos[$clave])) {
                continue;
            }

            $vistos[$clave] = true;
            $resultado[] = $norm;
        }

        return $resultado;
    }

    /**
     * Compara dos listas como conjuntos: mismo contenido sin importar orden ni mayúsculas.
     *
     * @param  string[]  $a
     * @param  string[]  $b
     */
    private static function mismasListas(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        return self::ordenarMinusculas($a) === self::ordenarMinusculas($b);
    }

    /**
     * @param  string[]  $lista
     * @return string[]
     */
    private static function ordenarMinusculas(array $lista): array
    {
        $minusculas = array_map(static fn (string $v): string => mb_strtolower($v), $lista);
        sort($minusculas);

        return $minusculas;
    }
}
