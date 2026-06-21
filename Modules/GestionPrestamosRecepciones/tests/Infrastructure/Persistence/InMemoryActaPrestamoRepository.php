<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class InMemoryActaPrestamoRepository implements ActaPrestamoRepositoryInterface
{
    /** @var array<string, ActaPrestamo> */
    private array $store = [];

    public function guardar(ActaPrestamo $acta): void
    {
        $this->store[(string) $acta->id()] = $acta;
    }

    public function buscarPorId(ActaPrestamoId $id): ?ActaPrestamo
    {
        return $this->store[(string) $id] ?? null;
    }

    public function buscarPorSolicitudId(SolicitudPrestamoId $solicitudId): ?ActaPrestamo
    {
        foreach ($this->store as $acta) {
            if ((string) $acta->solicitudPrestamoId() === (string) $solicitudId) {
                return $acta;
            }
        }

        return null;
    }

    public function nextIdentity(): ActaPrestamoId
    {
        return ActaPrestamoId::generate();
    }

    /**
     * @param array<int, string>|null $investigadorIds
     * @return array<int, array{actaId: string, numeroPrestamo: string, numeroSolicitud: string|null, investigadorId: string|null, estado: string, fecha: \DateTimeImmutable}>
     */
    public function listarParaBandeja(
        ?array $investigadorIds,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array {
        $actas = array_filter(
            $this->store,
            function (ActaPrestamo $acta) use ($estado, $busquedaTexto): bool {
                if ($estado !== '' && $acta->estado()->value !== $estado) {
                    return false;
                }

                if ($busquedaTexto !== ''
                    && ! str_contains(mb_strtolower((string) $acta->numeroPrestamo()), mb_strtolower($busquedaTexto))) {
                    return false;
                }

                return true;
            },
        );

        $actas = array_values($actas);

        usort($actas, function (ActaPrestamo $a, ActaPrestamo $b) use ($ordenCampo): int {
            return $ordenCampo === 'numeroPrestamo'
                ? strcmp((string) $a->numeroPrestamo(), (string) $b->numeroPrestamo())
                : $this->fecha($a) <=> $this->fecha($b);
        });

        if (strtolower($ordenDireccion) === 'desc') {
            $actas = array_reverse($actas);
        }

        return array_map(fn (ActaPrestamo $acta): array => [
            'actaId' => (string) $acta->id(),
            'numeroPrestamo' => (string) $acta->numeroPrestamo(),
            'numeroSolicitud' => null,
            'investigadorId' => null,
            'estado' => $acta->estado()->value,
            'fecha' => $this->fecha($acta),
        ], $actas);
    }

    private function fecha(ActaPrestamo $acta): \DateTimeImmutable
    {
        return $acta->validadaEn() ?? $acta->firmadaSubidaEn() ?? new \DateTimeImmutable('@0');
    }
}
