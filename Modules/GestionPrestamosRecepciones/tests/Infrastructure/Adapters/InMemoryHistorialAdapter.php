<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventoHistorialDto;
use Modules\GestionPrestamosRecepciones\Application\Ports\HistorialPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaSubida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaValidada;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoIniciado;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoObservada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoRechazada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoRegistrada;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class InMemoryHistorialAdapter implements HistorialPort
{
    /** @var array<string, EventoHistorialDto[]> */
    private array $porSolicitud = [];

    /** @var array<string, EventoHistorialDto[]> */
    private array $porPrestamo = [];

    public function registrar(object $evento): void
    {
        match (true) {
            $evento instanceof SolicitudPrestamoEnviada => $this->addSolicitud(
                (string) $evento->solicitudId,
                'solicitud_prestamo.enviada',
                $evento->enviadaEn,
            ),
            $evento instanceof SolicitudPrestamoObservada => $this->addSolicitud(
                (string) $evento->solicitudId,
                'solicitud_prestamo.observada',
                $evento->ocurridoEn,
                ['curadorId' => $evento->curadorId, 'observacion' => $evento->observacion],
            ),
            $evento instanceof SolicitudPrestamoAprobada => $this->addSolicitud(
                (string) $evento->solicitudId,
                'solicitud_prestamo.aprobada',
                $evento->ocurridoEn,
                ['curadorId' => $evento->curadorId],
            ),
            $evento instanceof SolicitudPrestamoRechazada => $this->addSolicitud(
                (string) $evento->solicitudId,
                'solicitud_prestamo.rechazada',
                $evento->ocurridoEn,
                ['curadorId' => $evento->curadorId, 'motivo' => $evento->motivo],
            ),
            $evento instanceof SolicitudPrestamoRegistrada => $this->addSolicitud(
                (string) $evento->solicitudId,
                'solicitud_prestamo.registrada',
                $evento->ocurridoEn,
                ['investigadorId' => $evento->investigadorId],
            ),
            $evento instanceof ActaEnviada => $this->addSolicitud(
                (string) $evento->solicitudId,
                'acta_prestamo.enviada',
                $evento->ocurridoEn,
                ['actaId' => (string) $evento->actaId],
            ),
            $evento instanceof ActaFirmadaSubida => $this->addSolicitud(
                (string) $evento->solicitudId,
                'acta_prestamo.firma_subida',
                $evento->ocurridoEn,
                ['actaId' => (string) $evento->actaId],
            ),
            $evento instanceof ActaValidada => $this->addSolicitud(
                (string) $evento->solicitudId,
                'acta_prestamo.validada',
                $evento->ocurridoEn,
                ['actaId' => (string) $evento->actaId, 'validadoPor' => $evento->validadoPor],
            ),
            $evento instanceof PrestamoIniciado => $this->addPrestamo(
                (string) $evento->prestamoId,
                'prestamo.iniciado',
                $evento->ocurridoEn,
                ['investigadorId' => $evento->investigadorId],
            ),
            default => null,
        };
    }

    public function obtenerEventosDeSolicitud(SolicitudPrestamoId $id): array
    {
        $eventos = $this->porSolicitud[(string) $id] ?? [];
        usort($eventos, fn (EventoHistorialDto $a, EventoHistorialDto $b) => $a->ocurridoEn <=> $b->ocurridoEn);

        return $eventos;
    }

    public function obtenerEventosDePrestamo(PrestamoId $id): array
    {
        $eventos = $this->porPrestamo[(string) $id] ?? [];
        usort($eventos, fn (EventoHistorialDto $a, EventoHistorialDto $b) => $a->ocurridoEn <=> $b->ocurridoEn);

        return $eventos;
    }

    private function addSolicitud(string $solicitudId, string $tipo, \DateTimeImmutable $ocurridoEn, array $datos = []): void
    {
        $this->porSolicitud[$solicitudId][] = new EventoHistorialDto($tipo, $ocurridoEn, $datos);
    }

    private function addPrestamo(string $prestamoId, string $tipo, \DateTimeImmutable $ocurridoEn, array $datos = []): void
    {
        $this->porPrestamo[$prestamoId][] = new EventoHistorialDto($tipo, $ocurridoEn, $datos);
    }
}
