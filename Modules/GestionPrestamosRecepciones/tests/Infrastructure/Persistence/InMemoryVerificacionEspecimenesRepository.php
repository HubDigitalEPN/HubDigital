<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\VerificacionEspecimenes;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEspecimenesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\VerificacionEspecimenesId;

final class InMemoryVerificacionEspecimenesRepository implements VerificacionEspecimenesRepositoryInterface
{
    /** @var array<string, VerificacionEspecimenes> */
    private array $store = [];

    public function guardar(VerificacionEspecimenes $verificacion): void
    {
        $this->store[(string) $verificacion->id()] = $verificacion;
    }

    public function buscarPorPrestamoYTipo(PrestamoId $prestamoId, TipoVerificacion $tipo): ?VerificacionEspecimenes
    {
        foreach ($this->store as $verificacion) {
            if (
                (string) $verificacion->prestamoId() === (string) $prestamoId
                && $verificacion->tipo() === $tipo
            ) {
                return $verificacion;
            }
        }

        return null;
    }

    public function nextIdentity(): VerificacionEspecimenesId
    {
        return VerificacionEspecimenesId::generate();
    }
}
