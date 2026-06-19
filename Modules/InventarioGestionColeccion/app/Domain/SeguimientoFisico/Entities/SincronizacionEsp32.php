<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoSincronizacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LecturaRanura;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\SincronizacionEsp32Id;

class SincronizacionEsp32
{
    private function __construct(
        private readonly SincronizacionEsp32Id $id,
        private readonly GabineteId $gabineteId,
        /** @var LecturaRanura[] */
        private readonly array $lecturas,
        private readonly EstadoSincronizacion $estado,
        private readonly \DateTimeImmutable $realizadaEn,
        private readonly int $totalIncongruencias,
    ) {}

    /**
     * @param  LecturaRanura[]  $lecturas
     */
    public static function registrar(
        SincronizacionEsp32Id $id,
        GabineteId $gabineteId,
        array $lecturas,
        \DateTimeImmutable $realizadaEn,
    ): self {
        $totalIncongruencias = count(array_filter($lecturas, fn (LecturaRanura $l) => $l->esIncongruente()));

        return new self(
            id: $id,
            gabineteId: $gabineteId,
            lecturas: $lecturas,
            estado: $totalIncongruencias > 0
                ? EstadoSincronizacion::ConIncongruencias
                : EstadoSincronizacion::Procesada,
            realizadaEn: $realizadaEn,
            totalIncongruencias: $totalIncongruencias,
        );
    }

    public function tieneIncongruencias(): bool
    {
        return $this->totalIncongruencias > 0;
    }

    /** @return LecturaRanura[] */
    public function lecturas(): array
    {
        return $this->lecturas;
    }

    /** @return LecturaRanura[] */
    public function lecturasIncongruentes(): array
    {
        return array_values(array_filter($this->lecturas, fn (LecturaRanura $l) => $l->esIncongruente()));
    }

    public function id(): SincronizacionEsp32Id
    {
        return $this->id;
    }

    public function gabineteId(): GabineteId
    {
        return $this->gabineteId;
    }

    public function estado(): EstadoSincronizacion
    {
        return $this->estado;
    }

    public function realizadaEn(): \DateTimeImmutable
    {
        return $this->realizadaEn;
    }

    public function totalIncongruencias(): int
    {
        return $this->totalIncongruencias;
    }
}
