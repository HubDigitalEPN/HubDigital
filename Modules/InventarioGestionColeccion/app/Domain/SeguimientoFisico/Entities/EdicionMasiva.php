<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use DateTimeImmutable;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEdicionMasiva;

/**
 * Cabecera de una edición masiva: qué se hizo, sobre qué campo, quién y cuándo.
 *
 * El "qué valor tenía antes cada fila" vive en {@see DetalleEdicionMasiva}, una
 * fila por espécimen. Aquí solo está lo común a toda la operación.
 */
final class EdicionMasiva
{
    private function __construct(
        private readonly string $id,
        private readonly TipoEdicionMasiva $tipo,
        private readonly string $campo,
        private readonly ?string $valorAplicado,
        private readonly ?string $textoBuscado,
        private readonly ?string $textoReemplazo,
        private readonly int $totalAfectados,
        private readonly ?string $actorId,
        private readonly ?string $actorNombre,
        private readonly DateTimeImmutable $creadoEn,
        private ?DateTimeImmutable $deshechaEn,
    ) {}

    public static function registrar(
        string $id,
        TipoEdicionMasiva $tipo,
        string $campo,
        ?string $valorAplicado,
        int $totalAfectados,
        DateTimeImmutable $creadoEn,
        ?string $actorId = null,
        ?string $actorNombre = null,
        ?string $textoBuscado = null,
        ?string $textoReemplazo = null,
    ): self {
        return new self(
            $id, $tipo, $campo, $valorAplicado, $textoBuscado, $textoReemplazo,
            $totalAfectados, $actorId, $actorNombre, $creadoEn, null,
        );
    }

    public static function reconstituir(
        string $id,
        TipoEdicionMasiva $tipo,
        string $campo,
        ?string $valorAplicado,
        ?string $textoBuscado,
        ?string $textoReemplazo,
        int $totalAfectados,
        ?string $actorId,
        ?string $actorNombre,
        DateTimeImmutable $creadoEn,
        ?DateTimeImmutable $deshechaEn,
    ): self {
        return new self(
            $id, $tipo, $campo, $valorAplicado, $textoBuscado, $textoReemplazo,
            $totalAfectados, $actorId, $actorNombre, $creadoEn, $deshechaEn,
        );
    }

    /**
     * Sella la edición como deshecha.
     *
     * Solo se puede una vez: a la segunda, los valores previos guardados ya no
     * describen el estado del que se partía, así que revertir de nuevo o bien
     * no haría nada o bien desharía un cambio posterior que nadie pidió tocar.
     *
     * @throws \DomainException
     */
    public function marcarDeshecha(DateTimeImmutable $cuando): void
    {
        if ($this->deshechaEn !== null) {
            throw new \DomainException('Esta edición masiva ya se deshizo; no puede deshacerse dos veces.');
        }

        $this->deshechaEn = $cuando;
    }

    public function fueDeshecha(): bool
    {
        return $this->deshechaEn !== null;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function tipo(): TipoEdicionMasiva
    {
        return $this->tipo;
    }

    public function campo(): string
    {
        return $this->campo;
    }

    public function valorAplicado(): ?string
    {
        return $this->valorAplicado;
    }

    public function textoBuscado(): ?string
    {
        return $this->textoBuscado;
    }

    public function textoReemplazo(): ?string
    {
        return $this->textoReemplazo;
    }

    public function totalAfectados(): int
    {
        return $this->totalAfectados;
    }

    public function actorId(): ?string
    {
        return $this->actorId;
    }

    public function actorNombre(): ?string
    {
        return $this->actorNombre;
    }

    public function creadoEn(): DateTimeImmutable
    {
        return $this->creadoEn;
    }

    public function deshechaEn(): ?DateTimeImmutable
    {
        return $this->deshechaEn;
    }
}
