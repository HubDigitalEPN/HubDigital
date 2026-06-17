<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Value object inmutable que representa un documento adjunto a una solicitud
 * (nombre lógico + ruta de almacenamiento).
 *
 * Construir vía {@see of()}, que garantiza que nombre y ruta no estén vacíos.
 */
final readonly class DocumentoAdjunto
{
    private function __construct(
        private string $nombre,
        private string $ruta,
    ) {}

    /**
     * Crea un documento adjunto validando que nombre y ruta no estén vacíos.
     *
     * @throws \DomainException Si el nombre o la ruta están vacíos.
     */
    public static function of(string $nombre, string $ruta): self
    {
        if (trim($nombre) === '') {
            throw new \DomainException('El nombre del documento adjunto no puede estar vacío');
        }

        if (trim($ruta) === '') {
            throw new \DomainException('La ruta del documento adjunto no puede estar vacía');
        }

        return new self($nombre, $ruta);
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function ruta(): string
    {
        return $this->ruta;
    }

    public function equals(self $other): bool
    {
        return $this->nombre === $other->nombre && $this->ruta === $other->ruta;
    }
}
