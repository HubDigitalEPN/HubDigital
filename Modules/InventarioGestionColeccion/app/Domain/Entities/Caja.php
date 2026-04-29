<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Entities;

use Modules\InventarioGestionColeccion\Domain\Events\CajaIngresadaEnRanura;
use Modules\InventarioGestionColeccion\Domain\Events\CajaRetiradaDeRanura;
use Modules\InventarioGestionColeccion\Domain\Exceptions\CajaNoEnGabineteException;
use Modules\InventarioGestionColeccion\Domain\Exceptions\CajaNoEnTransitoException;
use Modules\InventarioGestionColeccion\Domain\Exceptions\RfidNoAsignadoException;
use Modules\InventarioGestionColeccion\Domain\Exceptions\RfidYaAsignadoException;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CodigoRfid;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\RanuraId;

class Caja
{
    /** @var object[] */
    private array $eventos = [];

    private function __construct(
        private readonly CajaId $id,
        private readonly CodigoCaja $codigo,
        private readonly ?string $familiaTaxonomicaId,
        private readonly ?string $nombre,
        private readonly ?int $capacidadMaxima,
        private EstadoCaja $estado,
        private ?RanuraId $ranuraActualId,
        private ?CodigoRfid $codigoRfid,
    ) {}

    public static function crear(
        CajaId $id,
        CodigoCaja $codigo,
        ?string $familiaTaxonomicaId = null,
        ?string $nombre = null,
        ?int $capacidadMaxima = null,
    ): self {
        return new self(
            id: $id,
            codigo: $codigo,
            familiaTaxonomicaId: $familiaTaxonomicaId,
            nombre: $nombre,
            capacidadMaxima: $capacidadMaxima,
            estado: EstadoCaja::EnTransito,
            ranuraActualId: null,
            codigoRfid: null,
        );
    }

    public static function reconstituir(
        CajaId $id,
        CodigoCaja $codigo,
        ?string $familiaTaxonomicaId,
        EstadoCaja $estado,
        ?RanuraId $ranuraActualId,
        ?CodigoRfid $codigoRfid,
        ?string $nombre = null,
        ?int $capacidadMaxima = null,
    ): self {
        return new self(
            id: $id,
            codigo: $codigo,
            familiaTaxonomicaId: $familiaTaxonomicaId,
            nombre: $nombre,
            capacidadMaxima: $capacidadMaxima,
            estado: $estado,
            ranuraActualId: $ranuraActualId,
            codigoRfid: $codigoRfid,
        );
    }

    public function ingresarEnRanura(RanuraId $ranuraId): void
    {
        if (!$this->estado->equals(EstadoCaja::EnTransito)) {
            throw new CajaNoEnTransitoException($this->id, $this->estado);
        }

        $this->estado = EstadoCaja::EnGabinete;
        $this->ranuraActualId = $ranuraId;
        $this->eventos[] = new CajaIngresadaEnRanura($this->id, $ranuraId, new \DateTimeImmutable());
    }

    public function retirarDeRanura(): void
    {
        if (!$this->estado->equals(EstadoCaja::EnGabinete)) {
            throw new CajaNoEnGabineteException($this->id, $this->estado);
        }

        $this->estado = EstadoCaja::EnTransito;
        $this->ranuraActualId = null;
        $this->eventos[] = new CajaRetiradaDeRanura($this->id, new \DateTimeImmutable());
    }

    public function asignarRfid(CodigoRfid $rfid): void
    {
        if ($this->codigoRfid !== null) {
            throw new RfidYaAsignadoException($this->id, $this->codigoRfid);
        }

        $this->codigoRfid = $rfid;
    }

    public function reasignarRfid(CodigoRfid $rfid): void
    {
        $this->codigoRfid = $rfid;
    }

    public function desasignarRfid(): void
    {
        if ($this->codigoRfid === null) {
            throw new RfidNoAsignadoException($this->id);
        }

        $this->codigoRfid = null;
    }

    public function marcarUbicacionIncorrecta(): void
    {
        if (!$this->estado->equals(EstadoCaja::EnGabinete)) {
            throw new CajaNoEnGabineteException($this->id, $this->estado);
        }

        $this->estado = EstadoCaja::UbicacionIncorrecta;
    }

    public function marcarPendienteClasificacion(): void
    {
        if (!$this->estado->equals(EstadoCaja::EnGabinete)) {
            throw new CajaNoEnGabineteException($this->id, $this->estado);
        }

        $this->estado = EstadoCaja::PendienteClasificacion;
    }

    public function tieneRfidAsignado(): bool
    {
        return $this->codigoRfid !== null;
    }

    public function sinAsignacion(): bool
    {
        return $this->codigoRfid === null && $this->ranuraActualId === null;
    }

    /** @return object[] */
    public function pullEvents(): array
    {
        $eventos = $this->eventos;
        $this->eventos = [];

        return $eventos;
    }

    public function id(): CajaId
    {
        return $this->id;
    }

    public function codigo(): CodigoCaja
    {
        return $this->codigo;
    }

    public function familiaTaxonomicaId(): ?string
    {
        return $this->familiaTaxonomicaId;
    }

    public function nombre(): ?string
    {
        return $this->nombre;
    }

    public function capacidadMaxima(): ?int
    {
        return $this->capacidadMaxima;
    }

    public function estadoActual(): EstadoCaja
    {
        return $this->estado;
    }

    public function ranuraActualId(): ?RanuraId
    {
        return $this->ranuraActualId;
    }

    public function codigoRfid(): ?CodigoRfid
    {
        return $this->codigoRfid;
    }
}
