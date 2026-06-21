<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;

/**
 * Visitante de la colección registrado por el curador. Su identidad persiste para
 * poder regenerarle un acceso (QR) sin volver a registrarlo y para la futura
 * trazabilidad de la reubicación guiada.
 *
 * `versionAcceso` versiona los accesos emitidos: al regenerar el QR se incrementa,
 * lo que invalida cualquier enlace de acceso anterior aunque aún no haya expirado.
 */
class Visitante
{
    private function __construct(
        private readonly VisitanteId $id,
        private string $nombre,
        private ?string $contacto,
        private int $versionAcceso,
        private readonly \DateTimeImmutable $registradoEn,
    ) {}

    public static function crear(
        VisitanteId $id,
        string $nombre,
        ?string $contacto,
        \DateTimeImmutable $registradoEn,
    ): self {
        return new self(
            id: $id,
            nombre: trim($nombre),
            contacto: self::normalizarOpcional($contacto),
            versionAcceso: 1,
            registradoEn: $registradoEn,
        );
    }

    public static function reconstituir(
        VisitanteId $id,
        string $nombre,
        ?string $contacto,
        int $versionAcceso,
        \DateTimeImmutable $registradoEn,
    ): self {
        return new self(
            id: $id,
            nombre: $nombre,
            contacto: $contacto,
            versionAcceso: $versionAcceso,
            registradoEn: $registradoEn,
        );
    }

    /**
     * Invalida los accesos (QR) previos emitiendo una nueva versión de acceso.
     */
    public function regenerarAcceso(): void
    {
        $this->versionAcceso++;
    }

    private static function normalizarOpcional(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $trim = trim($valor);

        return $trim === '' ? null : $trim;
    }

    public function id(): VisitanteId
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function contacto(): ?string
    {
        return $this->contacto;
    }

    public function versionAcceso(): int
    {
        return $this->versionAcceso;
    }

    public function registradoEn(): \DateTimeImmutable
    {
        return $this->registradoEn;
    }
}
