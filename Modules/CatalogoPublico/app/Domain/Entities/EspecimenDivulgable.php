<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Entities;

use Modules\CatalogoPublico\Domain\Events\ConfiguracionDivulgacionModificada;
use Modules\CatalogoPublico\Domain\Events\DomainEvent;
use Modules\CatalogoPublico\Domain\Events\EspecimenSincronizado;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenDivulgableId;

final class EspecimenDivulgable
{
    /** @var list<DomainEvent> */
    private array $events = [];

    private function __construct(
        private readonly EspecimenDivulgableId $id,
        private readonly string $occurrenceID,
        private ConfiguracionVisibilidad $configuracion,
    ) {}

    public static function sincronizar(
        EspecimenDivulgableId $id,
        string $occurrenceID,
        ConfiguracionVisibilidad $configuracion,
    ): self {
        if ($occurrenceID === '') {
            throw new \InvalidArgumentException('El occurrenceID no puede ser vacío');
        }

        $divulgable = new self(
            id: $id,
            occurrenceID: $occurrenceID,
            configuracion: $configuracion,
        );

        $divulgable->events[] = new EspecimenSincronizado(
            id: $id,
            occurrenceID: $occurrenceID,
            configuracion: $configuracion,
        );

        return $divulgable;
    }

    public function actualizarConfiguracion(ConfiguracionVisibilidad $nueva): void
    {
        $anterior = $this->configuracion;
        $this->configuracion = $nueva;

        $this->events[] = new ConfiguracionDivulgacionModificada(
            id: $this->id,
            occurrenceID: $this->occurrenceID,
            configuracionAnterior: $anterior,
            configuracionNueva: $nueva,
        );
    }

    /**
     * Retorna solo los campos del espécimen cuyo flag de visibilidad está habilitado.
     * Campos opcionales con valor null pero flag true se incluyen con valor null.
     *
     * @return array<string, scalar|null>
     */
    public function obtenerDatosVisibles(Especimen $especimen): array
    {
        $datos = [];

        if ($this->configuracion->occurrenceIDVisible()) {
            $datos['occurrenceID'] = $especimen->occurrenceID();
        }

        if ($this->configuracion->scientificNameVisible()) {
            $datos['scientificName'] = $especimen->scientificName();
        }

        if ($this->configuracion->individualCountVisible()) {
            $datos['individualCount'] = $especimen->individualCount();
        }

        if ($this->configuracion->typeStatusVisible()) {
            $datos['typeStatus'] = $especimen->typeStatus();
        }

        if ($this->configuracion->typeNotesVisible()) {
            $datos['typeNotes'] = $especimen->typeNotes();
        }

        if ($this->configuracion->specimenNotesVisible()) {
            $datos['specimenNotes'] = $especimen->specimenNotes();
        }

        if ($this->configuracion->samplingProtocolVisible()) {
            $datos['samplingProtocol'] = $especimen->samplingProtocol();
        }

        if ($this->configuracion->recordedByVisible()) {
            $datos['recordedBy'] = $especimen->recordedBy();
        }

        if ($this->configuracion->occurrenceStatusVisible()) {
            $datos['occurrenceStatus'] = $especimen->occurrenceStatus();
        }

        if ($this->configuracion->familyVisible()) {
            $datos['family'] = $especimen->family();
        }

        if ($this->configuracion->genusVisible()) {
            $datos['genus'] = $especimen->genus();
        }

        if ($this->configuracion->countryVisible()) {
            $datos['country'] = $especimen->country();
        }

        if ($this->configuracion->localityNameVisible()) {
            $datos['localityName'] = $especimen->localityName();
        }

        if ($this->configuracion->decimalLatitudeVisible()) {
            $datos['decimalLatitude'] = $especimen->decimalLatitude();
        }

        if ($this->configuracion->decimalLongitudeVisible()) {
            $datos['decimalLongitude'] = $especimen->decimalLongitude();
        }

        return $datos;
    }

    /** @return list<DomainEvent> */
    public function pullEvents(): array
    {
        $eventos = $this->events;
        $this->events = [];

        return $eventos;
    }

    public function id(): EspecimenDivulgableId
    {
        return $this->id;
    }

    public function occurrenceID(): string
    {
        return $this->occurrenceID;
    }

    public function occurrenceIDVisible(): bool
    {
        return $this->configuracion->occurrenceIDVisible();
    }

    public function scientificNameVisible(): bool
    {
        return $this->configuracion->scientificNameVisible();
    }

    public function individualCountVisible(): bool
    {
        return $this->configuracion->individualCountVisible();
    }

    public function typeStatusVisible(): bool
    {
        return $this->configuracion->typeStatusVisible();
    }

    public function typeNotesVisible(): bool
    {
        return $this->configuracion->typeNotesVisible();
    }

    public function specimenNotesVisible(): bool
    {
        return $this->configuracion->specimenNotesVisible();
    }

    public function samplingProtocolVisible(): bool
    {
        return $this->configuracion->samplingProtocolVisible();
    }

    public function recordedByVisible(): bool
    {
        return $this->configuracion->recordedByVisible();
    }

    public function occurrenceStatusVisible(): bool
    {
        return $this->configuracion->occurrenceStatusVisible();
    }

    public function familyVisible(): bool
    {
        return $this->configuracion->familyVisible();
    }

    public function genusVisible(): bool
    {
        return $this->configuracion->genusVisible();
    }

    public function countryVisible(): bool
    {
        return $this->configuracion->countryVisible();
    }

    public function localityNameVisible(): bool
    {
        return $this->configuracion->localityNameVisible();
    }

    public function decimalLatitudeVisible(): bool
    {
        return $this->configuracion->decimalLatitudeVisible();
    }

    public function decimalLongitudeVisible(): bool
    {
        return $this->configuracion->decimalLongitudeVisible();
    }

    public function configuracion(): ConfiguracionVisibilidad
    {
        return $this->configuracion;
    }

    public static function reconstituir(
        EspecimenDivulgableId $id,
        string $occurrenceID,
        ConfiguracionVisibilidad $configuracion,
    ): self {
        return new self(
            id: $id,
            occurrenceID: $occurrenceID,
            configuracion: $configuracion,
        );
    }
}
