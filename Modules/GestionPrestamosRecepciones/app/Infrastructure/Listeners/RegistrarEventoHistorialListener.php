<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaDevueltaPorFirmaInvalida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaSubida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaValidada;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoActivado;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoIniciado;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecordatorioDevolucionEnviado;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\VerificacionEntregaAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\VerificacionEntregaRegistrada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoObservada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoRechazada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoRegistrada;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\HistorialEventoEloquentModel;

final class RegistrarEventoHistorialListener
{
    public function handle(object $event): void
    {
        HistorialEventoEloquentModel::create([
            'id' => (string) Str::uuid(),
            'tipo_agregado' => $this->resolverTipoAgregado($event),
            'agregado_id' => $this->resolverAgregadoId($event),
            'tipo_evento' => class_basename($event),
            'datos' => $this->resolverDatos($event),
            'ocurrido_en' => $this->resolverOcurridoEn($event)->format('Y-m-d H:i:s'),
        ]);
    }

    private function resolverTipoAgregado(object $event): string
    {
        return match (true) {
            $event instanceof SolicitudPrestamoRegistrada,
            $event instanceof SolicitudPrestamoEnviada,
            $event instanceof SolicitudPrestamoObservada,
            $event instanceof SolicitudPrestamoAprobada,
            $event instanceof SolicitudPrestamoRechazada,
            $event instanceof ActaEnviada,
            $event instanceof ActaFirmadaSubida,
            $event instanceof ActaDevueltaPorFirmaInvalida,
            $event instanceof ActaValidada => 'solicitud_prestamo',
            $event instanceof PrestamoIniciado,
            $event instanceof RecordatorioDevolucionEnviado,
            $event instanceof VerificacionEntregaRegistrada,
            $event instanceof VerificacionEntregaAprobada,
            $event instanceof PrestamoActivado => 'prestamo',
        };
    }

    private function resolverAgregadoId(object $event): string
    {
        return match (true) {
            $event instanceof SolicitudPrestamoRegistrada,
            $event instanceof SolicitudPrestamoEnviada,
            $event instanceof SolicitudPrestamoObservada,
            $event instanceof SolicitudPrestamoAprobada,
            $event instanceof SolicitudPrestamoRechazada => (string) $event->solicitudId,
            $event instanceof ActaEnviada,
            $event instanceof ActaFirmadaSubida,
            $event instanceof ActaValidada => (string) $event->solicitudId,
            $event instanceof ActaDevueltaPorFirmaInvalida => (string) (
                ActaPrestamoModel::find((string) $event->actaId)?->solicitud_prestamo_id
                ?? (string) $event->actaId
            ),
            $event instanceof PrestamoIniciado,
            $event instanceof RecordatorioDevolucionEnviado,
            $event instanceof VerificacionEntregaRegistrada,
            $event instanceof VerificacionEntregaAprobada,
            $event instanceof PrestamoActivado => (string) $event->prestamoId,
        };
    }

    private function resolverOcurridoEn(object $event): DateTimeImmutable
    {
        return match (true) {
            $event instanceof SolicitudPrestamoEnviada => $event->enviadaEn,
            default => $event->ocurridoEn,
        };
    }

    private function resolverDatos(object $event): array
    {
        $datos = [];

        foreach (get_object_vars($event) as $key => $value) {
            if ($value instanceof DateTimeImmutable) {
                $datos[$key] = $value->format('Y-m-d H:i:s');
            } elseif ($value instanceof \BackedEnum) {
                $datos[$key] = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $datos[$key] = $value->name;
            } elseif (is_object($value)) {
                $datos[$key] = (string) $value;
            } else {
                $datos[$key] = $value;
            }
        }

        return $datos;
    }
}
