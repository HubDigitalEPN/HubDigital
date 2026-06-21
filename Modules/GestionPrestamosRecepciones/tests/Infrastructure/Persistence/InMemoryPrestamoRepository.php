<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final class InMemoryPrestamoRepository implements PrestamoRepositoryInterface
{
    /** @var array<string, Prestamo> */
    private array $store = [];

    public function guardar(Prestamo $prestamo): void
    {
        $this->store[(string) $prestamo->id()] = $prestamo;
    }

    public function buscarPorId(PrestamoId $id): ?Prestamo
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorActaId(ActaPrestamoId $actaId): ?Prestamo
    {
        foreach ($this->store as $prestamo) {
            if ((string) $prestamo->actaPrestamoId() === (string) $actaId) {
                return $prestamo;
            }
        }

        return null;
    }

    public function listarActivos(): array
    {
        return array_values(array_filter(
            $this->store,
            fn (Prestamo $p) => $p->estado()->equals(EstadoPrestamo::Activo),
        ));
    }

    public function nextIdentity(): PrestamoId
    {
        return PrestamoId::generate();
    }

    /**
     * @param array<int, string>|null $investigadorIds
     * @return array<int, array{prestamoId: string, numeroPrestamo: string|null, investigadorId: string|null, estado: string, iniciadoEn: \DateTimeImmutable|null, fechaFin: \DateTimeImmutable|null}>
     */
    public function listarParaBandeja(
        ?string $investigadorId,
        ?array $investigadorIds,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array {
        $prestamos = array_filter(
            $this->store,
            function (Prestamo $prestamo) use ($investigadorId, $investigadorIds, $estado): bool {
                if ($investigadorId !== null && $prestamo->investigadorId() !== $investigadorId) {
                    return false;
                }

                if ($investigadorIds !== null && ! in_array($prestamo->investigadorId(), $investigadorIds, true)) {
                    return false;
                }

                if ($estado !== '' && $prestamo->estado()->value !== $estado) {
                    return false;
                }

                return true;
            },
        );

        $prestamos = array_values($prestamos);

        usort($prestamos, function (Prestamo $a, Prestamo $b) use ($ordenCampo): int {
            return $ordenCampo === 'fechaFin'
                ? $a->fechaFin() <=> $b->fechaFin()
                : $a->iniciadoEn() <=> $b->iniciadoEn();
        });

        if (strtolower($ordenDireccion) === 'desc') {
            $prestamos = array_reverse($prestamos);
        }

        return array_map(fn (Prestamo $prestamo): array => [
            'prestamoId' => (string) $prestamo->id(),
            'numeroPrestamo' => null,
            'investigadorId' => $prestamo->investigadorId(),
            'estado' => $prestamo->estado()->value,
            'iniciadoEn' => $prestamo->iniciadoEn(),
            'fechaFin' => $prestamo->fechaFin(),
        ], $prestamos);
    }
}
