<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

/**
 * Resultado de atar las filas de una matriz a los especímenes que ya produjeron.
 *
 * `discrepancias` manda sobre todo lo demás: si el orden reconstruido de la matriz no
 * coincide con lo que se ingresó, no se escribe nada y aquí queda dicho por qué.
 *
 * @param  string[]  $discrepancias
 */
final readonly class ResultadoVinculacionDeposito
{
    public function __construct(
        public int $especimenesVinculados,
        public int $filasAnotadas,
        public int $yaVinculados,
        public int $sinEspecimen,
        public array $discrepancias,
        public bool $simulado,
    ) {}

    public function esConsistente(): bool
    {
        return $this->discrepancias === [];
    }

    public static function sinMatriz(): self
    {
        return new self(0, 0, 0, 0, ['la solicitud no tiene matriz de especies'], true);
    }
}
