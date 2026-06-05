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
        private readonly string $especimenId,
        private ConfiguracionVisibilidad $configuracion,
    ) {}

    public static function sincronizar(
        EspecimenDivulgableId $id,
        string $especimenId,
        ConfiguracionVisibilidad $configuracion,
    ): self {
        if ($especimenId === '') {
            throw new \InvalidArgumentException('El especimenId no puede ser vacío');
        }

        $divulgable = new self(
            id: $id,
            especimenId: $especimenId,
            configuracion: $configuracion,
        );

        $divulgable->events[] = new EspecimenSincronizado(
            id: $id,
            especimenId: $especimenId,
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
            especimenId: $this->especimenId,
            configuracionAnterior: $anterior,
            configuracionNueva: $nueva,
        );
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

    public function especimenId(): string
    {
        return $this->especimenId;
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
        string $especimenId,
        ConfiguracionVisibilidad $configuracion,
    ): self {
        return new self(
            id: $id,
            especimenId: $especimenId,
            configuracion: $configuracion,
        );
    }
}
