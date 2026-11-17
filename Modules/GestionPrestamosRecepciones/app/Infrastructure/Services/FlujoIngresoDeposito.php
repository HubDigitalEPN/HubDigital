<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;

/** Lectura común de capacidades; el estado documental y los documentos históricos se conservan. */
final readonly class FlujoIngresoDeposito
{
    public function __construct(private Solicitud $solicitud) {}

    public function cerrado(): bool
    {
        return $this->solicitud->recepcionLote?->firmada_en !== null && filled($this->solicitud->recepcionLote?->acta_firmada_ruta);
    }

    public function puedeRecibir(): bool
    {
        return $this->solicitud->estado === 'Aprobada Documentalmente'
            && filled($this->solicitud->codigo_qr)
            && ! $this->solicitud->recepcion_qr_en
            && ! $this->solicitud->recepcion_qr_rechazada_en
            && ! $this->solicitud->recepcionLote;
    }

    public function puedeValidarContenido(): bool
    {
        return $this->solicitud->estado === 'Aprobada Documentalmente'
            && $this->solicitud->recepcion_qr_en !== null
            && ! $this->solicitud->recepcion_qr_rechazada_en
            && ! $this->solicitud->contenido_validado_en && ! $this->cerrado();
    }

    public function etiqueta(): string
    {
        if ($this->solicitud->estado !== 'Aprobada Documentalmente') {
            return $this->solicitud->estado;
        }
        if ($this->cerrado()) {
            return 'Finalizada · acta firmada';
        }
        if ($this->solicitud->recepcion_qr_rechazada_en) {
            return 'Entrega no recibida · pendiente de Curaduría';
        }
        if ($this->solicitud->contenido_validado_en) {
            return 'Recibida y validada';
        }
        if ($this->solicitud->recepcion_qr_en) {
            return 'Recibida · pendiente de validar contenido';
        }

        return $this->solicitud->estado;
    }
}
