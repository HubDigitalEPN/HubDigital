<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class InMemorySolicitudPrestamoRepository implements SolicitudPrestamoRepositoryInterface
{
    /** @var array<string, SolicitudPrestamo> */
    private array $store = [];

    public function guardar(SolicitudPrestamo $solicitud): void
    {
        $this->store[(string) $solicitud->id()] = $solicitud;
    }

    public function buscarPorId(SolicitudPrestamoId $id): ?SolicitudPrestamo
    {
        return $this->store[(string) $id] ?? null;
    }

    public function nextIdentity(): SolicitudPrestamoId
    {
        return SolicitudPrestamoId::generate();
    }

    /**
     * @param  list<string>  $itemPrestamoIds
     * @return array<string, string>
     */
    public function mapaCodigosExternos(array $itemPrestamoIds): array
    {
        if ($itemPrestamoIds === []) {
            return [];
        }

        $buscados = array_flip($itemPrestamoIds);
        $mapa = [];

        foreach ($this->store as $solicitud) {
            foreach ($solicitud->items() as $item) {
                $itemId = (string) $item->id();

                if (isset($buscados[$itemId])) {
                    $mapa[$itemId] = $item->especimenCodigoExterno();
                }
            }
        }

        return $mapa;
    }

    /**
     * @param array<int, string>|null $investigadorIds
     * @return array<int, array{solicitudId: string, numeroSolicitud: string|null, tituloEstudio: string|null, investigadorId: string|null, estado: string, fecha: \DateTimeImmutable}>
     */
    public function listarParaBandeja(
        ?array $investigadorIds,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array {
        $solicitudes = array_filter(
            $this->store,
            function (SolicitudPrestamo $solicitud) use ($investigadorIds, $estado, $busquedaTexto): bool {
                if ($solicitud->estado() === EstadoSolicitud::Borrador) {
                    return false;
                }

                if ($estado !== '' && $solicitud->estado()->value !== $estado) {
                    return false;
                }

                if ($investigadorIds !== null && ! in_array($solicitud->investigadorId(), $investigadorIds, true)) {
                    return false;
                }

                return $this->coincideBusqueda($solicitud, $busquedaTexto);
            },
        );

        $solicitudes = $this->ordenar($solicitudes, $ordenCampo, $ordenDireccion);

        return array_values(array_map(fn (SolicitudPrestamo $solicitud): array => [
            'solicitudId' => (string) $solicitud->id(),
            'numeroSolicitud' => (string) $solicitud->codigoPrestamo(),
            'tituloEstudio' => $solicitud->tituloEstudio(),
            'investigadorId' => $solicitud->investigadorId(),
            'estado' => $solicitud->estado()->value,
            'fecha' => $this->fecha($solicitud),
        ], $solicitudes));
    }

    /**
     * @return array<int, array{solicitudId: string, numeroSolicitud: string|null, tituloEstudio: string|null, estado: string, fecha: \DateTimeImmutable, actaId: string|null, actaEstado: string|null}>
     */
    public function listarPorInvestigador(
        string $investigadorId,
        string $estado,
        string $busquedaTexto,
        string $ordenCampo,
        string $ordenDireccion,
    ): array {
        $solicitudes = array_filter(
            $this->store,
            function (SolicitudPrestamo $solicitud) use ($investigadorId, $estado, $busquedaTexto): bool {
                if ($solicitud->investigadorId() !== $investigadorId) {
                    return false;
                }

                if ($estado !== '' && $solicitud->estado()->value !== $estado) {
                    return false;
                }

                return $this->coincideBusqueda($solicitud, $busquedaTexto);
            },
        );

        $solicitudes = $this->ordenar($solicitudes, $ordenCampo, $ordenDireccion);

        return array_values(array_map(fn (SolicitudPrestamo $solicitud): array => [
            'solicitudId' => (string) $solicitud->id(),
            'numeroSolicitud' => (string) $solicitud->codigoPrestamo(),
            'tituloEstudio' => $solicitud->tituloEstudio(),
            'estado' => $solicitud->estado()->value,
            'fecha' => $this->fecha($solicitud),
            'actaId' => null,
            'actaEstado' => null,
        ], $solicitudes));
    }

    private function coincideBusqueda(SolicitudPrestamo $solicitud, string $busquedaTexto): bool
    {
        if ($busquedaTexto === '') {
            return true;
        }

        $aguja = mb_strtolower($busquedaTexto);

        return str_contains(mb_strtolower((string) $solicitud->tituloEstudio()), $aguja)
            || str_contains(mb_strtolower((string) $solicitud->codigoPrestamo()), $aguja);
    }

    /**
     * @param  array<string, SolicitudPrestamo>  $solicitudes
     * @return list<SolicitudPrestamo>
     */
    private function ordenar(array $solicitudes, string $ordenCampo, string $ordenDireccion): array
    {
        $solicitudes = array_values($solicitudes);

        usort($solicitudes, function (SolicitudPrestamo $a, SolicitudPrestamo $b) use ($ordenCampo): int {
            return $ordenCampo === 'titulo'
                ? strcmp((string) $a->tituloEstudio(), (string) $b->tituloEstudio())
                : $this->fecha($a) <=> $this->fecha($b);
        });

        if (strtolower($ordenDireccion) === 'desc') {
            $solicitudes = array_reverse($solicitudes);
        }

        return $solicitudes;
    }

    private function fecha(SolicitudPrestamo $solicitud): \DateTimeImmutable
    {
        return $solicitud->enviadaEn() ?? $solicitud->resueltaEn() ?? new \DateTimeImmutable('@0');
    }
}
