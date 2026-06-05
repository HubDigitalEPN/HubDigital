<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoEnvio;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ObservacionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\VerificacionEntregaId;

final class VerificacionEntregaPrestamo
{
    /** @param list<ObservacionEspecimen> $observaciones */
    private function __construct(
        private readonly VerificacionEntregaId $id,
        private readonly PrestamoId $prestamoId,
        private readonly EstadoEnvio $estadoEnvio,
        private readonly array $observaciones,
    ) {}

    /**
     * @param  list<ObservacionEspecimen>  $observaciones
     */
    public static function registrar(
        VerificacionEntregaId $id,
        PrestamoId $prestamoId,
        EstadoEnvio $estadoEnvio,
        array $observaciones,
    ): self {
        if ($estadoEnvio === EstadoEnvio::ConNovedades && count($observaciones) === 0) {
            throw new InvalidArgumentException(
                'Debe registrar al menos una observación cuando el estado del envío es con novedades.'
            );
        }

        return new self(
            id: $id,
            prestamoId: $prestamoId,
            estadoEnvio: $estadoEnvio,
            observaciones: $observaciones,
        );
    }

    /**
     * Reconstitución desde persistencia — no valida invariantes.
     *
     * @param  list<ObservacionEspecimen>  $observaciones
     */
    public static function reconstituir(
        VerificacionEntregaId $id,
        PrestamoId $prestamoId,
        EstadoEnvio $estadoEnvio,
        array $observaciones,
    ): self {
        return new self(
            id: $id,
            prestamoId: $prestamoId,
            estadoEnvio: $estadoEnvio,
            observaciones: $observaciones,
        );
    }

    public function id(): VerificacionEntregaId
    {
        return $this->id;
    }

    public function prestamoId(): PrestamoId
    {
        return $this->prestamoId;
    }

    public function estadoEnvio(): EstadoEnvio
    {
        return $this->estadoEnvio;
    }

    /** @return list<ObservacionEspecimen> */
    public function observaciones(): array
    {
        return $this->observaciones;
    }
}
