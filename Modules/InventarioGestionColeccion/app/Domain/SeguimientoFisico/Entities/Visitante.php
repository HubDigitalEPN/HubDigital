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
        private bool $puedeReubicar,
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
            puedeReubicar: false,
            registradoEn: $registradoEn,
        );
    }

    public static function reconstituir(
        VisitanteId $id,
        string $nombre,
        ?string $contacto,
        int $versionAcceso,
        bool $puedeReubicar,
        \DateTimeImmutable $registradoEn,
    ): self {
        return new self(
            id: $id,
            nombre: $nombre,
            contacto: $contacto,
            versionAcceso: $versionAcceso,
            puedeReubicar: $puedeReubicar,
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

    /**
     * Habilita o revoca la capacidad del visitante de reubicar especímenes en la
     * colección. Sin esta habilitación cualquier intento de reubicación es rechazado.
     */
    public function definirReubicacion(bool $habilitado): void
    {
        $this->puedeReubicar = $habilitado;
    }

    public function puedeReubicar(): bool
    {
        return $this->puedeReubicar;
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
