<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

final readonly class IdentificadorEspecimen
{
    private function __construct(
        private TipoIdentificadorEspecimen $tipo,
        private string $valor,
    ) {}

    public static function crear(string $tipo, string $valor): self
    {
        $tipoVO = TipoIdentificadorEspecimen::tryFrom($tipo);

        if ($tipoVO === null) {
            throw new \InvalidArgumentException("Tipo de identificador de espécimen inválido: '{$tipo}'.");
        }

        $valor = trim($valor);

        if ($valor === '') {
            throw new \InvalidArgumentException('El valor del identificador de espécimen no puede estar vacío.');
        }

        return new self($tipoVO, $valor);
    }

    public function tipo(): TipoIdentificadorEspecimen
    {
        return $this->tipo;
    }

    public function valor(): string
    {
        return $this->valor;
    }

    /** @return array{tipo: string, valor: string} */
    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo->value,
            'valor' => $this->valor,
        ];
    }
}
