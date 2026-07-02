<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoQrId;

/**
 * CodigoQr — etiqueta física de un espécimen.
 *
 * Vincula un espécimen (por su GUID) con un `payload` opaco que viaja codificado
 * en el código QR impreso. Al escanear, el `payload` permite resolver de vuelta
 * la ficha digital del espécimen sin exponer su GUID directamente. Cada espécimen
 * admite a lo sumo un QR vigente (invariante garantizada por el caso de uso).
 */
class CodigoQr
{
    private function __construct(
        private readonly CodigoQrId $id,
        private readonly string $especimenId,
        private readonly string $payload,
        private readonly \DateTimeImmutable $generadoEn,
    ) {}

    public static function generar(
        CodigoQrId $id,
        string $especimenId,
        string $payload,
        \DateTimeImmutable $generadoEn,
    ): self {
        $especimenId = trim($especimenId);
        $payload = trim($payload);

        if ($especimenId === '') {
            throw new \InvalidArgumentException('El especimenId del código QR no puede estar vacío.');
        }
        if ($payload === '') {
            throw new \InvalidArgumentException('El payload del código QR no puede estar vacío.');
        }

        return new self($id, $especimenId, $payload, $generadoEn);
    }

    public static function reconstituir(
        CodigoQrId $id,
        string $especimenId,
        string $payload,
        \DateTimeImmutable $generadoEn,
    ): self {
        return new self($id, $especimenId, $payload, $generadoEn);
    }

    public function id(): CodigoQrId
    {
        return $this->id;
    }

    public function especimenId(): string
    {
        return $this->especimenId;
    }

    public function payload(): string
    {
        return $this->payload;
    }

    public function generadoEn(): \DateTimeImmutable
    {
        return $this->generadoEn;
    }
}
