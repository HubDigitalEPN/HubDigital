<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Entities;

use DateTimeImmutable;
use Modules\CatalogoPublico\Domain\Events\DomainEvent;
use Modules\CatalogoPublico\Domain\Events\ImagenSubida;
use Modules\CatalogoPublico\Domain\ValueObjects\ArchivoImagen;
use Modules\CatalogoPublico\Domain\ValueObjects\AutorImagen;
use Modules\CatalogoPublico\Domain\ValueObjects\ImagenTaxonomicaId;

final class ImagenTaxonomica
{
    /** @var list<DomainEvent> */
    private array $events = [];

    private function __construct(
        private readonly ImagenTaxonomicaId $id,
        private readonly string $occurrenceID,
        private readonly ArchivoImagen $archivo,
        private readonly AutorImagen $autor,
        private readonly bool $marcaAguaAplicada,
        private readonly DateTimeImmutable $subidaEn,
    ) {}

    public static function subir(
        ImagenTaxonomicaId $id,
        string $occurrenceID,
        ArchivoImagen $archivo,
        AutorImagen $autor,
    ): self {
        if (trim($occurrenceID) === '') {
            throw new \InvalidArgumentException('El occurrenceID del espécimen no puede ser vacío');
        }

        $imagen = new self(
            id: $id,
            occurrenceID: $occurrenceID,
            archivo: $archivo,
            autor: $autor,
            marcaAguaAplicada: true,
            subidaEn: new DateTimeImmutable,
        );

        $imagen->events[] = new ImagenSubida(
            id: $id,
            occurrenceID: $occurrenceID,
            autor: $autor,
        );

        return $imagen;
    }

    public static function reconstituir(
        ImagenTaxonomicaId $id,
        string $occurrenceID,
        ArchivoImagen $archivo,
        AutorImagen $autor,
        bool $marcaAguaAplicada,
        DateTimeImmutable $subidaEn,
    ): self {
        return new self(
            id: $id,
            occurrenceID: $occurrenceID,
            archivo: $archivo,
            autor: $autor,
            marcaAguaAplicada: $marcaAguaAplicada,
            subidaEn: $subidaEn,
        );
    }

    /** @return list<DomainEvent> */
    public function pullEvents(): array
    {
        $eventos = $this->events;
        $this->events = [];

        return $eventos;
    }

    public function id(): ImagenTaxonomicaId
    {
        return $this->id;
    }

    public function occurrenceID(): string
    {
        return $this->occurrenceID;
    }

    public function archivo(): ArchivoImagen
    {
        return $this->archivo;
    }

    public function autor(): AutorImagen
    {
        return $this->autor;
    }

    public function nombreAutor(): string
    {
        return $this->autor->nombreCompleto();
    }

    public function marcaAguaAplicada(): bool
    {
        return $this->marcaAguaAplicada;
    }

    public function subidaEn(): DateTimeImmutable
    {
        return $this->subidaEn;
    }
}
