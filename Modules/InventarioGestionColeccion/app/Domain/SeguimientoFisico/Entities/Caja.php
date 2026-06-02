<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Events\CajaIngresadaEnRanura;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Events\CajaRetiradaDeRanura;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\CajaNoEnGabineteException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\CajaNoEnTransitoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\RfidNoAsignadoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\RfidYaAsignadoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ClasificacionTaxonomica;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;

class Caja
{
    /** @var object[] */
    private array $eventos = [];

    private function __construct(
        private readonly CajaId $id,
        private readonly CodigoCaja $codigo,
        private readonly bool $esEspecial,
        private readonly ?string $observacion,
        private readonly ?string $nombre,
        private ?ClasificacionTaxonomica $clasificacionTaxonomica,
        private EstadoCaja $estado,
        private ?RanuraId $ranuraActualId,
        private ?CodigoRfid $codigoRfid,
    ) {}

    public static function crear(
        CajaId $id,
        CodigoCaja $codigo,
        bool $esEspecial = false,
        ?string $observacion = null,
        ?string $nombre = null,
        ?ClasificacionTaxonomica $clasificacionTaxonomica = null,
    ): self {
        if ($esEspecial && ($observacion === null || trim($observacion) === '')) {
            throw new \InvalidArgumentException('Una Caja especial requiere una observación no vacía.');
        }

        return new self(
            id: $id,
            codigo: $codigo,
            esEspecial: $esEspecial,
            observacion: $observacion,
            nombre: $nombre,
            clasificacionTaxonomica: $clasificacionTaxonomica,
            estado: EstadoCaja::EnTransito,
            ranuraActualId: null,
            codigoRfid: null,
        );
    }

    public static function reconstituir(
        CajaId $id,
        CodigoCaja $codigo,
        EstadoCaja $estado,
        ?RanuraId $ranuraActualId,
        ?CodigoRfid $codigoRfid,
        bool $esEspecial = false,
        ?string $observacion = null,
        ?string $nombre = null,
        ?ClasificacionTaxonomica $clasificacionTaxonomica = null,
    ): self {
        if ($esEspecial && ($observacion === null || trim($observacion) === '')) {
            throw new \InvalidArgumentException('Una Caja especial requiere una observación no vacía.');
        }

        return new self(
            id: $id,
            codigo: $codigo,
            esEspecial: $esEspecial,
            observacion: $observacion,
            nombre: $nombre,
            clasificacionTaxonomica: $clasificacionTaxonomica,
            estado: $estado,
            ranuraActualId: $ranuraActualId,
            codigoRfid: $codigoRfid,
        );
    }

    public function ingresarEnRanura(RanuraId $ranuraId): void
    {
        // Una caja entra a una ranura tanto en su ingreso normal (EnTransito) como
        // al devolverse tras una extracción prolongada. El ESP32 detecta ambos casos
        // como el mismo evento físico de inserción.
        if (! $this->estado->equals(EstadoCaja::EnTransito)
            && ! $this->estado->equals(EstadoCaja::ExtraccionProlongada)) {
            throw new CajaNoEnTransitoException($this->id, $this->estado);
        }

        $this->estado = EstadoCaja::EnGabinete;
        $this->ranuraActualId = $ranuraId;
        $this->eventos[] = new CajaIngresadaEnRanura($this->id, $ranuraId, new \DateTimeImmutable);
    }

    public function retirarDeRanura(): void
    {
        // El retiro físico es válido desde cualquier estado en que la caja está alojada
        // en su ranura (EnGabinete, UbicacionIncorrecta, PendienteClasificacion). Las dos
        // últimas son banderas de negocio sobre una caja presente: sacarla del gabinete las
        // resuelve igual que un retiro normal.
        if (! $this->estado->estaAlojadaEnRanura()) {
            throw new CajaNoEnGabineteException($this->id, $this->estado);
        }

        $this->estado = EstadoCaja::EnTransito;
        $this->ranuraActualId = null;
        $this->eventos[] = new CajaRetiradaDeRanura($this->id, new \DateTimeImmutable);
    }

    public function reconciliarEnRanura(RanuraId $ranuraId): void
    {
        // Sensor reset recovery: the ESP32 lost its in-memory state and rediscovered
        // a caja that is already EnGabinete in the DB. Bypasses the EnTransito
        // precondition — the hardware is the source of truth for physical location.
        $this->ranuraActualId = $ranuraId;
        $this->eventos[] = new CajaIngresadaEnRanura($this->id, $ranuraId, new \DateTimeImmutable);
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
        if (! $this->estado->equals(EstadoCaja::EnGabinete)) {
            throw new CajaNoEnGabineteException($this->id, $this->estado);
        }

        $this->estado = EstadoCaja::UbicacionIncorrecta;
    }

    public function marcarPendienteClasificacion(): void
    {
        if (! $this->estado->equals(EstadoCaja::EnGabinete)) {
            throw new CajaNoEnGabineteException($this->id, $this->estado);
        }

        $this->estado = EstadoCaja::PendienteClasificacion;
    }

    public function marcarExtraccionProlongada(): void
    {
        if (! $this->estado->equals(EstadoCaja::EnTransito)) {
            throw new CajaNoEnTransitoException($this->id, $this->estado);
        }

        $this->estado = EstadoCaja::ExtraccionProlongada;
    }

    /**
     * Actualiza la clasificación taxonómica cacheada en la Caja.
     * La Application layer la llama cuando los UnitTrays propagan su clasificación dominante.
     */
    public function actualizarClasificacion(ClasificacionTaxonomica $clasificacion): void
    {
        $this->clasificacionTaxonomica = $clasificacion;
    }

    /**
     * Limpia la clasificación cuando la Caja queda sin UnitTrays clasificados.
     */
    public function limpiarClasificacion(): void
    {
        $this->clasificacionTaxonomica = null;
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

    public function esEspecial(): bool
    {
        return $this->esEspecial;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function clasificacionTaxonomica(): ?ClasificacionTaxonomica
    {
        return $this->clasificacionTaxonomica;
    }

    public function nombre(): ?string
    {
        return $this->nombre;
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
